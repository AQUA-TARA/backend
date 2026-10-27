# LAPORAN STEP 4D.14: READ-ONLY DATABASE TEST READINESS AUDIT AQUA-TARA

**Tanggal:** 2026-10-12  
**Jenis Audit:** Read-Only Static Architecture, Isolation, & Schema Readiness Audit  
**Status Kesiapan:** **READY (DENGAN REKOMENDASI URUTAN PENGUJIAN KETAT)**  

---

## 1. Ringkasan Eksekutif

Audit pra-pengujian database (**Step 4D.14**) telah diselesaikan secara menyeluruh tanpa mengeksekusi PHPUnit Feature tests, tanpa menjalankan migrasi / seeder / wipe, tanpa koneksi ke database apa pun, dan tanpa mengekspos rahasia atau kredensial.

Audit ini mengevaluasi 5 domain utama:
1. **Isolasi Konfigurasi & Runtime Guard:** Memastikan 100% isolasi ke `127.0.0.1:5432/aquatara_test` dan penolakan koneksi remote/Supabase sebelum pembukaan soket jaringan DB.
2. **Karakteristik & Risiko Test Feature:** Mengklasifikasikan test suite berdasarkan penggunaan database traits (`RefreshDatabase` vs `DatabaseTransactions` vs No Database).
3. **Analisis Keselarasan Skema vs Model Eloquent:** Memeriksa kompatibilitas 14 file migrasi terhadap 11 Model Eloquent dan batasan constraint fisik PostgreSQL.
4. **Evaluasi Factory & Seeder:** Memeriksa ketersediaan factory dan seeder untuk pengujian database.
5. **Rekomendasi Urutan Pengujian Bertahap:** Merancang urutan eksekusi aman berisiko minimum.

---

## 2. Status Variabel Konfigurasi & Lingkungan Testing (Zero-Secret)

Pemeriksaan pasif terhadap [backend/.env.testing](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/.env.testing) dan [backend/phpunit.xml](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/phpunit.xml):

| Variabel | Status Nilai | Target Nilai Terverifikasi | Catatan Keamanan |
| :--- | :---: | :--- | :--- |
| `APP_ENV` | Terisi | `testing` | Mengaktifkan bootstrap `.env.testing` |
| `DB_CONNECTION` | Terisi | `pgsql` | Driver database PostgreSQL |
| `DB_HOST` | Terisi | `127.0.0.1` | Loopback IP lokal terisolasi |
| `DB_PORT` | Terisi | `5432` | Port service PostgreSQL lokal |
| `DB_DATABASE` | Terisi | `aquatara_test` | Dedicated database pengujian terisolasi |
| `DB_USERNAME` | Terisi | `aquatara_test_user` | Dedicated user role testing |
| `DB_PASSWORD` | **Terisi** | `[TERSEMBUNYI]` | Nilai tidak dibaca / dicetak |
| `DB_URL` | Kosong | `""` | Dipaksa kosong via `phpunit.xml` (`force="true"`) |
| `DB_SSLMODE` | Terisi | `disable` | Sesuai untuk koneksi loopback lokal |

