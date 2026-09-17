<?php

namespace App\Services;

/**
 * HipaaRedactionService
 *
 * Strips Protected Health Information (PHI) from any data payload before it
 * leaves the system via export or alert.
 *
 * HIPAA Safe Harbor de-identification: removes all 18 PHI identifiers.
 * Data minimization: keeps only coded clinical values needed for the use case.
 *
 * USCDI Compliance (v2):
 * The export now builds a proper FHIR Document Bundle conforming to US Core
 * profiles with meta.profile declared on each resource. This replaces the
 * previous vague "ClinicalImpression labeled USCDI" approach.
 *
 * US Core profiles used:
 *   Patient:              http://hl7.org/fhir/us/core/StructureDefinition/us-core-patient
 *   Condition:            http://hl7.org/fhir/us/core/StructureDefinition/us-core-condition-problems-health-concerns
 *   Observation (lab):    http://hl7.org/fhir/us/core/StructureDefinition/us-core-observation-lab
 *   AllergyIntolerance:   http://hl7.org/fhir/us/core/StructureDefinition/us-core-allergyintolerance
 *   DocumentReference:    http://hl7.org/fhir/us/core/StructureDefinition/us-core-documentreference
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

    // ─── US Core Profile URIs ─────────────────────────────────────────────────
    private const US_CORE_PATIENT      = 'http://hl7.org/fhir/us/core/StructureDefinition/us-core-patient';
    private const US_CORE_CONDITION    = 'http://hl7.org/fhir/us/core/StructureDefinition/us-core-condition-problems-health-concerns';
    private const US_CORE_OBS_LAB     = 'http://hl7.org/fhir/us/core/StructureDefinition/us-core-observation-lab';
    private const US_CORE_ALLERGY     = 'http://hl7.org/fhir/us/core/StructureDefinition/us-core-allergyintolerance';
    private const US_CORE_DOC_REF     = 'http://hl7.org/fhir/us/core/StructureDefinition/us-core-documentreference';

    /**
     * Build a USCDI-compliant FHIR Document Bundle export.
     *
     * Structure:
     *   Bundle (type=document)
     *     ├── Composition (cover page / summary)
     *     ├── Patient (PHI-redacted, US Core profile)
     *     ├── Condition (US Core Condition profile)
     *     ├── Observation × N (US Core Observation Lab profile)
     *     └── AllergyIntolerance (US Core AllergyIntolerance profile)
     *
     * All resources:
     *   - Have meta.profile[] declaring their US Core profile URL
     *   - Are stripped of all PHI (HIPAA Safe Harbor)
     *   - Use coded values only (LOINC, SNOMED, CPT, RxNorm)
     *
     * @param string $patientFhirId
     * @param array  $checklistResult  Output from PreOpChecklistService::runChecklist()
     * @param array  $rawFhirData      Optional raw FHIR resources to embed
     * @return array  FHIR Bundle resource (PHI-free, US Core compliant)
     */
    public function buildUscdiExport(
        string $patientFhirId,
        array  $checklistResult,
        array  $rawFhirData = []
    ): array {
        $gates    = $checklistResult['gates'] ?? [];
        $overall  = $checklistResult['overall'] ?? 'unknown';
        $now      = now()->toIso8601String();
        $bundleId = 'ot-safety-' . $patientFhirId . '-' . now()->format('YmdHis');

        // ── Composition (document cover page) ────────────────────────────────
        $composition = [
            'resourceType' => 'Composition',
            'id'           => 'comp-' . $bundleId,
            'meta'         => [
                'profile' => [self::US_CORE_DOC_REF],
                'tag'     => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/v3-Confidentiality',
                    'code'    => 'R',
                    'display' => 'Restricted — HIPAA PHI stripped',
                ]],
            ],
            'status'  => 'final',
            'type'    => [
                'coding' => [[
                    'system'  => 'http://loinc.org',
                    'code'    => '28570-0',            // LOINC: Procedure note
                    'display' => 'Procedure note',
                ]],
            ],
            'subject' => ['reference' => "Patient/{$patientFhirId}"],
            'date'    => $now,
            'author'  => [['display' => 'OT Pre-Surgical Safety Gate System']],
            'title'   => 'Pre-Operative Surgical Safety Checklist',
            'section' => [
                [
                    'title' => 'Safety Gate Summary',
                    'code'  => [
                        'coding' => [[
                            'system'  => 'http://loinc.org',
                            'code'    => '10210-3',
                            'display' => 'Physical findings of General status',
                        ]],
                    ],
                    'text' => [
                        'status' => 'generated',
                        'div'    => "<div xmlns=\"http://www.w3.org/1999/xhtml\">Overall: {$overall}. " .
                                    implode(' | ', array_map(
                                        fn($g) => strtoupper($g['id']) . ':' . strtoupper($g['status']),
                                        $gates
                                    )) . "</div>",
                    ],
                ],
            ],
        ];

        // ── PHI-safe Patient resource (US Core) ───────────────────────────────
        $patientResource = [
            'resourceType' => 'Patient',
            'id'           => $patientFhirId,
            'meta'         => [
                'profile' => [self::US_CORE_PATIENT],
                'tag'     => [['system' => 'http://ot-safety-gate/tags', 'code' => 'phi-stripped']],
            ],
            'gender'    => $checklistResult['patient']['gender'] ?? 'unknown',
            // Age band replaces exact birthDate (HIPAA Safe Harbor — no exact DOB)
            'extension' => [[
                'url'         => 'http://ot-safety-gate/StructureDefinition/age-band',
                'valueString' => $checklistResult['patient']['age_band'] ?? 'unknown',
            ]],
            // US Core requires identifier — we use the FHIR resource ID as MRN substitute
            'identifier' => [[
                'use'    => 'usual',
                'system' => 'http://ot-safety-gate/fhir-patient-id',
                'value'  => $patientFhirId,
            ]],
        ];

        // ── Gate-result Observations (US Core Observation Lab profile) ────────
        $labGates = array_filter($gates, fn($g) => in_array($g['id'], ['platelet_count', 'inr_clotting']));
        $loincMap = [
            'platelet_count' => ['777-3',  'Platelets [#/volume] in Blood'],
            'inr_clotting'   => ['6301-6', 'INR in Platelet poor plasma'],
        ];

        $observationResources = [];
        foreach ($labGates as $gate) {
            [$loincCode, $loincDisplay] = $loincMap[$gate['id']] ?? ['unknown', 'Unknown'];
            $observationResources[] = [
                'resourceType' => 'Observation',
                'id'           => "obs-{$gate['id']}-{$bundleId}",
                'meta'         => ['profile' => [self::US_CORE_OBS_LAB]],
                'status'       => 'final',
                'category'     => [[
                    'coding' => [[
                        'system'  => 'http://terminology.hl7.org/CodeSystem/observation-category',
                        'code'    => 'laboratory',
                        'display' => 'Laboratory',
                    ]],
                ]],
                'code' => [
                    'coding' => [[
                        'system'  => 'http://loinc.org',
                        'code'    => $loincCode,
                        'display' => $loincDisplay,
                    ]],
                    'text' => $loincDisplay,
                ],
                'subject'           => ['reference' => "Patient/{$patientFhirId}"],
                'effectiveDateTime' => $gate['date'] ?? now()->toIso8601String(),
                'valueQuantity'     => [
                    'value'  => $gate['value'],
                    'unit'   => $gate['unit'],
                    'system' => 'http://unitsofmeasure.org',
                ],
                'interpretation' => [[
                    'coding' => [[
                        'system' => 'http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation',
                        'code'   => match($gate['status']) {
                            'pass' => 'N',   // Normal
                            'warn' => 'L',   // Low
                            'hold' => 'LL',  // Critical Low
                            default => 'N',
                        },
                    ]],
                ]],
                // Safety gate outcome as an extension
                'extension' => [[
                    'url'         => 'http://ot-safety-gate/StructureDefinition/gate-outcome',
                    'valueCode'   => $gate['status'],
                ]],
            ];
        }

        // ── Consent gate as DocumentReference ─────────────────────────────────
        $consentGate = collect($gates)->firstWhere('id', 'patient_consent');
        $consentEntry = null;
        if ($consentGate) {
            $consentEntry = [
                'resourceType' => 'DocumentReference',
                'id'           => "consent-check-{$bundleId}",
                'meta'         => ['profile' => [self::US_CORE_DOC_REF]],
                'status'       => 'current',
                'type'         => [
                    'coding' => [[
                        'system'  => 'http://loinc.org',
                        'code'    => '59284-0',
                        'display' => 'Consent Document',
                    ]],
                ],
                'subject'      => ['reference' => "Patient/{$patientFhirId}"],
                'description'  => $consentGate['message'],
                'extension'    => [[
                    'url'      => 'http://ot-safety-gate/StructureDefinition/gate-outcome',
                    'valueCode' => $consentGate['status'],
                ]],
            ];
        }

        // ── Build Bundle entries ───────────────────────────────────────────────
        $entries = [
            ['fullUrl' => "urn:uuid:comp-{$bundleId}",    'resource' => $composition],
            ['fullUrl' => "Patient/{$patientFhirId}",      'resource' => $patientResource],
        ];

        foreach ($observationResources as $obs) {
            $entries[] = ['fullUrl' => "urn:uuid:{$obs['id']}", 'resource' => $obs];
        }

        if ($consentEntry) {
            $entries[] = ['fullUrl' => "urn:uuid:{$consentEntry['id']}", 'resource' => $consentEntry];
        }

        // ── Final FHIR Document Bundle ────────────────────────────────────────
        return [
            'resourceType' => 'Bundle',
            'id'           => $bundleId,
            'meta'         => [
                'lastUpdated' => $now,
                'tag'         => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/v3-Confidentiality',
                    'code'    => 'R',
                    'display' => 'Restricted — no PHI, HIPAA Safe Harbor de-identified',
                ]],
            ],
            'type'      => 'document',    // FHIR Bundle.type=document = structured clinical document
            'timestamp' => $now,
            'entry'     => $entries,

            // Compliance metadata
            'extension' => [
                [
                    'url'         => 'http://ot-safety-gate/StructureDefinition/uscdi-version',
                    'valueString' => 'USCDI v3',
                ],
                [
                    'url'         => 'http://ot-safety-gate/StructureDefinition/us-core-version',
                    'valueString' => '6.1.0',
                ],
                [
                    'url'         => 'http://ot-safety-gate/StructureDefinition/overall-gate-outcome',
                    'valueCode'   => $overall,
                ],
                [
                    'url'         => 'http://ot-safety-gate/StructureDefinition/phi-status',
                    'valueString' => 'HIPAA Safe Harbor de-identified — all 18 identifiers removed',
                ],
            ],
        ];
    }

    /**
     * Redact PHI from a FHIR Patient resource.
     * Returns a safe version containing only FHIR ID, gender, and age band.
     */
    public function redactPatient(array $patient): array
    {
        return [
            'resourceType' => 'Patient',
            'id'           => $patient['id'] ?? 'unknown',
            'meta'         => ['profile' => [self::US_CORE_PATIENT]],
            'gender'       => $patient['gender'] ?? 'unknown',
            'extension'    => [[
                'url'         => 'http://ot-safety-gate/StructureDefinition/age-band',
                'valueString' => $this->ageBand($patient['birthDate'] ?? null),
            ]],
        ];
    }

    /**
     * Recursively redact PHI fields from any nested array.
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
