# Step 4D.10.1 — Installation Plan Safety Review

**Date:** 2026-10-11
**Author:** Antigravity Pairing Agent
**Scope:** Static audit of STEP_4D_10_POSTGRES_INSTALLATION_PLAN.md — No Installation — No Database Access
**Status:** REVIEW COMPLETE — Plan revised; INSTALLATION NOT EXECUTED

---

## 1. Purpose

This document audits the installation plan produced in Step 4D.10
(`backend/docs/STEP_4D_10_POSTGRES_INSTALLATION_PLAN.md`) before any installation is approved.
The goal is to identify unsafe claims, unverified assumptions, and gaps that could allow tests to
accidentally target Supabase or allow destructive operations without proper diagnosis.

No installer was run. No database was accessed. No configuration file was changed. No service was
modified.

---

## 2. Audit Findings

### Finding 1 — Unverified Installer Behaviour Stated as Fact (Phase 1, line 127-129)

**Original text:**
> "The EnterpriseDB installer is known to detect and overwrite existing service entries of the
> same name."

**Problem:**
This is an untested assumption. The behaviour of the EnterpriseDB installer when encountering an
orphaned service entry with a missing binary has NOT been verified in this environment. Stating it
as a known fact creates false confidence and may cause the user to skip manual verification of the
orphaned service before running the installer.

**Risk:**
If the installer silently reuses the orphaned service entry without re-registering the binary
path, or if it aborts due to the stale entry, the installation may fail or leave the system in an
ambiguous state.

**Correction applied in revised plan:**
Reworded to clearly label this as a documented installer characteristic that requires user
verification before proceeding. A mandatory checkpoint is added: the user must decide (before
running the installer) whether to delete the orphaned service manually or proceed and observe
the installer's behaviour, ready to intervene.

---

### Finding 2 — DB_HOST=127.0.0.1 Alone Does Not Prove Safety (Phase 5 and 6)

**Original text (Phase 5):**
> "`DB_HOST` must be `127.0.0.1` — not a hostname that resolves remotely."

**Problem:**
In `backend/config/database.php`, the `pgsql` connection (line 89) defines:
```php
'url' => env('DB_URL'),
'host' => env('DB_HOST', '127.0.0.1'),
```

When `DB_URL` is set to a non-empty value (e.g., a Supabase connection URL), Laravel's database
resolver **uses the URL and ignores `host`, `port`, and `database` entirely**. This means:

- If the production `.env` has `DATABASE_URL` or `DB_URL` pointing to Supabase, and `phpunit.xml`
  only sets `DB_HOST=127.0.0.1` but fails to clear `DB_URL`, the Supabase URL still takes effect.
- `phpunit.xml` currently sets `<env name="DB_URL" value=""/>` which clears it. This is necessary,
  but it is not self-sufficient: the resolved configuration must be explicitly verified after
  bootstrap to confirm no URL override survived.

Additionally, `mysql`, `mariadb`, and `sqlsrv` connections all share the same `DB_URL`,
`DB_HOST`, and `DB_DATABASE` environment variables. If any of those connections are inadvertently
active (e.g., via explicit `DB::connection('mysql')` calls), they would also resolve to the same
environment. The guard already inspects ALL connections, which mitigates this — but the plan
did not explain this dependency clearly.

**Correction applied in revised plan:**
- Phase 6 now explicitly explains why `DB_URL=""` must be set in both `.env.testing` and
  `phpunit.xml`, and why `DB_URL` takes precedence over `DB_HOST`.
- The Phase 7 pre-PHPUnit checklist now includes a step to verify the resolved configuration
  output (via `php artisan config:show database --env=testing`) to confirm no Supabase URL
  survived into the test bootstrap.
- A note explains that `TestEnvironmentGuard` inspects all connections and will catch any
  connection where `url` or `host` contains a Supabase identifier.

---

### Finding 3 — Recovery Procedure Is Immediately Destructive Without Diagnosis (Section 7)

