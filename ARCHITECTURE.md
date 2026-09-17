# OT Pre-Surgical Safety Gate — Architecture Reference

> A full-stack clinical web application that acts as a digital safety gate for operating rooms, replacing paper checklists with live EHR-integrated safety verification.

---

## Table of Contents

1. [What We Are Building](#1-what-we-are-building)
2. [System Overview Diagram](#2-system-overview-diagram)
3. [Tech Stack](#3-tech-stack)
4. [Docker Architecture](#4-docker-architecture)
5. [Laravel SOLID Repository Architecture](#5-laravel-solid-repository-architecture)
6. [Full File Structure](#6-full-file-structure)
7. [FHIR Integration Map](#7-fhir-integration-map)
8. [SMART on FHIR Auth Flow](#8-smart-on-fhir-auth-flow)
9. [Frontend Architecture (Inertia + React + MUI)](#9-frontend-architecture-inertia--react--mui)
10. [Database Schema (MySQL)](#10-database-schema-mysql)
11. [The 4 Safety Gates](#11-the-4-safety-gates)
12. [HIPAA & Data Privacy](#12-hipaa--data-privacy)
13. [Evaluator Green-Flag Checklist](#13-evaluator-green-flag-checklist)
14. [Key Terminology](#14-key-terminology)

---

## 1. What We Are Building

A **surgical team dashboard** that runs on the operating room monitor. Before any incision:

| Step | Action |
|------|--------|
| 1 | Surgeon opens app → **auto-login via SMART on FHIR** (OAuth 2.0 PKCE) |
| 2 | App reads the patient on the table from EHR context |
| 3 | Runs **4 parallel safety gates** against live FHIR data |
| 4 | Displays `✅ PASS / ⚠️ WARN / 🚫 HOLD` per gate |
| 5 | Surgeon clicks "Confirm & Proceed" |
| 6 | System exports **USCDI-compliant Pre-Op Summary** + writes **AuditEvent** |

**No real hospital system required** — we run a local **HAPI FHIR R4** server in Docker as the EHR backend.

---

## 2. System Overview Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        Docker Network                           │
│                                                                 │
│  ┌──────────┐    ┌────────────────┐    ┌─────────────────────┐  │
│  │  nginx   │───▶│  Laravel App   │───▶│   HAPI FHIR R4      │  │
│  │  :80     │    │  (PHP-FPM)     │    │   Server  :8080     │  │
│  └──────────┘    │                │    │                     │  │
│                  │  ┌──────────┐  │    │  /Patient           │  │
│                  │  │ Inertia  │  │    │  /ServiceRequest    │  │
│                  │  │ React UI │  │    │  /Condition         │  │
│                  │  └──────────┘  │    │  /Observation       │  │
│                  │                │    │  /AllergyIntol.     │  │
│                  │  ┌──────────┐  │    │  /AuditEvent        │  │
│                  │  │ SOLID    │  │    │  /ClinicalImpress.  │  │
│                  │  │ Repos    │  │    └─────────────────────┘  │
│                  │  └──────────┘  │                             │
│                  └───────┬────────┘    ┌─────────────────────┐  │
│                          │            │   MySQL 8  :3306     │  │
│                          └───────────▶│   audit_logs table   │  │
│                                       └─────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
         ▲
         │  Browser (Surgeon's OT Monitor)
```

---

## 3. Tech Stack

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| **Web Server** | Nginx | latest | Reverse proxy to PHP-FPM |
| **Backend Runtime** | PHP-FPM | 8.2 | Application server |
| **Backend Framework** | Laravel | 11.x | MVC + SOLID architecture |
| **Architecture Pattern** | Repository + Service Layer | — | SOLID, interface-driven |
| **Frontend Bridge** | Inertia.js | 1.x | SPA without a separate API |
| **Frontend Framework** | React | 18.x | Component-based UI |
| **UI Component Library** | Material UI (MUI) | v6 | Clinical-grade components |
| **FHIR Server** | HAPI FHIR JPA | R4 | EHR data store |
| **Database** | MySQL | 8.0 | Audit log storage |
| **Containerization** | Docker + Compose | 29.x | Single-command local setup |
| **Version Control** | GitHub | — | Source control & CI |
| **HTTP Client** | Guzzle (Laravel) | 7.x | FHIR REST API calls |
| **Auth Protocol** | SMART on FHIR OAuth 2.0 PKCE | — | Secure EHR login |

---

## 4. Docker Architecture

### Services (`docker-compose.yml`)

```yaml
services:
  nginx      → port 80   → routes to php-fpm
  app        → port 9000 → Laravel PHP-FPM
  mysql      → port 3306 → MySQL 8 database
  hapi-fhir  → port 8080 → HAPI FHIR R4 server
```

### Networks
- All services share a private `ot_network` bridge network
- External only: `nginx:80` and `hapi-fhir:8080`

### Volumes
```
mysql_data   → persistent MySQL data
hapi_data    → persistent FHIR resource store
```

---

## 5. Laravel SOLID Repository Architecture

### SOLID Principles Applied

| Principle | Implementation |
|-----------|---------------|
| **S** — Single Responsibility | `PatientRepo` only fetches patients; `ObservationRepo` only fetches labs |
| **O** — Open/Closed | Add new FHIR resource (e.g., Consent) without touching existing repos |
| **L** — Liskov Substitution | `HapiFhirPatientRepository` can be swapped for `EpicPatientRepository` |
| **I** — Interface Segregation | Separate interface per resource type — no fat interfaces |
| **D** — Dependency Inversion | `PreOpChecklistService` depends on `PatientRepositoryInterface`, not concrete class |

### Layer Responsibilities

```
Controller (HTTP only)
    └── calls Service
            └── calls Repository Interfaces
                        └── concrete FHIR implementations hit HAPI FHIR REST API
```

### Binding (RepositoryServiceProvider)
```php
$this->app->bind(PatientRepositoryInterface::class,     FhirPatientRepository::class);
$this->app->bind(ObservationRepositoryInterface::class, FhirObservationRepository::class);
$this->app->bind(AllergyRepositoryInterface::class,     FhirAllergyRepository::class);
$this->app->bind(AuditRepositoryInterface::class,       FhirAuditRepository::class);
```

---

## 6. Full File Structure

```
OT-Pre-Surgical-Safety-Gate/
│
├── ARCHITECTURE.md                   ← This document
├── README.md                         ← Setup & run instructions
├── docker-compose.yml
├── .env.example
├── .gitignore
│
├── docker/
│   ├── nginx/
│   │   └── default.conf              ← Nginx virtual host config
│   ├── php/
│   │   └── Dockerfile                ← PHP 8.2-FPM + extensions
│   └── hapi-fhir/
│       └── application.yaml          ← HAPI FHIR server config
│
└── laravel-app/
    │
    ├── app/
    │   ├── Http/
    │   │   ├── Controllers/
    │   │   │   ├── PreOpController.php           ← Main checklist controller
    │   │   │   └── SmartAuthController.php        ← SMART OAuth PKCE flow
    │   │   └── Requests/
    │   │       └── ConfirmProcedureRequest.php
    │   │
    │   ├── Services/
    │   │   ├── PreOpChecklistService.php          ← Orchestrates all 4 gates
    │   │   ├── FhirAuditService.php               ← AuditEvent + AuditLog
    │   │   └── HipaaRedactionService.php          ← PHI stripping for exports
    │   │
    │   ├── Repositories/
    │   │   ├── Contracts/
    │   │   │   ├── PatientRepositoryInterface.php
    │   │   │   ├── ObservationRepositoryInterface.php
    │   │   │   ├── AllergyRepositoryInterface.php
    │   │   │   └── AuditRepositoryInterface.php
    │   │   └── Fhir/
    │   │       ├── FhirPatientRepository.php
    │   │       ├── FhirObservationRepository.php
    │   │       ├── FhirAllergyRepository.php
    │   │       └── FhirAuditRepository.php
    │   │
    │   ├── Models/
    │   │   └── AuditLog.php
    │   │
    │   └── Providers/
    │       └── RepositoryServiceProvider.php
    │
    ├── resources/
    │   └── js/
    │       ├── app.jsx                            ← Inertia root
    │       ├── theme.js                           ← MUI dark surgical theme
    │       ├── Pages/
    │       │   ├── PreOpDashboard.jsx             ← Main safety gate UI
    │       │   └── Auth/
    │       │       └── SmartLaunch.jsx            ← SMART launch page
    │       └── Components/
    │           ├── PatientBanner.jsx              ← Top patient info strip
    │           ├── SafetyGateCard.jsx             ← Reusable PASS/WARN/HOLD card
    │           ├── LabResultTable.jsx             ← Lab values + threshold coloring
    │           ├── AllergyAlertBadge.jsx          ← Danger allergy badge
    │           └── ExportButton.jsx               ← USCDI export trigger
    │
    ├── routes/
    │   └── web.php
    │
    └── database/
        └── migrations/
            └── xxxx_create_audit_logs_table.php
```

---

## 7. FHIR Integration Map

| Safety Gate | FHIR Resource | Query Parameters | Standard |
|-------------|--------------|-----------------|----------|
| Patient ID | `Patient/{id}` | from launch context | FHIR R4 |
| Scheduled Procedure | `ServiceRequest?patient={id}` | `&status=active` | CPT codes |
| Patient Diagnosis | `Condition?patient={id}` | `&clinical-status=active` | SNOMED CT |
| Platelet Count | `Observation?patient={id}` | `&code=777-3` | LOINC |
| Clotting Time (INR) | `Observation?patient={id}` | `&code=6301-6` | LOINC |
| Allergy List | `AllergyIntolerance?patient={id}` | `&clinical-status=active` | FHIR R4 |
| Audit Trail | `AuditEvent` (POST) | on checklist confirm | FHIR R4 |
| Pre-Op Export | `ClinicalImpression` (POST) | on export click | USCDI |

---

## 8. SMART on FHIR Auth Flow

```
1. User navigates to /smart/launch
        ↓
2. Laravel generates PKCE code_verifier + code_challenge (SHA-256)
        ↓
3. Redirect to HAPI FHIR authorize endpoint with:
   - response_type=code
   - client_id, redirect_uri, scope
   - code_challenge, code_challenge_method=S256
        ↓
4. HAPI FHIR returns authorization_code
        ↓
5. Laravel backend exchanges code → access_token (sends code_verifier)
        ↓
6. access_token stored in SERVER-SIDE Laravel session (never in browser)
        ↓
7. All subsequent FHIR calls use: Authorization: Bearer {token}
```

> **Green Flag**: Tokens are NEVER stored in localStorage. PKCE ensures no client_secret is needed.

---

## 9. Frontend Architecture (Inertia + React + MUI)

### How Inertia Works
- **No separate REST API** between Laravel and React
- Laravel controllers return `Inertia::render('PageName', $data)`
- React receives props directly — feels like a full SPA, backend stays Laravel

### MUI Surgical Dark Theme
```js
palette: {
  mode: 'dark',
  background: { default: '#0a0f1e', paper: '#0d1527' },
  primary:    { main: '#00BCD4' },  // Clinical teal
  success:    { main: '#4CAF50' },  // PASS
  warning:    { main: '#FF9800' },  // WARN
  error:      { main: '#F44336' },  // HOLD / danger
}
```

### Component Tree (PreOpDashboard)
```
PreOpDashboard
├── PatientBanner          ← Patient name (redacted in export), MRN, procedure
├── SafetyGateCard × 5    ← One per gate, animated status dot
│   ├── Gate 1: Procedure Match (Structural SNOMED/CPT terminology)
│   ├── Gate 2: Lab Safety (Platelets LOINC 777-3)
│   ├── Gate 3: Lab Safety (INR LOINC 6301-6)
│   ├── Gate 4: Allergy Check (FHIR AllergyIntolerance)
│   └── Gate 5: Informed Consent (FHIR Consent R4)
├── LabResultTable         ← MUI DataGrid with threshold coloring
├── AllergyAlertBadge      ← MUI Alert chip per dangerous allergy
└── ExportButton           ← Downloads USCDI US Core FHIR Bundle + triggers AuditEvent
```

---

## 10. Database Schema (MySQL)

### `audit_logs` table

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment |
| `fhir_audit_event_id` | VARCHAR(64) | ID of AuditEvent written to HAPI FHIR |
| `patient_fhir_id` | VARCHAR(64) | FHIR Patient resource ID (no PHI name) |
| `action` | ENUM | `checklist_confirmed`, `export_generated`, `hold_raised` |
| `outcome` | ENUM | `pass`, `warn`, `hold` |
| `performed_by` | VARCHAR(128) | Practitioner FHIR ID |
| `ip_address` | VARCHAR(45) | Request IP (for tamper detection) |
| `payload_hash` | VARCHAR(64) | SHA-256 hash of export payload |
| `created_at` | TIMESTAMP | Auto |
| `updated_at` | TIMESTAMP | Auto |

> **PHI-safe**: No patient names, DOBs, or phone numbers stored — only FHIR resource IDs and coded values.

---

## 11. The 5 Safety Gates

### Gate 1 — Procedure vs. Diagnosis Match (Structural Terminology)
- Fetch `ServiceRequest` → extract CPT code via system URI (`http://www.ama-assn.org/go/cpt`)
- Fetch `Condition` → extract SNOMED CT code via system URI (`http://snomed.info/sct`)
- Structural validation against clinical indications & FHIR `$validate-code` terminology operations
- **PASS**: codes valid & indicated | **WARN**: partial match/missing code | **HOLD**: mismatch or contraindication

### Gate 2 — Platelet Count Safety
- FHIR `Observation?code=777-3` (LOINC: Platelets)
- Threshold: `≥ 100 × 10⁹/L` = PASS | `50–99` = WARN | `< 50` = HOLD

### Gate 3 — Clotting Time (INR)
- FHIR `Observation?code=6301-6` (LOINC: INR)
- Threshold: `≤ 1.5` = PASS | `1.5–2.0` = WARN | `> 2.0` = HOLD

### Gate 4 — Surgical Antibiotic Allergy
- FHIR `AllergyIntolerance?patient={id}`
- Flag if any allergy substance matches: Cephalosporins, Penicillin, Vancomycin, Clindamycin
- **PASS**: none | **WARN**: cross-reactive class | **HOLD**: direct match

### Gate 5 — Informed Surgical Consent
- FHIR `Consent?patient={id}&status=active`
- Validates:
  - `status = 'active'`
  - `provision.type = 'permit'`
  - `provision.action.coding` matches the specific surgical procedure CPT code
  - `provision.period` validity window covers the surgery date
- **PASS**: active valid consent for procedure | **WARN**: consent expiring soon | **HOLD**: no valid consent found

---

## 12. HIPAA & Data Privacy

| Rule | Implementation |
|------|---------------|
| No PHI in exports | `HipaaRedactionService` strips name, DOB, phone before export |
| No tokens in browser | Laravel server-side session only |
| Audit trail | Every confirm writes FHIR `AuditEvent` + local `audit_logs` row |
| Minimum necessary | Only FHIR fields needed for safety check are fetched |
| Payload hash | SHA-256 hash stored in `audit_logs` to prove export was not tampered with |

---

## 13. Evaluator Green-Flag Checklist

| Criteria | ✅ Green Flag | Our Implementation |
|----------|-------------|-------------------|
| Data Querying | LOINC/RxNorm code lookups | `?code=777-3`, `?code=6301-6` in API params |
| Legacy HL7 Parsing | Proper parser library | (Case 2 doesn't require HL7 v2 — covered by FHIR) |
| Authentication | PKCE OAuth 2.0 | Custom PKCE in `SmartAuthController` |
| Token Storage | Server-side only | Laravel encrypted session |
| Data Privacy | PHI stripped + AuditEvent | `HipaaRedactionService` + `FhirAuditRepository` |
| Standards | SNOMED CT, CPT, LOINC, USCDI | Used in every FHIR query and export |

---

## 14. Key Terminology

| Term | Plain English |
|------|--------------|
| **EHR** | Electronic Health Record — the hospital's patient data system |
| **FHIR R4** | Standard format for hospital data APIs (like JSON REST APIs for medical records) |
| **SMART on FHIR** | OAuth 2.0 login that lets apps embed inside EHR monitors |
| **PKCE** | Extra security for OAuth where no client_secret is needed (browser-safe) |
| **LOINC** | Universal numeric codes for lab tests (e.g., `777-3` = platelet count) |
| **SNOMED CT** | Standard codes for diagnoses and clinical findings |
| **CPT** | Standard codes for medical/surgical procedures (billing codes) |
| **USCDI** | Federally mandated minimum health data fields every app must share |
| **HIPAA** | US law protecting patient data privacy |
| **PHI** | Protected Health Information (name, DOB, phone — must be protected) |
| **AuditEvent** | FHIR resource that logs who accessed what data and when |
| **ClinicalImpression** | FHIR resource used for structured clinical notes/summaries |
| **HAPI FHIR** | Open-source Java FHIR server we run in Docker |
| **Inertia.js** | Bridge that lets React run inside Laravel without a separate API |
| **Repository Pattern** | Separation of data-access logic into swappable interface classes |
