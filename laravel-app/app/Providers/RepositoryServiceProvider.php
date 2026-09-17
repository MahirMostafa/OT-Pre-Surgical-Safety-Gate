<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Interfaces
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\ObservationRepositoryInterface;
use App\Repositories\Contracts\AllergyRepositoryInterface;
use App\Repositories\Contracts\AuditRepositoryInterface;
use App\Repositories\Contracts\ConsentRepositoryInterface;

// Concrete FHIR Implementations
use App\Repositories\Fhir\FhirPatientRepository;
use App\Repositories\Fhir\FhirObservationRepository;
use App\Repositories\Fhir\FhirAllergyRepository;
use App\Repositories\Fhir\FhirAuditRepository;
use App\Repositories\Fhir\FhirConsentRepository;

/**
 * RepositoryServiceProvider
 *
 * Binds repository interfaces to their concrete FHIR implementations.
 * Swapping to a different EHR backend (e.g., Epic) means only changing bindings here.
 *
 * SOLID — Dependency Inversion Principle:
 * Controllers and Services depend on abstractions, not concretions.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PatientRepositoryInterface::class,     FhirPatientRepository::class);
        $this->app->bind(ObservationRepositoryInterface::class, FhirObservationRepository::class);
        $this->app->bind(AllergyRepositoryInterface::class,     FhirAllergyRepository::class);
        $this->app->bind(AuditRepositoryInterface::class,       FhirAuditRepository::class);
        $this->app->bind(ConsentRepositoryInterface::class,     FhirConsentRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
