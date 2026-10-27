# LAPORAN STEP 4D.31: AUDIT KESIAPAN INTEGRASI FLASK NYATA (READ-ONLY)

**Tanggal Audit:** 2026-10-12 13:18 WIB  
**Auditor:** Autonomous Agent  
**Status Audit:** READ-ONLY COMPLETED  
**Prinsip Batasan:** Tidak menjalankan test (PHPUnit/pytest/artisan test), tidak menjalankan proses server/Flask/Laravel/worker, tidak mengakses database (Supabase maupun lokal), tidak melakukan mutasi kode/konfigurasi, dan tidak membocorkan nilai rahasia.

---

## 1. Ringkasan Eksekutif Hasil Pemeriksaan

| Bagian | Item Pemeriksaan | Status | Ringkasan Penilaian |
|:---|:---|:---:|:---|
| **A** | Kontrak Laravel ↔ Flask | **PASS** | Kontrak request dan response 100% selaras antara pemanggil (`MLAnalysisService.php`) dan endpoint Flask (`analyze.py`, `schemas/analyze.py`, `production_analysis.py`). Validasi batas nilai, proteksi cluster/risk_score, dan penolakan payload cacat terdefinisi eksplisit. |
| **B** | URL, Timeout, dan Error Handling | **PASS** | URL target `config('services.ml.url', 'http://127.0.0.1:5000')` dengan path `/internal-ml/analyze`. Timeout disetel 10 detik. Penanganan kegagalan koneksi/status 4xx/5xx/non-JSON dibungkus exception terkontrol. *Catatan:* Konfigurasi `services.ml.url` di `config/services.php` belum didaftarkan eksplisit (mengandalkan default fallback di service). |
| **C** | Validasi Environment dan Keamanan | **PASS** | Endpoint Flask bersifat murni internal (`/internal-ml/analyze`). Komponen Flask tidak memiliki library database/ORM, driver database, atau kredensial Supabase. Komunikasi data sepenuhnya mediated oleh Laravel. Secret aman. |
| **D** | Kesiapan Menjalankan Flask Lokal | **PASS** | Factory app tersedia di `ml/app/__init__.py:create_app()`. Manifest dependencies `requirements.txt` mencantumkan `flask>=3.1.0`. Dependensi matematika runtime (`scikit-learn`, `numpy`) sudah terpasang/tersedia di environment Python lokal. Prosedur operasional yang aman telah dirancang. |
| **E** | Gap Mock vs Flask Nyata | **NOT VERIFIED** | Validitas integrasi pada pengujian sebelumnya (STEP 4D.27, 4D.29) hanya dibuktikan via `Http::fake()`. Serialisasi data nyata, proses fitting K-Means runtime (`scipy`/`numpy`), konkurensi port 5000, dan ketahanan network timeout nyata belum pernah dibuktikan secara fisik. |

---

## 2. Rincian Temuan per Bagian Pemeriksaan

### A. Kontrak Laravel ↔ Flask
**Status:** `PASS`

#### 1. Titik Temu Kode
- **Sisi Laravel (Pemanggil):**
  - File: `backend/app/Services/MLAnalysisService.php` (baris 33–51, 85–128, 133–179)
  - Dispatcher Command: `backend/app/Console/Commands/RunMLAnalysis.php` (baris 27–54)
- **Sisi Flask (Layanan ML):**
  - Route: `ml/app/routes/analyze.py` (baris 28–90)
  - Schema Validation: `ml/app/schemas/analyze.py` (baris 16–84)
  - Algoritma Produksi: `ml/app/services/production_analysis.py` (baris 23–90)

#### 2. Spesifikasi Request (Laravel ➝ Flask)
- **Method & Endpoint:** `POST /internal-ml/analyze`
- **Header:** `Content-Type: application/json`
- **Payload Request:**
  ```json
  {
    "analysis_context": {
      "requested_at": "ISO-8601 string"
    },
    "citizen_reports": [
      {
        "report_id": 1,
        "latitude": -6.9175,
        "longitude": 107.6191
      }
    ],
    "iot_data": [
      {
        "node_id": 1,
        "latitude": -6.9200,
        "longitude": 107.6150,
        "turbidity": 4.5,
        "flow_rate": 12.3,
        "pressure": 2.8,
        "measured_at": "YYYY-MM-DD HH:MM:SS"
      }
    ]
  }
  ```
- **Kesesuaian Skema & Validasi:**
  - `ml/app/schemas/analyze.py:validate_analyze_request()` memvalidasi:
    - `citizen_reports` wajib berupa list objek dengan field wajib `report_id`, `latitude` (numerik), dan `longitude` (numerik).
    - `iot_data` wajib berupa list objek dengan field wajib `node_id`, `latitude`, `longitude`, `turbidity`, `flow_rate`, `pressure` (seluruhnya wajib numerik).
    - `analysis_context` opsional, namun jika ada wajib berupa dict/object.
  - Sisi Laravel (`MLAnalysisService.php:buildDataset()`) menghasilkan struktur yang identik 100%.

