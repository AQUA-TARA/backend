# STEP 4D.53 — READ-ONLY TICKET TRACKING AND HISTORY API AUDIT REPORT

## 1. Inventarisasi Endpoint Tracking dan History Terdaftar

Berdasarkan pemeriksaan langsung terhadap `routes/api.php` dan seluruh controller aplikasi:

| Endpoint (URI) | HTTP Method | Middleware | Controller & Method | Parameter Route | Deskripsi & Tujuan |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `/api/tickets/{ticket}` | `GET` | *None* (Publik / Tanpa Auth) | `ReportController@show` | `{ticket}` (diikat ke argumen `$ticket_id` di controller) | Pelacakan status tiket individual publik (mendukung pelacakan anonim per DEC-006 & DEC-009). |
| `/api/citizen/history` | `GET` | `auth:citizen` | `CitizenHistoryController@index` | *None* | Riwayat seluruh tiket/laporan milik warga yang sedang login (autentikasi wajib per DEC-005 & DEC-006). |
| `/api/admin/tickets/{ticket_id}/status` | `PUT` | `auth:sanctum` | `AdminTicketController@updateStatus` | `{ticket_id}` | Pembaruan status tiket oleh staf admin/dispatcher (wajib token Admin User). |

**Temuan:**
- **TERBUKTI DARI KODE ([routes/api.php:58](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/routes/api.php#L58))**: Endpoint tracking tiket individual adalah `GET /api/tickets/{ticket}`.
- **TERBUKTI DARI KODE ([ReportController.php:127](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L127))**: Tidak ada FormRequest khusus untuk endpoint tracking; parameter `$ticket_id` diterima langsung oleh controller method `show($ticket_id)`.
- **TIDAK DITEMUKAN**: Tidak ada endpoint publik atau terpisah bernama `/api/tickets/{id}/history` atau sejenisnya di route.

---

## 2. Analisis Kepemilikan dan Otorisasi (ID Guessing / Enumeration)

### A. Endpoint `GET /api/tickets/{ticket}` (Tracking Publik)
- **Sumber Kode:** [ReportController.php:114-139](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L114-L139)
- **Klasifikasi:** **TERBUKTI DARI KODE**
- **Analisis:**
  1. **Autentikasi:** Endpoint ini **tidak memiliki middleware autentikasi**. Siapa saja (tamu anonim, citizen, bot) dapat memanggil endpoint ini tanpa header `Authorization`.
  2. **Otorisasi Kepemilikan:** Controller menggunakan kueri langsung:
     ```php
     $ticket = Ticket::with(['statusHistories' => ...])->find($ticket_id);
     ```
     Tidak ada pengecekan kepemilikan (`citizen_id`, session, atau token).
  3. **ID Format & Guessing Risk:**
     - Kolom `tickets.id` di skema database ([2026_01_01_000060_create_tickets_table.php:29](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/database/migrations/2026_01_01_000060_create_tickets_table.php#L29)) adalah **BIGINT UNSIGNED AUTO_INCREMENT PK** (sequential integer: 1, 2, 3, ...), bukan UUID atau random nano-ID.
     - **Implikasi:** Siapa pun dapat melakukan enumerasi (`GET /api/tickets/1`, `GET /api/tickets/2`, dst.) dan membaca status tiket warga lain.
     - **Konteks Desain:** Komentar controller baris 119–120 menyatakan *"Business rules (LOCKED): Anonymous access allowed (DEC-006)"*. Akses publik terhadap tracking memang disengaja oleh keputusan arsitektur awal agar warga yang melapor secara anonim tetap dapat memantau tiketnya menggunakan ID tiket yang diterimanya saat submit.

### B. Endpoint `GET /api/citizen/history` (Riwayat Terproteksi)
- **Sumber Kode:** [CitizenHistoryController.php:53-65](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/CitizenHistoryController.php#L53-L65)
- **Klasifikasi:** **TERBUKTI DARI KODE**
- **Analisis:**
  - Warga **TIDAK BISA** melihat tiket warga lain melalui endpoint ini.
  - Query secara ketat difilter menggunakan `Report::where('citizen_id', $citizen->id)`. Parameter query string seperti `?citizen_id=...` diabaikan sepenuhnya (telah teruji aman pada `CitizenHistoryTest`).

---

## 3. Data Aktual yang Dikembalikan oleh `GET /api/tickets/{ticket}`

Berdasarkan pembacaan aktual [ReportController.php:150-167](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L150-L167):

### Struktur Respons Sukses (HTTP 200):
```json
{
  "success": true,
  "data": {
    "ticket_id": 1,
    "status": "SUBMITTED",
    "display_status": "Diterima",
    "created_at": "2026-10-12T08:00:00.000000Z",
    "updated_at": "2026-10-12T08:00:00.000000Z"
  }
}
```

### Inventarisasi Data yang Dikembalikan vs Data yang Dilindungi:
| Kategori Data | Status dalam Respons | Bukti Kode & Penjelasan |
| :--- | :--- | :--- |
| **Status Terkini** | **ADA** | `data.status` (canonical) dan `data.display_status` (bahasa Indonesia). |
| **Waktu Tiket** | **ADA** | `data.created_at` dan `data.updated_at` dari record `Ticket`. |
| **ID Tiket** | **ADA** | `data.ticket_id`. |
| **Detail Laporan (Deskripsi)** | **TIDAK ADA** | Kolom `reports.description` **TIDAK diekspos**. |
| **Lokasi (Latitude / Longitude)**| **TIDAK ADA** | Koordinat geografis pelapor **TIDAK diekspos**. |
| **Foto Laporan (`photo_path`)** | **TIDAK ADA** | URL/path foto laporan **TIDAK diekspos**. |
| **Identitas Citizen (`citizen_id`)**| **TIDAK ADA** | Model `Citizen` dan kolom `reports.citizen_id` **TIDAK diekspos**. |
| **Nomor Telepon Pelapor** | **TIDAK ADA** | Nomor telepon warga sama sekali tidak disentuh atau dikembalikan. |
| **Audit Trail (`history`)** | **TIDAK ADA (DI-KOMENTARI)** | Baris 158–165 kode pemetaan `history` **masih dikomentari (commented out)**! |

**Analisis Privasi & Kebocoran:**
- **TERBUKTI DARI KODE**: Meskipun ID tiket sequential dapat ditebak secara publik, endpoint tracking **TIDAK membocorkan informasi pribadi warga, foto, koordinat lokasi, maupun teks deskripsi keluhan**. Endpoint ini murni mengekspos status operasional tiket (`status`, `display_status`, `timestamps`).

---

## 4. Sumber Status Terkini dan Riwayat Status

- **Status Terkini ([ReportController.php:154-155](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L154-L155))**:
  - Diambil langsung dari kolom `tickets.status` dan dipetakan via array `$statusMapping`:
    - `'SUBMITTED'` -> `'Diterima'`
    - `'IN_REVIEW'` -> `'Sedang Ditinjau'`
    - `'CONFIRMED'` -> `'Laporan Dikonfirmasi'`
    - `'IN_PROGRESS'` -> `'Sedang Ditangani'`
    - `'CLOSED'` -> `'Selesai'`
- **Riwayat Status (`statusHistories`) ([ReportController.php:130-132, 158-165](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L130-L132))**:
  - Query controller memuat eager loading:
    ```php
    Ticket::with(['statusHistories' => function ($query) {
        $query->orderBy('created_at', 'desc');
    }])->find($ticket_id);
    ```
  - **POTENSI RISIKO YANG PERLU DIVERIFIKASI / GAP DOKUMENTASI**:
    Meskipun relasi `statusHistories` di-eager-load dari database, bagian payload JSON yang menyajikan array `history` di baris 158–165 **berada dalam komentar blok**:
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
    Artinya, **saat ini endpoint `GET /api/tickets/{ticket}` TIDAK mengembalikan array history di level JSON**, hanya status tiket saat ini. Eager loading query yang dijalankan baris 130 menjadi query mubazir (*wasted query*) karena hasilnya tidak dipakai dalam respons JSON.

---

## 5. Perilaku untuk Laporan Anonim (`citizen_id = NULL`)

- **Pelacakan Tiket Anonim ([ReportController.php:119-122](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L119-L122))**:
  - **TERBUKTI DARI KODE**: Tiket yang berasal dari laporan anonim memiliki record di tabel `tickets` yang identik dengan tiket laporan terautentikasi.
  - Tiket anonim **dapat dilacak secara publik** melalui `GET /api/tickets/{ticket_id}` dengan respons status 200 dan payload yang sama persis.
  - Karena data `Report` dan `Citizen` tidak di-serialize ke dalam JSON respons, tiket anonim tidak mengekspos data internal apa pun.

---

## 6. Penanganan Edge Cases & Respons Error

1. **Tiket Tidak Ditemukan (`find($ticket_id)` mengembalikan `null`)**:
   - Status HTTP: `404 Not Found` ([ReportController.php:134-139](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L134-L139))
   - Body: `{"success": false, "message": "Tiket tidak ditemukan."}`
2. **ID Tidak Valid / Non-Numeric (misal `GET /api/tickets/abc`)**:
   - Route `Route::get('/tickets/{ticket}', ...)` tidak memiliki regex constraint `->whereNumber('ticket')`.
   - String `"abc"` diteruskan ke `Ticket::find('abc')`, yang pada PostgreSQL/Eloquent menghasilkan `null`.
   - Menghasilkan HTTP `404 Not Found` (`"Tiket tidak ditemukan."`).
3. **Akses dengan Token (Citizen / Admin / Tanpa Token)**:
   - Karena rute tidak dipasangi middleware auth, request dengan header Bearer token (baik token valid Citizen, Admin, maupun token invalid) tetap diproses secara publik dan mengembalikan HTTP 200 jika tiket ada. Token tidak dicek maupun divalidasi oleh endpoint ini.
4. **Exception Handling / Internal Error**:
   - Jika terjadi exception (misalnya koneksi database terputus), ditangkap oleh block `catch (Throwable $e)` ([ReportController.php:169-181](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L169-L181)).
   - Status HTTP: `500 Internal Server Error`
   - Body: `{"success": false, "message": "Gagal mengambil data tiket."}`

---

## 7. Perbandingan dengan Cakupan Test Feature yang Sudah Ada

Pemeriksaan terhadap file test yang ada di `tests/Feature`:
- **`CitizenHistoryTest.php`**: Menguji `GET /api/citizen/history` (riwayat multi-tiket milik citizen terautentikasi).
- **`AdminTicketLifecycleTest.php`**: Menguji `PUT /api/admin/tickets/{ticket_id}/status` (mutasi status tiket oleh admin).
- **`ReportSubmissionTest.php`**: Menguji `POST /api/reports` (pembuatan laporan dan inisialisasi tiket).
- **`TicketTracking`**: **BELUM MEMILIKI TEST FEATURE KHUSUS SAMA SEKALI!**
  Endpoint `GET /api/tickets/{ticket}` belum pernah diuji secara formal di dalam `tests/Feature/`.

---

## 8. Rekomendasi Test Feature Lanjutan (`TicketTrackingTest.php`)

Daftar skenario uji yang direkomendasikan untuk dibuat pada tahap implementasi berikutnya:

| No | Skenario Uji | HTTP Status | Assertion Utama Berdasarkan Kode Aktual |
| :--- | :--- | :--- | :--- |
| 1 | **Pelacakan Tiket Valid Publik** | `200 OK` | Mengembalikan `ticket_id`, `status: SUBMITTED`, `display_status: Diterima`, `created_at`, `updated_at`. |
| 2 | **Pelacakan Tiket Laporan Anonim** | `200 OK` | Tiket dari laporan dengan `citizen_id = null` dapat dilacak dengan sukses tanpa memerlukan autentikasi. |
| 3 | **Pelacakan Tiket Laporan Terautentikasi Tanpa Token** | `200 OK` | Tiket yang dibuat oleh citizen login tetap dapat dilacak melalui endpoint tracking publik tanpa token. |
| 4 | **Ketiadaan Kebocoran Data Sensitif** | `200 OK` | `assertJsonMissing(['description', 'latitude', 'longitude', 'photo_path', 'citizen_id', 'phone_number'])` memastikan data privasi tidak bocor ke publik. |
| 5 | **Pemetaan Seluruh Status Display** | `200 OK` | Memverifikasi `display_status` untuk kelima status kanonikal (`SUBMITTED`, `IN_REVIEW`, `CONFIRMED`, `IN_PROGRESS`, `CLOSED`). |
| 6 | **Tiket Tidak Ditemukan** | `404 Not Found` | ID tiket acak/tidak ada mengembalikan `{success: false, message: 'Tiket tidak ditemukan.'}`. |
| 7 | **ID Tiket Non-Numerik / String** | `404 Not Found` | Request dengan ID `abc` atau `invalid-id` mengembalikan HTTP 404 tanpa memicu HTTP 500 error. |
| 8 | **Akses dengan Token Tidak Mempengaruhi Respons** | `200 OK` | Request dengan Bearer token (baik Citizen maupun Admin) tetap menerima respons tracking publik yang identik. |

---

## 9. Keterbatasan Audit Statis

1. Seluruh analisis di atas diperoleh secara statis melalui inspeksi kode tanpa eksekusi test runtime.
2. Status array `history` yang dikomentari di controller diverifikasi secara statis; konfirmasi apakah array `history` perlu diaktifkan di masa mendatang merupakan keputusan produk yang berada di luar cakupan test audit ini.
