# Step 4D.10.4 — Final Read-Only Audit of Legacy PostgreSQL Service & Data

**Date:** 2026-10-12  
**Timestamp of Audit:** 2026-10-12T02:20:00+07:00 – 2026-10-12T02:22:00+07:00  
**Auditor:** Antigravity Pairing Agent  
**Scope:** Strictly Read-Only Audit — No State Mutation — No Database Access — No Service Alteration  
**Audit Status:** PASS (Audit completed successfully; full evidence gathered)  

---

## 1. Audit Purpose & Strict Boundaries

The goal of this audit is to determine conclusively whether the Windows service `postgresql-x64-18` is an orphaned service entry and whether there are any residual data directories, processes, or ports in use prior to any local PostgreSQL installation decision.

### Absolute Constraints Maintained:
- **Zero System Changes:** No `sc.exe delete`, `sc.exe config`, `Stop-Service`, `Start-Service`, `Remove-Service`, or registry writes performed.
- **Zero File/Data Deletions or Alterations:** No files or folders deleted, renamed, or modified.
- **Zero Database Queries or Connections:** No SQL queries sent; no connections attempted to Supabase or local PostgreSQL.
- **Zero PHPUnit/Artisan/Migration Executions:** Neither test runner nor migrations were executed.
- **Zero Secret Exposure:** Passwords, tokens, or credentials were not printed or queried.
- **No Autonomous Administrative Action:** The agent makes no decision on service deletion; evidence is presented for explicit user decision.

---

## 2. Comprehensive Evidence & Findings

### 2.1 Service Configuration & Registry Details
Inspected via WMI (`Win32_Service`) and Registry (`HKLM:\SYSTEM\CurrentControlSet\Services\postgresql-x64-18`):

| Parameter | Observed Value | Evidence Source |
|---|---|---|
| **Service Name** | `postgresql-x64-18` | WMI / Registry |
| **Display Name** | `postgresql-x64-18 - PostgreSQL Server 18` | WMI / Registry |
| **Current State** | `Stopped` | WMI `State` / `Get-Service` |
| **Startup Type** | `Automatic` (`StartMode: Auto`, `Start: 2`) | WMI / Registry |
| **Account (ObjectName)**| `NT AUTHORITY\NetworkService` | WMI `StartName` / Registry `ObjectName` |
| **Dependencies** | `RPCSS` | Registry `DependOnService` |
| **Description** | `Provides relational database storage.` | WMI / Registry |
| **ImagePath (BinaryPathName)** | `"F:\pc\prog\postgresql\pgsql_18\bin\pg_ctl.exe" runservice -N "postgresql-x64-18" -D "F:\pc\prog\postgresql\pgsql_18\data" -w` | WMI `PathName` / Registry `ImagePath` |

---

### 2.2 Executable & Data Directory Physical Existence Check

We tested the physical existence of every path referenced in the service definition and typical installation locations:

| Target Path | Type | Existence | Evidence Command | Note |
|---|---|---|---|---|
| `F:\pc\prog\postgresql\pgsql_18\bin\pg_ctl.exe` | Executable | **False (NOT FOUND)** | `Test-Path` | Binary does not exist. |
| `F:\pc\prog\postgresql\pgsql_18\bin` | Binary Directory | **False (NOT FOUND)** | `Test-Path` | Directory does not exist. |
| `F:\pc\prog\postgresql\pgsql_18\data` | Service Data Directory | **False (NOT FOUND)** | `Test-Path` | No data directory in target path. |
| `F:\pc\prog\postgresql\pgsql_18` | Installation Directory | **False (NOT FOUND)** | `Test-Path` | Target folder does not exist. |
| `F:\pc\prog\postgresql` | Parent Directory | **True (EXISTS)** | `Test-Path` | Contains only installer (see below). |
| `C:\Program Files\PostgreSQL` | Standard 64-bit Directory | **False (NOT FOUND)** | `Test-Path` | No PostgreSQL installed on `C:`. |
| `C:\Program Files (x86)\PostgreSQL` | Standard 32-bit Directory | **False (NOT FOUND)** | `Test-Path` | No 32-bit PostgreSQL on `C:`. |

