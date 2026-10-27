# STEP 4D.58 — FRONTEND TRACKING INTEGRATION FIX REPORT

## 1. Ringkasan Eksekutif
Berdasarkan temuan audit pada `backend/docs/STEP_4D_57_READ_ONLY_FRONTEND_TRACKING_INTEGRATION_AUDIT.md`, telah diimplementasikan perbaikan integrasi pada antarmuka pelacakan tiket publik warga (`Tracking.vue`) dan HTTP client frontend (`client.js`).

Perbaikan ini mengeliminasi *hardcoded* URL port 4000 (Mock server), mengintegrasikan konfigurasi dinamis Vite `import.meta.env.VITE_API_URL` dengan fallback ke backend Laravel lokal (`http://localhost:8000`), menyelaraskan parsing data dengan kontrak backend publik aktual (`ReportController@show`), menambahkan proteksi *loading state* (mencegah klik ganda), serta menetralkan pesan error jaringan.

Tidak ada perubahan pada backend, route, migration, database, test backend, autentikasi, maupun dependensi paket.

---

## 2. File yang Diubah dan Dibuat

1. **`frontend/src/api/client.js`**
   - Mengubah konstanta `API_BASE` dari nilai statis `'http://localhost:4000'` menjadi resolusi URL dinamis berbasis `import.meta.env?.VITE_API_URL` dengan fallback `'http://localhost:8000'`.
   - Menambahkan sanitasi URL: menghapus *trailing slash* dan sufiks duplikat `'/api'` agar tidak memicu URL ganda `http://.../api/api/...`.
   - Mempertahankan `getTicket(ticketId)` memanggil `GET ${API_BASE}/api/tickets/${encodeURIComponent(ticketId)}` tanpa menyertakan header `Authorization` (endpoint publik).
   - Memperbarui komentar dokumentasi kontrak fungsi `getTicket` agar sesuai dengan `ReportController@show`.

2. **`frontend/src/pages/user/Tracking.vue`**
   - Menambahkan variabel reaktif `isLoading = ref(false)`.
   - Mengaktifkan *guard* pencegah klik ganda (`if (isLoading.value) return;`) pada `handleSearch()`.
   - Mengikat status `:disabled="isLoading"` pada input pencarian dan tombol "Lacak".
   - Menambahkan indikator visual *spinner* berputar dan teks "Memuat..." pada tombol saat request berlangsung.
   - Memastikan `isLoading.value = false` selalu dieksekusi dalam blok `finally`.
   - Menangani HTTP 404 dan respons error secara presisi melalui `resp?.message || resp?.error?.message || 'Tiket tidak ditemukan.'`.
   - Menetralkan pesan error jaringan pada blok `catch` menjadi: *"Tidak dapat terhubung ke server. Periksa koneksi internet Anda atau coba beberapa saat lagi."* (menghapus penyebutan Mock API).
   - Menghapus ketergantungan pada field yang tidak dikirim oleh endpoint publik backend (`latitude`, `longitude`, `description`, `photo_url`).
   - Mengganti panel detail aduan menjadi **Informasi Tiket** yang menampilkan data publik aktual:
     - Waktu Dibuat (`created_at`)
     - Pembaruan Terakhir (`updated_at`)
     - Status Kanonikal (`status` dan `display_status`)
     - Ticket ID (`ticket_id`)

3. **`frontend/.env.example`** (Dibuat baru sebagai template)
   - Menyediakan dokumentasi variabel lingkungan Vite:
     ```env
     # AQUA-TARA Frontend Environment Example
     # Base URL for AQUA-TARA Backend API (Laravel)
     VITE_API_URL=http://localhost:8000
     ```

---

## 3. Matriks Keputusan Base URL

