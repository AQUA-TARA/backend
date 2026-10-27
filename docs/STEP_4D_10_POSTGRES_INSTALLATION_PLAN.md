# Step 4D.10 — PostgreSQL Local Installation Plan (Revised)

**Date:** 2026-10-11
**Revised:** 2026-10-11 (after Step 4D.10.1 Safety Review)
**Author:** Antigravity Pairing Agent
**Scope:** Planning Only — No Installation — No Database Access — No Configuration Changes
**Status:** PLAN REVISED / Awaiting Explicit Approval to Execute

> This document was revised after the safety audit in
> `backend/docs/STEP_4D_10_1_INSTALLATION_PLAN_SAFETY_REVIEW.md`.
> Changes are documented in that report.

---

## 1. Purpose

This document is the formal installation plan for setting up a local PostgreSQL server dedicated
to running AQUA-TARA PHPUnit tests in isolation from the live Supabase database. No part of this
plan has been executed. No installer has been run. No database has been created. No configuration
files have been modified.

---

## 2. Context Summary

### Prior Steps Completed

| Step | Status | Key Finding |
|------|--------|-------------|
| 4D.7 | PASS | Guard reinforced in `TestCase::createApplication()` and `setUp()` |
| 4D.8 | PASS | `TestEnvironmentGuard` verified across 9 scenarios; live bootstrap confirmed to reject Supabase |
| 4D.9 | BLOCKED | No local PostgreSQL binaries or data directory found; ports 5432/5433 both closed |

### Why Local PostgreSQL Is Required

- `MLAnalysisService` uses the PostgreSQL-specific `DISTINCT ON` syntax.
- SQLite does **not** support `DISTINCT ON`. Replacing PostgreSQL with SQLite for testing would
  require explicit code changes and an explicit architectural decision. It is **not** an
  equivalent drop-in replacement.
- Supabase is the live production database. It must **never** be targeted by PHPUnit, artisan
  migrate, or any test operation.
- Docker is not available on the host.
- A native local PostgreSQL installation is the only viable path without code changes.

---

## 3. Verified Facts (Read-Only Inspection — No DB Access)

### 3.1 System

| Item | Verified Value |
|------|----------------|
| OS | Windows 10 / 11 64-bit (NT 10.0.26300.0) |
| PHP version | 8.3.16 (CLI, ZTS, Visual C++ 2019 x64) |
| PHP `pdo_pgsql` extension | LOADED — no extension changes needed |
| PHP `pgsql` extension | LOADED — no extension changes needed |

### 3.2 Installer

| Item | Verified Value |
|------|----------------|
| File path | `F:\pc\prog\postgresql\postgresql-18.1-2-windows-x64.exe` |
| File size | 371,622,632 bytes (354.41 MB) |
| Product version | 18 |
| Company | PostgreSQL Global Development Group (via EnterpriseDB) |
| Authenticode signature status | `Valid` (reported by Get-AuthenticodeSignature) |
| Certificate subject | CN=EnterpriseDB Corporation, O=EnterpriseDB Corporation, S=Massachusetts, C=US |
| Certificate valid from | 12/19/2022 |
| Certificate valid to | 12/19/2025 |
| Installer has been run | NO |

**Note on certificate expiry:** The signing certificate expired on 12/19/2025, before the current
date. The `Valid` status is correct because Windows Authenticode validates against the embedded
countersignature timestamp from when the file was signed (before the certificate expired), not
the current date. This is standard and expected behaviour for files signed in 2024-2025.
The `Valid` status confirms the file has not been altered after signing. It does NOT imply the
certificate is currently active for new signatures.

### 3.3 Existing Orphaned Service

| Item | Verified Value |
|------|----------------|
| Service name | `postgresql-x64-18` |
| Service state | Stopped |
| Service startup type | Automatic |
| Registered binary path | `F:\pc\prog\postgresql\pgsql_18\bin\pg_ctl.exe` |
| Binary exists | False — directory `F:\pc\prog\postgresql\pgsql_18` does not exist |
| Registered data directory | `F:\pc\prog\postgresql\pgsql_18\data` |
| Data directory exists | False |
| Assessment | Orphaned service from a previously uninstalled or relocated PostgreSQL instance |

