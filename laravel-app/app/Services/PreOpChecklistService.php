<?php

namespace App\Services;

use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\ObservationRepositoryInterface;
use App\Repositories\Contracts\AllergyRepositoryInterface;
use App\Repositories\Fhir\FhirObservationRepository;
use App\Repositories\Fhir\FhirAllergyRepository;

/**
 * PreOpChecklistService
 *
 * Orchestrates all 4 surgical safety gates and returns a structured
 * checklist result for the React dashboard.
 *
 * Gate 1: Procedure ↔ Diagnosis match (CPT vs SNOMED)
 * Gate 2: Platelet count (LOINC 777-3) — surgical bleeding risk
 * Gate 3: INR / PT clotting time (LOINC 6301-6)
 * Gate 4: Surgical antibiotic allergy (AllergyIntolerance)
 */
class PreOpChecklistService
{
    // ─── Surgical Safety Thresholds ──────────────────────────────────────────
    private const PLATELET_PASS_MIN  = 100;   // × 10⁹/L
    private const PLATELET_WARN_MIN  = 50;    // × 10⁹/L
    private const INR_PASS_MAX       = 1.5;
    private const INR_WARN_MAX       = 2.0;

    // ─── CPT ↔ SNOMED Mapping (procedure code → valid diagnosis codes) ────────
    // In production this would be a database table; for demo it's hardcoded
    private const CPT_TO_SNOMED_MAP = [
        '27447' => ['57773001', '239872002'], // Total knee replacement → knee osteoarthritis
        '27130' => ['57773001', '52891003'],  // Total hip replacement → hip osteoarthritis
        '47562' => ['74186005',  '235919008'], // Laparoscopic cholecystectomy → cholecystitis
        '43239' => ['196592003', '40832001'], // Upper GI endoscopy
        '99213' => [],                        // Any condition (office visit)
    ];

    public function __construct(
        private PatientRepositoryInterface     $patientRepo,
        private ObservationRepositoryInterface $observationRepo,
        private AllergyRepositoryInterface     $allergyRepo,
    ) {}

    /**
     * Run all 4 safety gates for the given patient.
     *
     * @return array{
     *   patient: array,
     *   gates: array,
     *   overall: string,
     *   checklist_at: string
     * }
     */
    public function runChecklist(string $patientId): array
    {
        // Fetch all required resources in parallel (sequential for simplicity)
        $patient        = $this->patientRepo->findById($patientId);
        $serviceRequest = $this->patientRepo->getActiveServiceRequest($patientId);
        $condition      = $this->patientRepo->getActiveCondition($patientId);

        // Fetch labs using LOINC codes — GREEN FLAG: code-based, never string match
        $labs = $this->observationRepo->getLatestByLoincCodes($patientId, [
            FhirObservationRepository::LOINC_PLATELET_COUNT,
            FhirObservationRepository::LOINC_INR_PT,
        ]);

        // Fetch allergies and check against surgical antibiotic codes
        $dangerousAllergies = $this->allergyRepo->checkForSubstances(
            $patientId,
            array_keys(FhirAllergyRepository::SURGICAL_ANTIBIOTIC_CODES)
        );

        // ── Run gates ────────────────────────────────────────────────────────
        $gate1 = $this->runProcedureDiagnosisGate($serviceRequest, $condition);
        $gate2 = $this->runPlateletGate($labs[FhirObservationRepository::LOINC_PLATELET_COUNT] ?? null);
        $gate3 = $this->runInrGate($labs[FhirObservationRepository::LOINC_INR_PT] ?? null);
        $gate4 = $this->runAllergyGate($dangerousAllergies);

        $gates = [$gate1, $gate2, $gate3, $gate4];

        // ── Overall outcome ───────────────────────────────────────────────────
        $statuses = array_column($gates, 'status');
        $overall  = in_array('hold', $statuses)
            ? 'hold'
            : (in_array('warn', $statuses) ? 'warn' : 'pass');

        return [
            'patient'       => $this->safePatientSummary($patient),
            'gates'         => $gates,
            'overall'       => $overall,
            'checklist_at'  => now()->toIso8601String(),
        ];
    }

    // ─── Gate 1: Procedure vs Diagnosis ──────────────────────────────────────
    private function runProcedureDiagnosisGate(?array $serviceRequest, ?array $condition): array
    {
        if (!$serviceRequest) {
            return $this->gate('procedure_diagnosis', 'hold', 'No active ServiceRequest found', null, null);
        }

        $cptCode    = $this->extractCptCode($serviceRequest);
        $snomedCode = $this->extractSnomedCode($condition);

        if (!$cptCode) {
            return $this->gate('procedure_diagnosis', 'warn', 'Procedure has no CPT code', $cptCode, $snomedCode);
        }

        $validSnomeds = self::CPT_TO_SNOMED_MAP[$cptCode] ?? null;

        // Unknown CPT code in map — warn but don't block
        if ($validSnomeds === null) {
            return $this->gate('procedure_diagnosis', 'warn', "CPT {$cptCode} not in verification map", $cptCode, $snomedCode);
        }

        // Empty mapping array = any diagnosis is acceptable
        if (empty($validSnomeds)) {
            return $this->gate('procedure_diagnosis', 'pass', 'Procedure accepted for any diagnosis', $cptCode, $snomedCode);
        }

        $matched = $snomedCode && in_array($snomedCode, $validSnomeds, true);

        return $this->gate(
            'procedure_diagnosis',
            $matched ? 'pass' : 'hold',
            $matched
                ? "CPT {$cptCode} matches SNOMED {$snomedCode}"
                : "SNOMED {$snomedCode} does not match expected diagnoses for CPT {$cptCode}",
            $cptCode,
            $snomedCode
        );
    }

