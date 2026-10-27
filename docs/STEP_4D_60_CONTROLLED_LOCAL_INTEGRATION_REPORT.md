# STEP 4D.60 — CONTROLLED LOCAL INTEGRATION REPORT

**Status:** **PASSED (CONTROLLED INTEGRATION TEST SUCCESSFUL)**  
**Tanggal:** 2026-10-12  
**Target Pengujian:** Integrasi nyata Frontend `Tracking.vue` dengan Backend Laravel lokal (`ReportController@show`) secara terkontrol  
**Target Host Backend:** `http://localhost:8000` (Laravel local dev server, `--env=testing`)  
**Target Host Frontend:** `http://localhost:5173` (Vite dev server)  
**Database Backend:** PostgreSQL Lokal `aquatara_test` pada `127.0.0.1:5432` (Isolasi 100% tanpa akses remote/Supabase)

---

## 1. Ringkasan Eksekutif

Pada **STEP 4D.60**, telah dilakukan pengujian integrasi nyata dan terkontrol antara komponen frontend pelacakan tiket ([Tracking.vue](file:///e:/TUGAS/pengkoding/anti/aqua-tara/frontend/src/pages/user/Tracking.vue)) dengan backend Laravel ([ReportController@show](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L127)).

Seluruh tahapan dipatuhi secara ketat sesuai protokol:
1. **Tahap 1 (Preflight):** Analisis statis membuktikan bahwa runtime `php artisan serve` tanpa parameter membaca `backend/.env` (Supabase). Namun penggunaan flag `--env=testing` meneruskan `APP_ENV=testing` ke worker HTTP Laravel, memuat file [backend/.env.testing](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/.env.testing) dan mengarahkan koneksi ke database lokal `aquatara_test` di `127.0.0.1:5432` dengan `DB_URL` kosong. Route `GET /api/tickets/{ticket}` hanya menggunakan middleware `api` tanpa session atau auth middleware.
2. **Tahap 2 (Rencana Uji Terkendali):** Memastikan tidak ada sentuhan ke Supabase, tidak ada modifikasi kode backend, tidak ada migrasi ulang, dan menggunakan 1 data tiket sintetis lokal pada database pengujian `aquatara_test`.
3. **Tahap 3 (Persetujuan & Eksekusi Terbatas):** Persetujuan eksplisit pengguna diperoleh sebelum menyalakan server lokal dan memicu akses database lokal.
4. **Verifikasi Live Browser & cURL:** Berhasil menguji tiket valid (HTTP 200) dan tiket tidak ditemukan (HTTP 404). Frontend terverifikasi mengirim request ke `http://localhost:8000`, bukan `localhost:4000`.

---

## 2. Hasil Audit Preflight & Mitigasi Risiko Konfigurasi

| Komponen Audit | Temuan Aktual | Evaluasi & Mitigasi |
|---|---|---|
| **Default `backend/.env`** | `DB_HOST=aws-0-ap-southeast-2.pooler.supabase.com`, `DB_DATABASE=postgres` | **Berisiko tinggi jika server dijalankan default.** Mitigasi: Wajib menyalakan server dengan argumen eksplisit `--env=testing`. |
| **Pemuatan Environment Laravel** | Laravel `ServeCommand` memasukkan `APP_ENV` ke `$passthroughVariables`. `LoadEnvironmentVariables::checkForSpecificEnvironmentFile()` mendeteksi `APP_ENV=testing` dan memuat `.env.testing`. | **Aman & Terbukti.** Konfigurasi aktif: Host `127.0.0.1`, DB `aquatara_test`, Port `5432`, `DB_URL=""`. |
| **Cakupan `TestEnvironmentGuard`** | Hanya dipanggil di harness PHPUnit `Tests\TestCase`. Tidak terpasang di runtime HTTP `public/index.php`. | Divalidasi via preflight bootstrap check CLI bahwa environment yang di-boot adalah `aquatara_test` lokal sebelum server dinyalakan. |
| **Route Middleware** | `GET /api/tickets/{ticket}` hanya memiliki middleware group `api`. | Group `api` tidak memuat `StartSession`, `Authenticate`, atau middleware database lainnya. Tidak ada query DB saat bootstrap route. |
| **Service Providers** | [AppServiceProvider.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Providers/AppServiceProvider.php) memiliki method `register()` dan `boot()` kosong. | Tidak ada pemanggilan model atau query database pada level provider. |

---

## 3. Data Tiket Sintetis Lokal

Pada database lokal `aquatara_test` (`127.0.0.1:5432`), disiapkan 1 record fixture sintetis terkontrol melalui perintah artisan tinker lokal (tanpa menyentuh Supabase):
- **Report ID:** Di-generate otomatis di lokal
- **Fixture Ticket ID:** `55`
- **Status Kanonikal:** `IN_PROGRESS`
- **Display Status Sesuai Pemetaan:** `Sedang Ditangani`
- **Deskripsi:** *"Laporan uji integrasi lokal frontend-backend"*
- **ID Uji Non-Existent:** `99999` (Untuk pengujian skenario 404)

---

## 4. Eksekusi Pengujian & Bukti Nyata

### A. Pengujian HTTP Langsung (cURL / Network Protocol)

#### 1. Skenario Tiket Valid (#55) — HTTP 200 OK
**Request:**
```http
GET /api/tickets/55 HTTP/1.1
Host: localhost:8000
Accept: application/json
```
**Respons Aktual:**
```http
HTTP/1.1 200 OK
Host: localhost:8000
Connection: close
X-Powered-By: PHP/8.3.16
Cache-Control: no-cache, private
Date: Mon, 12 Oct 2026 15:37:08 GMT
Content-Type: application/json
Access-Control-Allow-Origin: *

{"success":true,"data":{"ticket_id":55,"status":"IN_PROGRESS","display_status":"Sedang Ditangani","created_at":"2026-10-12T15:36:23.000000Z","updated_at":"2026-10-12T15:36:23.000000Z"}}
```

#### 2. Skenario Tiket Tidak Ada (#99999) — HTTP 404 Not Found
**Request:**
```http
GET /api/tickets/99999 HTTP/1.1
Host: localhost:8000
Accept: application/json
```
**Respons Aktual:**
```http
HTTP/1.1 404 Not Found
Host: localhost:8000
Connection: close
X-Powered-By: PHP/8.3.16
Cache-Control: no-cache, private
Date: Mon, 12 Oct 2026 15:37:13 GMT
Content-Type: application/json
Access-Control-Allow-Origin: *

{"success":false,"message":"Tiket tidak ditemukan."}
```

---

### B. Pengujian Nyata Browser Frontend (`Tracking.vue`)

Pengujian dilakukan menggunakan browser subagent otomatis pada URL `http://localhost:5173/dev-user/tracking`.

#### 1. Skenario Tiket Valid (#55)
- **Input:** Pengguna memasukkan `55` dan menekan tombol **"Lacak Laporan"**.
- **Network Call:** Browser mengirim request `GET http://localhost:8000/api/tickets/55` (**bukan** `http://localhost:4000`).
- **HTTP Status:** `200 OK` (waktu pemrosesan Laravel ~508 ms).
- **Hasil Render UI:**
  - Header: `ID Laporan: #55`
  - Badge Status: `Sedang Ditangani` (warna biru `bg-blue-50 text-blue-700`)
  - Stepper Progres: Step 1 (*Laporan Diterima*), Step 2 (*Sedang Ditinjau*), Step 3 (*Laporan Dikonfirmasi*), Step 4 (*Sedang Ditangani*) aktif.
  - Tanggal/Waktu: Terformat rapi dari timestamp aktual database lokal.

#### 2. Skenario Tiket Tidak Ditemukan (#99999)
- **Input:** Pengguna memasukkan `99999` dan menekan tombol **"Lacak Laporan"**.
- **Network Call:** Browser mengirim request `GET http://localhost:8000/api/tickets/99999`.
- **HTTP Status:** `404 Not Found` (waktu pemrosesan Laravel ~505 ms).
- **Hasil Render UI:**
  - Kartu detail laporan sebelumnya otomatis disembunyikan/dibersihkan.
  - Menampilkan kotak pesan error merah:
    - Judul: *Pelacakan Gagal*
    - Pesan: *Tiket tidak ditemukan.* (tepat sesuai pesan kontrak backend `ReportController`).

---

## 5. Log Server Laravel (`php artisan serve --env=testing`)

Bukti log penerimaan request nyata pada server backend lokal:
```text
INFO  Server running on [http://127.0.0.1:8000].  
2026-10-12 22:37:08 /api/tickets/55 .................................................................... ~ 514.48ms
2026-10-12 22:37:13 /api/tickets/99999 ................................................................. ~ 502.34ms
2026-10-12 22:38:49 /api/tickets/55 .................................................................... ~ 508.95ms
2026-10-12 22:39:19 /api/tickets/99999 ................................................................. ~ 505.73ms
```

---

## 6. Bukti Artefak Rekaman Browser

Artefak sesi browser telah disimpan secara otomatis:
- **Video Rekaman Sesi WebP:** `C:\Users\Sahab\.gemini\antigravity-ide\brain\fa1e6d5e-3292-4672-a538-c3f1293b1929\tracking_test_1791819511195.webp`
- **Status Server Setelah Pengujian:** Seluruh proses server latar belakang (`php artisan serve` dan `vite`) telah dimatikan secara bersih (*gracefully killed*) untuk menjaga kebersihan sistem lokal.

---

## 7. Batasan & Bagian yang Belum Diverifikasi

1. **Endpoint Frontend Lain:**
   - Endpoint lain seperti `POST /api/reports` (kirim laporan baru) dan `GET /api/map/public` belum diuji secara integrasi live di step ini (fokus STEP 4D.60 strictly pada `Tracking.vue`).
2. **Koneksi Database Remote / Supabase:**
   - Database Supabase sama sekali tidak tersentuh (100% isolasi lokal dipertahankan).
3. **Autentikasi Citizen:**
   - Sesuai spesifikasi, pelacakan tiket publik mendukung pelaporan anonim tanpa bearer token. Integrasi endpoint berotentikasi (admin / citizen logged-in) adalah lingkup fase tersendiri.

---

## 8. Kesimpulan

Integrasi nyata frontend `Tracking.vue` dengan backend Laravel lokal **SUKSES 100%**:
- Konfigurasi `VITE_API_URL` / fallback `http://localhost:8000` di [client.js](file:///e:/TUGAS/pengkoding/anti/aqua-tara/frontend/src/api/client.js) terbukti akurat dan tidak lagi memanggil server mock `4000`.
- Kontrak respons backend [ReportController@show](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L127) diparsing secara sempurna oleh frontend (status kanonikal `IN_PROGRESS` ditampilkan sebagai `Sedang Ditangani`).
- Penanganan error 404 dari backend tertangkap dengan tepat dan disajikan secara ramah pengguna di UI.
