# STEP 4D.57 — READ-ONLY FRONTEND TRACKING INTEGRATION AUDIT

## 1. Ringkasan Eksekutif
Audit ini dilakukan secara statis (*read-only*) untuk mengevaluasi kesiapan integrasi frontend warga dengan API publik pelacakan tiket backend AQUA-TARA (`GET /api/tickets/{ticket}`).

Pemeriksaan mencakup komponen halaman pelacakan, API client/service, HTTP method, URL base dan endpoint, parameter routing, ekspektasi payload data, penanganan loading/error state, serta keamanan autentikasi.

Tidak ada kode produksi, migration, file test, konfigurasi `.env`, atau dependency yang diubah selama audit ini. Tidak ada server (Vite/Laravel/Mock) atau test suite (PHPUnit/Pest) yang dijalankan.

---

## 2. File dan Baris yang Diperiksa

### A. Frontend
1. **`frontend/src/api/client.js`**
   - Baris 15: Definisi `API_BASE = 'http://localhost:4000'` (hardcoded ke Mock server).
   - Baris 17–38: Helper fungsi `apiFetch(endpoint, options)`.
   - Baris 40–51: Implementasi API client `getTicket(ticketId)`.
2. **`frontend/src/pages/user/Tracking.vue`**
   - Baris 1–190: Template antarmuka (search bar, error badge, timeline status, detail aduan GPS/foto/deskripsi).
   - Baris 192–245: Script setup Vue 3 (reaktif state `ticketIdInput`, `ticketData`, `ticketError`, `handleSearch`).
   - Baris 247–332: Scoped styling antarmuka pelacakan.

### B. Backend (Pembanding Kontrak Aktual)
1. **`backend/routes/api.php`**
   - Baris 45: `Route::get('/tickets/{ticket}', [ReportController::class, 'show']);` (route publik tanpa auth middleware).
2. **`backend/app/Http/Controllers/ReportController.php`**
   - Baris 131–168: Method `show(string $ticket)`:
     - Mengambil tiket berdasarkan `ticket_id` (case-insensitive via `strtoupper`).
     - Jika tidak ditemukan: return HTTP 404 `{ "success": false, "message": "Tiket tidak ditemukan." }`.
     - Jika ditemukan: return HTTP 200 dengan payload:
       ```json
       {
         "success": true,
         "data": {
           "ticket_id": "...",
           "status": "...",
           "display_status": "...",
           "created_at": "...",
           "updated_at": "..."
         }
       }
       ```
3. **Dokumentasi Audit Sebelumnya**
   - `backend/docs/STEP_4D_53_READ_ONLY_TICKET_TRACKING_AUDIT.md`
   - `backend/docs/STEP_4D_56_READ_ONLY_TRACKING_FINAL_AUDIT.md`

---

## 3. Hasil Pencocokan Kontrak Integrasi

| Aspek Integrasi | Kontrak Backend Aktual | Implementasi Frontend (`client.js` & `Tracking.vue`) | Status / Kesesuaian |
| :--- | :--- | :--- | :--- |
| **HTTP Method** | `GET` | `GET` via `fetch` default | **MATCH** |
| **Endpoint Path** | `/api/tickets/{ticket}` | `/api/tickets/${encodeURIComponent(ticketId)}` | **MATCH** |
| **Header Auth** | Publik (tanpa header Authorization) | Tidak mengirim token Sanctum / Bearer | **MATCH** (Aman, token tidak bocor) |
| **Base URL** | Default port `8000` (Laravel) | Hardcoded `http://localhost:4000` (`client.js:15`) | **MISMATCH (BLOCKER)** |
| **Format Error 404**| `{ success: false, message: "..." }` | Mengecek `resp.error?.message \|\| 'Ticket tidak ditemukan.'` | **SUBTLE MISMATCH (RISK)** |
| **Payload Status** | Mengembalikan `status` & `display_status` | Membaca `ticketData.status` & `ticketData.display_status` | **MATCH** |
| **Payload Timestamp**| Mengembalikan `created_at` & `updated_at` | Menampilkan tanggal pada status tracker | **MATCH** |
| **Payload Geolocation**| **TIDAK DISEDIAKAN** (Privasi & anonimitas) | Mengekspektasikan `ticketData.latitude` & `longitude` | **MISMATCH (RISK)** |
| **Payload Deskripsi** | **TIDAK DISEDIAKAN** | Mengekspektasikan `ticketData.description` | **MISMATCH (RISK)** |
| **Payload Bukti Foto**| **TIDAK DISEDIAKAN** | Mengekspektasikan `ticketData.photo_url` | **MISMATCH (RISK)** |
| **Riwayat Status** | **TIDAK DISEDIAKAN** (Endpoint publik hanya snapshot) | Memiliki pemetaan statis step 1-4 di UI | **PARTIAL** |

---

## 4. Evaluasi State & UI/UX Frontend (`Tracking.vue`)