**Original text:**
> "1. Stop the service.
>  2. Drop and recreate the data directory using `initdb`."

**Problem:**
`initdb` wipes the entire PostgreSQL data directory (including all databases, roles, and
configuration files). Recommending this as step 2 of a recovery procedure — before any diagnosis
— is disproportionate. Many failure modes (crashed service, locked file, port conflict, corrupted
WAL) can be resolved without destroying the data directory.

**Correction applied in revised plan:**
Recovery procedure is rewritten as a tiered escalation:
1. Diagnose: check service status, event logs, and pg_isready.
2. Attempt safe restart.
3. Only escalate to data directory recreation after diagnosis confirms data corruption and the
   user explicitly approves the destructive action.
Supabase safety note is retained.

---

### Finding 4 — `php artisan migrate --env=testing` in Checklist Is Not a Passive Check (Phase 7)

**Original checklist item:**
> "- [ ] `php artisan migrate --env=testing` runs against `aquatara_test` only (not Supabase)"

**Problem:**
`php artisan migrate` is a state-mutating operation, not a read-only verification. Listing it in
a "pre-PHPUnit safety checklist" suggests it is a routine confirmation step, but it actually
modifies the database schema. Running it prematurely, before all configuration checks pass, could
apply migrations to the wrong database.

**Correction applied in revised plan:**
- The checklist item is reworded to make clear that migration is a one-time setup step, not a
  passive check, and must only be run after all other checklist items are verified.
- A new checklist item is added before it: verify the resolved `DB_HOST` and `DB_DATABASE` in the
  artisan configuration before running migrate.

---

### Finding 5 — `.env.testing` Is NOT in `.gitignore` (Unverified Assumption)

**Original text (Phase 5):**
> "This file must be listed in `.gitignore` to prevent committing credentials."
> "Check `.gitignore` to confirm `.env.testing` is excluded before creating the file."

**Verified fact:**
Inspection of `backend/.gitignore` shows only:
```
.env
.env.backup
.env.production
```

`.env.testing` is **not listed**. The plan advised the user to check this, but the verification
was left as a future action. This is a concrete gap that should be called out explicitly.

**Correction applied in revised plan:**
- Section 3 (Verified Facts) now includes a table entry for `.gitignore` coverage showing that
  `.env.testing` is NOT currently excluded.
- Phase 5 is updated: before creating `.env.testing`, the user must add `.env.testing` to
  `.gitignore`. This is listed as a mandatory prerequisite step, not an optional note.
- The approval checklist includes a new item confirming this was done.

---

### Finding 6 — Digital Signature Claim Lacks Nuance (Section 3.2)

**Original text:**
> "Digital signature status: **Valid** — signature verified"
> "The installer is authentic and unmodified."

**Verified facts:**
- `Get-AuthenticodeSignature` returned `Status: Valid`.
- The signing certificate `NotAfter` date was 12/19/2025, which is before the current date
  (2026-10-11).
- Windows Authenticode validates against the embedded countersignature timestamp from when the
  file was signed, not the current date. A `Valid` status with an expired certificate means the
  file was signed before the certificate expired and the embedded timestamp is trusted — this is
  standard and legitimate behaviour.

**Problem:**
Simply stating "signature verified" without noting the certificate expiry date could be misleading
if the user later questions whether the installer is safe.

**Correction applied in revised plan:**
- Table now separates `Signature status`, `Certificate valid from`, `Certificate valid to`, and
  an explanation that `Valid` status reflects timestamp-based validation (the file was signed
  before the certificate expired).
- The phrase "The installer is authentic and unmodified" is replaced with:
  "The installer's digital signature was valid at signing time. The file has not been altered
  after signing. The signing certificate has since expired, which is expected for a file signed
  in 2024–2025; this does not invalidate the file's integrity."

---

### Finding 7 — Orphaned Service Decision Not Formalized as a Checkpoint

**Original text (Phase 1):**
> "The orphaned entry should be deleted or left for the installer to overwrite."
> "Alternative approach: Rename the service..."

