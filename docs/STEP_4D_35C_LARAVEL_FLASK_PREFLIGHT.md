# LAPORAN STEP 4D.35C: LARAVEL-TO-FLASK INTEGRATION PREFLIGHT AUDIT

**Tanggal Audit:** 2026-10-12 13:46 WIB  
**Auditor:** Autonomous Agent  
**Status Audit:** READ-ONLY PREFLIGHT COMPLETED  
**Keputusan Akhir:** **READY FOR CONTROLLED REAL TEST**  
**Prinsip Batasan:** Tidak menjalankan PHPUnit, Artisan, command analisis ML, server Flask, HTTP request, query SQL, migrasi, seeder, atau koneksi database. Tidak mengakses Supabase, tidak membaca/menampilkan rahasia, dan tidak mengubah file apa pun selain laporan ini.

---

## 1. Ringkasan Eksekutif Hasil Preflight

| Area Audit | Status | Bukti / Catatan Singkat |
|:---|:---:|:---|
| **A. Konfigurasi Testing & Guard Lifecycle** | **PASS** | Target terverifikasi `127.0.0.1:5432/aquatara_test`. `DB_URL` dipaksa kosong (`force="true"` di `phpunit.xml:28`). Guard `TestEnvironmentGuard` aktif di `TestCase::createApplication()` sebelum trait database berjalan. Seluruh named connections (`pgsql`, `sqlite`, `mysql`, `mariadb`) diaudit. |
| **B. Jalur HTTP Nyata & Error Handling** | **PASS** | `MLAnalysisService.php:35-45` memanggil `$flaskUrl` via `Http::timeout(10)->post()`. Error jaringan/timeout/non-200 dibungkus ke Exception terkontrol. `RunMLAnalysis.php:40-55` menangkap exception dan mengembalikan `Command::FAILURE`. |
| **C. Mutasi Database & Boundary Transaksi** | **PASS** | Seluruh operasi penulisan (`ml_analyses`, `risk_results`, `clusters`) berada di dalam blok `DB::transaction()` atomik (`MLAnalysisService.php:53-79`). **Status tiket tidak pernah dimutasi**. |
| **D. Evaluasi Mock vs Desain Uji Nyata** | **PASS** | Seluruh test eksisting terbukti menggunakan `Http::fake()`. Dirancang satu test terisolasi dengan trait `DatabaseTransactions` yang menjamin rollback penuh data uji di `aquatara_test`. |
| **E. Stop Conditions & Safety Gates** | **PASS** | Kondisi penghentian darurat didefinisikan secara eksplisit (port tidak responsif, guard trigger, kegagalan koneksi DB lokal). |

---

## 2. Rincian Temuan Preflight

