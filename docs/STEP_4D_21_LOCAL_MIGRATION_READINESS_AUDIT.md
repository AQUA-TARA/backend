# LAPORAN STEP 4D.21: LOCAL MIGRATION READINESS AUDIT AQUA-TARA

**Tanggal:** 2026-10-12  
**Target Database:** `aquatara_test` di `127.0.0.1:5432` (Role: `aquatara_test_user`)  
**Jenis Audit:** Read-Only Static Architecture, Migration DDL, Model Consistency, & Test Suite Audit  
**Status Kesiapan:** **READY (DENGAN REKOMENDASI URUTAN PENGUJIAN BERTINGKAT)**  

---

## 1. Ringkasan Eksekutif

Audit statis kesiapan migrasi lokal (**Step 4D.21**) telah diselesaikan untuk mempersiapkan pembentukan skema pada database pengujian `aquatara_test` yang bersih.

Audit ini beroperasi di bawah prinsip isolasi dan keselamatan mutlak:
- **Zero Database Operations:** Tidak ada perintah Artisan, PHPUnit, migrasi, seeder, SQL, query, atau koneksi database langsung yang dijalankan.
- **Zero Cloud / Supabase Access:** Konfigurasi testing tetap 100% terisolasi ke host lokal `127.0.0.1:5432`.
- **Zero Secret Exposure:** Tidak ada password, APP_KEY, token, atau secret apa pun yang dibaca atau dicetak.
- **Zero Code Modification:** Tidak ada file kode, konfigurasi, migrasi, maupun environment yang diubah.

Hasil evaluasi menyeluruh menunjukkan bahwa seluruh 14 file migrasi memiliki urutan kronologis yang valid, relasi foreign key terdefinisi tanpa circular dependency, DDL sepenuhnya kompatibel dengan engine PostgreSQL lokal, serta model Eloquent dan Feature test telah teridentifikasi karakteristik perilakunya.

---

## 2. Audit Temuan Rinci per File Migrasi (14 File)

| No | File Migrasi | Tabel Dibuat | Foreign Keys & Dependensi | PostgreSQL DDL / Indeks Khusus | Status |
| :-: | :--- | :--- | :--- | :--- | :-: |
| 1 | `0001_01_01_000000_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` | `sessions.user_id` (nullable, index) | Primary key, unique email, longText payload | **PASS** |
| 2 | `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks` | Tidak ada | Key primary, expiration index | **PASS** |
| 3 | `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` | Tidak ada | Queue index, composite index `(connection, queue, failed_at)` | **PASS** |
| 4 | `2026_01_01_000010_create_citizens_table.php` | `citizens` | Tidak ada | `phone_number` unique, single timestamp (`created_at`) | **PASS** |
| 5 | `2026_01_01_000020_create_iot_nodes_table.php` | `iot_nodes` | Tidak ada | `hardware_id` unique, `latitude DECIMAL(10,8)`, `longitude DECIMAL(11,8)` | **PASS** |
| 6 | `2026_01_01_000030_create_pipe_networks_table.php` | `pipe_networks` | Tidak ada | `geo_data JSONB` (PostgreSQL native JSONB passthrough) | **PASS** |
| 7 | `2026_01_01_000040_create_ml_analyses_table.php` | `ml_analyses` | Tidak ada | Single timestamp `generated_at`, raw index query `generated_at` | **PASS** |
| 8 | `2026_01_01_000050_create_reports_table.php` | `reports` | `citizen_id` -> `citizens(id)` (`nullOnDelete`) | Mendukung Anonymous Report (`citizen_id NULL`), `DECIMAL` geo coords | **PASS** |
| 9 | `2026_01_01_000060_create_tickets_table.php` | `tickets` | `report_id` -> `reports(id)` (`restrictOnDelete`, UNIQUE 1:1) | **Raw CHECK constraint:** `chk_ticket_status` (canonical lifecycle: SUBMITTED, IN_REVIEW, CONFIRMED, IN_PROGRESS, CLOSED), raw index `status` | **PASS** |
| 10 | `2026_01_01_000070_create_ticket_status_histories_table.php` | `ticket_status_histories` | `ticket_id` -> `tickets(id)` (`cascadeOnDelete`), `actor_id` -> `users(id)` (`nullOnDelete`) | **Raw CHECK constraint:** `chk_tsh_old_status` dan `chk_tsh_new_status` | **PASS** |
| 11 | `2026_01_01_000080_create_iot_measurements_table.php` | `iot_measurements` | `node_id` -> `iot_nodes(id)` (`restrictOnDelete`) | **Raw Composite Index:** `CREATE INDEX idx_iot_measurements_node_time ON iot_measurements (node_id, measured_at DESC)` | **PASS** |
| 12 | `2026_01_01_000090_create_risk_results_table.php` | `risk_results` | `analysis_id` -> `ml_analyses(id)` (`restrictOnDelete`), `report_id` -> `reports(id)` (`restrictOnDelete`) | **Raw CHECK constraint:** `chk_risk_score_range` (0-100), raw index `report_id` & `analysis_id` | **PASS** |
| 13 | `2026_01_01_000100_create_clusters_table.php` | `clusters` | `analysis_id` -> `ml_analyses(id)` (`restrictOnDelete`) | Raw index `analysis_id`, `DECIMAL` centroid coordinates | **PASS** |
| 14 | `2026_10_04_065120_create_personal_access_tokens_table.php` | `personal_access_tokens` | Polymorphic `tokenable_type`, `tokenable_id` | Unique token 64 char, expires_at index | **PASS** |