### 3.4 Port Availability

| Port | Status at Audit Time |
|------|----------------------|
| 5432 | Closed — no process listening |
| 5433 | Closed — no process listening |
| 5434 | Closed — no process listening |

Port 5432 is the planned target. This must be re-confirmed immediately before installation with
`Test-NetConnection -ComputerName 127.0.0.1 -Port 5432`.

### 3.5 PostgreSQL CLI Tools

No PostgreSQL executables (`psql`, `pg_ctl`, `postgres`, `pg_isready`) were found on the system
PATH. They will be available after installation.

### 3.6 `.gitignore` Coverage (Gap Identified)

| File pattern in `.gitignore` | Present |
|------------------------------|---------|
| `.env` | Yes |
| `.env.backup` | Yes |
| `.env.production` | Yes |
| `.env.testing` | **NO — MISSING** |

`.env.testing` is **not currently excluded** from version control. This must be corrected before
`.env.testing` is created. See Phase 5.

### 3.7 Configuration Resolution Risk: DB_URL Takes Precedence

In `backend/config/database.php`, the `pgsql` connection (and all other network connections)
includes:
```php
'url' => env('DB_URL'),
'host' => env('DB_HOST', '127.0.0.1'),
```

Laravel's database resolver uses `DB_URL` when it is non-empty, **ignoring** `DB_HOST`,
`DB_PORT`, and `DB_DATABASE` entirely. If the production `.env` sets `DB_URL` to a Supabase
connection string, and `.env.testing` or `phpunit.xml` fail to clear it, the Supabase URL
will override all other settings.

Setting `DB_HOST=127.0.0.1` alone does NOT guarantee safety. `DB_URL` must be explicitly cleared
to an empty string in both `.env.testing` and `phpunit.xml`, AND the resolved configuration must
be verified before any test or migration is run.

---

## 4. Items That Cannot Be Verified Until After Installation

The following cannot be confirmed without running the installer and starting the service:

1. Whether the installer requires administrator elevation for service registration.
2. The exact installation destination chosen by the installer UI.
3. How the installer handles the orphaned `postgresql-x64-18` service entry.
4. Whether `pg_hba.conf` will require modification to allow `localhost` connections.
5. The superuser credentials set during installation (chosen interactively).
6. Whether `pg_ctl` or `pg_isready` respond correctly after first start.
7. Whether the installer registers the `bin` directory on the system PATH automatically.

---

## 5. Installation Plan

> **This is a plan only. No step below has been executed.**
> Each phase is a gate: do not proceed to the next phase without completing all checks in the
> current phase.

---

### Phase 1 — Orphaned Service Decision (MANDATORY USER CHECKPOINT)

The existing Windows service `postgresql-x64-18` is orphaned: it references binaries and a data
directory that do not exist. Three options are available. **The user must choose one before
proceeding to Phase 2.**

| Option | Description | When to choose |
|--------|-------------|----------------|
| A | Proceed with installer as-is; observe its behaviour carefully | If comfortable intervening if installer fails |
| B | Delete the orphaned service first: `sc delete postgresql-x64-18` (requires admin) | Safest; eliminates ambiguity |
| C | Install under a different service name via installer UI (e.g., `postgresql-18-test`) | If A and B are undesirable |

**What is NOT known:** It has not been verified in this environment whether the EnterpriseDB
installer handles orphaned service entries gracefully. It may succeed, fail silently, or prompt
for manual intervention. Assuming it will work without verification is unsafe.

**User must confirm their chosen option in the Approval Checklist (Section 8) before
Phase 2 begins.**

---

### Phase 2 — Run the Installer

**Installer path:** `F:\pc\prog\postgresql\postgresql-18.1-2-windows-x64.exe`

Re-confirm port 5432 is still available immediately before running:
```powershell
Test-NetConnection -ComputerName 127.0.0.1 -Port 5432
```

**Recommended installation settings:**

| Setting | Recommended Value | Rationale |
|---------|-------------------|-----------|
| Installation directory | `F:\pc\prog\postgresql\pgsql_18` | Matches prior orphaned service path; avoids naming confusion |
| Data directory | `F:\pc\prog\postgresql\pgsql_18\data` | Same rationale |
| Port | 5432 | Default; confirmed available at audit time |
| Locale | `C` or match system locale | Consistent with Supabase collation (C.UTF-8) |
| Components | Server, Command Line Tools | pgAdmin optional; Stack Builder can be skipped |