### Perlindungan Guard terhadap Koneksi Remote:
- **Rujukan:** [TestEnvironmentGuard.php:63, 92-99, 204-221](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/Support/TestEnvironmentGuard.php#L63) & [TestCase.php:14-21](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/TestCase.php#L14-L21)
- `TestCase::createApplication()` mengintersepsi aplikasi segera setelah di-boot dan sebelum method `setUp()` atau trait pengujian dijalankan.
- Jika host runtime bukan `127.0.0.1`, `localhost`, atau `::1`, atau jika host/URL mengandung kata kunci Supabase/pooler (`supabase.com`, `supabase.co`, `pooler.supabase`, `pooler`), guard langsung melempar `RuntimeException` dan menghentikan pengujian sebelum soket database dibuka.

---

## 3. Identifikasi & Klasifikasi Seluruh Feature Test

Audit statis terhadap direktori [backend/tests/Feature/](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/Feature):

| File Test | Database Trait | Operasi Database | Asumsi Data Awal | Risiko & Catatan |
| :--- | :---: | :--- | :--- | :--- |
| **`ExampleTest.php`** | **Tidak Ada** (`RefreshDatabase` dikomentari di line 5) | HTTP GET `/` (Welcome route) | Tidak ada | **Sangat Rendah.** Tidak memerlukan tabel atau data DB. |
| **`RunMLAnalysisTest.php`** | **`RefreshDatabase`** (line 17) | Menjalankan migrasi otomatis ke DB testing; membuat data dummy manual (`Citizen`, `Report`, `Ticket`); memanggil command `ml:run-analysis`; asersi ke `ml_analyses`, `risk_results`, `clusters` | Membuat dummy record di `setUp()` | **Tinggi saat run pertama.** `RefreshDatabase` akan menjalankan seluruh migrasi ke DB `aquatara_test` yang kosong. |
| **`IoTIngestTest.php`** | **`DatabaseTransactions`** (line 16) | Insert `IoTNode`; POST `/api/iot/ingest`; validasi asersi `iot_measurements` | Memeriksa `IoTMeasurement::count()` sebelum dan sesudah request | **Sedang.** Bergantung pada keberadaan tabel `iot_nodes` dan `iot_measurements`. Memerlukan migrasi telah ada di DB. |
| **`MLAnalysisIntegrationTest.php`** | **`DatabaseTransactions`** (line 21) | Insert dummy `Report`, `Ticket`, `Citizen`; memanggil `MLAnalysisService::runAnalysis()`; validasi logic filter | Membaca baseline `Report::count()` | **Sedang.** Bergantung pada keberadaan tabel skema ML dan tiket. Memerlukan migrasi telah ada di DB. |

---

## 4. Analisis Keselarasan Skema Migrasi vs Model Eloquent

Analisis statis silang antara 14 file migrasi di [database/migrations/](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/database/migrations) dan 11 Model Eloquent di [app/Models/](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models):

### 4.1 Tabel Publik & Autentikasi:
- **`users`** (`0001_01_01_000000_create_users_table.php`): Sesuai dengan [User.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/User.php). Kolom: `name`, `email`, `password`, `remember_token`. Memiliki timestamps.
- **`personal_access_tokens`** (`2026_10_04_065120_create_personal_access_tokens_table.php`): Mendukung trait `HasApiTokens` pada `Citizen` dan `User`.
- **`citizens`** (`2026_01_01_000010_create_citizens_table.php`): Sesuai dengan [Citizen.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/Citizen.php). Kolom: `phone_number`, `created_at`. Model menonaktifkan `$timestamps = false` secara benar.

### 4.2 Pelaporan & Tiket:
- **`reports`** (`2026_01_01_000050_create_reports_table.php`): Sesuai dengan [Report.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/Report.php). Kolom: `citizen_id` (nullable), `description`, `latitude`, `longitude`, `photo_path`, `created_at`. Model menonaktifkan `$timestamps = false`.
- **`tickets`** (`2026_01_01_000060_create_tickets_table.php`): Sesuai dengan [Ticket.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/Ticket.php). Kolom: `report_id` (unique), `status` (default SUBMITTED). Memiliki timestamps. Memuat `chk_ticket_status` (PostgreSQL raw statement).
- **`ticket_status_histories`** (`2026_01_01_000070_create_ticket_status_histories_table.php`): Sesuai dengan [TicketStatusHistory.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/TicketStatusHistory.php). Kolom: `ticket_id`, `old_status`, `new_status`, `actor_id`, `created_at`. Model `$timestamps = false`.

### 4.3 IoT Ingestion:
- **`iot_nodes`** (`2026_01_01_000020_create_iot_nodes_table.php`): Sesuai dengan [IoTNode.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/IoTNode.php). Kolom: `hardware_id`, `latitude`, `longitude`, `is_active`, `created_at`. Model `$timestamps = false`.
- **`iot_measurements`** (`2026_01_01_000080_create_iot_measurements_table.php`): Sesuai dengan [IoTMeasurement.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/IoTMeasurement.php). Kolom: `node_id`, `turbidity`, `flow_rate`, `pressure`, `measured_at`. Model `$timestamps = false`. Memuat composite index `idx_iot_measurements_node_time` dengan sorting `DESC`.

### 4.4 ML Analysis & Spatial:
- **`ml_analyses`** (`2026_01_01_000040_create_ml_analyses_table.php`): Sesuai dengan [MLAnalysis.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/MLAnalysis.php). Kolom: `generated_at`. Model `$timestamps = false`.
- **`risk_results`** (`2026_01_01_000090_create_risk_results_table.php`): Sesuai dengan [RiskResult.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/RiskResult.php). Kolom: `analysis_id`, `report_id`, `risk_score`. Model `$timestamps = false`. Memuat constraint `chk_risk_score_range` (0-100).
- **`clusters`** (`2026_01_01_000100_create_clusters_table.php`): Sesuai dengan [Cluster.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/Cluster.php). Kolom: `analysis_id`, `centroid_lat`, `centroid_lng`, `report_count`. Model `$timestamps = false`.
- **`pipe_networks`** (`2026_01_01_000030_create_pipe_networks_table.php`): Sesuai dengan [PipeNetwork.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/PipeNetwork.php). Kolom: `name`, `geo_data` (JSONB), `created_at`.

### 4.5 Ketersediaan Factory & Seeder:
- **Factory:** Hanya ada satu factory: [database/factories/UserFactory.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/database/factories/UserFactory.php) untuk model `User`.
- **Seeder:** Hanya ada [database/seeders/DatabaseSeeder.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/database/seeders/DatabaseSeeder.php) yang memanggil `User::factory()->create()`.
- **Catatan Penting:** Feature test (`RunMLAnalysisTest`, `IoTIngestTest`, `MLAnalysisIntegrationTest`) **tidak mengandalkan model factory atau seeder otomatis**, melainkan membuat model secara eksplisit menggunakan method `Model::create([...])` di dalam blok pengujian masing-masing.

---

## 5. Analisis Risiko & Mitigasi

| Risiko Potensial | Tingkat Keparahan | Analisis & Status Mitigasi |
| :--- | :---: | :--- |
| **Koneksi Leak ke Supabase** | Kritis | **Dimutlakkan Tidak Terjadi:** `DB_URL` kosong dipaksa via `phpunit.xml`. `TestEnvironmentGuard` aktif pada awal instansiasi `createApplication()`. Host non-127.0.0.1 diblokir runtime. |
| **Gagal Menjalankan Migrasi Otomatis** | Sedang | Basis data `aquatara_test` saat ini masih kosong. Test yang memakai `DatabaseTransactions` (`IoTIngestTest`) akan gagal jika tabel belum ada. |
| **Rollback vs Refresh Konflik** | Rendah | `RunMLAnalysisTest` menggunakan `RefreshDatabase` yang menjalankan migrasi saat pertama kali dan membungkus tiap test dalam transaksi basis data. |
| **Koneksi Lambat / Timeout** | Rendah | PostgreSQL lokal 127.0.0.1:5432 aktif dan telah diverifikasi sebelumnya. |

---

## 6. Rekomendasi Urutan Pengujian Aman (Staged Execution)

Untuk meminimalkan risiko dan memvalidasi integrasi langkah demi langkah:

### Tahap 1: Smoke Feature Test (Tanpa Akses Database)
- **Target:** `tests/Feature/ExampleTest.php`
- **Tujuan:** Memvalidasi bootstrapping HTTP kernel Laravel dan eksekusi `TestEnvironmentGuard` pada level Feature test tanpa menyentuh tabel basis data.
- **Perintah:**
  ```bash
  vendor\bin\phpunit.bat tests\Feature\ExampleTest.php
  ```

### Tahap 2: Pengujian Migrasi Skema Pertama Kali (via RefreshDatabase)
- **Target:** `tests/Feature/RunMLAnalysisTest.php`
- **Tujuan:** Menguji pembuatan skema 14 tabel lengkap pada basis data `aquatara_test` yang bersih menggunakan `RefreshDatabase`, serta menguji integrasi fungsional ML analysis dengan mocking HTTP.
- **Perintah:**
  ```bash
  vendor\bin\phpunit.bat tests\Feature\RunMLAnalysisTest.php
  ```

### Tahap 3: Pengujian Database Berbasis Transaksi (DatabaseTransactions)
- **Target:** `tests/Feature/IoTIngestTest.php` dilanjutkan dengan `tests/Feature/MLAnalysisIntegrationTest.php`
- **Tujuan:** Menguji endpoint API ingestion dan service ML dengan rollback otomatis tanpa merusak skema tabel.
- **Perintah:**
  ```bash
  vendor\bin\phpunit.bat tests\Feature\IoTIngestTest.php
  vendor\bin\phpunit.bat tests\Feature\MLAnalysisIntegrationTest.php
  ```

---

## 7. Pernyataan Penutup

Audit kesiapan database telah selesai dilakukan secara read-only.
- Status: **READY (AUDIT PASSED)**
- Tidak ada operasi database yang dijalankan.
- Tidak ada file yang dimodifikasi.
- Sistem berhenti dan menunggu persetujuan eksplisit Anda sebelum melanjutkan ke eksekusi Tahap 1 (Feature Smoke Test).