---

## 3. Analisis Ketergantungan & Kompatibilitas PostgreSQL

1. **Urutan Pembuatan Tabel (Dependency Order):**
   - Tabel independen dibuat terlebih dahulu: `users`, `citizens`, `iot_nodes`, `pipe_networks`, `ml_analyses`.
   - Tabel turunan tingkat pertama dibuat berikutnya: `reports` (bergantung pada `citizens`).
   - Tabel turunan tingkat kedua dibuat berikutnya: `tickets` (bergantung pada `reports`).
   - Tabel turunan tingkat ketiga dibuat berikutnya: `ticket_status_histories` (bergantung pada `tickets` dan `users`), `iot_measurements` (bergantung pada `iot_nodes`), `risk_results` (bergantung pada `ml_analyses` dan `reports`), `clusters` (bergantung pada `ml_analyses`).
   - **Kesimpulan:** Tidak ditemukan adanya *circular dependency* atau referensi ke tabel yang belum didefinisikan.
2. **Operasi Rollback (`down()`):**
   - Seluruh 14 file migrasi memiliki method `down()` yang konsisten menggunakan `Schema::dropIfExists(...)`.
   - Urutan drop berbanding terbalik dengan urutan create, menjamin rollback bersih saat database di-refresh.
3. **Kompatibilitas Sintaks PostgreSQL:**
   - Tipe data `jsonb` didukung secara native oleh PostgreSQL driver.
   - Perintah `DB::statement` untuk penambahan CHECK constraint (`chk_ticket_status`, `chk_tsh_old_status`, `chk_tsh_new_status`, `chk_risk_score_range`) menggunakan sintaks SQL ANSI standar yang didukung penuh oleh PostgreSQL 16/17 lokal.
   - Pembuatan composite index dengan urutan `DESC` (`idx_iot_measurements_node_time`) valid dan optimal untuk query time-series PostgreSQL.

---

## 4. Evaluasi Feature Test Suites & Karakteristik Database

Audit statis terhadap seluruh Feature Test yang melibatkan database:

### 4.1 [RunMLAnalysisTest.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/Feature/RunMLAnalysisTest.php)
- **Database Trait:** `Illuminate\Foundation\Testing\RefreshDatabase`.
- **Perilaku:**
  - Saat dijalankan pada database yang belum memiliki tabel, `RefreshDatabase` secara otomatis memicu migrasi skema (`artisan migrate`) ke dalam `aquatara_test`.
  - Setelah migrasi skema ada, setiap test method dibungkus dalam database transaction yang di-rollback secara otomatis.
- **Setup:** Memanggil `$this->seedDummyData()` yang membuat dummy `Citizen`, `Report`, dan `Ticket`.
  - *Catatan Observasi:* Baris 30 membuat `Citizen` dengan `'is_verified' => true`. Pada model `Citizen`, kolom `is_verified` tidak terdaftar di `$fillable` (hanya `phone_number`), dan tabel `citizens` tidak memiliki kolom `is_verified`. Karena tidak ada di `$fillable`, Eloquent mengabaikan attribute tersebut secara senyap (silent ignore) saat `Citizen::create()`, sehingga tidak menimbulkan SQL error.
- **Efek Samping:** Sangat ideal sebagai test pertama yang menginisialisasi skema tabel pengujian.

