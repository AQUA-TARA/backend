# STEP 4D.50 — READ-ONLY TICKET LIFECYCLE AUDIT REPORT

## 1. File dan Sumber yang Diperiksa
- **[routes/api.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/routes/api.php#L60-L68)**: Routing rute admin tiket status.
- **[app/Http/Controllers/AdminTicketController.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/AdminTicketController.php#L1-L128)**: Controller logika transisi status, otorisasi role, transaksi atomik, locking, dan format respons.
- **[app/Models/Ticket.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/Ticket.php#L1-L44)**: Model tiket, relasi `report()`, relasi `statusHistories()`.
- **[app/Models/TicketStatusHistory.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/TicketStatusHistory.php#L1-L66)**: Model riwayat status tiket, relasi `ticket()`, relasi `actor()`.
- **[app/Models/User.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/User.php#L1-L49)**: Model admin/user dengan `HasApiTokens`.
- **[app/Models/Citizen.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Models/Citizen.php#L1-L45)**: Model citizen dengan `HasApiTokens`.
- **[database/migrations/2026_01_01_000060_create_tickets_table.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/database/migrations/2026_01_01_000060_create_tickets_table.php#L26-L68)**: Skema tabel `tickets`, constraint CHECK status, indeks.
- **[database/migrations/2026_01_01_000070_create_ticket_status_histories_table.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/database/migrations/2026_01_01_000070_create_ticket_status_histories_table.php#L26-L79)**: Skema tabel `ticket_status_histories`, constraint CHECK old/new status.
- **[config/auth.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/config/auth.php#L40-L79)**: Konfigurasi guard `web`, `citizen`, dan provider `users`, `citizens`.
- **[tests/TestCase.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/TestCase.php#L1-L47)**: Pengaman lingkungan pengujian `TestEnvironmentGuard`.
- **[tests/Support/TestEnvironmentGuard.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/Support/TestEnvironmentGuard.php#L1-L223)**: Logika isolasi DB lokal dan proteksi Supabase.

---

## 2. Route dan Middleware Autentikasi / Otorisasi Admin

### Temuan Sumber:
- **Route Definition ([routes/api.php:66-68](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/routes/api.php#L66-L68))**:
  ```php
  Route::middleware('auth:sanctum')->group(function () {
      Route::put('/admin/tickets/{ticket_id}/status', [AdminTicketController::class, 'updateStatus']);
  });
  ```
  - URI Lengkap: `PUT /api/admin/tickets/{ticket_id}/status`
  - HTTP Verb: `PUT`
  - Middleware Route: `auth:sanctum`

### Lapisan Otorisasi Controller ([AdminTicketController.php:31-33](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/AdminTicketController.php#L31-L33)):
```php
if (!auth('sanctum')->user() instanceof \App\Models\User) {
    return response()->json(['success' => false, 'message' => 'Unauthorized. Admin access required.'], 401);
}
```

---

## 3. Perilaku Berdasarkan Token dan Identitas Pemanggil

Berdasarkan analisis statis kode dan konfigurasi:

1. **Request Tanpa Token (Unauthenticated)**:
   - Ditolak langsung oleh middleware `auth:sanctum` sebelum masuk controller.
   - Status HTTP: `401 Unauthorized`.
   - Body: `{"message": "Unauthenticated."}`.
2. **Request dengan Token Tidak Valid / Rusak**:
   - Ditolak langsung oleh middleware `auth:sanctum`.
   - Status HTTP: `401 Unauthorized`.
   - Body: `{"message": "Unauthenticated."}`.
3. **Request dengan Token Milik Citizen (`App\Models\Citizen`)**:
   - Di tingkat Laravel Sanctum, tabel `personal_access_tokens` bersifat polimorfik (`tokenable_type`, `tokenable_id`). Token Citizen berhasil di-resolve oleh guard `auth:sanctum` sehingga melewati middleware route.
   - Namun, pada level controller ([AdminTicketController.php:31-33](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/AdminTicketController.php#L31-L33)), terdapat pertahanan:
     ```php
     if (!auth('sanctum')->user() instanceof \App\Models\User) {
         return response()->json(['success' => false, 'message' => 'Unauthorized. Admin access required.'], 401);
     }
     ```
   - Karena token Citizen mengembalikan instance `App\Models\Citizen` dan bukan `App\Models\User`, request ditolak dengan HTTP `401 Unauthorized` dan payload:
     ```json
     {
       "success": false,
       "message": "Unauthorized. Admin access required."
     }
     ```
   *(Terbukti secara eksplisit: Token warga dilarang keras mengubah status tiket).*

---

## 4. Transisi Status yang Diizinkan

### Konstanta Transisi ([AdminTicketController.php:14-19](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/AdminTicketController.php#L14-L19)):
```php
const VALID_TRANSITIONS = [
    'SUBMITTED'   => 'IN_REVIEW',
    'IN_REVIEW'   => 'CONFIRMED',
    'CONFIRMED'   => 'IN_PROGRESS',
    'IN_PROGRESS' => 'CLOSED',
];
```

### Aturan Transisi:
1. `SUBMITTED` -> hanya boleh ke `IN_REVIEW`.
2. `IN_REVIEW` -> hanya boleh ke `CONFIRMED`.
3. `CONFIRMED` -> hanya boleh ke `IN_PROGRESS`.
4. `IN_PROGRESS` -> hanya boleh ke `CLOSED`.
5. **`CLOSED` bersifat terminal**:
   - `self::VALID_TRANSITIONS['CLOSED']` menghasilkan `null`.
   - Tidak ada transisi keluar dari `CLOSED` (tidak bisa kembali ke status mana pun, dan tidak bisa ke dirinya sendiri).
6. **Loncat Status (Skip Stage) Dilarang**:
   - Contoh: `SUBMITTED` langsung ke `CONFIRMED` atau `CLOSED` akan menghasilkan error `invalid_transition`.
7. **Kembali ke Status Sebelumnya (Backward Transition) Dilarang**:
   - Contoh: `IN_PROGRESS` ke `IN_REVIEW` akan menghasilkan error `invalid_transition`.

---

## 5. Respons HTTP dan Struktur Payload

### A. Transisi Valid Berhasil (HTTP 200)
- **Logika ([AdminTicketController.php:104-113](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/AdminTicketController.php#L104-L113))**:
  ```json
  {
    "success": true,
    "message": "Status tiket berhasil diperbarui.",
    "data": {
      "ticket_id": 1,
      "status": "IN_REVIEW",
      "display_status": "Sedang Ditinjau",
      "updated_at": "2026-10-12T10:00:00.000000Z"
    }
  }
  ```
- **Pemetaan Label Bahasa Indonesia (`STATUS_DISPLAY`)**:
  - `SUBMITTED` -> `"Diterima"`
  - `IN_REVIEW` -> `"Sedang Ditinjau"`
  - `CONFIRMED` -> `"Laporan Dikonfirmasi"`
  - `IN_PROGRESS` -> `"Sedang Ditangani"`
  - `CLOSED` -> `"Selesai"`

### B. Transisi Tidak Valid / Lompat / Mundur (HTTP 422)
- **Logika ([AdminTicketController.php:57-64, 91-97](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/AdminTicketController.php#L57-L64))**:
  ```json
  {
    "success": false,
    "message": "Transisi status tidak valid.",
    "current_status": "SUBMITTED",
    "requested_status": "CLOSED",
    "allowed_next": "IN_REVIEW"
  }
  ```
  *(Catatan: Jika current status adalah `CLOSED`, `allowed_next` bernilai `null`)*.

### C. Status Sama / Tidak Ada Perubahan (HTTP 422)
- **Logika ([AdminTicketController.php:51-53, 86-90](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/AdminTicketController.php#L51-L53))**:
  ```json
  {
    "success": false,
    "message": "Status tiket sudah sama. Tidak ada perubahan.",
    "current_status": "SUBMITTED"
  }
  ```

### D. Status Tidak Dikenal / Nilai Invalid (HTTP 422)
- **Logika ([AdminTicketController.php:35-37](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/AdminTicketController.php#L35-L37))**:
  - Request divalidasi via `$request->validate(['status' => ['required', 'string', 'in:SUBMITTED,IN_REVIEW,CONFIRMED,IN_PROGRESS,CLOSED']])`.
  - Jika mengirim status asing (misal `"PENDING"`, `"RESOLVED"`, `"123"`):
  - Mengembalikan HTTP 422 standard validation error dengan key `errors.status`.

### E. Tiket Tidak Ditemukan (HTTP 404)
- **Logika ([AdminTicketController.php:45-47, 82-85](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/AdminTicketController.php#L45-L47))**:
  ```json
  {
    "success": false,
    "message": "Tiket tidak ditemukan."
  }
  ```

---

## 6. Konsistensi dan Atomisitas Mutasi State

### Analisis Atomisitas:
- **Database Transaction ([AdminTicketController.php:42-78](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/AdminTicketController.php#L42-L78))**:
  Seluruh mutasi dibungkus di dalam `DB::transaction(function () { ... })`.
- **Pessimistic Locking**:
  Baris 43 menggunakan `Ticket::lockForUpdate()->find($ticket_id)`, mencegah race condition atau update status paralel oleh admin lain pada tiket yang sama.
- **Konsistensi Audit History ([AdminTicketController.php:66-75](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/AdminTicketController.php#L66-L75))**:
  1. `$ticket->status = $newStatus; $ticket->save();`
  2. `TicketStatusHistory::create([...]);`
  Jika pembuatan `TicketStatusHistory` gagal (misalnya foreign key atau constraint error), pembaruan status `Ticket` akan di-rollback sepenuhnya oleh transaksi database. Tidak ada kondisi setengah-terupdate.

---

## 7. Skema Database, Constraint, dan Kebutuhan Fixture

### A. Tabel `tickets` ([2026_01_01_000060_create_tickets_table.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/database/migrations/2026_01_01_000060_create_tickets_table.php))
- `report_id`: `BIGINT UNSIGNED UNIQUE NOT NULL`, foreign key ke `reports.id`.
- `status`: `VARCHAR NOT NULL DEFAULT 'SUBMITTED'`.
- CHECK constraint: `CHECK (status IN ('SUBMITTED', 'IN_REVIEW', 'CONFIRMED', 'IN_PROGRESS', 'CLOSED'))`.
- `created_at`, `updated_at`: `TIMESTAMP NOT NULL`.

### B. Tabel `ticket_status_histories` ([2026_01_01_000070_create_ticket_status_histories_table.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/database/migrations/2026_01_01_000070_create_ticket_status_histories_table.php))
- `ticket_id`: `BIGINT UNSIGNED NOT NULL`, foreign key ke `tickets.id` (`cascadeOnDelete`).
- `old_status`: `VARCHAR NULLABLE`, CHECK constraint `old_status IS NULL OR old_status IN ('SUBMITTED', 'IN_REVIEW', 'CONFIRMED', 'IN_PROGRESS', 'CLOSED')`.
- `new_status`: `VARCHAR NOT NULL`, CHECK constraint `new_status IN ('SUBMITTED', 'IN_REVIEW', 'CONFIRMED', 'IN_PROGRESS', 'CLOSED')`.
- `actor_id`: `BIGINT UNSIGNED NULLABLE`, foreign key ke `users.id` (`nullOnDelete`).
- `created_at`: `TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`.

### C. Kebutuhan Fixture Minimal untuk Test:
Untuk membuat tiket yang valid:
1. Sebuah record `Report` harus dibuat terlebih dahulu dengan kolom NOT NULL: `description`, `latitude`, `longitude`, `photo_path`.
2. Sebuah record `Ticket` dibuat terhubung ke `report.id`.
3. Untuk aktor admin, sebuah record `User` harus dibuat dengan kolom `name`, `email`, `password`. Token diterbitkan via `$user->createToken('admin-test')->plainTextToken`.
4. Untuk pengujian penolakan token Citizen, record `Citizen` dibuat dengan `phone_number`, dan token diterbitkan via `$citizen->createToken('citizen-test')->plainTextToken`.

---

## 8. Skrip Standalone Lama: `tests/test_lifecycle.php`
- Pada audit STEP 4D.40 sebelumnya, file `tests/test_lifecycle.php` dilaporkan memiliki masalah encoding (UTF-16 LE / NULL bytes), sehingga pembacaan standar teks mengalami error.
- Skrip tersebut tidak boleh dijalankan dan tidak diperlukan lagi karena alur lengkap lifecycle tiket akan diimplementasikan secara resmi di `tests/Feature/AdminTicketLifecycleTest.php` menggunakan `Tests\TestCase` dan `DatabaseTransactions`.

---

## 9. Rancangan Test Feature yang Diperlukan & Mitigasi Risiko

Untuk menguji seluruh kontrak tiket admin, direkomendasikan membuat `backend/tests/Feature/AdminTicketLifecycleTest.php` dengan cakupan skenario berikut:

| No | Skenario Uji | Metode & Status HTTP | Assertion Utama & Mitigasi Risiko |
| :--- | :--- | :--- | :--- |
| 1 | **Transisi Sukses Sepanjang Lifecycle**<br>(SUBMITTED -> IN_REVIEW -> CONFIRMED -> IN_PROGRESS -> CLOSED) | `PUT` bertahap,<br>semua `200 OK` | Verifikasi `status`, `display_status`, perubahan di DB `tickets`, serta pembentukan record di `ticket_status_histories` dengan `actor_id` yang cocok. |
| 2 | **Pencegahan Transisi dari Status Terminal (CLOSED)** | `PUT` ke status apa pun saat tiket `CLOSED`, `422 Unprocessable` | Respons JSON memuat `'message': 'Transisi status tidak valid.'` dan `'allowed_next': null`. Tiket di DB tetap `CLOSED`. |
| 3 | **Pencegahan Loncat Status (Skip Stage)** | `PUT /status` dari `SUBMITTED` ke `CONFIRMED`, `422 Unprocessable` | Respons JSON memuat `'allowed_next': 'IN_REVIEW'`. Tiket di DB tetap `SUBMITTED`. |
| 4 | **Pencegahan Transisi Mundur (Backward)** | `PUT /status` dari `IN_PROGRESS` ke `IN_REVIEW`, `422 Unprocessable` | Respons JSON memuat `'allowed_next': 'CLOSED'`. |
| 5 | **Request dengan Status Sama (No-op)** | `PUT /status` dengan status yang sama, `422 Unprocessable` | Respons memuat `'message': 'Status tiket sudah sama. Tidak ada perubahan.'`. |
| 6 | **Status Tidak Dikenal / Di Luar Enum** | `PUT /status` dengan payload invalid (misal `"PENDING"`), `422 Unprocessable` | Validasi FormRequest gagal pada rule `in:...`. |
| 7 | **Tiket Tidak Ditemukan** | `PUT /admin/tickets/999999/status`, `404 Not Found` | Respons memuat `'message': 'Tiket tidak ditemukan.'`. |
| 8 | **Unauthenticated Request (Tanpa Token)** | `PUT` tanpa header Authorization, `401 Unauthorized` | Ditolak oleh middleware `auth:sanctum`. |
| 9 | **Invalid Token (Token Rusak / Palsu)** | `PUT` dengan `Bearer invalid-token`, `401 Unauthorized` | Ditolak oleh middleware `auth:sanctum`. |
| 10 | **Token Citizen Ditolak (RBAC Boundary)** | `PUT` dengan Bearer token milik `Citizen`, `401 Unauthorized` | Respons memuat `'message': 'Unauthorized. Admin access required.'`. Membuktikan token warga tidak bisa mengakses admin. |

---

## 10. Konfirmasi Batasan & Kepatuhan

- **TIDAK ADA** test yang dijalankan (PHPUnit, Pest, artisan test, dsb.).
- **TIDAK ADA** koneksi atau query database yang dibuka (baik PostgreSQL lokal maupun Supabase).
- **TIDAK ADA** migrasi, seeder, atau manipulasi DDL yang dieksekusi.
- **TIDAK ADA** file produksi, konfigurasi `.env`, atau secret yang diubah atau diakses.
- Laporan ini dibuat secara murni berdasarkan audit statis source code yang ada.