| Skenario Konfigurasi | Nilai `VITE_API_URL` | Hasil Resolusi `API_BASE` | URL Target `getTicket('TICK-001')` | Keterangan |
| :--- | :--- | :--- | :--- | :--- |
| **Default / Fallback** | *(Tidak disetel / undefined)* | `http://localhost:8000` | `http://localhost:8000/api/tickets/TICK-001` | Sesuai default Laravel |
| **Origin Only** | `http://127.0.0.1:8000` | `http://127.0.0.1:8000` | `http://127.0.0.1:8000/api/tickets/TICK-001` | Bersih tanpa `/api` |
| **Origin dengan Trailing Slash** | `http://localhost:8000/` | `http://localhost:8000` | `http://localhost:8000/api/tickets/TICK-001` | Trailing slash dibersihkan |
| **Disertai Prefix `/api`** | `http://localhost:8000/api` | `http://localhost:8000` | `http://localhost:8000/api/tickets/TICK-001` | Mencegah `/api/api` ganda |
| **Production Domain** | `https://api.aquatara.id/api/`| `https://api.aquatara.id` | `https://api.aquatara.id/api/tickets/TICK-001` | Normalisasi otomatis |

Logika normalisasi yang diimplementasikan:
```javascript
const rawApiUrl = (import.meta.env?.VITE_API_URL || 'http://localhost:8000').trim().replace(/\/+$/, '')
const API_BASE = rawApiUrl.endsWith('/api') ? rawApiUrl.slice(0, -4) : rawApiUrl
```

---

## 4. Perbandingan Kontrak Payload Frontend

| Field Kontrak | Sebelum Perbaikan | Sesudah Perbaikan | Kesesuaian Backend (`ReportController@show`) |
| :--- | :--- | :--- | :--- |
| `ticket_id` | Dibaca dari dev state statis / `data.ticket_id` | Dibaca dari `ticketData.ticket_id` | **MATCH (Valid)** |
| `status` | Dibaca dari `data.status` | Dibaca dari `ticketData.status` | **MATCH (Valid)** |
| `display_status`| Diabaikan (di-generate manual) | Diprioritaskan dari `ticketData.display_status` | **MATCH (Valid)** |
| `created_at` | Dibaca dari `data.created_at` | Diformat dengan tanggal lokal ID | **MATCH (Valid)** |
| `updated_at` | Tidak ditampilkan | Ditampilkan sebagai *Pembaruan Terakhir* | **MATCH (Valid)** |
| `description`| Ditampilkan (selalu kosong/undefined) | **Dihapus dari template publik** | **MATCH (Aman & Tidak Miskonsepsi)** |
| `latitude` / `longitude` | Ditampilkan (selalu kosong/undefined) | **Dihapus dari template publik** | **MATCH (Melindungi privasi GPS)** |
| `photo_url` | Ditampilkan (selalu kosong/undefined) | **Dihapus dari template publik** | **MATCH (Melindungi privasi foto)** |

---

## 5. Hasil Validasi Aktual

1. **Pemeriksaan Git & Sintaks:**
   - `git diff --check frontend`: Exit code `0` (tidak ada trailing whitespace atau konflik format).
   - Struktur komponen Vue 3 `<script setup>` dan `<template>` valid.
2. **Review Header Autentikasi:**
   - Tidak ada token Bearer atau Sanctum yang ditambahkan pada request `getTicket`. Endpoint tetap publik anonim sesuai `DEC-006` dan `DEC-009`.
3. **Pemeriksaan State Loading & Error:**
   - Penekanan tombol "Lacak" memicu `isLoading = true`, mendisain tombol menjadi *disabled* dengan *spinner*, dan memastikan `isLoading = false` via blok `finally`.

---

## 6. Batasan & Hal yang Belum Diverifikasi

- **Verifikasi Runtime Terhadap Live Server:**
  Karena batasan mandat melarang menjalankan server lokal (Vite, artisan serve, maupun backend listener), integrasi HTTP secara end-to-end melalui browser/CURL belum dieksekusi secara langsung.
- **Konfirmasi Backend & Database:**
  Dipastikan **tidak ada file backend, route, migration, konfigurasi database, atau test suite backend** yang disentuh atau dieksekusi selama tahap ini.