**Superuser account (`postgres`):**
- A strong password must be chosen interactively at the installer prompt.
- The password must NOT appear in any file, report, commit message, or documentation.
- Store it exclusively in a local password manager or OS credential vault.

---

### Phase 3 — Post-Installation Verification

All four commands must succeed before proceeding to Phase 4. If any fails, diagnose and resolve
before continuing.

```powershell
# 1. Confirm service is running
Get-Service -Name "postgresql-x64-18"
# Expected: Status = Running

# 2. Confirm port 5432 is listening
Test-NetConnection -ComputerName 127.0.0.1 -Port 5432
# Expected: TcpTestSucceeded = True

# 3. Confirm psql is accessible
psql --version
# Expected: psql (PostgreSQL) 18.x.x

# 4. Confirm server is accepting connections
pg_isready -h 127.0.0.1 -p 5432
# Expected: 127.0.0.1:5432 - accepting connections (exit code 0)
```

If `psql` is not on PATH after installation, add the PostgreSQL `bin` directory
(e.g., `F:\pc\prog\postgresql\pgsql_18\bin`) to the user or system PATH manually.

---

### Phase 4 — Create Dedicated Test Database

> This phase must only begin after all Phase 3 checks pass.
> This connects to the LOCAL server only (127.0.0.1). Do not use any Supabase credentials here.

```sql
-- Connect to local PostgreSQL as superuser:
-- psql -U postgres -h 127.0.0.1 -p 5432

-- Create a dedicated role for test operations
CREATE ROLE aquatara_test_user WITH LOGIN PASSWORD '<choose-a-strong-local-password>';

-- Create the dedicated test database
CREATE DATABASE aquatara_test OWNER aquatara_test_user;

-- Connect to the new database and grant schema access
\c aquatara_test
GRANT ALL ON SCHEMA public TO aquatara_test_user;

-- Verify
\l aquatara_test
```

**Database name:** `aquatara_test`

The name is explicit, non-system, and passes `TestEnvironmentGuard` validation (which forbids
`postgres`, `mysql`, `sys`, `public`, `template0`, `template1`).

The `aquatara_test_user` password must NOT be recorded in any file committed to version control
or in any documentation.

---

### Phase 5 — Add `.env.testing` to `.gitignore` Then Create `.env.testing`

> Phase 4 must be confirmed before this phase begins.

**Step 5a — Update `.gitignore` FIRST (mandatory prerequisite):**

`backend/.gitignore` currently does NOT include `.env.testing`. Before creating the file,
add it:
```
.env.testing
```
Add this line to `backend/.gitignore`. Verify the change is committed before creating
`.env.testing`.

**Step 5b — Create `backend/.env.testing`:**

```ini
APP_ENV=testing
APP_KEY=                         # Generate after: php artisan key:generate --env=testing
APP_DEBUG=true

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=aquatara_test
DB_USERNAME=aquatara_test_user
DB_PASSWORD=<local-test-password-never-commit>
DB_URL=

CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array

LOG_CHANNEL=stack
LOG_LEVEL=debug
```

**Critical rules:**
- `DB_URL=` must be explicitly set to empty. Laravel prioritises `DB_URL` over `DB_HOST` when
  it is non-empty. An empty string prevents any production URL from leaking through.
- `DB_HOST` must be `127.0.0.1`.
- `DB_DATABASE` must be `aquatara_test`.
- Do not set `DB_PASSWORD` to any Supabase password. Use the local password created in Phase 4.

---

### Phase 6 — Update `phpunit.xml`

> This phase modifies an existing file. It must not begin until Phase 5 is confirmed.

