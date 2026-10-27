# Step 4D.11.5 — Audit Konfigurasi Laravel untuk Database Testing Lokal

**Date:** 2026-10-12  
**Timestamp:** 2026-10-12T03:18:00+07:00  
**Author:** Antigravity Pairing Agent  
**Scope:** Read-Only Audit — Konfigurasi Efektif Testing, Celah, dan Rencana Perubahan Minimal  
**Final Status:** ✅ **AUDIT SELESAI — Rencana Siap untuk Ditinjau**

---

## 1. Ringkasan Eksekutif

Audit read-only ini memetakan seluruh rantai resolusi konfigurasi database yang akan berlaku
saat PHPUnit dijalankan, mengidentifikasi celah yang masih ada, dan menyusun perubahan **minimal**
yang diperlukan agar test suite dapat berjalan aman menggunakan database lokal `aquatara_test`
di `127.0.0.1:5432`.

### Kepatuhan Batasan Mutlak:
- Tidak ada file yang diubah pada step ini.
- Tidak ada secret, password, token, atau connection string yang ditampilkan.
- Tidak ada koneksi ke Supabase.
- Tidak ada PHPUnit, migrasi, atau seeder yang dijalankan.
- `.env.testing` belum dibuat.

---

## 2. Inventaris File yang Diaudit

| File | Status | Catatan |
|---|---|---|
| `backend/.gitignore` | ✅ Ada | Baris 6: `.env.testing` terdaftar |
| `backend/phpunit.xml` | ✅ Ada | Berisi `<env>` tags termasuk `DB_URL force=true` |
| `backend/config/database.php` | ✅ Ada | Default connection: `env('DB_CONNECTION', 'sqlite')` |
| `backend/tests/TestCase.php` | ✅ Ada | Guard dipanggil di `createApplication()` dan `setUp()` |
| `backend/tests/Support/TestEnvironmentGuard.php` | ✅ Ada | Guard lengkap: Supabase, URL parsing, system DB |
| `backend/.env` | ✅ Ada | Konfigurasi produksi Supabase — **tidak diubah** |
| `backend/.env.testing` | ❌ Belum ada | Harus dibuat sebelum test bisa berjalan |

---

## 3. Temuan: Konfigurasi `.env` Produksi (Read-Only, Tanpa Secret)

| Variabel | Nilai (Status) | Risiko Testing |
|---|---|---|
| `DB_CONNECTION` | `pgsql` (aman untuk dilaporkan) | Benar, tapi harus ditimpa ke lokal |
| `DB_HOST` | `[REDACTED]` — **is_supabase=True** | 🔴 **KRITIS**: Mengarah ke Supabase cloud |
| `DB_PORT` | `5432` (aman untuk dilaporkan) | Sama dengan lokal — tidak bermasalah |
| `DB_DATABASE` | `[REDACTED]` — is_test_db=False | 🔴 **KRITIS**: Bukan `aquatara_test` |
| `DB_USERNAME` | `[REDACTED]` — is_supabase_user=True | 🔴 **KRITIS**: User Supabase |
| `DB_PASSWORD` | `[REDACTED]` — has_value=True | 🔴 **KRITIS**: Password Supabase |
| `DB_URL` | **Tidak ada entri di `.env`** | ✅ Aman: tidak ada URL yang bisa bypass guard |

---

## 4. Analisis `phpunit.xml` — Variabel yang Di-set

| Variabel di phpunit.xml | Nilai | force | Efek |
|---|---|---|---|
| `APP_ENV` | `testing` | tidak | Aktifkan environment testing Laravel |
| `DB_URL` | `""` (string kosong) | **true** | Paksa DB_URL kosong — mencegah URL bypass guard |
| `APP_MAINTENANCE_DRIVER` | `file` | tidak | — |
| `BCRYPT_ROUNDS` | `4` | tidak | Percepat hashing di test |
| `BROADCAST_CONNECTION` | `null` | tidak | — |
| `CACHE_STORE` | `array` | tidak | In-memory cache |
| `MAIL_MAILER` | `array` | tidak | — |
| `QUEUE_CONNECTION` | `sync` | tidak | — |
| `SESSION_DRIVER` | `array` | tidak | In-memory session |
| `PULSE_ENABLED` | `false` | tidak | — |
| `TELESCOPE_ENABLED` | `false` | tidak | — |
| `NIGHTWATCH_ENABLED` | `false` | tidak | — |
| `DB_CONNECTION` | **tidak ada** | — | ⚠️ Akan fall-through ke `.env` → `pgsql` |
| `DB_HOST` | **tidak ada** | — | 🔴 Akan fall-through ke `.env` → [SUPABASE HOST] |
| `DB_DATABASE` | **tidak ada** | — | 🔴 Akan fall-through ke `.env` → [SUPABASE DB] |
| `DB_USERNAME` | **tidak ada** | — | 🔴 Akan fall-through ke `.env` → [SUPABASE USER] |
| `DB_PASSWORD` | **tidak ada** | — | 🔴 Akan fall-through ke `.env` → [SUPABASE PASS] |

