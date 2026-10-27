# Step 4D.10.3 — Change Review & Service Decision Report

**Date:** 2026-10-12  
**Auditor / Reviewer:** Antigravity Pairing Agent  
**Scope:** Post-Step 4D.10.2 Diff Review, Vulnerability & Regression Check, and Service Handling Decision Presentation  
**Status:** REVIEW COMPLETE — System Action Blocked Awaiting User Decision  

---

## 1. Executive Summary

This audit reviews all changes applied in Step 4D.10.2, conducts an exhaustive vulnerability and regression analysis across the test isolation guard and harness files, differentiates between verified facts and report claims, and presents the formal decision framework for handling the orphaned Windows service `postgresql-x64-18`.

Strictly following the project safety boundaries:
- **Zero Database Operations:** No queries, migrations, PHPUnit suites, seeders, or database wipes were executed.
- **Zero Network Connections to Supabase:** No connections opened to Supabase direct hosts or poolers.
- **Zero System Modifications:** No PostgreSQL installer was run; no Windows service entries were modified, created, or deleted.
- **No Secret Exposure:** Zero secrets, passwords, tokens, or live URLs exposed.

---

## 2. Review of Actual Changes Made in Step 4D.10.2

### 2.1 File Diffs & Specific Changes

#### A. [backend/tests/Support/TestEnvironmentGuard.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/Support/TestEnvironmentGuard.php)
- **Previous implementation:** Only inspected raw `$config['host']` and checked string inclusion of `supabase` / `pooler` in `$config['url']`. If `$config['host']` was `127.0.0.1` but `$config['url']` pointed to a non-Supabase remote database (e.g. AWS RDS or an external IP) or a system maintenance database (`/postgres`), the guard passed.
- **Enhanced implementation:**
  1. Preserved raw signature check `assertNoSupabaseSignatures()` across raw fields.
  2. Implemented `resolveUrlConfiguration()`:
     - Parses URL using `parse_url()`. Rejects malformed URLs immediately.
     - Decodes raw components with `rawurldecode()`.
     - Validates that network drivers (`pgsql`, `mysql`, `mariadb`, `sqlsrv`) have an explicit, non-empty host in the URL.
     - Validates that the URL host matches the local whitelist `['127.0.0.1', 'localhost', '::1']`.
     - Validates that the URL database path does not target forbidden system databases (`postgres`, `public`, `template0`, `template1`, `mysql`, `sys`, `information_schema`).
     - Hydrates options via Laravel’s `Illuminate\Support\ConfigurationUrlParser`, guaranteeing exact runtime parity with Laravel’s `DatabaseManager`.
  3. Enforced that network drivers must have a non-empty `host` and non-empty `database`.

#### B. [backend/tests/TestCase.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/TestCase.php)
- **Status:** **UNTOUCHED / UNCHANGED**.
- **Inspection:** Evaluates `assertSafeTestEnvironment()` inside `createApplication()` (before `$app` is returned to PHPUnit and before `setUpTraits()` executes) and repeats the assertion in `setUp()`.

#### C. [backend/phpunit.xml](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/phpunit.xml)
- **Modified Line 28:** Changed `<env name="DB_URL" value=""/>` to `<env name="DB_URL" value="" force="true"/>`.
- **Impact:** Forces PHPUnit to overwrite any ambient `DB_URL` environment variable that might be set in the host OS session.

#### D. [backend/tests/Support/verify_guard_logic.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/Support/verify_guard_logic.php)
- Expanded synthetic test cases from 8 to 17 scenarios, covering Supabase URLs, remote PostgreSQL URLs, malformed URLs, URLs without host, valid local URLs, system database paths, named secondary connections, and missing configurations.
- Verified: All 17 cases execute with pure logic and return 100% PASS.

#### E. [backend/tests/Support/verify_url_guard.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/Support/verify_url_guard.php)
- Synchronized exception messages with `TestEnvironmentGuard`.
- Verified: All 18 synthetic test scenarios return 100% PASS.

---

## 3. Vulnerability, Bypass & Regression Analysis