### A. Konfigurasi Testing Efektif, `DB_URL`, Named Connections, & Guard Lifecycle
- **Pengosongan Paksa `DB_URL`:**
  - File: [backend/phpunit.xml:28](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/phpunit.xml#L28)
  - Deklarasi: `<env name="DB_URL" value="" force="true"/>`
  - Bukti: Mengabaikan variabel environment sistem/OS yang berpotensi menyuntikkan connection string Supabase.
- **Konfigurasi Target Testing:**
  - File: `backend/.env.testing`
  - Variabel: `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=aquatara_test`, `DB_USERNAME=aquatara_test_user`.
  - Terverifikasi terabaikan oleh Git (`git check-ignore backend/.env.testing` = 0).
- **Inspeksi Seluruh Named Connections:**
  - File: [backend/config/database.php:36-126](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/config/database.php#L36-L126)
  - `TestEnvironmentGuard::assertSafeEnvironment()` (di [backend/tests/Support/TestEnvironmentGuard.php:25-44](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/Support/TestEnvironmentGuard.php#L25-L44)) mengiterasi **seluruh** array connections (`sqlite`, `mysql`, `mariadb`, `pgsql`).
  - Pemeriksaan D ([TestEnvironmentGuard.php:84-99](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/Support/TestEnvironmentGuard.php#L84-L99)) menolak keras host selain `127.0.0.1`, `localhost`, atau `::1`.
  - Pemeriksaan E ([TestEnvironmentGuard.php:102-108](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/Support/TestEnvironmentGuard.php#L102-L108)) melarang database sistem (`postgres`, `public`, `template0`, dll).
- **Urutan Lifecycle Eksekusi Guard:**
  - File: [backend/tests/TestCase.php:14-31](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/TestCase.php#L14-L31)
  ```text
  PHPUnit Boot
    │
    ▼
  Tests\TestCase::createApplication()
    │
    ├─► parent::createApplication() (Laravel bootstraps config/app)
    │
    ├─► $this->assertSafeTestEnvironment($app) ◄── [GUARD INTERCEPTOR]
    │     (Mengevaluasi seluruh connection & DB_URL SEBELUM trait database berjalan)
    │     (Jika target melanggar -> RuntimeException seketika -> Eksekusi batal)
    │
    ▼
  Laravel setUpTraits()
    │
    ├─► DatabaseTransactions::beginDatabaseTransaction()
    │
    ▼
  Tests\TestCase::setUp()
    │
    └─► $this->assertSafeTestEnvironment($this->app) (Defense-in-depth re-check)
  ```
  - **Hasil:** Guard dijamin 100% berjalan **sebelum** koneksi database dibuka oleh trait pengujian.

---

### B. Jalur HTTP Nyata, Timeout, Error Handling, & Efek Samping
- **Lokasi Pemanggilan Endpoint:**
  - File: [backend/app/Services/MLAnalysisService.php:33-45](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Services/MLAnalysisService.php#L33-L45)
  ```php
  $flaskUrl = config('services.ml.url', 'http://127.0.0.1:5000') . '/internal-ml/analyze';
  
  try {
      $response = Http::timeout(10)->post($flaskUrl, $payload);
  } catch (Exception $e) {
      throw new Exception("ML Service Unavailable: " . $e->getMessage());
  }

  if (!$response->successful()) {
      throw new Exception("ML Service returned HTTP " . $response->status() . ": " . $response->body());
  }
  ```
- **Karakteristik & Efek Samping:**
  - **Timeout:** 10 detik (`Http::timeout(10)`).
  - **Retry:** 0 (gagal koneksi langsung melempar Exception).
  - **Error Handling:** Kegagalan jaringan atau respons 4xx/5xx memicu Exception sebelum blok persistensi database tercapai.
- **Handler Command Artisan:**
  - File: [backend/app/Console/Commands/RunMLAnalysis.php:26-55](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Console/Commands/RunMLAnalysis.php#L26-L55)
  - Jika laporan kosong: `Log::info()` & return `Command::SUCCESS` (skip).
  - Jika terjadi kegagalan HTTP/ML: `Log::error()` & return `Command::FAILURE` (exit code 1).
  - Tidak ada side-effect ke tabel `tickets` atau mutasi state antrean.

---

### C. Tabel yang Ditulis & Batasan Transaksi Database
- **Tabel yang Ditulis oleh `MLAnalysisService`:**
  1. `ml_analyses` ([MLAnalysisService.php:55-57](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Services/MLAnalysisService.php#L55-L57))
  2. `risk_results` ([MLAnalysisService.php:60-66](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Services/MLAnalysisService.php#L60-L66))
  3. `clusters` ([MLAnalysisService.php:69-76](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Services/MLAnalysisService.php#L69-L76))
- **Integritas Transaksi:**
  - Ketiga operasi penulisan di atas dibungkus dalam `DB::transaction(function () { ... })` ([MLAnalysisService.php:53-79](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Services/MLAnalysisService.php#L53-L79)).
  - Jika salah satu penulisan gagal atau response tidak lolos validasi `validateResponse()` di baris 50, seluruh transaksi dibatalkan (rollback).
- **Status Tiket:**
  - Tabel `tickets` **hanya dibaca** (`select` di `buildDataset()` baris 89-92) dan **tidak pernah diubah**.

---

### D. Status Mocking Eksisting vs Desain Uji Nyata Terkontrol

#### 1. Bukti Penggunaan Mock Saat Ini
- Pencarian `Http::fake` pada `backend/tests/Feature/`:
  - `RunMLAnalysisTest.php`: Baris 51, 66 (seluruh uji command menggunakan fake).
  - `MLAnalysisIntegrationTest.php`: Baris 128, 171, 186, 208, 230 (seluruh uji integrasi menggunakan fake).
- **Kesimpulan:** Integrasi fisik Laravel ➔ Flask nyata belum pernah diuji dalam test suite PHPUnit.

#### 2. Desain Uji Nyata Terkontrol (Tanpa Mock)
Untuk menguji integrasi nyata dengan aman:
- **Trait Database Wajib:** `use Illuminate\Foundation\Testing\DatabaseTransactions;`
  - Trait ini membungkus seluruh test method dalam transaksi database tingkat terluar dan mengeksekusi `ROLLBACK` saat `tearDown()`.
  - Data uji dummy (seperti laporan dan pengukuran IoT) yang dibuat untuk pengujian akan terhapus otomatis dari `aquatara_test` begitu pengujian selesai.
- **Penetapan Target HTTP:**
  - **TIDAK** memanggil `Http::fake()`.
  - Membiarkan Laravel mengirim request HTTP POST nyata ke `http://127.0.0.1:5000/internal-ml/analyze`.
- **Verifikasi Assertions:**
  - `$analysis = $this->mlService->runAnalysis();` berhasil mengembalikan instance `MLAnalysis`.
  - Record tersimpan di database dalam scope transaksi (`assertDatabaseHas('ml_analyses', ...)`).
  - Nilai `risk_score` numerik di tabel `risk_results` cocok dengan kalkulasi `scikit-learn` K-Means.

---

### E. Stop Conditions (Kondisi Penghentian Wajib)

Pengujian nyata **HARUS DIBATALKAN / DIHENTIKAN SEKETIKA** jika terjadi salah satu kondisi berikut:
1. **Port 5000 Tidak Responsif:** Server Flask lokal tidak aktif sebelum test dijalankan.
2. **Koneksi Database Non-Lokal Terdeteksi:** Guard memicu exception atau konfigurasi `DB_HOST` berubah dari `127.0.0.1`.
3. **`DB_URL` Tidak Kosong:** Ditemukan nilai string pada `config('database.connections.pgsql.url')`.
4. **Target Database Bukan `aquatara_test`:** Guard menolak karena target mengarah ke nama database lain.
5. **Kegagalan Rollback Database:** Terjadi error fatal pada level PDO yang menggagalkan transaksi `DatabaseTransactions`.

---

## 3. Keputusan Akhir Preflight

### **STATUS: READY FOR CONTROLLED REAL TEST**

**Rekomendasi Langkah Terkontrol Berikutnya (Menunggu Persetujuan Pengguna):**
1. Menjalankan Flask server secara lokal di latar belakang pada direktori `ml/`:
   ```powershell
   cmd /c "python -m flask --app app:create_app run --host 127.0.0.1 --port 5000"
   ```
2. Menyiapkan 1 file test terisolasi (misal `RealFlaskIntegrationTest.php`) dengan `DatabaseTransactions` tanpa `Http::fake()`.
3. Menjalankan test tersebut secara tunggal via PHPUnit terhadap database lokal `aquatara_test`.
4. Menghentikan proses server Flask segera setelah test selesai.

**STOP — Menunggu instruksi pengguna sebelum mengambil tindakan apa pun.**