**Kesimpulan phpunit.xml:** Hanya `DB_URL` yang dikunci (force=true kosong). Semua variabel
DB lainnya tidak di-set di `phpunit.xml` — akan diambil dari `.env` yang mengarah ke Supabase.

---

## 5. Analisis `TestEnvironmentGuard` — Cakupan Perlindungan

### 5.1 Pemeriksaan yang Aktif

| Check | Logika | Status |
|---|---|---|
| **A — Supabase signature (raw host/url)** | Deteksi `supabase.com`, `supabase.co`, `pooler` di host dan URL mentah | ✅ Aktif |
| **B — URL parsing** | Jika `DB_URL` non-kosong, parse dan validasi host URL | ✅ Aktif (tapi DB_URL dikosongkan di phpunit.xml) |
| **C — Driver ada** | Driver tidak boleh kosong | ✅ Aktif |
| **D — Host lokal** | Host harus `127.0.0.1`, `localhost`, atau `::1` | ✅ Aktif |
| **E — System DB terlarang** | Tidak boleh target `postgres`, `template0`, `template1`, dll | ✅ Aktif |
| **F — Database tidak kosong** | DB name tidak boleh string kosong | ✅ Aktif |
| **Inspect ALL connections** | Guard memeriksa *semua* named connections, bukan hanya default | ✅ Aktif |

### 5.2 Simulasi Guard dengan Konfigurasi Saat Ini (Tanpa `.env.testing`)

```
DB_URL = ""          -> Lewat Check B (tidak ada URL untuk di-parse) ✅
DB_HOST = [SUPABASE] -> Check A: str_contains(host, 'supabase.com') = TRUE
                     -> RuntimeException dilempar
                     -> Test suite DITOLAK ✅ (guard bekerja dengan benar)
```

**Guard berfungsi sebagai safety net:** Jika `.env.testing` tidak ada atau salah dikonfigurasi,
guard **memblokir** test suite sebelum terjadi akses ke Supabase.

### 5.3 Celah yang Perlu Diperhatikan

| Celah | Risiko | Mitigasi |
|---|---|---|
| Semua named connections di-check (termasuk `mysql`, `mariadb`, `sqlsrv`) | Koneksi non-default yang mengarah ke remote bisa memblokir test walau tidak dipakai | Akun untuk ini: `.env.testing` hanya perlu meng-override `pgsql` connection vars, bukan koneksi lain. Namun `mysql`, `mariadb`, `sqlsrv` masih membaca `DB_HOST` dari `.env` → [SUPABASE HOST]. **Ini akan memblokir guard!** |
| Guard checks ALL connections, not just default | — | **Solusi wajib:** `.env.testing` harus mengubah `DB_HOST` menjadi `127.0.0.1` agar semua named connections yang menggunakan `DB_HOST` aman |

> **Temuan Kritis:** Karena `config/database.php` mendefinisikan `mysql`, `mariadb`, dan `sqlsrv`
> yang semuanya menggunakan `env('DB_HOST', '127.0.0.1')`, dan `.env` menetapkan
> `DB_HOST=[SUPABASE HOST]`, maka **semua tiga koneksi** ini akan dievaluasi guard dengan
> host Supabase → guard akan throw **untuk setiap koneksi**, bukan hanya default.
>
> Solusinya: `.env.testing` menetapkan `DB_HOST=127.0.0.1` sehingga semua koneksi
> yang menggunakan `DB_HOST` otomatis menjadi lokal.

---

## 6. Resolusi Konfigurasi Efektif yang Diperkirakan

### Skenario A — Tanpa `.env.testing` (Status Saat Ini)

```
Sumber resolusi: phpunit.xml (force) → .env (produksi)

DB_CONNECTION = pgsql          [dari .env]
DB_HOST       = [SUPABASE]     [dari .env] 🔴
DB_PORT       = 5432           [dari .env]
DB_DATABASE   = [SUPABASE_DB]  [dari .env] 🔴
DB_USERNAME   = [SUPABASE_USR] [dari .env] 🔴
DB_PASSWORD   = [SUPABASE_PWD] [dari .env] 🔴
DB_URL        = ""             [dari phpunit.xml, force=true] ✅

→ Guard MEMBLOKIR. Test suite tidak dapat berjalan.
```

### Skenario B — Dengan `.env.testing` yang Benar (Target)

