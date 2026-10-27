# LAPORAN STEP 4D.32: ML URL CONFIGURATION AUDIT (READ-ONLY)

**Tanggal Audit:** 2026-10-12 13:21 WIB  
**Auditor:** Autonomous Agent  
**Status Audit:** READ-ONLY COMPLETED  
**Prinsip Batasan:** Tidak mengubah file apa pun, tidak menjalankan test/server/worker/command, tidak mengakses database (lokal maupun remote), tidak menampilkan secret/kredensial, dan tidak memasang dependensi atau koneksi jaringan.

---

## 1. Ringkasan Eksekutif Hasil Pemeriksaan

| ID | Item Pemeriksaan | Status | Ringkasan Penilaian |
|:---|:---|:---:|:---|
| **A** | Peninjauan `MLAnalysisService.php` & `config/services.php` | **PASS** | `MLAnalysisService.php:35` memanggil `config('services.ml.url', 'http://127.0.0.1:5000')`. Kunci `'ml'` belum didaftarkan di `config/services.php`. |
| **B** | Konfigurasi Environment & Fallback Terkait ML | **PASS** | Tidak ada variabel environment `ML_*` yang tercatat di `.env`, `.env.testing`, maupun `.env.example`. Nilai fallback hardcoded ke `'http://127.0.0.1:5000'`. |
| **C** | Ketersediaan Configuration Cache | **PASS** | Diperiksa di `bootstrap/cache/`: file `config.php` **TIDAK ADA** (tidak di-cache). Perubahan file konfigurasi akan langsung berlaku di runtime. |
| **D** | Penelusuran Semua Referensi URL ML di Backend & Flask | **PASS** | Seluruh referensi URL/path di Laravel dan Flask konsisten mengarah ke path `/internal-ml/analyze`. |
| **E** | Konsistensi Endpoint & Path Request | **PASS** | Path Laravel: `{base_url}/internal-ml/analyze`. Path Flask: `@analyze_bp.route("/internal-ml/analyze", methods=["POST"])`. Sinkron 100%. |
| **F** | Rencana Perubahan Minimal (Tanpa Menerapkan) | **PASS** | Dirumuskan perubahan deklaratif minimal 4 baris di `config/services.php` dan 1 baris di `.env.example` / `.env.testing`. |
| **G** | Rencana Verifikasi Statis Tanpa Test/DB | **PASS** | Prosedur verifikasi statis murni dirancang menggunakan script CLI read-only / PHP syntax lint tanpa memicu koneksi DB atau bootstrap testing. |

---

## 2. Rincian Temuan & Bukti Audit

