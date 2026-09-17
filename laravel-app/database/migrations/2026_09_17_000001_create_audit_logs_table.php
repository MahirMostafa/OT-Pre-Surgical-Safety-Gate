<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // FHIR AuditEvent resource ID (cross-reference to FHIR server)
            $table->string('fhir_audit_event_id', 64)->nullable()->index();

            // FHIR Patient resource ID — NO PHI (no names, no DOBs, no phone numbers)
            $table->string('patient_fhir_id', 64)->index();

            // What happened
            $table->enum('action', [
                'checklist_confirmed',
                'export_generated',
                'hold_raised',
                'login',
                'logout',
            ])->index();

            // Outcome of the safety check
            $table->enum('outcome', ['pass', 'warn', 'hold', 'error'])->index();

            // Who performed the action (FHIR Practitioner resource ID)
            $table->string('performed_by', 128)->nullable();

            // Network info for tamper detection
            $table->string('ip_address', 45)->nullable();

            // SHA-256 hash of the export payload — proves it wasn't tampered with
            $table->string('payload_hash', 64)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