**Problem:**
Three options are presented without requiring the user to choose one before installation begins.
This creates ambiguity about which path was taken, making post-installation diagnosis harder.

**Correction applied in revised plan:**
Phase 1 is restructured as a mandatory decision checkpoint with three explicit options. The user
must select one and document their choice in the approval checklist before proceeding to Phase 2.

---

## 3. Summary of Issues Found and Corrected

| # | Issue | Severity | Corrected In |
|---|-------|----------|-------------|
| F1 | Unverified installer behaviour stated as fact | Medium | Revised plan Phase 1 |
| F2 | DB_HOST=127.0.0.1 insufficient; DB_URL must be explicitly cleared and verified | High | Revised plan Phase 5, 6, 7 |
| F3 | Recovery procedure immediately recommends destructive data directory wipe | High | Revised plan Section 7 |
| F4 | `php artisan migrate` listed as passive check, not setup step | Medium | Revised plan Phase 7 |
| F5 | `.env.testing` not in `.gitignore` (concrete gap, not assumption) | High | Revised plan Phase 5, Section 3 |
| F6 | Signature claim lacked nuance about certificate expiry | Low | Revised plan Section 3.2 |
| F7 | Orphaned service decision not formalized as a user checkpoint | Medium | Revised plan Phase 1 |

---

## 4. Architecture Decisions Confirmed Unchanged

The following decisions from Step 4D.10 are confirmed correct and retained in the revised plan:

| Decision | Status |
|----------|--------|
| PostgreSQL (not SQLite) required for DISTINCT ON compatibility | Retained |
| Supabase must never be used as test fallback | Retained |
| Test database name: `aquatara_test` | Retained |
| TestEnvironmentGuard remains active and covers all connections | Retained |
| No secrets, passwords, or tokens in any documentation | Retained |
| Installation not executed in this step | Retained |

---

## 5. Files Changed

| File | Action | Reason |
|------|--------|--------|
| `backend/docs/STEP_4D_10_1_INSTALLATION_PLAN_SAFETY_REVIEW.md` | **Created** | This review document |
| `backend/docs/STEP_4D_10_POSTGRES_INSTALLATION_PLAN.md` | **Revised** | Corrected unsafe claims and gaps identified in this audit |

---

## 6. Verification of Constraints

| Constraint | Status |
|------------|--------|
| Installer NOT run | Not run |
| No administrator access used | None used |
| No Windows service modified | None modified |
| No database accessed or created | None accessed |
| No Supabase connection | Not accessed |
| No PHPUnit or artisan test executed | Not executed |
| No source code, .env, phpunit.xml, or .gitignore modified | None modified |
| No passwords, tokens, or secrets printed | None printed |
| Step 4D.11 and Step 4E not started | Not started |

---

## 7. Items Still Requiring User Decision Before Installation

The following decisions cannot be made by the agent — they require explicit user choice:

1. **Orphaned service handling:** Delete `postgresql-x64-18` manually before installation, OR
   proceed and observe installer behaviour, OR install under a different service name.
2. **Installation directory:** Confirm `F:\pc\prog\postgresql\pgsql_18` (reuses prior path) or
   choose a different location.
3. **`.gitignore` update:** Confirm `.env.testing` will be added to `.gitignore` before the file
   is created.
4. **Administrator access:** Confirm a session with admin rights will be used for the installer.
5. **Superuser password:** Will be chosen interactively; user must have a credential vault ready.

---

## 8. Status

# REVIEW COMPLETE — PLAN REVISED — INSTALLATION NOT EXECUTED

The installation plan has been corrected. All identified issues are documented above and addressed
in the revised `STEP_4D_10_POSTGRES_INSTALLATION_PLAN.md`.

To proceed with installation, the user must:
1. Review this safety review document.
2. Review the revised installation plan.
3. Make the five decisions listed in Section 7.
4. Explicitly request: **"Proceed with Step 4D.11 — PostgreSQL Installation Execution."**
