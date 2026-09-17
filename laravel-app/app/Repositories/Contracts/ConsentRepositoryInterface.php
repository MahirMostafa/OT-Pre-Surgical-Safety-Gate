<?php

namespace App\Repositories\Contracts;

interface ConsentRepositoryInterface
{
    /**
     * Fetch all Consent resources for a patient.
     *
     * @param string $patientId FHIR Patient resource ID
     * @return array  Array of FHIR Consent resources
     */
    public function getForPatient(string $patientId): array;

    /**
     * Check if the patient has a valid, active Consent for a specific procedure.
     *
     * Looks for a Consent resource whose:
     *   - status = 'active'
     *   - provision.type = 'permit'
     *   - provision.action[].coding matches the given procedure code (CPT)
     *
     * @param string $patientId
     * @param string $procedureCode  CPT code of the scheduled procedure
     * @return array|null  The matching Consent resource, or null if not found
     */
    public function findProcedureConsent(string $patientId, string $procedureCode): ?array;

    /**
     * Check if a general surgical consent exists (any active permit-type consent).
     *
     * @param string $patientId
     * @return array|null  Most recent active Consent or null
     */
    public function findActiveGeneralConsent(string $patientId): ?array;
}
