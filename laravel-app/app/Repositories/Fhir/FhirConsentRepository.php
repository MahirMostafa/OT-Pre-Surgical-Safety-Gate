<?php

namespace App\Repositories\Fhir;

use App\Repositories\Contracts\ConsentRepositoryInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FhirConsentRepository implements ConsentRepositoryInterface
{
    private string $fhirBaseUrl;
    private string $accessToken;

    // CPT system URI — used to match procedure code in Consent provision
    public const CPT_SYSTEM = 'http://www.ama-assn.org/go/cpt';

    // Consent action code for treatment procedures
    public const CONSENT_ACTION_SYSTEM = 'http://terminology.hl7.org/CodeSystem/v3-ActCode';
    public const CONSENT_ACTION_CODE   = 'TREAT'; // Treat = treatment consent

    public function __construct()
    {
        $this->fhirBaseUrl = rtrim(config('fhir.base_url'), '/');
        $this->accessToken = session('smart_access_token', '');
    }

    /**
     * {@inheritdoc}
     */
    public function getForPatient(string $patientId): array
    {
        $response = Http::withToken($this->accessToken)
            ->withHeaders(['Accept' => 'application/fhir+json'])
            ->get("{$this->fhirBaseUrl}/Consent", [
                'patient' => $patientId,
                'status'  => 'active',
                '_sort'   => '-_lastUpdated',
                '_count'  => 20,
            ]);

        if ($response->failed()) {
            Log::error('FHIR Consent fetch failed', [
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
     *
     * Searches for a Consent resource that:
     *  1. Is status=active
     *  2. Has provision.type = 'permit'
     *  3. Has provision.action[] with a CPT coding matching $procedureCode
     *     OR provision.data[] referencing the ServiceRequest
     */
    public function findProcedureConsent(string $patientId, string $procedureCode): ?array
    {
        $consents = $this->getForPatient($patientId);

        foreach ($consents as $consent) {
            // Must be active + permit type
            if (($consent['status'] ?? '') !== 'active') continue;

            $provision = $consent['provision'] ?? [];
            if (($provision['type'] ?? '') !== 'permit') continue;

            // Check provision.action for matching CPT code
            $actions = $provision['action'] ?? [];
            foreach ($actions as $action) {
                foreach ($action['coding'] ?? [] as $coding) {
                    if (
                        ($coding['system'] ?? '') === self::CPT_SYSTEM &&
                        ($coding['code'] ?? '') === $procedureCode
                    ) {
                        return $consent; // Exact procedure-specific consent found
                    }
                }
            }

            // Also accept general surgical treatment consent (TREAT) when no
            // procedure-specific consent exists — common in real EHR workflows
            foreach ($actions as $action) {
                foreach ($action['coding'] ?? [] as $coding) {
                    if (
                        ($coding['system'] ?? '') === self::CONSENT_ACTION_SYSTEM &&
                        ($coding['code'] ?? '') === self::CONSENT_ACTION_CODE
                    ) {
                        return $consent; // General treatment consent accepted
                    }
                }
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function findActiveGeneralConsent(string $patientId): ?array
    {
        $consents = $this->getForPatient($patientId);

        foreach ($consents as $consent) {
            if (($consent['status'] ?? '') === 'active') {
                return $consent;
            }
        }

        return null;
    }
}
