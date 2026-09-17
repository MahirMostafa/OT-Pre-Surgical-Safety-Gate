<?php

namespace App\Repositories\Fhir;

use App\Repositories\Contracts\PatientRepositoryInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FhirPatientRepository implements PatientRepositoryInterface
{
    private string $fhirBaseUrl;
    private string $accessToken;

    public function __construct()
    {
        $this->fhirBaseUrl = rtrim(config('fhir.base_url'), '/');
        $this->accessToken = session('smart_access_token', '');
    }

    /**
     * {@inheritdoc}
     */
    public function findById(string $patientId): array
    {
        $response = Http::withToken($this->accessToken)
            ->withHeaders(['Accept' => 'application/fhir+json'])
            ->get("{$this->fhirBaseUrl}/Patient/{$patientId}");

        if ($response->failed()) {
            Log::error('FHIR Patient fetch failed', [
                'patient_id' => $patientId,
                'status'     => $response->status(),
            ]);
            return [];
        }

        return $response->json();
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveServiceRequest(string $patientId): ?array
    {
        // Query by patient + active status — CPT codes are in ServiceRequest.code
        $response = Http::withToken($this->accessToken)
            ->withHeaders(['Accept' => 'application/fhir+json'])
            ->get("{$this->fhirBaseUrl}/ServiceRequest", [
                'patient' => $patientId,
                'status'  => 'active',
                '_sort'   => '-_lastUpdated',
                '_count'  => 1,
            ]);

        if ($response->failed()) {
            Log::error('FHIR ServiceRequest fetch failed', ['patient_id' => $patientId]);
            return null;
        }

        $bundle = $response->json();
        $entries = $bundle['entry'] ?? [];

        return !empty($entries) ? $entries[0]['resource'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveCondition(string $patientId): ?array
    {
        // clinical-status=active returns only current SNOMED-coded diagnoses
        $response = Http::withToken($this->accessToken)
            ->withHeaders(['Accept' => 'application/fhir+json'])
            ->get("{$this->fhirBaseUrl}/Condition", [
                'patient'         => $patientId,
                'clinical-status' => 'active',
                '_sort'           => '-_lastUpdated',
                '_count'          => 1,
            ]);

        if ($response->failed()) {
            Log::error('FHIR Condition fetch failed', ['patient_id' => $patientId]);
            return null;
        }

        $bundle = $response->json();
        $entries = $bundle['entry'] ?? [];

        return !empty($entries) ? $entries[0]['resource'] : null;
    }
}
