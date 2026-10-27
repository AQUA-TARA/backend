# LAPORAN STEP 4D.35F: CONTROLLED REAL INTEGRATION RETEST

**Tanggal Pengujian:** 2026-10-12 14:00 WIB  
**Auditor:** Autonomous Agent  
**Hasil Akhir:** **BERHASIL PENUH (PASS)**  
**Tujuan:** Memvalidasi perbaikan *type mismatch* (STEP 4D.35E) dan mengeksekusi satu kali uji integrasi nyata fisik Laravel ➔ Flask ➔ Laravel dengan socket HTTP nyata dan pembuktian rollback transaksi database.

---

## 1. Ringkasan Eksekutif Hasil Pengujian

| Evaluasi | Status | Bukti / Ringkasan Temuan |
|:---|:---:|:---|
| **FASE 1: Review & Pemeriksaan Statis** | **PASS** | `MLAnalysisService.php:94-144` menormalisasi nilai numerik secara eksplisit: float untuk koordinat/metrik, int untuk ID, mempertahankan `null` asli, tanpa mengubah logika scoring atau status tiket. |
| **FASE 2.1: Kunci Keamanan & Guard Target** | **PASS** | Target terverifikasi `127.0.0.1:5432/aquatara_test` (role `aquatara_test_user`). `DB_URL` dipaksa kosong di `phpunit.xml`. Guard `TestEnvironmentGuard` aktif pada lifecycle awal. Nol koneksi ke Supabase. |
| **FASE 2.2: Startup Server Flask Lokal** | **PASS** | Server dijalankan dari `ml/` dengan PID `15608`. Log binding: `* Running on http://127.0.0.1:5000`. Netstat mengonfirmasi port 5000 berstatus LISTENING. |
| **FASE 2.3 & 2.4: Eksekusi Test Nyata & Respons HTTP** | **PASS** | `vendor\bin\phpunit.bat tests\Feature\RealFlaskIntegrationTest.php` **1 test, 19 assertions PASSED** (3.318s). Log Flask mencatat HTTP POST nyata: `"POST /internal-ml/analyze HTTP/1.1" 200 -`. Tanpa `Http::fake()`. |
| **FASE 2.5: Atomisitas & Rollback Transaksi** | **PASS** | Trait `DatabaseTransactions` berhasil membungkus fixture sintetis dan record hasil ML. Seluruh baris uji di-rollback secara otomatis saat teardown. |
| **FASE 2.6: Teardown Server Flask & Pelepasan Port** | **PASS** | Server Flask PID 15608 dihentikan via `manage_task(kill)`. Verifikasi `netstat` pasca-test membuktikan port 5000 kembali kosong. |

---

## 2. Rincian FASE 1: Review Kode & Audit Statis

### A. Evaluasi Perubahan pada `backend/app/Services/MLAnalysisService.php`
- **Method `buildDataset()`:**
  1. **Citizen Reports:**
     ```php
     $reportData[] = [
         'report_id' => is_numeric($report->report_id) ? (int) $report->report_id : $report->report_id,
         'latitude' => $report->latitude !== null && is_numeric($report->latitude) ? (float) $report->latitude : $report->latitude,
         'longitude' => $report->longitude !== null && is_numeric($report->longitude) ? (float) $report->longitude : $report->longitude,
     ];
     ```
  2. **IoT Data:**
     ```php
     $iotData[] = [
         'node_id' => is_numeric($m['node_id'] ?? null) ? (int) $m['node_id'] : ($m['node_id'] ?? null),
         'latitude' => isset($m['latitude']) && is_numeric($m['latitude']) ? (float) $m['latitude'] : ($m['latitude'] ?? null),
         'longitude' => isset($m['longitude']) && is_numeric($m['longitude']) ? (float) $m['longitude'] : ($m['longitude'] ?? null),
         'turbidity' => isset($m['turbidity']) && is_numeric($m['turbidity']) ? (float) $m['turbidity'] : ($m['turbidity'] ?? null),
         'flow_rate' => isset($m['flow_rate']) && is_numeric($m['flow_rate']) ? (float) $m['flow_rate'] : ($m['flow_rate'] ?? null),
         'pressure' => isset($m['pressure']) && is_numeric($m['pressure']) ? (float) $m['pressure'] : ($m['pressure'] ?? null),
         'measured_at' => $m['measured_at'] ?? null,
     ];
     ```
- **Kepatuhan Terhadap Batasan:**
  - `null` tetap bernilai `null`, tidak diam-diam berubah menjadi `0` atau `0.0`.
  - Serialisasi JSON menghasilkan native JSON number (misal `-6.9175` bukan `"-6.91750000"`).
  - Formula perhitungan skor `0.8 * T + 0.2 * S`, bobot, dan threshold tidak disentuh.
  - Skema database dan status tiket tidak dimodifikasi.

---

## 3. Rincian FASE 2: Eksekusi Uji Terkontrol Nyata

### A. Preflight Lingkungan & Database
- `DB_HOST`: `127.0.0.1`
- `DB_PORT`: `5432`
- `DB_DATABASE`: `aquatara_test`
- `DB_USERNAME`: `aquatara_test_user`
- `DB_URL`: Kosong (`""`), dipaksa oleh `phpunit.xml`.
- Guard: `TestEnvironmentGuard::assertSafeEnvironment()` aktif di awal `TestCase::createApplication()`.
- Supabase: Dilarang dan terisolasi total.

