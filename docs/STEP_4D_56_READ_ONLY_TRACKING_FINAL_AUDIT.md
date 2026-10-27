# STEP 4D.56 — READ-ONLY TRACKING FINAL AUDIT REPORT

## 1. Pencocokan 8 Skenario `TicketTrackingTest` terhadap Kontrak Aktual & Bukti Eksekusi STEP 4D.55

Berdasarkan perbandingan antara laporan eksekusi [STEP_4D_55_CONTROLLED_TICKET_TRACKING_TEST_REPORT.md](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/docs/STEP_4D_55_CONTROLLED_TICKET_TRACKING_TEST_REPORT.md) dan verifikasi ulang langsung terhadap kode sumber [ReportController.php:127-183](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L127-L183) serta [routes/api.php:58](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/routes/api.php#L58):

| No | Skenario Test | Hasil Eksekusi STEP 4D.55 | Verifikasi Ulang dari Source Code | Status Keselarasan Kontrak |
| :--- | :--- | :--- | :--- | :--- |
| 1 | `test_valid_ticket_returns_200_with_expected_structure` | **PASSED** (2 assertions) | [ReportController.php:150-157](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L150-L157): Mengembalikan payload JSON dengan kunci `ticket_id`, `status`, `display_status`, `created_at`, dan `updated_at`. | **TERBUKTI DARI KODE & TEST** |
| 2 | `test_display_status_mapping_matches_source_contract` | **PASSED** (10 assertions) | [ReportController.php:142-148, 155](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L142-L148): Array `$statusMapping` memetakan secara persis 5 status kanonikal (`SUBMITTED` -> `Diterima`, `IN_REVIEW` -> `Sedang Ditinjau`, `CONFIRMED` -> `Laporan Dikonfirmasi`, `IN_PROGRESS` -> `Sedang Ditangani`, `CLOSED` -> `Selesai`). | **TERBUKTI DARI KODE & TEST** |
| 3 | `test_anonymous_report_ticket_can_be_tracked_publicly` | **PASSED** (2 assertions) | [ReportController.php:119-122](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L119-L122): Aturan bisnis terkunci menyatakan *"Anonymous access allowed (DEC-006)"*. Kueri tidak memerlukan `citizen_id` NOT NULL. | **TERBUKTI DARI KODE & TEST** |
| 4 | `test_non_existent_ticket_returns_404` | **PASSED** (2 assertions) | [ReportController.php:134-139](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L134-L139): Jika `!$ticket`, controller mengembalikan HTTP 404 `{"success": false, "message": "Tiket tidak ditemukan."}`. | **TERBUKTI DARI KODE & TEST** |
| 5 | `test_tracking_response_does_not_expose_sensitive_or_citizen_data` | **PASSED** (8 assertions) | [ReportController.php:152-167](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L152-L167): Kunci `description`, `latitude`, `longitude`, `photo_path`, `citizen_id`, dan `phone_number` sama sekali tidak disertakan dalam array respons. | **TERBUKTI DARI KODE & TEST** |
| 6 | `test_query_string_citizen_id_does_not_affect_ticket_retrieval` | **PASSED** (3 assertions) | [ReportController.php:127-133](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L127-L133): Controller hanya mengambil parameter `$ticket_id` dari rute URL dan mengabaikan `$request->query('citizen_id')`. | **TERBUKTI DARI KODE & TEST** |
| 7 | `test_history_is_not_exposed_in_current_response` | **PASSED** (3 assertions) | [ReportController.php:158-165](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L158-L165): Blok pemetaan riwayat status terbukti secara faktual masih berada dalam komentar baris (`// 'history' => ...`). | **TERBUKTI DARI KODE & TEST** |
| 8 | `test_endpoint_is_public_and_unaffected_by_tokens` | **PASSED** (6 assertions) | [routes/api.php:58](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/routes/api.php#L58): Rute didefinisikan di luar middleware `auth:citizen` atau `auth:sanctum`. Request dengan atau tanpa token diproses secara publik. | **TERBUKTI DARI KODE & TEST** |

---

## 2. Dokumentasi Respons Tracking Saat Ini

- **Route & Method ([routes/api.php:58](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/routes/api.php#L58)):**
  `GET /api/tickets/{ticket}`
- **Sifat Akses:**
  Publik tanpa autentikasi (`middleware: none`).
- **Dukungan Laporan Anonim:**
  Tiket dari laporan anonim (`reports.citizen_id = NULL`) mendapatkan representasi tiket yang identik di database dan dapat dilacak dengan normal.
- **Struktur Respons Sukses (HTTP 200):**
  ```json
  {
    "success": true,
    "data": {
      "ticket_id": 1,
      "status": "SUBMITTED",
      "display_status": "Diterima",
      "created_at": "2026-10-12T10:00:00.000000Z",
      "updated_at": "2026-10-12T10:00:00.000000Z"
    }
  }
  ```
- **Struktur Respons Tiket Tidak Ditemukan (HTTP 404):**
  ```json
  {
    "success": false,
    "message": "Tiket tidak ditemukan."
  }
  ```

---

## 3. Status Faktual Penyimpanan dan Pengiriman `statusHistories`

### A. Apakah `statusHistories` Tersimpan di Database?
- **TERBUKTI DARI KODE ([AdminTicketController.php:69-75](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/AdminTicketController.php#L69-L75))**:
  **YA, TERSIMPAN.** Setiap kali admin memperbarui status tiket melalui endpoint `PUT /api/admin/tickets/{ticket_id}/status`, record baru dibuat di tabel `ticket_status_histories` dengan kolom `ticket_id`, `old_status`, `new_status`, `actor_id`, dan `created_at`.
- **Relasi Eloquent ([Ticket.php:39-42](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/Ticket.php#L39-L42))**:
  Relasi `$ticket->statusHistories()` terdefinisi dengan benar (`hasMany(TicketStatusHistory::class)`).

### B. Apakah Riwayat Dikirimkan oleh Endpoint Publik `GET /api/tickets/{ticket}`?
- **TERBUKTI DARI KODE ([ReportController.php:130-132, 158-165](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L130-L132))**:
  **TIDAK DIKIRIMKAN.**
  Meskipun controller melakukan eager loading:
  ```php
  $ticket = Ticket::with(['statusHistories' => function ($query) {
      $query->orderBy('created_at', 'desc');
  }])->find($ticket_id);
  ```
  Pada pembentukan array respons JSON, bagian `history` secara faktual **masih dikomentari (commented out)**:
  ```php
  // Note: If you want to include history, format it here.
  // 'history'        => $ticket->statusHistories->map(function ($history) use ($statusMapping) {
  //    return [
  //        'status' => $history->new_status,
  //        'display_status' => $statusMapping[$history->new_status] ?? $history->new_status,
  //        'timestamp' => $history->created_at,
  //    ];
  // }),
  ```
  Akibatnya, payload JSON **sama sekali tidak memuat riwayat status** saat ini. Eager loading yang dijalankan pada baris 130 merupakan kueri yang tidak dimanfaatkan (inefisien) hingga keputusan produk mengaktifkan kembali blok tersebut atau menghapus eager loading tersebut.

---

## 4. Evaluasi Risiko ID Tiket Berurutan (Sequential Auto-Increment ID)

- **Fakta Desain Database ([2026_01_01_000060_create_tickets_table.php:29](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/database/migrations/2026_01_01_000060_create_tickets_table.php#L29)):**
  Kolom `tickets.id` menggunakan `BIGINT UNSIGNED AUTO_INCREMENT PK` (integer berurutan: 1, 2, 3, ...).
- **Analisis Dampak Privasi:**
  - **Bukan Kebocoran Data Pribadi (PII):** Karena endpoint tracking tidak mengembalikan nama, nomor telepon, foto, lokasi, maupun deskripsi keluhan, seorang pihak ketiga yang menebak ID tiket (misal `/api/tickets/5`) **tidak dapat mengetahui identitas pelapor atau lokasi kebocoran**.
- **Potensi Risiko Penyalahgunaan:**
  1. **Estimasi Metrik Volume Publik (Business Scraping):** Pihak luar dapat mengetahui jumlah total tiket keluhan yang masuk ke PDAM per hari dan laju penanganan tiket hanya dengan memantau ID dan status tiket.
  2. **Enumeration Attack (Brute-Force Scraping):** Tanpa pembatasan laju request (rate limiting), bot dapat melakukan crawling massal terhadap ribuan ID tiket.
- **Opsi Mitigasi Arsitektur (Usulan Desain, Bukan Implementasi):**
  - Penerapan `throttle:60,1` (Rate Limiting pada route tracking publik).
  - Di masa depan: Menambahkan kolom `tracking_code` / `tracking_token` acak (misal format string 8 karakter atau UUID) yang hanya diberikan kepada pelapor saat pengiriman laporan, sehingga ID sequential tidak dijadikan kunci pencarian publik.

---

## 5. Analisis Trade-Off: Jika Riwayat Status (`history`) Diaktifkan di Masa Depan

Jika blok komentar pada `ReportController.php:158-165` diaktifkan kembali:

### A. Manfaat
- Warga pelapor (termasuk warga anonim) mendapatkan transparansi alur tiket (misalnya kapan tiket beralih dari `Diterima` ke `Sedang Ditinjau` dan `Sedang Ditangani`).
- Eager loading query yang saat ini mubazir di baris 130 menjadi terjustifikasi.

### B. Batasan Field yang Aman vs Field Internal Sensitif
- **Field yang Aman Diekspos Publik:**
  - `status`: Status kanonikal (misal `"IN_REVIEW"`).
  - `display_status`: Label status bahasa Indonesia (misal `"Sedang Ditinjau"`).
  - `timestamp`: Waktu perubahan status (`created_at` dari histori).
- **Field Internal yang HARUS DILINDUNGI (TIDAK BOLEH DIEKSPOS):**
  - `actor_id`: ID staf admin/dispatcher yang melakukan perubahan.
  - Relasi `actor` (Nama admin, email admin). Mengekspos identitas staf ke publik berisiko menimbulkan masalah privasi dan keamanan internal pegawai.
  - Catatan internal staf (jika kelak ditambahkan kolom `internal_notes`).

---

## 6. Opsi Keputusan Produk & Rekomendasi Urutan Tindakan

Berdasarkan bukti audit statis dan hasil uji Feature Test yang ada, disajikan 3 opsi keputusan:

| Opsi | Deskripsi | Kelebihan | Kekurangan / Trade-Off |
| :--- | :--- | :--- | :--- |
| **Opsi A (Status Quo)** | **Pertahankan status terkini saja.** Hapus atau rapikan eager loading `$ticket->with(['statusHistories'])` di `ReportController@show` agar kueri lebih efisien. | Sederhana, payload sangat ringan, aman, dan 100% selaras dengan 8 skenario `TicketTrackingTest` yang baru saja PASS. | Warga tidak melihat jejak waktu perubahan status historis secara publik. |
| **Opsi B (Ekspos History Publik Terbatas)** | **Aktifkan blok `history` dengan field publik terbatas.** Hanya ekspos `status`, `display_status`, dan `timestamp`. Pastikan `actor_id` tetap disembunyikan. | Memberikan transparansi linimasa kepada pelapor anonim maupun warga terdaftar. | Membutuhkan modifikasi kode produksi `ReportController.php` dan pembaruan assertion pada `TicketTrackingTest.php`. |
| **Opsi C (Redesain Akses Tracking Token)** | **Audit dan redesain pelacakan tiket berbasis Token/Kode Unik Acak.** Mengganti lookup integer ID sequential dengan tracking token. | Mencegah enumerasi total, menyembunyikan volume tiket PDAM dari publik, paling aman secara jangka panjang. | Membutuhkan migrasi database baru (`tracking_token`), perubahan alur `POST /api/reports`, dan pembaruan kontrak frontend. |

### Rekomendasi Urutan Bertahap (Recommended Action Sequence):
1. **Jangka Pendek (Fase Saat Ini):**
   **Pilih Opsi A.** Seluruh backend Feature Test suite (Citizen Auth, Report Submission, Citizen History, Admin Ticket Lifecycle, dan Ticket Tracking) telah berstatus **100% PASS** terhadap kode yang ada. Pertahankan stabilitas ini dan jangan mengubah kode sebelum ada arahan kebutuhan bisnis baru.
2. **Jangka Menengah:**
   Jika tim produk menginginkan linimasa status publik di antarmuka citizen tracking, aktifkan **Opsi B** dengan aturan ketat bahwa `actor_id` tidak boleh diekspos.
3. **Jangka Panjang:**
   Terapkan rate limiting (`throttle`) dan evaluasi **Opsi C** pada rilis versi berikutnya sebelum deploy ke produksi penuh.

---

## 7. Konfirmasi Kepatuhan Eksplisit

- **TIDAK ADA** pengujian (PHPUnit, Pest, artisan test, dsb.) yang dijalankan dalam audit ini.
- **TIDAK ADA** query, koneksi, atau transaksi database yang dibuka (baik PostgreSQL lokal maupun Supabase).
- **TIDAK ADA** perubahan pada kode produksi, route, migration, konfigurasi `.env`, atau file test.
- Laporan ini bersifat read-only berdasarkan bukti kode aktual dan riwayat pengujian terkontrol sebelumnya.
