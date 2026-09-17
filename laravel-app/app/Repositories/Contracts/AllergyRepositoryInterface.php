<?php

namespace App\Repositories\Contracts;

interface AllergyRepositoryInterface
{
    /**
     * Fetch all active AllergyIntolerance resources for a patient.
     *
     * @param string $patientId FHIR Patient resource ID
     * @return array  Array of FHIR AllergyIntolerance resources
     */
    public function getActiveAllergies(string $patientId): array;

    /**
     * Check if any active allergy matches a given set of substance codes.
     *
     * @param string $patientId
     * @param array  $substanceCodes  RxNorm or SNOMED codes of substances to check
     * @return array  Matched allergy resources (empty = no match)
     */
    public function checkForSubstances(string $patientId, array $substanceCodes): array;
}