#### 3. Spesifikasi Response (Flask ➝ Laravel)
- **HTTP 200 OK Body:**
  ```json
  {
    "success": true,
    "analysis": {
      "generated_at": "ISO-8601 string"
    },
    "risk_results": [
      {
        "report_id": 1,
        "risk_score": 85
      }
    ],
    "clusters": [
      {
        "centroid_lat": -6.9175,
        "centroid_lng": 107.6191,
        "report_count": 1
      }
    ]
  }
  ```
- **Penanganan Validasi Sisi Laravel (`MLAnalysisService.php:validateResponse()`):**
  - **`success === true`:** Jika false/kosong, melempar Exception (*Analysis unsuccessful*).
  - **`report_id` tidak dikenal:** Dicocokkan dengan laporan yang dikirim (`$submittedReportIds`). Jika Flask mengembalikan `report_id` tak terdaftar, melempar Exception `ML returned risk result for unknown or unsubmitted report_id`.
  - **Batas Nilai `risk_score`:** Wajib numerik dan berada dalam rentang `0 <= risk_score <= 100`. Jika di luar rentang, melempar Exception.
  - **Proteksi Field Cluster Terlarang:**
    - Pada objek cluster, field `risk_score` dilarang keras ada (`array_key_exists('risk_score', $cluster)` memicu Exception).
    - Format centroid wajib numerik `centroid_lat` dan `centroid_lng`.
  - *Catatan Non-Blocking:* Baris 162–165 di `MLAnalysisService.php` (`cluster_id` check pada `risk_results`) dikomentari, namun Flask `production_analysis.py` memang tidak pernah memancarkan `cluster_id` pada `risk_results`.

---

### B. URL, Timeout, dan Error Handling
**Status:** `PASS`

#### 1. Konfigurasi Endpoint
- **Lokasi Kode:** `backend/app/Services/MLAnalysisService.php` baris 35:
  ```php
  $flaskUrl = config('services.ml.url', 'http://127.0.0.1:5000') . '/internal-ml/analyze';
  ```
- **Variabel Environment:** `services.ml.url` dapat diisi via config. Jika tidak disetel, otomatis menggunakan fallback default: `http://127.0.0.1:5000`.
- **Status di `backend/config/services.php`:** Kunci `'ml'` belum terdaftar eksplisit di dalam array `config/services.php`. Fallback string `'http://127.0.0.1:5000'` di method `config()` berjalan sebagai penopang default yang aman.

#### 2. Timeout & Ketahanan Koneksi
- **Timeout HTTP:** Disetel `Http::timeout(10)` (10 detik) pada pemanggilan POST (`MLAnalysisService.php` baris 38).
- **Retry:** Tidak ada mekanisme automatic retry (0 retry). Kegagalan langsung diteruskan ke blok penanganan error.
- **Perilaku Skenario Kegagalan:**
  - *Koneksi Gagal / Timeout:* Tertangkap oleh `catch (Exception $e)` baris 39-41, dibungkus menjadi `Exception("ML Service Unavailable: " . $e->getMessage())`.
  - *HTTP 4xx / 5xx:* Tertangkap oleh baris 43-45 (`!$response->successful()`), melempar `Exception("ML Service returned HTTP " . $status . ": " . $body)`.
  - *Response Bukan JSON:* `$response->json()` mengembalikan `null`, yang kemudian gagal pada `validateResponse()` karena ketiadaan `success` / `risk_results`.
  - *Atomisitas Transaksi:* Logika penyimpanan database dibungkus `DB::transaction()` (baris 53-79) yang baru dieksekusi **setelah** seluruh validasi lolos. Jika ada error validasi atau jaringan, tidak ada baris data atau skema yang tersisa.

---

### C. Validasi Environment dan Keamanan
**Status:** `PASS`

- **Isolasi Database Remote:**
  - Modul Python/Flask di direktori `ml/` tidak memiliki dependensi database client (tidak ada `psycopg2`, `SQLAlchemy`, `supabase-py`, atau konektor DB lainnya).
  - Flask hanya menerima JSON via request HTTP POST dan membalas dengan JSON string.
  - Sisi Laravel (`MLAnalysisService.php`) mengambil data dari database lokal via Eloquent dan meneruskan hanya data publik yang relevan (`report_id`, `latitude`, `longitude` serta pembacaan IoT).
  - Tidak ada jalur bagi Flask untuk berinteraksi dengan Supabase atau jaringan luar.
- **Kebersihan Secret:**
  - Tidak ada API key, token, APP_KEY, atau DB_PASSWORD yang disimpan di file kode Flask, schema, atau layanan ML.
  - Autentikasi internal Laravel ↔ Flask pada tahap lokal berjalan tanpa secret tambahan (OPEN/LAN internal `127.0.0.1`), sesuai dokumen arsitektur `DEC-016` & `API_CONTRACT.md §22`.

---

### D. Kesiapan Menjalankan Flask Secara Lokal
**Status:** `PASS`

