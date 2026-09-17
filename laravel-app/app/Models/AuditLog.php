<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * AuditLog
 *
 * Local MySQL shadow of every FHIR AuditEvent.
 * PHI-safe: stores only FHIR resource IDs and coded outcome values.
 * SHA-256 payload_hash allows tamper detection.
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'fhir_audit_event_id',
        'patient_fhir_id',
        'action',
        'outcome',
        'performed_by',
        'ip_address',
        'payload_hash',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForPatient($query, string $patientFhirId)
    {
        return $query->where('patient_fhir_id', $patientFhirId);
    }

    public function scopeHolds($query)
    {
        return $query->where('outcome', 'hold');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
