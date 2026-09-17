<?php

namespace App\Http\Controllers;

use App\Services\PreOpChecklistService;
use App\Services\FhirAuditService;
use App\Services\HipaaRedactionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * PreOpController
 *
 * Thin controller — delegates all business logic to services.
 * Only responsibility: receive HTTP requests, call services, return responses.
 *
 * SOLID — Single Responsibility Principle.
 */
class PreOpController extends Controller
{
    public function __construct(
        private PreOpChecklistService $checklistService,
        private FhirAuditService      $auditService,
        private HipaaRedactionService $redactionService,
    ) {}

    /**
     * Show the main pre-op dashboard for a patient.
     * Renders the React PreOpDashboard via Inertia.
     */
    public function dashboard(Request $request): InertiaResponse
    {
        $patientId = session('smart_patient_id', 'test-patient-001');

        // Run all 4 safety gates
        $checklist = $this->checklistService->runChecklist($patientId);

        return Inertia::render('PreOpDashboard', [
            'patient'   => $checklist['patient'],
            'gates'     => $checklist['gates'],
            'overall'   => $checklist['overall'],
            'checkedAt' => $checklist['checklist_at'],
        ]);
    }

    /**
     * Confirm the pre-op checklist (surgeon clicks "Confirm & Proceed").
     * Writes FHIR AuditEvent + MySQL audit log.
     */
    public function confirm(Request $request): JsonResponse
    {
        $patientId       = session('smart_patient_id', 'test-patient-001');
        $practitionerId  = session('smart_practitioner_id', 'practitioner-001');

        // Re-run checklist to get latest state
        $checklist = $this->checklistService->runChecklist($patientId);

        // Write dual audit trail
        $auditLog = $this->auditService->recordChecklistConfirmed(
            patientId:      $patientId,
            practitionerId: $practitionerId,
            overall:        $checklist['overall'],
            gates:          $checklist['gates'],
        );

        return response()->json([
            'success'        => true,
            'overall'        => $checklist['overall'],
            'audit_log_id'   => $auditLog->id,
            'fhir_audit_id'  => $auditLog->fhir_audit_event_id,
            'confirmed_at'   => now()->toIso8601String(),
        ]);
    }

    /**
     * Export USCDI-compliant pre-op summary (PHI-stripped ClinicalImpression).
     */
    public function export(Request $request): JsonResponse
    {
        $patientId      = session('smart_patient_id', 'test-patient-001');
        $practitionerId = session('smart_practitioner_id', 'practitioner-001');

        // Run checklist for export data
        $checklist = $this->checklistService->runChecklist($patientId);

        // Build PHI-free USCDI export
        $exportPayload = $this->redactionService->buildUscdiExport($patientId, $checklist);

        // Write audit trail for export event
        $this->auditService->recordExportGenerated($patientId, $practitionerId, $exportPayload);

        return response()->json($exportPayload)
            ->withHeaders([
                'Content-Disposition' => 'attachment; filename="pre-op-summary-' . now()->format('Ymd-His') . '.json"',
                'Content-Type'        => 'application/fhir+json',
            ]);
    }
}