#### 1. Entry Point & Dependensi
- **Entry Point Flask:** `ml/app/__init__.py:create_app()` sebagai Application Factory.
- **File Dependensi:** `ml/requirements.txt` berisi `flask>=3.1.0`.
- **Kesiapan Runtime Python:**
  - `production_analysis.py` mengimpor `from sklearn.cluster import KMeans` dan `import numpy as np`.
  - Dependensi `scikit-learn` dan `numpy` telah diverifikasi tersedia pada environment Python lokal dari pengujian unit sebelumnya.
- **Port Layanan:** Port default adalah `5000` (atau disesuaikan dengan konfigurasi).

#### 2. Prosedur Operasional yang Aman untuk Pengujian Berikutnya (Diusulkan - Belum Dijalankan)
Jika pengguna menyetujui pengujian terpisah pada tahap berikutnya:
1. **Jalankan Flask di terminal terpisah secara lokal:**
   ```powershell
   cmd /c "python -m flask --app ml.app:create_app run --port 5000"
   ```
2. **Pastikan Laravel Testing tetap terikat pada database pengujian lokal:**
   - Memastikan `APP_ENV=testing` dan `DB_DATABASE=aquatara_test` tetap aktif.
   - Guard `TestEnvironmentGuard` pada `Tests\TestCase::createApplication()` tetap melindungi agar tidak menyentuh Supabase.
3. **Eksekusi test integrasi terkontrol tanpa Http::fake()** atau pemanggilan console command `php artisan ml:run-analysis` dengan database lokal `aquatara_test`.

---

### E. Gap Mock vs Flask Nyata
**Status:** `NOT VERIFIED` (Keterbatasan Pembuktian Statis)

Pengujian pada STEP 4D.27 dan STEP 4D.29 berhasil membuktikan jalur logika Laravel karena menggunakan `Http::fake()`. Namun, mock tersebut belum membuktikan hal-hal kritis berikut pada dunia nyata:
1. **Serialisasi Request & Parsing JSON Python Asli:**  
   `Http::fake()` tidak menguji apakah data tipe floating point (koordinat lat/lng) dari database PostgreSQL Laravel terserialisasi dengan presisi yang diterima oleh `validate_analyze_request()` Flask tanpa pembulatan atau encoding issue.
2. **Kinerja & Algoritma Nyata K-Means:**  
   Mock sebelumnya mengembalikan nilai dummy statis. Perilaku `scikit-learn` K-Means sesungguhnya dengan bobot spatial density (`0.8 * T + 0.2 * S`) terhadap dataset nyata belum diverifikasi dalam interaksi HTTP riil antara proses PHP dan Python.
3. **Kompatibilitas Runtime Port & Network Inter-Process:**  
   Interaksi socket TCP `127.0.0.1:5000` pada sistem operasi Windows (termasuk potensi konflik firewall lokal, port busy, atau timeout proses) belum dibuktikan.
4. **Respon Error Nyata Flask (Controlled vs Unhandled 500):**  
   Bagaimana Laravel menangani respons crash Python yang sesungguhnya (misal jika terjadi OOM atau exception tak terduga) di luar payload sintesis mock.

---

## 3. Perintah yang Dijalankan Selama Audit

Seluruh perintah yang dijalankan berstatus strictly read-only:
1. `grep_search` pada `backend/app/Services/MLAnalysisService.php` dan `ml/` (mencari titik temu kontrak, konfigurasi URL, port, dan error handling).
2. `view_file` pada `MLAnalysisService.php`, `RunMLAnalysis.php`, `config/services.php`, `ml/app/__init__.py`, `ml/app/routes/analyze.py`, `ml/app/schemas/analyze.py`, `ml/app/services/production_analysis.py`, `ml/requirements.txt`, `API_CONTRACT.md`, dan dokumen rekayasa terkait.
3. `list_dir` pada direktori `ml/app`, `ml/app/routes`, `ml/app/schemas`, `ml/app/services`.
4. `cmd /c type nul > ...` untuk inisialisasi file laporan tunggal.

**Pernyataan Kepatuhan:**
Tidak ada test (PHPUnit/pytest) yang dijalankan. Tidak ada server Flask, Laravel artisan serve, atau worker yang dijalankan. Tidak ada akses jaringan atau database remote/Supabase. Tidak ada mutasi kode sumber, konfigurasi, atau environment.

---

## 4. Kesimpulan & Rekomendasi

- **Kesiapan Desain & Kontrak:** Siap dan kompatibel 100%. Tidak ada inkonsistensi field nama antara Laravel dan Flask.
- **Risiko Utama:**
  1. `config/services.php` belum memetakan `'ml' => ['url' => env('ML_SERVICE_URL', 'http://127.0.0.1:5000')]` secara eksplisit, meskipun saat ini aman karena ada fallback default di `MLAnalysisService.php`.
  2. Ketergantungan runtime Flask pada port 5000 lokal memerlukan proses server terpisah yang harus dipastikan aktif sebelum Laravel memanggilnya.
- **Rekomendasi Langkah Berikutnya:**
  Menunggu persetujuan pengguna sebelum menyiapkan dan mengeksekusi pengujian integrasi terkontrol dengan server Flask lokal yang aktif.
