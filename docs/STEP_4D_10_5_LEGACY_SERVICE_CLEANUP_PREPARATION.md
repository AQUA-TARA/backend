# Step 4D.10.5 — Controlled Legacy Service Cleanup Preparation

**Date:** 2026-10-12  
**Timestamp of Verification:** 2026-10-12T02:25:20+07:00 – 2026-10-12T02:26:00+07:00  
**Author:** Antigravity Pairing Agent  
**Scope:** Read-Only Re-Verification & Preparation Document for Service Cleanup — NO SYSTEM MUTATIONS  
**Preparation Status:** PASS (Re-verification complete; target unambiguous; execution held pending user approval)  

---

## 1. Executive Summary & Boundaries

This document carries out **Step 4D.10.5**, preparing for the controlled cleanup of the orphaned Windows service `postgresql-x64-18` based on the audit in [STEP_4D_10_4_LEGACY_POSTGRES_READ_ONLY_AUDIT.md](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/docs/STEP_4D_10_4_LEGACY_POSTGRES_READ_ONLY_AUDIT.md).

### Absolute Boundaries Maintained:
- **Zero System Mutations:** `sc.exe delete`, `sc.exe config`, `Stop-Service`, `Start-Service`, `Remove-Service`, or registry modifications were **NOT** executed.
- **Zero Elevation Actions:** No elevated Administrator sessions or administrative actions were triggered by the agent.
- **Zero File/Folder Alterations:** No files or folders deleted, renamed, overwritten, or created.
- **Zero Database Operations:** No queries sent; no connections opened to Supabase or local databases.
- **Zero PHPUnit/Artisan/Migration Executions:** Neither test runner nor migrations were executed.
- **Zero Secret Exposure:** Passwords, tokens, or credentials were not printed or queried.
- **Strict Role:** Status **PASS** means preparation and read-only verification are complete. The service has **NOT** been deleted. Action requires explicit user authorization.

---

## 2. Phase 1 — Read-Only Re-Verification Results

Re-verified live on the system at `2026-10-12T02:25:25+07:00`:

| Parameter | Value Observed | Method | Verification State |
|---|---|---|---|
| **Target Service Name** | `postgresql-x64-18` | `Get-Service` / WMI | **CONFIRMED** |
| **Display Name** | `postgresql-x64-18 - PostgreSQL Server 18` | `Get-Service` / WMI | **CONFIRMED** |
| **Current Service State** | `Stopped` | `Get-Service` / WMI | **CONFIRMED (INACTIVE)** |
| **Startup Type** | `Automatic` (`StartMode: Auto`) | WMI `win32_service` | **CONFIRMED** |
| **Service Account** | `NT AUTHORITY\NetworkService` | WMI `win32_service` | **CONFIRMED** |
| **ImagePath (BinaryPathName)** | `"F:\pc\prog\postgresql\pgsql_18\bin\pg_ctl.exe" runservice -N "postgresql-x64-18" -D "F:\pc\prog\postgresql\pgsql_18\data" -w` | WMI / Registry | **CONFIRMED** |
| **Physical Binary Path** | `F:\pc\prog\postgresql\pgsql_18\bin\pg_ctl.exe` | `Test-Path` | **FALSE (DOES NOT EXIST)** |
| **Referenced Data Directory** | `F:\pc\prog\postgresql\pgsql_18\data` | `Test-Path` | **FALSE (DOES NOT EXIST)** |
| **Installation Directory** | `F:\pc\prog\postgresql\pgsql_18` | `Test-Path` | **FALSE (DOES NOT EXIST)** |
| **Other PostgreSQL Services** | None (`postgresql-x64-18` is the only match) | `Get-Service *postgres*` | **CONFIRMED UNIQUE** |
| **Running PostgreSQL Processes** | None (`postgres`, `pg_ctl`, `psql` = 0 processes) | `Get-Process` | **CONFIRMED NONE** |
| **Standard Locations on C:** | `C:\Program Files\PostgreSQL` | `Test-Path` | **FALSE (DOES NOT EXIST)** |

### Scope Boundary on Residual Data:
- The data directory referenced by the service configuration (`F:\pc\prog\postgresql\pgsql_18\data`) is definitively absent.
- The parent directory `F:\pc\prog\postgresql` contains strictly the installer file `postgresql-18.1-2-windows-x64.exe` (371.6 MB) and zero subfolders.
- **Important Distinction:** This audit does not make sweeping claims that no other PostgreSQL data exists anywhere across unreferenced drives; however, it proves beyond doubt that **no data directory exists in the location mapped to this specific service entry**. Therefore, deleting this service entry cannot destroy active data mapped to `postgresql-x64-18`.

