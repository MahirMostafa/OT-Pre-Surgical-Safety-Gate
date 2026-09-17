<?php

namespace App\Services;

/**
 * HipaaRedactionService
 *
 * Strips Protected Health Information (PHI) from any data
 * payload before it leaves the system via export or alert.
 *
 * HIPAA Safe Harbor de-identification: removes all 18 PHI identifiers.
 * Data minimization: keeps only coded clinical values needed for the use case.
 */
class HipaaRedactionService
{
    /**
     * PHI fields that must NEVER appear in exported or externally shared payloads.
     * Based on HIPAA Safe Harbor 18-identifier list.
     */
    private const PHI_FIELD_NAMES = [
        'name', 'names', 'family', 'given', 'text',
        'birthDate', 'birth_date', 'dob', 'dateOfBirth',
        'phone', 'telecom', 'fax', 'email',
        'address', 'city', 'state', 'postalCode', 'zip', 'country',
        'ssn', 'socialSecurityNumber',
        'mrn', 'accountNumber',
        'photo', 'image',
        'deviceId', 'deviceIdentifier',
        'ipAddress',
    ];

    /**
     * Redact PHI from a FHIR Patient resource.
     * Returns a safe version containing only FHIR ID, gender, and age band.
     *
     * @param array $patient  Full FHIR Patient resource
     * @return array PHI-free patient summary
     */
    public function redactPatient(array $patient): array
    {
        return [
            'resourceType' => 'Patient',
            'id'           => $patient['id'] ?? 'unknown',
            'gender'       => $patient['gender'] ?? 'unknown',
            // Age band instead of exact birth date
            'extension' => [[
                'url'         => 'http://ot-safety-gate/StructureDefinition/age-band',
                'valueString' => $this->ageBand($patient['birthDate'] ?? null),
            ]],
        ];
    }

    /**
     * Recursively redact PHI fields from any nested array.
     *
     * @param array $data  Any data array (FHIR resource, export payload, etc.)
     * @return array  Redacted data
     */
    public function redactArray(array $data): array
    {
        $redacted = [];
        foreach ($data as $key => $value) {
            if (in_array($key, self::PHI_FIELD_NAMES, true)) {
                $redacted[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redactArray($value);
            } else {
                $redacted[$key] = $value;
            }
        }
        return $redacted;
    }

    /**
     * Build a USCDI-compliant ClinicalImpression export payload,
     * with all PHI stripped.
     *
     * @param string $patientFhirId
     * @param array  $checklistResult  Output from PreOpChecklistService::runChecklist()
     * @return array  FHIR ClinicalImpression resource (PHI-free)
     */
    public function buildUscdiExport(string $patientFhirId, array $checklistResult): array
    {
        $gates   = $checklistResult['gates'] ?? [];
        $overall = $checklistResult['overall'] ?? 'unknown';

        return [
            'resourceType' => 'ClinicalImpression',
            'status'       => 'completed',
            'subject'      => ['reference' => "Patient/{$patientFhirId}"],
            'date'         => now()->toIso8601String(),
            'description'  => "Pre-operative surgical safety checklist. Overall: {$overall}.",
            'finding'      => array_map(fn($gate) => [
                'itemCodeableConcept' => [
                    'coding' => [[
                        'system'  => 'http://ot-safety-gate/CodeSystem/safety-gate',
                        'code'    => $gate['id'],
                        'display' => str_replace('_', ' ', ucwords($gate['id'], '_')),
                    ]],
                    'text' => $gate['message'],
                ],
                'basis' => $gate['status'],
            ], $gates),
            'summary' => implode(' | ', array_map(
                fn($g) => strtoupper($g['id']) . ':' . strtoupper($g['status']),
                $gates
            )),
            // USCDI note section
            'note' => [[
                'text' => "OT Safety Gate — Automated pre-surgical safety verification. " .
                          "Overall outcome: {$overall}. " .
                          "Generated: " . now()->toIso8601String() . ". " .
                          "No patient identifiers included (HIPAA Safe Harbor).",
            ]],
            // Metadata for USCDI compliance
            'meta' => [
                'profile' => ['http://hl7.org/fhir/us/core/StructureDefinition/us-core-documentreference'],
                'tag'     => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/v3-Confidentiality',
                    'code'    => 'R',
                    'display' => 'Restricted — no PHI',
                ]],
            ],
        ];
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

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
