# LAPORAN STEP 4D.35D: CONTROLLED REAL LARAVEL–FLASK INTEGRATION TEST

**Tanggal Pengujian:** 2026-10-12 13:49 WIB  
**Auditor:** Autonomous Agent  
**Status Pengujian:** STOPPED WITH FINDING (FAIL — TYPE MISMATCH GAP)  
**Tujuan:** Membuktikan satu jalur integrasi fisik nyata Laravel ➔ Flask ➔ Laravel dengan HTTP socket riil dan pembuktian atomisitas transaksi.

---

## 1. Ringkasan Eksekutif Hasil Pengujian

| Komponen Evaluasi | Status | Bukti / Catatan Singkat |
|:---|:---:|:---|
| **A. Preflight Keamanan & Guard** | **PASS** | Target terverifikasi `127.0.0.1:5432/aquatara_test`. `DB_URL` dipaksa kosong di `phpunit.xml`. Port 5000 bersih sebelum pengujian. |
| **B. Startup Server Flask Lokal** | **PASS** | Dijalankan dari `ml/` dengan PID `32480`. Log binding: `* Running on http://127.0.0.1:5000`. |
| **C. Eksekusi Test Integrasi Nyata** | **FAIL** | Test `RealFlaskIntegrationTest.php` memicu HTTP POST riil ke Flask. Flask merespons `HTTP 400 VALIDATION_ERROR` karena PostgreSQL Eloquent me-return kolom numerik/decimal sebagai string PHP. |
| **D. Penghentian Terkontrol & Teardown Port** | **PASS** | Server Flask PID 32480 segera dihentikan via `manage_task(kill)`. Verifikasi `netstat` membuktikan port 5000 kembali bersih. |
| **E. Pembuktian Rollback & Zero Residual** | **PASS** | Transaksi dibatalkan (rollback) karena kegagalan HTTP 400 memicu Exception sebelum DB write. Tidak ada data residual yang tersisa di `aquatara_test`. |
| **F. Kepatuhan Zero Remote / Supabase** | **PASS** | Guard aktif. Tidak ada akses ke Supabase atau database remote. |

---

## 2. Rincian Eksekusi PHPUnit Aktual

### Output PHPUnit
```json
{
  "tool": "phpunit",
  "result": "failed",
  "tests": 1,
  "passed": 0,
  "assertions": 0,
  "duration_ms": 665,
  "errors": 1,
  "error_details": [
    {
      "test": "Tests\\Feature\\RealFlaskIntegrationTest::test_real_flask_ml_analysis_pipeline_execution_and_persistence",
      "file": "E:\\TUGAS\\pengkoding\\anti\\aqua-tara\\backend\\tests\\Feature\\RealFlaskIntegrationTest.php",
      "line": 77,
      "message": "ML Service returned HTTP 400: {\"error\":{\"code\":\"VALIDATION_ERROR\",\"details\":[\"citizen_reports[0]: 'latitude' must be numeric\",\"citizen_reports[0]: 'longitude' must be numeric\",\"citizen_reports[1]: 'latitude' must be numeric\",\"citizen_reports[1]: 'longitude' must be numeric\",\"iot_data[0]: 'latitude' must be numeric\",\"iot_data[0]: 'longitude' must be numeric\",\"iot_data[0]: 'turbidity' must be numeric\",\"iot_data[0]: 'flow_rate' must be numeric\",\"iot_data[0]: 'pressure' must be numeric\"],\"message\":\"Request validation failed.\"},\"success\":false}",
      "trace": [
        "E:\\TUGAS\\pengkoding\\anti\\aqua-tara\\backend\\app\\Services\\MLAnalysisService.php:44",
        "E:\\TUGAS\\pengkoding\\anti\\aqua-tara\\backend\\tests\\Feature\\RealFlaskIntegrationTest.php:77"
      ]
    }
  ]
}
```

### Log Server Flask (Runtime Riil)
```text
 * Serving Flask app 'app:create_app'
 * Debug mode: off
WARNING: This is a development server. Do not use it in a production deployment. Use a production WSGI server instead.
 * Running on http://127.0.0.1:5000
Press CTRL+C to quit
127.0.0.1 - - [12/Oct/2026 13:49:12] "POST /internal-ml/analyze HTTP/1.1" 400 -
```

---

## 3. Analisis Akar Masalah (Root Cause Analysis)

