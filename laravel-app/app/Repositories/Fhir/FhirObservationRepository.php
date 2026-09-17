<?php

namespace App\Repositories\Fhir;

use App\Repositories\Contracts\ObservationRepositoryInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FhirObservationRepository implements ObservationRepositoryInterface
{
    private string $fhirBaseUrl;
    private string $accessToken;

    // ─── LOINC Code Constants ─────────────────────────────────────────────────
    // GREEN FLAG: Always query by LOINC code — never string-match on display names
    public const LOINC_PLATELET_COUNT = '777-3';
    public const LOINC_INR_PT         = '6301-6';
    public const LOINC_SYSTEM         = 'http://loinc.org';

    public function __construct()
    {
        $this->fhirBaseUrl = rtrim(config('fhir.base_url'), '/');
        $this->accessToken = session('smart_access_token', '');
    }

    /**
     * {@inheritdoc}
     */
    public function getLatestByLoincCode(string $patientId, string $loincCode): ?array
    {
        // FHIR code search: system|code format for precise LOINC lookup
        $response = Http::withToken($this->accessToken)
            ->withHeaders(['Accept' => 'application/fhir+json'])
            ->get("{$this->fhirBaseUrl}/Observation", [
                'patient' => $patientId,
                'code'    => self::LOINC_SYSTEM . '|' . $loincCode,
                '_sort'   => '-date',
                '_count'  => 1,
                'status'  => 'final,amended',
            ]);

        if ($response->failed()) {
            Log::error('FHIR Observation fetch failed', [
                'patient_id' => $patientId,
                'loinc_code' => $loincCode,
                'status'     => $response->status(),
            ]);
            return null;
        }

        $bundle  = $response->json();
        $entries = $bundle['entry'] ?? [];

        return !empty($entries) ? $entries[0]['resource'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getLatestByLoincCodes(string $patientId, array $loincCodes): array
    {
        // Build comma-separated system|code string for multi-code query
        $codeParam = implode(',', array_map(
            fn($code) => self::LOINC_SYSTEM . '|' . $code,
            $loincCodes
        ));

        $response = Http::withToken($this->accessToken)
            ->withHeaders(['Accept' => 'application/fhir+json'])
            ->get("{$this->fhirBaseUrl}/Observation", [
                'patient' => $patientId,
                'code'    => $codeParam,
                '_sort'   => '-date',
                '_count'  => count($loincCodes) * 2,
                'status'  => 'final,amended',
            ]);

        if ($response->failed()) {
            Log::error('FHIR multi-Observation fetch failed', [
                'patient_id'  => $patientId,
                'loinc_codes' => $loincCodes,
            ]);
            return [];
        }

        $bundle  = $response->json();
        $entries = $bundle['entry'] ?? [];

        // Return only the most recent per LOINC code
        $latestByCode = [];
        foreach ($entries as $entry) {
            $resource = $entry['resource'] ?? [];
            $codes    = $resource['code']['coding'] ?? [];
            foreach ($codes as $coding) {
                $code = $coding['code'] ?? '';
                if (in_array($code, $loincCodes) && !isset($latestByCode[$code])) {
                    $latestByCode[$code] = $resource;
                }
            }
        }

        return $latestByCode;
    }
}