Update `backend/phpunit.xml` `<php>` block to explicitly override database connection values:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="APP_MAINTENANCE_DRIVER" value="file"/>
    <env name="BCRYPT_ROUNDS" value="4"/>
    <env name="BROADCAST_CONNECTION" value="null"/>
    <env name="CACHE_STORE" value="array"/>
    <env name="DB_CONNECTION" value="pgsql"/>
    <env name="DB_HOST" value="127.0.0.1"/>
    <env name="DB_PORT" value="5432"/>
    <env name="DB_DATABASE" value="aquatara_test"/>
    <env name="DB_URL" value=""/>
    <!-- DB_USERNAME and DB_PASSWORD come from .env.testing; do not put credentials here -->
    <env name="MAIL_MAILER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
    <env name="PULSE_ENABLED" value="false"/>
    <env name="TELESCOPE_ENABLED" value="false"/>
    <env name="NIGHTWATCH_ENABLED" value="false"/>
</php>
```

**Why `DB_URL=""` is required here:**
If `.env.testing` is absent or incorrectly loaded, `phpunit.xml`'s `DB_URL=""` ensures that
no Supabase URL from the production `.env` can pass through. Setting `DB_HOST=127.0.0.1` is
necessary but insufficient on its own.

**Limitation:** `phpunit.xml` env overrides do not set `DB_USERNAME` and `DB_PASSWORD` for
security. These must be in `.env.testing`. If `.env.testing` is missing or malformed, the
guard will reject the configuration because the resolved `pgsql` connection will reference
the production host.

---

### Phase 7 — Pre-PHPUnit Safety Checklist

Before running PHPUnit or `php artisan test` for the first time, every item below must pass.
Do not skip any item. Do not run PHPUnit if any item fails.

**Infrastructure checks:**
- [ ] PostgreSQL service `postgresql-x64-18` is Running (`Get-Service`)
- [ ] `pg_isready -h 127.0.0.1 -p 5432` exits with code 0
- [ ] Database `aquatara_test` exists on `127.0.0.1:5432` (confirm via `psql -l`)

**Configuration checks:**
- [ ] `backend/.env.testing` exists
- [ ] `backend/.env.testing` contains `DB_HOST=127.0.0.1`, `DB_DATABASE=aquatara_test`, `DB_URL=`
- [ ] `.env.testing` is listed in `backend/.gitignore`
- [ ] `backend/phpunit.xml` overrides `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, and `DB_URL=""`
- [ ] Production `backend/.env` is unchanged (no Supabase credentials altered)

**Resolved configuration verification:**
- [ ] Run `php artisan config:show database --env=testing` and confirm:
  - `connections.pgsql.host` = `127.0.0.1`
  - `connections.pgsql.database` = `aquatara_test`
  - `connections.pgsql.url` = `null` or empty
  - No connection entry contains a Supabase host string

**Guard verification:**
- [ ] `TestEnvironmentGuard::assertSafeEnvironment()` passes with the resolved test config
      (verify using `tests/Support/verify_guard_logic.php` or by confirming the guard logic
      statically — do not run PHPUnit for this step)

**One-time setup step (not a passive check):**
- [ ] All above checks pass, THEN run:
      `php artisan migrate --env=testing`
      Verify output confirms it targeted `aquatara_test` on `127.0.0.1`.
      If there is any doubt about the target database, STOP.

**Final gate:**
- [ ] All items above are checked and confirmed

Only when every item is checked may `./vendor/bin/phpunit` or `php artisan test` be run.

---

## 6. Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Installer fails due to orphaned service entry | Medium | Medium | Phase 1 decision checkpoint; Option B (delete service first) eliminates risk |
| DB_URL from production .env overrides test settings | Low | Critical | `DB_URL=""` in both `.env.testing` and `phpunit.xml`; guard rejects Supabase URLs |
| `.env.testing` committed with credentials | Low | High | `.env.testing` added to `.gitignore` before file creation (Phase 5a) |
| Tests run against `postgres` system db | Very Low | Critical | `TestEnvironmentGuard` throws `RuntimeException`; listed as system database |
| `phpunit.xml` missing — guard falls back to Supabase | Low | Critical | Guard runs at `createApplication()` before any DB operation; aborts immediately |
| SQLite substitution invalidating DISTINCT ON tests | N/A | Critical | SQLite is not an option without explicit code changes; ruled out |
| Port 5432 taken by another process after reboot | Very Low | Low | `pg_isready` pre-flight check before each test run |
| Supabase baseline data altered by test operations | Very Low | Critical | Guard prevents any test from reaching Supabase; two-layer protection |

---