1. **Loading State:**
   - **Temuan:** Di dalam `handleSearch()` (`Tracking.vue:205-231`), tidak ada variabel reaktif `isLoading = ref(false)` yang diatur saat memanggil `getTicket()`.
   - **Dampak UX:** Pengguna tidak menerima umpan balik visual (spinner/disabled button) saat request jaringan sedang berlangsung, memungkinkan klik berulang (*double request*).

2. **Ticket Tidak Ditemukan (404) & Parsing Error:**
   - Backend mengembalikan:
     ```json
     { "success": false, "message": "Tiket tidak ditemukan." }
     ```
   - Frontend `client.js:28-36` mem-parsing respons non-2xx:
     ```js
     const error = await res.json().catch(() => ({ message: res.statusText }));
     return { success: false, error };
     ```
   - Di `Tracking.vue:219`:
     ```js
     ticketError.value = resp.error?.message || 'Ticket tidak ditemukan.';
     ```
   - Karena backend mengirim property `message` pada root objek JSON (bukan di dalam objek `error`), maka `resp.error.message` terisi dengan benar (`"Tiket tidak ditemukan."`). Jika parsing gagal, fallback string `'Ticket tidak ditemukan.'` aktif.

3. **Error Jaringan (Network Failure):**
   - Di `Tracking.vue:226-230`:
     ```js
     } catch (e) {
       ticketError.value = 'Tidak dapat terhubung ke server. Pastikan Mock API berjalan.';
     }
     ```
   - Menangani kegagalan koneksi secara elegan, namun pesan error masih mengacu pada "Mock API" port 4000.

4. **Tampilan Detail Aduan (GPS, Deskripsi, Foto):**
   - Template baris 150–186 menampilkan card "Detail Aduan" dengan koordinat peta, deskripsi keluhan, dan foto.
   - Karena backend publik `ReportController@show` dengan sengaja tidak mengembalikan `description`, `latitude`, `longitude`, maupun `photo_path` untuk melindungi privasi pengadu, field-field ini bernilai `undefined`.
   - Card ini akan menampilkan nilai kosong/placeholder (`-` atau string kosong).

---

## 5. Klasifikasi Temuan

### A. Blocker
1. **Hardcoded Base URL Mock Server (`frontend/src/api/client.js:15`)**
   - Nilai `const API_BASE = 'http://localhost:4000'` di-hardcode ke server Mock.
   - Frontend tidak menggunakan `import.meta.env.VITE_API_URL` sehingga tidak dapat terhubung langsung ke backend Laravel aktual (biasanya berjalan di port 8000).

### B. Risiko
1. **Ekspektasi Data Detail yang Disembunyikan Backend Publik (`Tracking.vue:150-186`)**
   - Frontend mengasumsikan pelacakan tiket publik menyertakan detail aduan lengkap (`description`, `latitude`, `longitude`, `photo_url`).
   - Sesuai keputusan arsitektur AQUA-TARA (STEP 4D.53 & 4D.56), endpoint `GET /api/tickets/{ticket}` adalah endpoint **publik tanpa login** untuk mendukung pelaporan anonim. Menampilkan koordinat GPS dan foto warga ke publik berpotensi melanggar privasi infrastruktur dan identitas pelapor.
   - Jika UI tidak disesuaikan, bagian "Detail Aduan" akan selalu kosong saat terhubung ke backend Laravel.

### C. Saran Penyempurnaan
1. **Tambahkan Indikator Reaktif Loading (`isLoading`)**
   - Menambahkan status loading dan visual spinner pada tombol "Lacak" agar interaksi lebih responsif dan mencegah *double submit*.
2. **Perbarui Pesan Fallback Network Error**
   - Ganti teks `'Pastikan Mock API berjalan'` menjadi pesan netral seperti `'Gagal terhubung ke layanan server. Periksa koneksi internet Anda.'`.
3. **Penyelarasan Informasi Antarmuka Pelacakan Publik**
   - Card "Detail Aduan" dapat disesuaikan untuk hanya menampilkan informasi yang tersedia secara publik (seperti waktu pelaporan dan status proses), atau menyembunyikan bagian GPS/Foto jika diakses dari endpoint publik.

---

## 6. Keterbatasan Audit & Konfirmasi Kepatuhan

- **Keterbatasan:** Audit dilakukan murni secara statis berbasis pembacaan file sumber tanpa menjalankan browser headless, dev server, atau emulator jaringan.
- **Konfirmasi Kepatuhan:**
  - Tidak ada perubahan kode pada backend maupun frontend.
  - Tidak ada dependency atau lockfile yang dimodifikasi.
  - Tidak ada tes (PHPUnit/Pest) yang dieksekusi.
  - Tidak ada koneksi ke database PostgreSQL lokal atau Supabase.
  - Seluruh batasan *read-only* dipatuhi secara ketat.