### A. Pembacaan `MLAnalysisService.php` dan `config/services.php`
- **Lokasi Pemanggilan:**
  - File: [backend/app/Services/MLAnalysisService.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Services/MLAnalysisService.php#L34-L36)
  - Baris 34–35:
    ```php
    // Flask is assumed to run on port 5000 internally.
    $flaskUrl = config('services.ml.url', 'http://127.0.0.1:5000') . '/internal-ml/analyze';
    ```
- **Kondisi `config/services.php`:**
  - File: [backend/config/services.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/config/services.php#L1-L38)
  - Berisi konfigurasi layanan pihak ketiga bawaan (`postmark`, `resend`, `ses`, `slack`).
  - **Temuan:** Kunci `'ml'` **tidak ada** di dalam array konfigurasi ini. Fungsi `config('services.ml.url', ...)` mengembalikan nilai default fallback `'http://127.0.0.1:5000'` karena kunci tidak ditemukan.

---

### B. Konfigurasi Environment Terkait ML
- **Pemeriksaan File Environment:**
  - `backend/.env`: Tidak ada variabel dengan nama `ML_URL`, `ML_SERVICE_URL`, atau awalan `ML_`.
  - `backend/.env.testing`: Tidak ada variabel terkait `ML_`.
  - `backend/.env.example`: Tidak ada variabel terkait `ML_`.
- **Variabel yang Dibaca Kode:**
  - Kode tidak langsung membaca `env()` melainkan memanggil `config('services.ml.url')`.
  - Karena `services.php` belum membaca variabel environment apa pun untuk kunci `ml.url`, tidak ada mekanisme bagi user/operator untuk mengarahkan port/host Flask tanpa mengubah kode `MLAnalysisService.php` kecuali `services.php` dikonfigurasi.

---

### C. Status Configuration Cache
- **Pemeriksaan Direktori Cache:**
  - Lokasi: `backend/bootstrap/cache/`
  - Isi direktori:
    - `.gitignore`
    - `packages.php` (daftar paket Laravel auto-discovery)
    - `services.php` (daftar provider yang di-cache)
  - **File `config.php` TIDAK ADA.**
- **Implikasi:**
  - Laravel sedang berjalan dalam mode konfigurasi dinamis (non-cached configuration).
  - Ketika perubahan diterapkan pada `config/services.php`, perubahan tersebut akan langsung dibaca oleh framework tanpa perlu menjalankan `php artisan config:clear`.

---

### D & E. Penelusuran Referensi URL & Konsistensi Endpoint
- **Referensi di Sisi Laravel:**
  - `backend/app/Services/MLAnalysisService.php` baris 35:
    `config('services.ml.url', 'http://127.0.0.1:5000') . '/internal-ml/analyze'`
  - `backend/tests/Feature/MLAnalysisIntegrationTest.php`:
    - Baris 129: `'*/internal-ml/analyze' => Http::response(...)`
    - Baris 172: `'*/internal-ml/analyze' => Http::response(null, 500)`
    - Baris 187, 209, 231: `'*/internal-ml/analyze' => Http::response(...)`
- **Referensi di Sisi Python Flask:**
  - `ml/app/routes/analyze.py` baris 28:
    `@analyze_bp.route("/internal-ml/analyze", methods=["POST"])`
  - `ml/tests/test_analyze.py` baris 23, 36, 50, 63, 77:
    `client.post("/internal-ml/analyze", json=payload)`
  - `ml/tests/test_production_algorithm.py` baris 270:
    `client.post("/internal-ml/analyze", json=payload)`
- **Penilaian Konsistensi:**
  - Base path: default `http://127.0.0.1:5000`
  - Request path: `/internal-ml/analyze`
  - Method: `POST`
  - Content-Type: `application/json`
  - **Status:** Konsisten 100% antar-komponen.

---

### F. Rencana Perubahan Minimal (Usulan Tanpa Menerapkan)

Untuk memungkinkan pengoperasian URL Flask secara dinamis melalui `.env` tanpa merusak arsitektur yang ada:

#### 1. Perubahan pada `backend/config/services.php`
Tambahkan entri `'ml'` ke dalam array konfigurasi:
```php
    'ml' => [
        'url' => env('ML_SERVICE_URL', 'http://127.0.0.1:5000'),
    ],
```
*Mengapa `ML_SERVICE_URL`?*  
Nama ini deskriptif, spesifik untuk servis ML, dan menghindari tabrakan penamaan dengan variabel URL lain.

#### 2. Perubahan pada `backend/.env.example`
Tambahkan dokumentasi variabel:
```env
ML_SERVICE_URL=http://127.0.0.1:5000
```

#### 3. Perubahan pada `backend/.env.testing` (Opsional/Jika Diperlukan)
Jika pengujian ingin menargetkan port tertentu:
```env
ML_SERVICE_URL=http://127.0.0.1:5000
```

#### 4. Kode `MLAnalysisService.php`
Kode di `backend/app/Services/MLAnalysisService.php` baris 35:
```php
$flaskUrl = config('services.ml.url', 'http://127.0.0.1:5000') . '/internal-ml/analyze';
```
**TIDAK PERLU DIUBAH SAMA SEKALI.** Kode ini sudah memanggil `config('services.ml.url')` dengan fallback yang tepat.

---

### G. Metodologi Verifikasi Statis (Tanpa Test & Tanpa Akses DB)

Bagaimana usulan perubahan di atas dapat diverifikasi setelah nanti diterapkan, tanpa menyentuh database atau mengeksekusi test suite:

1. **Pemeriksaan Sintaks PHP (Linting):**
   ```powershell
   php -l backend/config/services.php
   ```
   *Tujuan:* Memastikan tidak ada syntax error (koma, tanda kurung) yang merusak file config.

2. **Pemeriksaan Evaluasi Nilai Config secara Statis (In-Memory Isolation):**
   Jalankan script evaluasi array PHP murni secara lokal tanpa bootstrap Laravel:
   ```powershell
   php -r "$cfg = include 'backend/config/services.php'; echo isset($cfg['ml']['url']) ? 'CONFIG_ML_OK: ' . $cfg['ml']['url'] : 'CONFIG_ML_MISSING';"
   ```
   *Tujuan:* Membuktikan array memuat kunci `'ml']['url'` dan fallback berfungsi ketika `env('ML_SERVICE_URL')` tidak ada, tanpa menginisialisasi database connection atau application service providers.

3. **Pemeriksaan Git Hygiene:**
   ```powershell
   git diff backend/config/services.php
   ```
   *Tujuan:* Memastikan perubahan hanya berupa penambahan 3–4 baris array tanpa perubahan tidak diinginkan pada baris lain.

---

## 3. Risiko yang Diidentifikasi

1. **Trailing Slash pada URL:**  
   Jika pengguna menetapkan `ML_SERVICE_URL=http://127.0.0.1:5000/` (dengan tanda garis miring di akhir), penggabungan string `$flaskUrl . '/internal-ml/analyze'` akan menghasilkan double slash: `http://127.0.0.1:5000//internal-ml/analyze`.  
   *Mitigasi:* Menggunakan `rtrim(config('services.ml.url', 'http://127.0.0.1:5000'), '/') . '/internal-ml/analyze'` jika ingin lebih defensif, atau mendokumentasikan format URL tanpa trailing slash.
2. **Ketiadaan Config Cache vs Lingkungan Produksi:**  
   Saat ini tidak ada `config.php` di cache. Jika kelak di lingkungan produksi dijalankan `php artisan config:cache`, pemanggilan `env()` di luar file `config/` akan mengembalikan `null`. Penempatan `env()` di dalam `config/services.php` justru menyelesaikan masalah ini dengan benar sesuai best practice Laravel.

---

## 4. Kesimpulan & Status Akhir

- Audit konfigurasi URL ML selesai dengan status **PASS**.
- Tidak ada file yang diubah.
- Tidak ada test, server, atau database yang diakses.
- Rekomendasi perubahan minimal bersifat terisolasi dan non-destruktif.
- **STOP & MENUNGGU INSTRUKSI PENGGUNA.**