## 7. Recovery Procedure (Tiered Escalation)

If the local PostgreSQL service becomes unresponsive or the test database is corrupted **after
installation**:

**Step 1 — Diagnose (non-destructive):**
- Check service state: `Get-Service postgresql-x64-18`
- Check port: `Test-NetConnection -ComputerName 127.0.0.1 -Port 5432`
- Check pg_isready: `pg_isready -h 127.0.0.1 -p 5432`
- Review Windows Event Viewer (Application log) for PostgreSQL errors.
- Review the PostgreSQL log file in the data directory (`pgsql_18\data\log\`).

**Step 2 — Attempt safe restart (non-destructive):**
- Restart the service: `Restart-Service postgresql-x64-18`
- If service refuses to start, check the event log for the specific error.

**Step 3 — Database-level recovery (non-destructive to data directory):**
- If only `aquatara_test` is corrupted, connect as superuser and run:
  `DROP DATABASE aquatara_test;` then recreate via Phase 4 commands.
- This does not touch other databases or the data directory.

**Step 4 — Full data directory recreation (DESTRUCTIVE — requires explicit user approval):**
Only if Steps 1–3 are exhausted and the data directory is confirmed corrupted:
- Stop service: `Stop-Service postgresql-x64-18`
- Back up `pg_hba.conf` and `postgresql.conf` if accessible.
- Delete the data directory and reinitialise with `initdb`.
- Re-create `aquatara_test` and re-run migrations.
- This step requires explicit user approval before execution.

**Supabase is unaffected by any of the above steps.** The live database has no dependency on
the local PostgreSQL instance.

---

## 8. Approval Checklist (Pre-Installation)

The following items require explicit user confirmation before Phase 2 begins:

- [ ] **Orphaned service option chosen:** Option A (proceed and observe), B (delete first), or C (different service name) — **specify which option**
- [ ] **Installation path confirmed:** `F:\pc\prog\postgresql\pgsql_18`
- [ ] **Data directory confirmed:** `F:\pc\prog\postgresql\pgsql_18\data`
- [ ] **Port confirmed:** `5432`
- [ ] **User acknowledges administrator privileges are required** to install a Windows service
- [ ] **Superuser password:** Will be chosen interactively; stored only in a local credential vault
- [ ] **User confirms Supabase `.env` will not be touched** during or after installation
- [ ] **User approves creation of `aquatara_test` database** after installation succeeds (Phase 4)
- [ ] **User confirms `.env.testing` will be added to `.gitignore` before the file is created** (Phase 5a)
- [ ] **User has read the Safety Review** in `backend/docs/STEP_4D_10_1_INSTALLATION_PLAN_SAFETY_REVIEW.md`

---

## 9. Confirmation of Constraints Satisfied

| Constraint | Status |
|------------|--------|
| Installer NOT run | Not run |
| No administrator access requested or used | None used |
| No Windows service modified | None modified |
| No database accessed or created | None touched |
| No Supabase connection | Not accessed |
| No PHPUnit, artisan migrate, or seed executed | Not executed |
| No source code, .env, phpunit.xml, or .gitignore modified | None modified |
| No passwords, tokens, or secrets printed | None printed |
| Step 4D.11 and Step 4E not started | Not started |

---

## 10. Files in This Step

| File | Action |
|------|--------|
| `backend/docs/STEP_4D_10_POSTGRES_INSTALLATION_PLAN.md` | Revised (this document) |
| `backend/docs/STEP_4D_10_1_INSTALLATION_PLAN_SAFETY_REVIEW.md` | Created (audit report) |

**No source code, configuration, or `.env` files were modified.**

---

## 11. Status and Next Step

# PLAN REVISED — AWAITING EXPLICIT APPROVAL

**Installation has NOT been executed.**
**No database has been created.**
**No configuration has been changed.**

To proceed:
1. Read `backend/docs/STEP_4D_10_1_INSTALLATION_PLAN_SAFETY_REVIEW.md`.
2. Complete the Approval Checklist in Section 8, specifying the orphaned service option.
3. Ensure administrator access is available for the installation session.
4. Explicitly request: **"Proceed with Step 4D.11 — PostgreSQL Installation Execution."**

Execution will not begin automatically.
