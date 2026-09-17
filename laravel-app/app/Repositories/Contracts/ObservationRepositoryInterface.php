<?php

namespace App\Repositories\Contracts;

interface ObservationRepositoryInterface
{
    /**
     * Fetch the most recent Observation for a patient by LOINC code.
     *
     * @param string $patientId FHIR Patient resource ID
     * @param string $loincCode LOINC code (e.g. '777-3' for platelets)
     * @return array|null FHIR Observation resource or null
     */
    public function getLatestByLoincCode(string $patientId, string $loincCode): ?array;

    /**
     * Fetch multiple Observations by LOINC codes (comma-separated).
     *
     * @param string $patientId
     * @param array  $loincCodes  e.g. ['777-3', '6301-6']
     * @return array  Array of FHIR Observation resources
     */
    public function getLatestByLoincCodes(string $patientId, array $loincCodes): array;
}