#### Targeted Parent Directory Inspection: `F:\pc\prog\postgresql`
Inspection of `Get-ChildItem -Path "F:\pc\prog\postgresql" -Force`:
- Contains exactly **one item**:
  - Name: `postgresql-18.1-2-windows-x64.exe`
  - Size: `371,622,632 bytes` (~354.4 MB)
  - LastWriteTime: `1/16/2026 1:29:03 PM`
  - Mode: `-a----` (Regular Archive File)
- **Subdirectories:** 0 subdirectories. There are no subfolders, no `.dat` files, no `.conf` files, and no `data/` directories anywhere inside `F:\pc\prog\postgresql`.

---

### 2.3 Other PostgreSQL Services & Port Availability Check

#### A. Other Registered Services
Inspected via `Get-Service | Where-Object { $_.Name -like "*postgres*" -or $_.DisplayName -like "*postgres*" }`:
- **Result:** Exactly **1 service found** across the entire OS: `postgresql-x64-18`.
- No other PostgreSQL service exists (no PostgreSQL 14, 15, 16, 17, or generic PostgreSQL service).

#### B. Local Port Audit
Audited ports `5432`, `5433`, and `5434` on `127.0.0.1` via `Get-NetTCPConnection` and `Test-NetConnection`:

| Port | Protocol | Listening Process | TCP Connection Result | Port Status |
|---|---|---|---|---|
| **5432** | TCP | None (`Get-NetTCPConnection` returned nothing) | `TcpTestSucceeded: False` (WARNING: TCP connect failed) | **AVAILABLE / CLOSED** |
| **5433** | TCP | None (`Get-NetTCPConnection` returned nothing) | `TcpTestSucceeded: False` (WARNING: TCP connect failed) | **AVAILABLE / CLOSED** |
| **5434** | TCP | None (`Get-NetTCPConnection` returned nothing) | `TcpTestSucceeded: False` (WARNING: TCP connect failed) | **AVAILABLE / CLOSED** |

All three ports are completely free and not in use by any process on `127.0.0.1`.

---

### 2.4 Installer Metadata & Digital Signature Verification

Inspected file `F:\pc\prog\postgresql\postgresql-18.1-2-windows-x64.exe` without executing:
- **Full Path:** `F:\pc\prog\postgresql\postgresql-18.1-2-windows-x64.exe`
- **File Size:** `371,622,632 bytes`
- **Last Modified:** `1/16/2026 1:29:03 PM`
- **Authenticode Signature Status:** **`Valid` (Signature verified)**
- **Signer Subject:** `CN=EnterpriseDB Corporation, O=EnterpriseDB Corporation, S=Massachusetts, C=US`
- **Issuer:** `CN=Sectigo Public Code Signing CA R36, O=Sectigo Limited, C=GB`
- **Cert Serial Number:** `64346960AD81BDCB83A8A3C6760F4D47`
- **Thumbprint:** `698BA51AA27CC31282AACA5055E4B9190BC6C0E9`
- **Execution:** File was **NOT** executed.

---

## 3. Comparison with Prior Reports & Unverified Claims Check

| Item / Claim | Prior Report Assertion | Step 4D.10.4 Finding | Assessment |
|---|---|---|---|
| **Service Status** | Stopped in 4D.9, 4D.10.1, 4D.10.3 | Stopped (`State: Stopped`) | **PROVEN FACT** |
| **ImagePath** | Target binary `pg_ctl.exe` missing | Missing (`Test-Path: False`) | **PROVEN FACT** |
| **Data Directory** | Speculated missing in 4D.9 | Verified `F:\pc\prog\postgresql\pgsql_18\data` does not exist (`Test-Path: False`). No data directory in parent. | **PROVEN FACT** |
| **Port Status** | 5432 & 5433 closed in 4D.9 | 5432, 5433, and 5434 verified closed (`TcpTestSucceeded: False`) | **PROVEN FACT** |
| **Installer Authenticity** | Mentioned in 4D.9 | Verified digital signature from EnterpriseDB Corporation | **PROVEN FACT** |
| **Installer Behavior on Existing Service** | "Installer will automatically overwrite existing service" (Step 4D.10 original) | Flagged as unverified claim in 4D.10.1; remains an **untested assumption**. Cannot be assumed safe without clean state. | **REMAINS UNVERIFIED ASSUMPTION** |