### Mengapa Mock Sebelumnya Lolos, tetapi Integrasi Nyata Gagal?
1. **Perilaku Mock pada STEP 4D.27 & 4D.29:**
   - Seluruh test sebelumnya menggunakan `Http::fake()`, di mana Laravel tidak pernah mengirimkan serialisasi payload sesungguhnya ke server Python dan hanya mengembalikan response mock yang sudah disiapkan.
2. **Serialisasi PostgreSQL PDO di Laravel:**
   - Di [backend/app/Models/Report.php:49-50](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/Report.php#L49-L50), kolom `latitude` dan `longitude` di-cast sebagai `'decimal:8'`. Di Laravel Eloquent, tipe cast `decimal` mengembalikan string numerik (misal `"-6.91750000"`) untuk mempertahankan presisi desimal tanpa floating point rounding issue.
   - Demikian pula, `DB::select()` pada [MLAnalysisService.php:102-115](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Services/MLAnalysisService.php#L102-L115) mengembalikan data mentah dari driver PDO PostgreSQL di mana tipe numerik Postgres dikembalikan sebagai string PHP.
3. **Validasi Tipe Data Python di Flask Schema:**
   - Di [ml/app/schemas/analyze.py:53-54](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/app/schemas/analyze.py#L53-L54) dan baris 76–77:
     ```python
     if not isinstance(report[coord], (int, float)):
         errors.append(f"{prefix}: '{coord}' must be numeric")
     ```
   - Schema Python memeriksa `isinstance(val, (int, float))`. Karena data dari JSON Laravel adalah `string` (misal `"latitude": "-6.91750000"`), validasi Python menolak payload dengan kode HTTP 400.

---

## 4. Pembuktian Atomisitas Database & Rollback

- **Boundary Transaksi:**
  - Di [backend/app/Services/MLAnalysisService.php:38-45](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Services/MLAnalysisService.php#L38-L45), ketika `$response->successful()` bernilai false, Laravel melempar:
    ```php
    throw new Exception("ML Service returned HTTP " . $response->status() . ": " . $response->body());
    ```
  - Exception ini terjadi **sebelum** blok `DB::transaction()` di baris 53 dieksekusi.
  - Selain itu, test method menggunakan trait `DatabaseTransactions`, sehingga seluruh fixture sintetis yang disisipkan (`Report`, `Ticket`, `IoTNode`, `IoTMeasurement`) langsung di-rollback oleh PHPUnit.
- **Hasil:**
  - Tidak ada data rusak atau tabel kotor di database `aquatara_test`.
  - Tabel `ml_analyses`, `risk_results`, dan `clusters` tetap bersih.

---

## 5. Teardown dan Pembersihan Lingkungan

1. **Penghentian Server Flask:**
   - Proses PID 32480 dihentikan seketika via `manage_task(kill)`.
2. **Status Port 5000 Pasca-Test:**
   - Status `LISTENING` telah dilepaskan dan bersih (hanya socket `TIME_WAIT` sesaat).
3. **Kepatuhan Batasan:**
   - Sesuai kriteria instruksi: *"STOP jika isolasi atau rollback tidak dapat dibuktikan"* dan *"Jangan memperbaiki kode dan jangan lanjut menjalankan integrasi nyata"*.
   - Tidak ada modifikasi kode pada `MLAnalysisService.php` atau `analyze.py` yang dilakukan pada tahap ini.

---

## 6. Kesimpulan & Rekomendasi

### Kesimpulan
Uji coba integrasi nyata berhasil membuktikan bahwa koneksi socket HTTP antara Laravel dan Flask berfungsi secara fisik (port 5000 menerima request dan merespons). Namun, ditemukan **type mismatch contract gap**: Laravel mengirimkan representasi desimal database sebagai string, sedangkan schema Flask mewajibkan tipe data native `float`/`int`.

### Rekomendasi Perbaikan untuk Tahap Selanjutnya (Menunggu Persetujuan)
1. **Opsi A (Di sisi Laravel):** Lakukan float casting eksplisit pada `buildDataset()` di `MLAnalysisService.php` sebelum `$payload` dikirimkan ke HTTP client:
   - `floatval($report->latitude)`, `floatval($report->longitude)`
   - `floatval($measurement->turbidity)`, dst.
2. **Opsi B (Di sisi Flask):** Perbarui `ml/app/schemas/analyze.py` agar mengizinkan string numerik (`str.replace('.', '', 1).isdigit()`) atau melakukan konversi otomatis `float(val)`.

**STATUS: STOPPED WITH FINDING — Menunggu instruksi pengguna.**