### 3.1 Potential Regressions Checked
| Check | Finding | Status |
|---|---|---|
| **Does the guard break legitimate local testing?** | Synthetic test cases with `host: 127.0.0.1` and `database: aquatara_test` (both with `url: ''` and valid local URL `postgresql://...127.0.0.1:5432/aquatara_test`) pass cleanly. | ✅ NO REGRESSION |
| **Does the guard break SQLite in-memory tests?** | SQLite connection definitions (`driver: sqlite`, `database: :memory:`) are unaffected because network driver checks only apply to `pgsql`, `mysql`, `mariadb`, `sqlsrv`. | ✅ NO REGRESSION |
| **Does the guard introduce framework dependencies?** | Uses `Illuminate\Support\ConfigurationUrlParser` (available in `vendor/autoload.php`), without database drivers, connection factories, or PDO initialization. | ✅ NO REGRESSION |

### 3.2 Bypass & Edge Case Verification
| Bypass Vector | Guard Defense Mechanism | Verified Result |
|---|---|---|
| **Percent-encoded remote host in URL** (`%31%39%32...`) | `rawurldecode()` decodes the host before comparing against the local whitelist. | ✅ BLOCKED |
| **URL targeting `/postgres` while config has `aquatara_test`** | URL path is decoded and evaluated against system DB blacklist. | ✅ BLOCKED |
| **Malformed URL syntax** (`http:///malformed::url`) | `parse_url()` returns false or throws; caught and rejected immediately. | ✅ BLOCKED |
| **URL without host for PostgreSQL** (`postgresql:/aquatara_test`) | Guard checks for non-empty `$urlHost`; throws missing host exception. | ✅ BLOCKED |
| **Triple-slash URL syntax** (`postgresql:///aquatara_test`) | `parse_url()` returns false; caught as malformed URL. | ✅ BLOCKED |
| **Named connection to remote host** | `assertSafeEnvironment()` iterates over ALL connection entries in config. | ✅ BLOCKED |

### 3.3 Bootstrap Order & Environment Priority Risks
1. **Bootstrap Order:**
   - Fact: `Tests\TestCase::createApplication()` runs when PHPUnit calls `setUp() -> refreshApplication() -> createApplication()`.
   - Fact: `setUpTraits()` (which triggers `RefreshDatabase` and `DatabaseTransactions`) runs *after* `createApplication()` completes.
   - Proof: If `TestEnvironmentGuard` throws in `createApplication()`, the application instance is never assigned, and `setUpTraits()` is never reached.
2. **Environment Priority:**
   - Risk: `.env.testing` does not yet exist. If a test runner boots without `phpunit.xml` environment overrides, Laravel loads the active `.env`.
   - Proof: When tested via [verify_live_bootstrap_guard.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/Support/verify_live_bootstrap_guard.php), `createApplication()` with the active `.env` threw `RuntimeException` immediately upon seeing the Supabase infrastructure.

---

## 4. Distinction Between Verified Facts and Report Claims

| Item | Status | Verified Evidence vs Claim |
|---|---|---|
| **Guard blocks Supabase URLs** | **VERIFIED FACT** | Proven by automated synthetic tests and live dry-run bootstrap test. |
| **Guard blocks non-Supabase remote URLs** | **VERIFIED FACT** | Proven by test cases `T06`, `T07`, `T11`, `T12` in both test harnesses. |
| **Guard runs before `RefreshDatabase`** | **VERIFIED FACT** | Traced directly in `Illuminate\Foundation\Testing\TestCase::setUp()` source code. |
| **Ports 5432 and 5433 are closed** | **VERIFIED FACT** | Re-tested via `Test-NetConnection` on 127.0.0.1: Port 5432 `TcpTestSucceeded: False`, Port 5433 `TcpTestSucceeded: False`. |
| **Service binary does not exist** | **VERIFIED FACT** | `Test-Path "F:\pc\prog\postgresql\pgsql_18\bin\pg_ctl.exe"` returned `False`. |
| **EDB Installer automatically overwrites orphaned service** | **UNVERIFIED CLAIM** | Flagged in Step 4D.10.1 as an assumption. Must NOT be assumed to work unattended. |

---

## 5. Service Handling Decision Framework: `postgresql-x64-18`

### 5.1 Context of the Orphaned Service
- **Service Name:** `postgresql-x64-18`
- **Display Name:** `postgresql-x64-18 - PostgreSQL Server 18`
- **Status:** `Stopped`
- **Startup Type:** `Automatic`
- **Binary Image Path:** `"F:\pc\prog\postgresql\pgsql_18\bin\pg_ctl.exe" runservice -N "postgresql-x64-18" -D "F:\pc\prog\postgresql\pgsql_18\data" -w`
- **Physical Reality:** Directory `F:\pc\prog\postgresql\pgsql_18` does not exist. Ports 5432 and 5433 are completely closed.

