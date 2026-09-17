<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FhirTestDataSeeder
 *
 * Seeds the local HAPI FHIR R4 server with realistic test data
 * for the OT Pre-Surgical Safety Gate demo.
 *
 * Creates:
 *   - 1 Patient (test-patient-001) — no real PHI
 *   - 1 ServiceRequest (knee replacement, CPT 27447)
 *   - 1 Condition (knee osteoarthritis, SNOMED 57773001)
 *   - 1 Observation: Platelet Count LOINC 777-3 = 145 × 10⁹/L (PASS)
 *   - 1 Observation: INR LOINC 6301-6 = 1.2 (PASS)
 *   - 1 AllergyIntolerance (none — safe)
 *
 * Usage: php artisan db:seed --class=FhirTestDataSeeder
 */
class FhirTestDataSeeder extends Seeder
{
    private string $fhirBaseUrl;

    public function run(): void
    {
        $this->fhirBaseUrl = config('fhir.base_url', 'http://localhost:8080/fhir');
        $this->command->info("Seeding HAPI FHIR at: {$this->fhirBaseUrl}");

        // ── 1. Patient ────────────────────────────────────────────────────────
        $this->upsert('Patient', 'test-patient-001', [
            'resourceType' => 'Patient',
            'id'           => 'test-patient-001',
            'gender'       => 'male',
            'birthDate'    => '1958-04-12',  // Age band: 60-74
            'meta'         => ['tag' => [['system' => 'http://ot-safety-gate/tags', 'code' => 'test-data']]],
        ]);

        // ── 2. ServiceRequest (Scheduled Surgery) — CPT 27447 (Total Knee) ───
        $this->upsert('ServiceRequest', 'sr-test-001', [
            'resourceType' => 'ServiceRequest',
            'id'           => 'sr-test-001',
            'status'       => 'active',
            'intent'       => 'order',
            'subject'      => ['reference' => 'Patient/test-patient-001'],
            'code'         => [
                'coding' => [[
                    'system'  => 'http://www.ama-assn.org/go/cpt',
                    'code'    => '27447',
                    'display' => 'Total knee arthroplasty',
                ]],
                'text' => 'Total Knee Replacement',
            ],
            'occurrenceDateTime' => now()->addDays(1)->toIso8601String(),
            'requester'    => ['display' => 'Dr. Test Surgeon'],
        ]);

        // ── 3. Condition (Diagnosis) — SNOMED 57773001 (Knee osteoarthritis) ─
        $this->upsert('Condition', 'cond-test-001', [
            'resourceType'   => 'Condition',
            'id'             => 'cond-test-001',
            'clinicalStatus' => [
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                    'code'   => 'active',
                ]],
            ],
            'subject' => ['reference' => 'Patient/test-patient-001'],
            'code'    => [
                'coding' => [[
                    'system'  => 'http://snomed.info/sct',
                    'code'    => '57773001',
                    'display' => 'Osteoarthritis of knee',
                ]],
                'text' => 'Knee Osteoarthritis',
            ],
            'onsetDateTime' => '2022-03-15',
        ]);

        // ── 4. Observation: Platelet Count (LOINC 777-3) ─────────────────────
        $this->upsert('Observation', 'obs-plt-001', [
            'resourceType' => 'Observation',
            'id'           => 'obs-plt-001',
            'status'       => 'final',
            'subject'      => ['reference' => 'Patient/test-patient-001'],
            'code'         => [
                'coding' => [[
                    'system'  => 'http://loinc.org',
                    'code'    => '777-3',
                    'display' => 'Platelets [#/volume] in Blood by Automated count',
                ]],
            ],
            'valueQuantity'      => ['value' => 145.0, 'unit' => '10^9/L', 'system' => 'http://unitsofmeasure.org', 'code' => '10*9/L'],
            'effectiveDateTime'  => now()->subHours(6)->toIso8601String(),
            'issued'             => now()->subHours(6)->toIso8601String(),
        ]);

        // ── 5. Observation: INR (LOINC 6301-6) ───────────────────────────────
        $this->upsert('Observation', 'obs-inr-001', [
            'resourceType' => 'Observation',
            'id'           => 'obs-inr-001',
            'status'       => 'final',
            'subject'      => ['reference' => 'Patient/test-patient-001'],
            'code'         => [
                'coding' => [[
                    'system'  => 'http://loinc.org',
                    'code'    => '6301-6',
                    'display' => 'INR in Platelet poor plasma by Coagulation assay',
                ]],
            ],
            'valueQuantity'      => ['value' => 1.2, 'unit' => 'INR', 'system' => 'http://unitsofmeasure.org', 'code' => '{INR}'],
            'effectiveDateTime'  => now()->subHours(6)->toIso8601String(),
            'issued'             => now()->subHours(6)->toIso8601String(),
        ]);

        // ── 6. Consent: Informed Surgical Consent for CPT 27447 ─────────────
        $this->upsert('Consent', 'consent-test-001', [
            'resourceType' => 'Consent',
            'id'           => 'consent-test-001',
            'status'       => 'active',
            'scope'        => [
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/consentscope',
                    'code'    => 'treatment',
                    'display' => 'Treatment',
                ]],
            ],
            'category' => [[
                'coding' => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/consentcategorycodes',
                    'code'    => 'acd',
                    'display' => 'Advance Care Directive',
                ]],
            ]],
            'patient'  => ['reference' => 'Patient/test-patient-001'],
            'dateTime' => now()->subDays(2)->toIso8601String(),
            'policyRule' => [
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/consentpolicycodes',
                    'code'   => 'opt-in',
                ]],
            ],
            'provision' => [
                'type'   => 'permit',
                'period' => [
                    'start' => now()->subDays(2)->toIso8601String(),
                    'end'   => now()->addDays(30)->toIso8601String(),
                ],
                'action' => [[
                    'coding' => [[
                        'system'  => 'http://www.ama-assn.org/go/cpt',
                        'code'    => '27447',
                        'display' => 'Total knee arthroplasty',
                    ]],
                ]],
            ],
        ]);

        $this->command->info('✅ HAPI FHIR test data seeded successfully!');
        $this->command->info('   Patient: test-patient-001');
        $this->command->info('   Procedure: Total Knee Arthroplasty (CPT 27447)');
        $this->command->info('   Diagnosis: Knee Osteoarthritis (SNOMED 57773001)');
        $this->command->info('   Labs: Platelets=145, INR=1.2 (both PASS)');
        $this->command->info('   Allergies: None');
        $this->command->info('   Consent: Active procedure-specific consent for CPT 27447');
    }

    private function upsert(string $resourceType, string $id, array $payload): void
    {
        $url = "{$this->fhirBaseUrl}/{$resourceType}/{$id}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/fhir+json',
            'Accept'       => 'application/fhir+json',
        ])->put($url, $payload);

        if ($response->successful()) {
            $this->command->line("  ✓ {$resourceType}/{$id}");
        } else {
            $this->command->warn("  ✗ {$resourceType}/{$id} — {$response->status()}: {$response->body()}");
        }
    }
}
