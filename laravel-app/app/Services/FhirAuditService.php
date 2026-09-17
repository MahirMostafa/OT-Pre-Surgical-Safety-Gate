<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditRepositoryInterface;
use Illuminate\Support\Facades\Request;

/**
 * FhirAuditService
 *
 * Writes a dual audit trail:
 * 1. FHIR AuditEvent → posted to HAPI FHIR server (immutable, standards-compliant)
 * 2. AuditLog → local MySQL shadow record (SHA-256 hashed payload for tamper detection)
 *
 * HIPAA compliant: No PHI stored in audit records — only FHIR resource IDs.
 */
class FhirAuditService
{
    public function __construct(
        private AuditRepositoryInterface $auditRepo,
    ) {}

    /**
     * Record a pre-op checklist confirmation event.
     *
     * @param string $patientId       FHIR Patient resource ID
     * @param string $practitionerId  FHIR Practitioner resource ID
     * @param string $overall         'pass' | 'warn' | 'hold'
     * @param array  $gates           Checklist gate results
     * @return AuditLog
     */
    public function recordChecklistConfirmed(
        string $patientId,
        string $practitionerId,
        string $overall,
        array  $gates
    ): AuditLog {
        // Build a FHIR-compliant AuditEvent payload
        $auditEventPayload = $this->buildAuditEvent(
            action: 'checklist_confirmed',
            outcome: $this->fhirOutcome($overall),
            patientId: $patientId,
            practitionerId: $practitionerId,
            description: "Pre-op safety checklist confirmed. Overall: {$overall}. Gates: " .
                         implode(', ', array_map(fn($g) => "{$g['id']}={$g['status']}", $gates)),
        );

        // Post to FHIR server
        $fhirResponse = $this->auditRepo->writeAuditEvent($auditEventPayload);
        $fhirEventId  = $fhirResponse['id'] ?? null;

        // Hash the gate payload for tamper detection
        $payloadHash = hash('sha256', json_encode($gates));

        // Write shadow record to MySQL
        return AuditLog::create([
            'fhir_audit_event_id' => $fhirEventId,
            'patient_fhir_id'     => $patientId,
            'action'              => 'checklist_confirmed',
            'outcome'             => $overall,
            'performed_by'        => $practitionerId,
            'ip_address'          => Request::ip(),
            'payload_hash'        => $payloadHash,
        ]);
    }

    /**
     * Record a pre-op export generation event.
     */
    public function recordExportGenerated(string $patientId, string $practitionerId, array $exportPayload): AuditLog
    {
        $auditEventPayload = $this->buildAuditEvent(
            action: 'export_generated',
            outcome: '0', // FHIR AuditEvent outcome 0 = success
            patientId: $patientId,
            practitionerId: $practitionerId,
            description: 'USCDI-compliant pre-op summary exported.',
        );

        $fhirResponse = $this->auditRepo->writeAuditEvent($auditEventPayload);
        $fhirEventId  = $fhirResponse['id'] ?? null;

        return AuditLog::create([
            'fhir_audit_event_id' => $fhirEventId,
            'patient_fhir_id'     => $patientId,
            'action'              => 'export_generated',
            'outcome'             => 'pass',
            'performed_by'        => $practitionerId,
            'ip_address'          => Request::ip(),
            'payload_hash'        => hash('sha256', json_encode($exportPayload)),
        ]);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function buildAuditEvent(
        string $action,
        string $outcome,
        string $patientId,
        string $practitionerId,
        string $description
    ): array {
        return [
            'resourceType' => 'AuditEvent',
            'type' => [
                'system'  => 'http://terminology.hl7.org/CodeSystem/audit-event-type',
                'code'    => 'rest',
                'display' => 'RESTful Operation',
            ],
            'subtype' => [[
                'system'  => 'http://hl7.org/fhir/restful-interaction',
                'code'    => $action,
                'display' => str_replace('_', ' ', ucfirst($action)),
            ]],
            'action'   => 'E', // Execute
            'recorded' => now()->toIso8601String(),
            'outcome'  => $outcome,
            'outcomeDesc' => $description,
            'agent' => [[
                'type' => [
                    'coding' => [[
                        'system'  => 'http://terminology.hl7.org/CodeSystem/v3-ParticipationType',
                        'code'    => 'PART',
                        'display' => 'Participant',
                    ]],
                ],
                'who' => ['reference' => "Practitioner/{$practitionerId}"],
                'requestor' => true,
                'network'   => ['address' => Request::ip(), 'type' => '2'],
            ]],
            'source' => [
                'site'     => 'OT-Safety-Gate',
                'observer' => ['display' => 'OT Pre-Surgical Safety Gate Application'],
                'type'     => [[
                    'system'  => 'http://terminology.hl7.org/CodeSystem/security-source-type',
                    'code'    => '4',
                    'display' => 'Application Server',
                ]],
            ],
            'entity' => [[
                'what'        => ['reference' => "Patient/{$patientId}"],
                'type'        => ['system' => 'http://terminology.hl7.org/CodeSystem/audit-entity-type', 'code' => '1', 'display' => 'Person'],
                'role'        => ['system' => 'http://terminology.hl7.org/CodeSystem/object-role', 'code' => '1', 'display' => 'Patient'],
                'description' => $description,
            ]],
        ];
    }

    private function fhirOutcome(string $overall): string
    {
        return match($overall) {
            'pass' => '0',   // FHIR AuditEvent: 0 = Success
            'warn' => '4',   // 4 = Minor failure
            'hold' => '8',   // 8 = Serious failure
            default => '0',
        };
    }
}