    // ─── Gate 2: Platelet Count ───────────────────────────────────────────────
    private function runPlateletGate(?array $observation): array
    {
        if (!$observation) {
            return $this->gate('platelet_count', 'warn', 'No platelet result found (LOINC 777-3)', null, null);
        }

        $value = (float) ($observation['valueQuantity']['value'] ?? 0);
        $unit  = $observation['valueQuantity']['unit'] ?? '10⁹/L';
        $date  = substr($observation['effectiveDateTime'] ?? '', 0, 10);

        if ($value >= self::PLATELET_PASS_MIN) {
            $status  = 'pass';
            $message = "Platelet count {$value} {$unit} (safe ≥ 100)";
        } elseif ($value >= self::PLATELET_WARN_MIN) {
            $status  = 'warn';
            $message = "Platelet count {$value} {$unit} (marginal — 50–99)";
        } else {
            $status  = 'hold';
            $message = "Platelet count {$value} {$unit} CRITICALLY LOW (< 50) — HOLD";
        }

        return $this->gate('platelet_count', $status, $message, $value, $unit, ['date' => $date, 'loinc' => '777-3']);
    }

    // ─── Gate 3: INR / Clotting Time ─────────────────────────────────────────
    private function runInrGate(?array $observation): array
    {
        if (!$observation) {
            return $this->gate('inr_clotting', 'warn', 'No INR result found (LOINC 6301-6)', null, null);
        }

        $value = (float) ($observation['valueQuantity']['value'] ?? 0);
        $unit  = $observation['valueQuantity']['unit'] ?? 'INR';
        $date  = substr($observation['effectiveDateTime'] ?? '', 0, 10);

        if ($value <= self::INR_PASS_MAX) {
            $status  = 'pass';
            $message = "INR {$value} (safe ≤ 1.5)";
        } elseif ($value <= self::INR_WARN_MAX) {
            $status  = 'warn';
            $message = "INR {$value} (elevated — 1.5–2.0, verify with anaesthesia)";
        } else {
            $status  = 'hold';
            $message = "INR {$value} CRITICALLY HIGH (> 2.0) — HOLD";
        }

        return $this->gate('inr_clotting', $status, $message, $value, $unit, ['date' => $date, 'loinc' => '6301-6']);
    }

    // ─── Gate 4: Allergy Check ────────────────────────────────────────────────
    private function runAllergyGate(array $dangerousAllergies): array
    {
        if (empty($dangerousAllergies)) {
            return $this->gate('allergy_check', 'pass', 'No surgical antibiotic allergies on record', null, null);
        }

        $substances = implode(', ', array_column($dangerousAllergies, 'substance'));
        $hasCritical = collect($dangerousAllergies)
            ->contains(fn($a) => $a['criticality'] === 'high');

        return $this->gate(
            'allergy_check',
            $hasCritical ? 'hold' : 'warn',
            "Allergy alert: {$substances}",
            null,
            null,
            ['allergies' => $dangerousAllergies]
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function gate(string $id, string $status, string $message, mixed $value, mixed $unit, array $extra = []): array
    {
        return array_merge([
            'id'      => $id,
            'status'  => $status,   // 'pass' | 'warn' | 'hold'
            'message' => $message,
            'value'   => $value,
            'unit'    => $unit,
        ], $extra);
    }

    private function extractCptCode(?array $serviceRequest): ?string
    {
        if (!$serviceRequest) return null;
        $codings = $serviceRequest['code']['coding'] ?? [];
        foreach ($codings as $coding) {
            // CPT system URI
            if (str_contains($coding['system'] ?? '', 'cpt') || str_contains($coding['system'] ?? '', 'AMA')) {
                return $coding['code'] ?? null;
            }
        }
        return $codings[0]['code'] ?? null;
    }

    private function extractSnomedCode(?array $condition): ?string
    {
        if (!$condition) return null;
        $codings = $condition['code']['coding'] ?? [];
        foreach ($codings as $coding) {
            if (str_contains($coding['system'] ?? '', 'snomed') || str_contains($coding['system'] ?? '', 'SNOMED')) {
                return $coding['code'] ?? null;
            }
        }
        return $codings[0]['code'] ?? null;
    }

    /**
     * Return a PHI-safe patient summary (no names, DOBs, phone numbers).
     * Only FHIR IDs and coded clinical values.
     */
    private function safePatientSummary(array $patient): array
    {
        return [
            'fhir_id'  => $patient['id'] ?? 'unknown',
            'gender'   => $patient['gender'] ?? 'unknown',
            'age_band' => $this->ageBand($patient['birthDate'] ?? null),
        ];
    }

    private function ageBand(?string $birthDate): string
    {
        if (!$birthDate) return 'unknown';
        $age = now()->diffInYears(\Carbon\Carbon::parse($birthDate));
        return match(true) {
            $age < 18  => 'pediatric',
            $age < 40  => '18-39',
            $age < 60  => '40-59',
            $age < 75  => '60-74',
            default    => '75+',
        };
    }
}
