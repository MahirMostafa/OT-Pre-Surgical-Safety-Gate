<?php

namespace App\Repositories\Contracts;

interface AuditRepositoryInterface
{
    /**
     * Write a FHIR AuditEvent resource to the FHIR server.
     *
     * @param array $auditEventPayload  Valid FHIR AuditEvent JSON array
     * @return array  Created FHIR AuditEvent resource with server-assigned ID
     */
    public function writeAuditEvent(array $auditEventPayload): array;

    /**
     * Fetch recent AuditEvents for a patient.
     *
     * @param string $patientId  FHIR Patient resource ID
     * @param int    $limit
     * @return array
     */
    public function getRecentForPatient(string $patientId, int $limit = 10): array;
}