### 4.2 [IoTIngestTest.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/Feature/IoTIngestTest.php)
- **Database Trait:** `Illuminate\Foundation\Testing\DatabaseTransactions`.
- **Perilaku:**
  - Trait `DatabaseTransactions` **TIDAK menjalankan migrasi skema**. Trait ini berasumsi bahwa tabel `iot_nodes` dan `iot_measurements` sudah tersedia di dalam database.
  - Setiap data yang dibuat (`IoTNode::create`, `POST /api/iot/ingest`) dibungkus transaksi dan di-rollback saat test selesai.
- **Dependensi Prasyarat:** Memerlukan migrasi telah berjalan terlebih dahulu (skema sudah terbentuk).

### 4.3 [MLAnalysisIntegrationTest.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/Feature/MLAnalysisIntegrationTest.php)
- **Database Trait:** `Illuminate\Foundation\Testing\DatabaseTransactions`.
- **Perilaku:**
  - Sama dengan `IoTIngestTest`, trait ini membungkus pengujian dalam transaksi DB dan mengandalkan tabel yang sudah ada.
  - Menguji logika filter Relevant Dataset ML, penolakan cluster yang memuat `risk_score` terlarang, serta validasi batas skor.
- **Dependensi Prasyarat:** Memerlukan skema tabel lengkap (`reports`, `tickets`, `citizens`, `ml_analyses`, dll.) telah terbuat sebelumnya.

---

## 5. Status Keamanan Konfigurasi & Guard (Zero Secret)

- **Target Database:** Terkonfirmasi `127.0.0.1:5432` / `aquatara_test` (role: `aquatara_test_user`).
- **Paksaan URL:** `DB_URL` dipaksa kosong via `phpunit.xml:28` (`force="true"`).
- **Enkripsi:** `APP_KEY` testing terkonfirmasi aktif di `backend/.env.testing`.
- **Guard Validation:** `TestEnvironmentGuard` aktif pada `TestCase::createApplication()` dan `TestCase::setUp()`, memblokir setiap koneksi non-localhost atau signature Supabase sebelum koneksi database dibuat.

---

## 6. Potensi Risiko & Mitigasi

| Risiko Potensial | Tingkat Risiko | Mitigasi yang Diterapkan |
| :--- | :---: | :--- |
| **Menjalankan test `DatabaseTransactions` sebelum skema ada** | Sedang | Menjalankan `RunMLAnalysisTest` (yang memiliki `RefreshDatabase`) terlebih dahulu agar skema 14 tabel terbentuk secara otomatis, atau memverifikasi kesiapan skema terlebih dahulu. |
| **Interaksi ke Supabase / Cloud** | Kritis | **Mitigasi 100% Aktif:** Guard memblokir seluruh akses non-127.0.0.1. `DB_URL` di-reset kosong di level PHPUnit. |
| **Konflik Constraint CHECK PostgreSQL** | Rendah | Nilai status pada seeder/test (`SUBMITTED`, `IN_PROGRESS`) dan nilai risk score (`0-100`) sepenuhnya mematuhi batas CHECK constraint. |

---

## 7. Rekomendasi Urutan Langkah Selanjutnya

1. **Tahap 1 (Eksekusi Inisialisasi Skema via RefreshDatabase):**
   Jalankan file pengujian tunggal:
   ```cmd
   cmd /c "vendor\bin\phpunit.bat tests\Feature\RunMLAnalysisTest.php"
   ```
   *Hasil yang diharapkan:* `RefreshDatabase` mendeteksi bahwa database `aquatara_test` bersih dan menjalankan seluruh 14 migrasi secara otomatis, kemudian memverifikasi fungsionalitas ML command.
2. **Tahap 2 (Eksekusi Pengujian Transaksional IoT):**
   Setelah skema terbentuk, jalankan:
   ```cmd
   cmd /c "vendor\bin\phpunit.bat tests\Feature\IoTIngestTest.php"
   ```
3. **Tahap 3 (Eksekusi Pengujian Integrasi Analisis ML):**
   ```cmd
   cmd /c "vendor\bin\phpunit.bat tests\Feature\MLAnalysisIntegrationTest.php"
   ```

---

## 8. Pernyataan Berhenti

Audit kesiapan migrasi telah selesai secara read-only.
- Status: **READY (AUDIT PASSED)**
- Tidak ada operasi database yang dijalankan.
- Tidak ada kode yang diubah.
- Sistem berhenti dan menunggu persetujuan eksplisit Anda sebelum langkah eksekusi pengujian database berikutnya.
