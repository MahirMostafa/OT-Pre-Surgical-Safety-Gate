# OT Pre-Surgical Safety Gate

> A full-stack clinical web application that acts as a **digital pre-surgical safety gate** for operating rooms. Built with Laravel 11 (SOLID Repository Pattern), Inertia.js + React 18, Material UI v6, HAPI FHIR R4, and MySQL — all containerized with Docker.

---

## 🏥 What It Does

Before any surgery begins, the surgical team opens this app on the OT monitor. It automatically:

1. **Logs in** via SMART on FHIR (OAuth 2.0 PKCE) — no manual login
2. **Identifies** the patient on the table from the EHR context
3. **Runs 4 live safety gates** against the FHIR server:
   - ✅ Procedure vs. Diagnosis match (CPT + SNOMED CT)
   - ✅ Platelet count safety (LOINC `777-3`)
   - ✅ Clotting time / INR (LOINC `6301-6`)
   - ✅ Surgical antibiotic allergy check (FHIR AllergyIntolerance)
4. **Displays** a real-time dashboard: `PASS / WARN / HOLD`
5. **Exports** a USCDI-compliant Pre-Op Summary + writes a FHIR `AuditEvent`

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 11 (PHP 8.2) |
| Pattern | SOLID Repository + Service Layer |
| Frontend | Inertia.js + React 18 |
| UI | Material UI (MUI) v6 |
| Database | MySQL 8 |
| FHIR Server | HAPI FHIR R4 (Docker) |
| Auth | SMART on FHIR OAuth 2.0 PKCE |
| Containers | Docker + Docker Compose |

See [ARCHITECTURE.md](./ARCHITECTURE.md) for full technical details.

---

## 🚀 Quick Start (One Command)

### Prerequisites
- Docker Desktop running
- Git

### Run

```bash
git clone https://github.com/MahirMostafa/OT-Pre-Surgical-Safety-Gate.git
cd OT-Pre-Surgical-Safety-Gate
cp .env.example .env
docker compose up --build
```

Then visit:
- **App**: http://localhost
- **HAPI FHIR**: http://localhost:8080/fhir

### First Time Setup (inside app container)

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

---

## 📁 Project Structure

See [ARCHITECTURE.md — Section 6](./ARCHITECTURE.md#6-full-file-structure) for the full annotated file tree.

---

## 🔐 Security

- OAuth tokens stored **server-side only** (Laravel session) — never in browser storage
- All PHI stripped by `HipaaRedactionService` before any export
- Every checklist confirmation writes a tamper-proof `AuditEvent` to FHIR
- SHA-256 payload hash stored in `audit_logs` for export integrity

---

## 📋 License

For recruitment/assessment purposes only.