---

## 3. Phase 2 — Cleanup Preparation & Procedure

### 3.1 Planned Command
The target service will be deleted from the Windows Service Control Manager using Windows' native Service Controller tool:
```cmd
sc.exe delete postgresql-x64-18
```
*(Alternative PowerShell cmdlet on modern Windows: `Remove-Service -Name "postgresql-x64-18"`).*

> [!IMPORTANT]
> **THIS COMMAND HAS NOT BEEN RUN.** It is presented here strictly as the planned, verified operation awaiting your explicit approval.

### 3.2 Technical Impact of the Planned Command
1. **What will be modified:**
   - The Service Control Manager registry subkey `HKLM:\SYSTEM\CurrentControlSet\Services\postgresql-x64-18` will be marked for deletion / removed.
   - The service `postgresql-x64-18` will no longer appear in `Get-Service` or Windows Service Manager (`services.msc`).
2. **What will NOT be modified:**
   - No files or folders on disk will be altered or deleted.
   - The installer file `F:\pc\prog\postgresql\postgresql-18.1-2-windows-x64.exe` will remain completely intact.
   - Network ports and system drivers will remain unaffected.
   - No database records, filesystems, or application code will be changed.

### 3.3 Post-Action Verification Procedure
Immediately following the execution of `sc.exe delete postgresql-x64-18`, the operator must execute the following read-only commands to verify success:

1. **Verify service removal:**
   ```powershell
   Get-Service postgresql-x64-18 -ErrorAction SilentlyContinue
   ```
   *Expected result:* Returns nothing (null), confirming the service entry is gone.

2. **Verify no remaining PostgreSQL services:**
   ```powershell
   Get-Service | Where-Object { $_.Name -like "*postgres*" -or $_.DisplayName -like "*postgres*" }
   ```
   *Expected result:* Empty list (0 services found).

3. **Verify registry key removal:**
   ```powershell
   Test-Path "HKLM:\SYSTEM\CurrentControlSet\Services\postgresql-x64-18"
   ```
   *Expected result:* `False`.

4. **Verify port cleanliness (Regression check):**
   ```powershell
   Test-NetConnection -ComputerName 127.0.0.1 -Port 5432
   ```
   *Expected result:* `TcpTestSucceeded: False` (Port 5432 clean and ready for clean installation).

---

## 4. Rollback / Contingency Information

Because the service target binary (`F:\pc\prog\postgresql\pgsql_18\bin\pg_ctl.exe`) does not exist on disk, the service in its current state is completely inoperable and unstartable. However, for full audit transparency, if the exact registry configuration ever needed to be recreated, the parameters recorded in Step 4D.10.4 are:
- Service Name: `postgresql-x64-18`
- Display Name: `postgresql-x64-18 - PostgreSQL Server 18`
- Binary Path: `"F:\pc\prog\postgresql\pgsql_18\bin\pg_ctl.exe" runservice -N "postgresql-x64-18" -D "F:\pc\prog\postgresql\pgsql_18\data" -w`
- Start Type: `auto`
- Account: `NT AUTHORITY\NetworkService`

---

## 5. Explicit Safety Confirmations

- [x] **Read-only re-verification performed and matched Step 4D.10.4 findings 100%.**
- [x] **No Windows services were stopped, started, modified, or deleted.**
- [x] **No registry keys were modified or removed.**
- [x] **No files or folders were created, moved, overwritten, or deleted.**
- [x] **No database queries or connections were initiated (Supabase or local).**
- [x] **No PHPUnit test, Artisan command, or migration was executed.**
- [x] **No installer was executed.**
- [x] **Status PASS confirms preparation only — execution is strictly halted.**

---

## 6. Next Step & Decision Point

The preparation is complete and the target is uniquely identified. The agent will **NOT** execute the cleanup command autonomously.

**Pilihan untuk Pengguna:**
- **Setuju Pembersihan:** Berikan instruksi atau konfirmasi eksplisit untuk menjalankan `sc.exe delete postgresql-x64-18` (atau jalankan sendiri di terminal Administrator).
- **Tunda / Batalkan:** Instruksikan untuk menunda pembersihan jika ada hal lain yang ingin diinvestigasi.
