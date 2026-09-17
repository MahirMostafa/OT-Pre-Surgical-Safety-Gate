<?php

namespace App\Repositories\Fhir;

use App\Repositories\Contracts\AllergyRepositoryInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FhirAllergyRepository implements AllergyRepositoryInterface
{
    private string $fhirBaseUrl;
    private string $accessToken;

    // ─── Surgical Antibiotic Substance Codes (RxNorm) ────────────────────────
    // GREEN FLAG: Code-based lookup, never string match on drug names
    public const SURGICAL_ANTIBIOTIC_CODES = [
        '7454'   => 'Cephalosporin',
        '7980'   => 'Penicillin',
        '11124'  => 'Vancomycin',
        '2626'   => 'Clindamycin',
        '18631'  => 'Metronidazole',
        '25037'  => 'Gentamicin',
    ];

    public const RXNORM_SYSTEM = 'http://www.nlm.nih.gov/research/umls/rxnorm';

    public function __construct()
    {
        $this->fhirBaseUrl = rtrim(config('fhir.base_url'), '/');
        $this->accessToken = session('smart_access_token', '');
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveAllergies(string $patientId): array
    {
        $response = Http::withToken($this->accessToken)
            ->withHeaders(['Accept' => 'application/fhir+json'])
            ->get("{$this->fhirBaseUrl}/AllergyIntolerance", [
                'patient'         => $patientId,
                'clinical-status' => 'active',
                '_count'          => 50,
            ]);

        if ($response->failed()) {
            Log::error('FHIR AllergyIntolerance fetch failed', [
                'patient_id' => $patientId,
                'status'     => $response->status(),
            ]);
            return [];
        }

        $bundle  = $response->json();
        $entries = $bundle['entry'] ?? [];

        return array_map(fn($e) => $e['resource'], $entries);
    }

    /**
     * {@inheritdoc}
     */
    public function checkForSubstances(string $patientId, array $substanceCodes): array
    {
        $allergies = $this->getActiveAllergies($patientId);
        $matches   = [];

        foreach ($allergies as $allergy) {
            $codings = $allergy['code']['coding'] ?? [];
            foreach ($codings as $coding) {
                $code = $coding['code'] ?? '';
                if (in_array($code, $substanceCodes, true)) {
                    $matches[] = [
                        'resource'     => $allergy,
                        'matched_code' => $code,
                        'substance'    => self::SURGICAL_ANTIBIOTIC_CODES[$code] ?? $coding['display'] ?? 'Unknown',
                        'criticality'  => $allergy['criticality'] ?? 'unknown',
                        'reaction'     => $allergy['reaction'][0]['manifestation'][0]['coding'][0]['display'] ?? null,
                    ];
                    break;
                }
            }
        }

        return $matches;
    }
}
