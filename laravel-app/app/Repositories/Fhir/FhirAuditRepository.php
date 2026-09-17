<?php

namespace App\Repositories\Fhir;

use App\Repositories\Contracts\AuditRepositoryInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FhirAuditRepository implements AuditRepositoryInterface
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
    public function writeAuditEvent(array $auditEventPayload): array
    {
        // Ensure resource type is set
        $auditEventPayload['resourceType'] = 'AuditEvent';

        $response = Http::withToken($this->accessToken)
            ->withHeaders([
                'Accept'       => 'application/fhir+json',
                'Content-Type' => 'application/fhir+json',
            ])
            ->post("{$this->fhirBaseUrl}/AuditEvent", $auditEventPayload);

        if ($response->failed()) {
            Log::error('FHIR AuditEvent write failed', [
                'status'  => $response->status(),
                'body'    => $response->body(),
                'payload' => $auditEventPayload,
            ]);
            return [];
        }

        return $response->json();
    }

    /**
     * {@inheritdoc}
     */
    public function getRecentForPatient(string $patientId, int $limit = 10): array
    {
        $response = Http::withToken($this->accessToken)
            ->withHeaders(['Accept' => 'application/fhir+json'])
            ->get("{$this->fhirBaseUrl}/AuditEvent", [
                'patient' => $patientId,
                '_sort'   => '-_lastUpdated',
                '_count'  => $limit,
            ]);

        if ($response->failed()) {
            return [];
        }

        $bundle  = $response->json();
        $entries = $bundle['entry'] ?? [];

        return array_map(fn($e) => $e['resource'], $entries);
    }
}