---

## 4. Distinction: Orphan Service vs Residual Data

- **Orphan Service Proof:**
  - The registry entry `HKLM:\SYSTEM\CurrentControlSet\Services\postgresql-x64-18` points to `F:\pc\prog\postgresql\pgsql_18\bin\pg_ctl.exe`.
  - The directory `F:\pc\prog\postgresql\pgsql_18` has zero presence on disk.
  - Windows Service Control Manager cannot start this service (`The system cannot find the path specified`).
- **Residual Data Assessment:**
  - The configured data path `F:\pc\prog\postgresql\pgsql_18\data` **does not exist**.
  - The parent directory `F:\pc\prog\postgresql` contains **only** the installer binary.
  - Standard system locations (`C:\Program Files\PostgreSQL`) **do not exist**.
  - **Conclusion:** There is **NO** residual database data directory associated with this service on the disk. Deleting the orphaned service registry entry carries **ZERO** risk of data loss.

---

## 5. Uncertainties & Limitations

1. **Other Drive Partitions:** We did not run an unconstrained global drive scan across all disks (e.g. searching entire drives for `.pgpass` or custom folders), adhering strictly to constraint #3 (only relevant, known locations). However, for the specific path registered in the service (`F:\pc\prog\postgresql\pgsql_18\data`), existence was explicitly proven to be `False`.
2. **Elevated Privileges:** The service controller (`sc.exe delete`) requires an elevated (Run as Administrator) command prompt or user confirmation; it cannot and must not be run without explicit user instruction.

---

## 6. Evidence-Based Recommendation

Based on the definitive physical absence of both the executable binary and the database data directory:

### **Rekomendasi Terpilih: Opsi A (Service aman dihapus setelah persetujuan eksplisit)**

- **Justifikasi Teknis:**
  1. Service `postgresql-x64-18` adalah 100% *ghost/orphan registration* (biner dan data sama-sama tidak ada).
  2. Menghapus service ini **TIDAK** dapat menyebabkan kehilangan data karena tidak ada file database yang ada pada path tersebut.
  3. Menghapus service sebelum instalasi (`sc.exe delete postgresql-x64-18`) mengeliminasi risiko kegagalan installer akibat konflik nama service atau kegagalan menimpa registry yang terkunci.
  4. Port `5432` terbukti bersih dan siap digunakan oleh instalasi baru.

- **Prosedur Aman yang Direkomendasikan (Menunggu Persetujuan Pengguna):**
  1. Pengguna menjalankan PowerShell sebagai Administrator:
     ```powershell
     sc.exe delete postgresql-x64-18
     ```
  2. Verifikasi penghapusan:
     ```powershell
     Get-Service postgresql*
     ```
     *(Harus mengembalikan kosong / tidak ada service).*
  3. Lanjutkan ke instalasi PostgreSQL lokal secara terkontrol menggunakan installer terverifikasi `F:\pc\prog\postgresql\postgresql-18.1-2-windows-x64.exe`.

---

## 7. Explicit Safety Confirmation

- [x] **Audit was 100% read-only.**
- [x] **No Windows services were stopped, started, modified, or deleted.**
- [x] **No registry keys were modified or removed.**
- [x] **No files or folders were created, moved, overwritten, or deleted.**
- [x] **No database connection was attempted.**
- [x] **No queries were sent to Supabase or any remote/local host.**
- [x] **No PHPUnit test, Artisan command, or migration was executed.**
- [x] **No installer was executed.**
- [x] **Execution stops here awaiting explicit user choice.**