---

### 5.2 The Three Options

```
                          ┌─────────────────────────────────────┐
                          │ Orphaned Service: postgresql-x64-18 │
                          └──────────────────┬──────────────────┘
                                             │
             ┌───────────────────────────────┼───────────────────────────────┐
             ▼                               ▼                               ▼
       [OPSI A]                        [OPSI B]                        [OPSI C]
 Pertahankan & Amati             Hapus Service Resmi             Tunda / Batalkan
 (Controlled Install)            (Clean State First)             (Investigasi Lanjut)
             │                               │                               │
 ├─ Jalankan installer           ├─ Jalankan `sc.exe delete`     ├─ Jangan install
 ├─ Amati apakah service            di Admin PowerShell             lokal
    ditimpa/dibuat ulang         ├─ Verifikasi service hilang    ├─ Cari alternatif
 └─ Risiko: konflik/stale        └─ Install di kondisi bersih       (mock / remote test)
```

#### Opsi A: Pertahankan Service Lama lalu Jalankan Instalasi Terkontrol
- **Deskripsi:** Tidak menghapus service sebelum instalasi. Installer `postgresql-18.1-2-windows-x64.exe` dijalankan, dan operator mengamati apakah installer secara otomatis memperbarui `ImagePath` service atau meminta konfigurasi baru.
- **Keuntungan:** Tidak memerlukan intervensi manual terhadap registry/Service Control Manager sebelum instalasi.
- **Risiko:**
  - Jika installer gagal mendeteksi atau menolak service yang sudah ada, proses instalasi dapat *abort* atau meninggalkan konfigurasi setengah jalan (*partial installation*).
  - Port atau nama service bisa bentrok jika installer mencoba mendaftarkan nama yang sama dan gagal meregistrasi binary baru.
- **Rekomendasi:** **Tidak direkomendasikan** untuk sistem produksi/pengembangan yang memerlukan determinisme tinggi.

---

#### Opsi B: Dokumentasikan dan Tangani Service Lama Melalui Prosedur Resmi (REKOMENDASI)
- **Deskripsi:**
  1. Catat konfigurasi lengkap service lama sebagai dokumentasi (sudah tercatat di laporan).
  2. Hapus service yatim (*orphaned service*) menggunakan perintah resmi Windows Service Controller di PowerShell dengan hak Administrator:
     ```powershell
     sc.exe delete postgresql-x64-18
     ```
  3. Verifikasi bahwa service telah terhapus (`Get-Service postgresql*` mengembalikan kosong).
  4. Jalankan installer PostgreSQL resmi di atas *clean state*.
- **Keuntungan:**
  - Menghilangkan ambiguitas state Windows Service.
  - Memastikan installer membuat service baru dengan binary path, parameter data directory, dan service account yang valid dan terverifikasi dari nol.
  - Mencegah konflik nama atau port 5432.
- **Risiko:** Membutuhkan satu tindakan elevated manual (`sc.exe delete`) oleh pengguna.
- **Rekomendasi:** **SANGAT DIREKOMENDASIKAN (RECOMMENDED)**.

---

#### Opsi C: Hentikan / Tunda Instalasi Sampai Kondisi Service Dapat Dipastikan Lebih Lanjut
- **Deskripsi:** Menunda instalasi PostgreSQL lokal sama sekali. Pengujian tetap diblokir dari Supabase dan menunggu hingga pengguna memutuskan arsitektur alternatif (misalnya, membuat VM khusus atau opsi lain).
- **Keuntungan:** Zero impact pada sistem Windows host saat ini.
- **Risiko:** Pengujian PHPUnit AQUA-TARA tetap berada dalam status BLOCKED (karena query `DISTINCT ON` pada `MLAnalysisService` memerlukan PostgreSQL dan tidak dapat berjalan di SQLite).
- **Rekomendasi:** Dipilih hanya jika pengguna tidak memiliki izin Administrator pada mesin atau melarang perubahan apa pun pada Windows Service.

---

## 6. Konfirmasi Status & Pemberhentian Sistem

- **Tindakan Sistem:** Tidak ada installer yang dijalankan, tidak ada service yang dihapus, dan tidak ada database yang diakses.
- **Status Akhir Step 4D.10.3:** **SELESAI (REVIEW COMPLETE)**.
- **Proses Berhenti:** Sistem menunggu pilihan eksplisit dari pengguna antara **Opsi A**, **Opsi B**, atau **Opsi C**.
