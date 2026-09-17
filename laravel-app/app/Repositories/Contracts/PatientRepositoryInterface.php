<?php

namespace App\Repositories\Contracts;

interface PatientRepositoryInterface
{
    /**
     * Fetch a FHIR Patient resource by ID.
     *
     * @param string $patientId FHIR Patient resource ID
     * @return array Decoded FHIR Patient JSON
     */
    public function findById(string $patientId): array;

    /**
     * Fetch the active ServiceRequest (scheduled procedure) for a patient.
     *
     * @param string $patientId
     * @return array|null FHIR ServiceRequest or null if none
     */
    public function getActiveServiceRequest(string $patientId): ?array;

    /**
     * Fetch the active Condition (diagnosis) for a patient.
     *
     * @param string $patientId
     * @return array|null FHIR Condition or null if none
     */
    public function getActiveCondition(string $patientId): ?array;
}