### B. Startup Server Flask Lokal
- **Perintah:**
  ```powershell
  # Working Directory: ml/
  python -m flask --app app:create_app run --host 127.0.0.1 --port 5000
  ```
- **PID Proses:** `15608`
- **Log Binding:**
  ```text
   * Serving Flask app 'app:create_app'
   * Debug mode: off
  WARNING: This is a development server. Do not use it in a production deployment. Use a production WSGI server instead.
   * Running on http://127.0.0.1:5000
  Press CTRL+C to quit
  ```
- **Status Port (Netstat):** `TCP 127.0.0.1:5000 LISTENING PID 15608`.

### C. Eksekusi Test Integrasi Nyata via PHPUnit
- **Perintah:**
  ```powershell
  cmd /c "vendor\bin\phpunit.bat tests\Feature\RealFlaskIntegrationTest.php 2>&1"
  ```
- **Output JSON PHPUnit:**
  ```json
  {
    "tool": "phpunit",
    "result": "passed",
    "tests": 1,
    "passed": 1,
    "assertions": 19,
    "duration_ms": 3318
  }
  ```
- **Bukti Permintaan HTTP Nyata (Log Flask Server Aktual):**
  ```text
  127.0.0.1 - - [12/Oct/2026 13:57:58] "POST /internal-ml/analyze HTTP/1.1" 200 -
  ```
  *(Membuktikan bahwa Laravel mengirimkan request jaringan nyata ke port 5000 tanpa menggunakan mock `Http::fake()`, dan Flask berhasil memproses serta merespons dengan HTTP 200 OK).*

### D. Rincian 19 Assertions yang Lulus
1. `assertInstanceOf(MLAnalysis::class, $analysis)`: Laravel menerima dan menginstansiasi model analysis.
2. `assertNotNull($analysis->id)`: Analisis berhasil tersimpan dengan primary key.
3. `assertNotNull($analysis->generated_at)`: Timestamp analisis valid.
4. `assertDatabaseHas('ml_analyses', ['id' => $analysis->id])`: Record analisis tersimpan di tabel `ml_analyses`.
5. `assertDatabaseHas('risk_results', ['report_id' => $report1->id])`: Hasil kalkulasi untuk laporan 1 tersimpan.
6. `assertDatabaseHas('risk_results', ['report_id' => $report2->id])`: Hasil kalkulasi untuk laporan 2 tersimpan.
7. `assertDatabaseHas('clusters', ['report_count' => 2])`: Cluster spasial tersimpan dengan jumlah laporan yang tepat.
8. `assertCount(2, $riskResults)`: Jumlah baris risk results sesuai jumlah laporan yang dikirimkan.
9. `assertGreaterThanOrEqual(0, $rr->risk_score)` & `assertLessThanOrEqual(100, $rr->risk_score)`: Batasan skor 0–100 terpenuhi untuk seluruh laporan.
10. `assertCount(1, $clusters)`: Jumlah cluster sesuai algoritma K-Means untuk 2 laporan berdekatan (K=1).
11. `assertEquals(-6.91775, round($cluster->centroid_lat, 5))`: Koordinat centroid latitude presisi hasil kalkulasi `scikit-learn`.
12. `assertEquals(107.61955, round($cluster->centroid_lng, 5))`: Koordinat centroid longitude presisi hasil kalkulasi `scikit-learn`.

---

## 4. Pembuktian Rollback Transaksi & Kebersihan Database

- **Mekanisme Rollback:**
  - Test `RealFlaskIntegrationTest.php` menggunakan trait `DatabaseTransactions`.
  - Seluruh fixture yang dibuat (`Report`, `Ticket`, `IoTNode`, `IoTMeasurement`) dan record hasil analisis ML (`MLAnalysis`, `RiskResult`, `Cluster`) dibungkus dalam transaksi database tingkat terluar PDO.
  - Saat method `tearDown()` dieksekusi, PDO mengeluarkan perintah `ROLLBACK`.
- **Integritas Database:**
  - Tidak ada data uji sintetis yang tertinggal di tabel `aquatara_test`.
  - Tidak ada operasi `DELETE` manual yang dilakukan.
  - Tidak ada data baseline yang tersentuh atau dimodifikasi.

---

## 5. Teardown Server & Pelepasan Port

1. **Penghentian Proses Flask:**
   - Server task PID 15608 dihentikan segera setelah test selesai via `manage_task(kill)`.
2. **Verifikasi Pelepasan Port (Post-Teardown):**
   ```powershell
   netstat -ano | findstr :5000
   ```
   *Output:*
   *(Output kosong, exit code 1)*
   - Status `LISTENING` pada port 5000 telah dilepaskan dan kembali bersih 100%.

---

## 6. Kesimpulan & Status Akhir

1. Jalur integrasi fisik end-to-end **Laravel ➔ Socket HTTP (port 5000) ➔ Python Flask (scikit-learn K-Means) ➔ Laravel ➔ Persistensi Database (PostgreSQL lokal)** telah **TERBUKTI BERHASIL SECARA FISIK**.
2. Type mismatch telah terselesaikan secara elegan tanpa memodifikasi skema Flask atau melanggar arsitektur decoupling.
3. Seluruh batasan keamanan database (`aquatara_test`, non-Supabase, transaksi rollback) telah dipatuhi secara ketat.

**STATUS: BERHASIL PENUH (PASS) — BERHENTI DAN MENUNGGU INSTRUKSI PENGGUNA.**