```
Sumber resolusi: phpunit.xml (force) → .env.testing → .env (produksi)

DB_CONNECTION = pgsql          [dari .env.testing]   ✅
DB_HOST       = 127.0.0.1     [dari .env.testing]   ✅
DB_PORT       = 5432           [dari .env.testing]   ✅
DB_DATABASE   = aquatara_test  [dari .env.testing]   ✅
DB_USERNAME   = aquatara_test_user [dari .env.testing] ✅
DB_PASSWORD   = [LOCAL_PASS]   [dari .env.testing]   ✅ (tidak ditampilkan)
DB_URL        = ""             [dari phpunit.xml, force=true] ✅
DB_SSLMODE    = disable        [dari .env.testing]   ✅ (koneksi lokal)

→ Guard LULUS. Test suite dapat berjalan ke lokal.
```

---

## 7. Rencana Perubahan Minimal yang Disarankan

> **Tidak ada perubahan dilakukan pada step ini.** Rencana di bawah disajikan untuk ditinjau.

### 7.1 File yang Perlu Dibuat: `backend/.env.testing`

Variabel yang **wajib** ada:

```dotenv
# ============================================================
# .env.testing — AQUA-TARA Local Test Environment
# JANGAN commit file ini. Sudah dikecualikan di .gitignore.
# ============================================================

APP_ENV=testing
APP_KEY=                        # ← Harus diisi (artisan key:generate --env=testing)

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=aquatara_test
DB_USERNAME=aquatara_test_user
DB_PASSWORD=                    # ← Diisi oleh pengguna, tidak di-commit

DB_URL=                         # Dikosongkan — phpunit.xml sudah force, ini defense-in-depth
DB_SSLMODE=disable              # Koneksi lokal tidak butuh SSL
```

Variabel yang **disarankan** untuk disertakan (berikan override dari `.env` produksi):

```dotenv
# Override agar testing tidak menyentuh service produksi lain
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 7.2 Perubahan pada `phpunit.xml` — Tidak Diperlukan

`phpunit.xml` sudah benar:
- `DB_URL=""` dengan `force=true` sudah melindungi dari bypass via URL.
- `APP_ENV=testing` sudah ada.
- Tidak perlu menambahkan `DB_HOST`, `DB_DATABASE`, dll ke `phpunit.xml` — cukup via `.env.testing`.

### 7.3 Perubahan pada `TestEnvironmentGuard.php` — Tidak Diperlukan

Guard sudah lengkap dan benar. Tidak ada perubahan yang diperlukan.

### 7.4 Perubahan pada `TestCase.php` — Tidak Diperlukan

Sudah memanggil guard di `createApplication()` dan `setUp()`. Tidak ada perubahan.

---

## 8. Checklist Kesiapan Sebelum `.env.testing` Dibuat

| Prasyarat | Status |
|---|---|
| Server PostgreSQL lokal aktif di `127.0.0.1:5432` | ✅ Terverifikasi (Step 4D.11.3) |
| Database `aquatara_test` sudah dibuat | ✅ Dikonfirmasi manual oleh pengguna (Step 4D.11.4) |
| Role `aquatara_test_user` sudah dibuat | ✅ Dikonfirmasi manual oleh pengguna (Step 4D.11.4) |
| `.env.testing` dikecualikan di `.gitignore` | ✅ Baris 6 `backend/.gitignore` |
| `DB_URL` dikunci kosong di `phpunit.xml` | ✅ `force=true` |
| `TestEnvironmentGuard` aktif dan benar | ✅ Terverifikasi audit |
| `APP_KEY` untuk environment testing | ⚠️ Perlu di-generate (`artisan key:generate --env=testing`) |

---

## 9. Status Akhir

### ✅ AUDIT SELESAI — Tidak Ada File yang Diubah

**Temuan kunci:**
1. **Guard bekerja benar** — akan memblokir test suite saat ini (tanpa `.env.testing`) karena `DB_HOST` di `.env` mengarah ke Supabase. Ini adalah perilaku yang **diinginkan**.
2. **`.env.testing` diperlukan** — merupakan satu-satunya perubahan yang diperlukan untuk mengaktifkan testing lokal.
3. **`phpunit.xml` sudah benar** — tidak perlu diubah.
4. **Celah ALL-connections:** `DB_HOST` harus di-set ke `127.0.0.1` di `.env.testing` agar guard melewati semua named connections (`mysql`, `mariadb`, `sqlsrv`) yang semuanya menggunakan `DB_HOST`.
5. **`APP_KEY` testing** belum di-generate — diperlukan sebelum artisan/PHPUnit bisa berjalan.

> **Pemberhentian Sesuai Batasan:**
> Step 4D.11.5 selesai dan berhenti di sini. Tidak ada file yang diubah.
> Rencana di atas siap untuk ditinjau dan disetujui sebelum `.env.testing` dibuat pada step berikutnya.
