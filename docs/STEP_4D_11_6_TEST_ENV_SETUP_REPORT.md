# LAPORAN STEP 4D.11.6: SETUP AMAN BACKEND/.ENV.TESTING

**Tanggal & Waktu:** 2026-10-12  
**Status:** SELESAI & AMAN (Menunggu Input Pengguna untuk Password Lokal)  
**File Target Dibuat:** `backend/.env.testing`  

---

## 1. Ringkasan Eksekusi

Pada tahap Step 4D.11.6, environment testing terisolasi `backend/.env.testing` telah dibuat untuk mendukung eksekusi pengujian lokal menggunakan PostgreSQL lokal yang telah disiapkan pada tahap sebelumnya (`aquatara_test` di `127.0.0.1:5432`).

Proses penyiapan dilakukan dengan mematuhi seluruh protokol isolasi dan keamanan ketat:
- **Zero Secret Exposure:** Tidak ada password atau secret yang dibaca, dicetak, disimpan sementara, atau disalin dari environment lain. Variabel `DB_PASSWORD` dibiarkan kosong (`DB_PASSWORD=`).
- **Integritas Environment Produksi/Utama:** File `backend/.env` utama tidak diubah sama sekali.
- **Integritas Git:** File `backend/.env.testing` diverifikasi secara aktif diabaikan oleh Git via `backend/.gitignore`.
- **Tidak Ada Operasi Database:** Tidak ada perintah database (`migrate`, `db:wipe`, `test`, `seed`) yang dijalankan.
- **Zero Cloud Access:** Tidak ada koneksi yang mengarah ke Supabase maupun host eksternal.

---

## 2. Audit Pemilihan Environment Laravel & phpunit.xml

### 2.1 Mekanisme Bootstrap Laravel
Pada Laravel (komponen `Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables`):
1. Saat aplikasi di-bootstrap, sistem memeriksa variabel environment `APP_ENV`.
2. Jika `APP_ENV=testing`, Laravel secara native mendeteksi keberadaan file `.env.testing` di root folder backend (`base_path('.env.testing')`).
3. Jika `.env.testing` ditemukan, file tersebut dimuat untuk menggantikan file `.env` default.

### 2.2 Integrasi phpunit.xml
Dalam file `backend/phpunit.xml`:
- Bagian `<php>` mendefinisikan `<env name="APP_ENV" value="testing"/>`.
- Bagian `<env name="DB_URL" value="" force="true"/>` secara eksplisit memaksa `DB_URL` bernilai string kosong dengan atribut `force="true"`. Hal ini mencegah URL koneksi apa pun dari parent environment atau file `.env` utama menimpa konfigurasi host dan database lokal.
- Variabel koneksi individual (`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE`) **tidak didefinisikan secara hardcoded di phpunit.xml**, sehingga nilai yang diambil secara bersih berasal dari `backend/.env.testing`.

---

## 3. Analisis APP_KEY & Keputusan `key:generate`

### 3.1 Evaluasi Kebutuhan APP_KEY
- Di `backend/config/app.php`, konfigurasi encrypter membaca `'key' => env('APP_KEY')`.
- Untuk unit testing dan pengujian yang tidak menguji enkripsi payload/cookie session, `APP_KEY` tidak wajib ada untuk mem-boot database.
- Untuk HTTP Feature testing tertentu yang menggunakan middleware enkripsi cookie/session, `APP_KEY` dapat dibutuhkan di masa mendatang.

### 3.2 Alasan Mutlak Tidak Menjalankan `key:generate`
- Perintah standar `php artisan key:generate` secara default menargetkan dan **menimpa file `.env` utama**, yang merupakan pelanggaran kritis terhadap aturan integritas environment utama.
- Menjalankan `php artisan key:generate --env=testing` memerlukan konfirmasi eksplisit dari pengguna mengenai file target dan tidak boleh dijalankan tanpa persetujuan.
- **Keputusan:** Perintah `key:generate` **TIDAK DIJALANKAN**. `APP_KEY` tidak disertakan di `.env.testing` pada tahap ini, menjaga file seminimal dan sebersih mungkin.

---

## 4. Evaluasi Keselarasan dengan TestEnvironmentGuard

`Tests\Support\TestEnvironmentGuard` melakukan validasi ketat sebelum suite pengujian dijalankan:
1. **Pemeriksaan Driver:** `DB_CONNECTION=pgsql` (driver valid dan dikenali).
2. **Pemeriksaan Host Lokal:** `DB_HOST=127.0.0.1` termasuk dalam whitelist lokal (`127.0.0.1`, `localhost`, `::1`).
3. **Pemeriksaan Database Name:** `DB_DATABASE=aquatara_test` tidak kosong dan bukan database sistem terlarang (`postgres`, `public`, `template0`, dll).
4. **Pemeriksaan URL:** `DB_URL=` bernilai kosong, dan `phpunit.xml` memaksakan nilai kosong (`force="true"`), sehingga lolos validasi parsing URL.
5. **Pemeriksaan Supabase Signature:** Host `127.0.0.1` dan DB `aquatara_test` bebas dari string signature Supabase (`supabase.com`, `pooler`, dll).
6. **Named Connections:** Default fallback untuk `mysql`, `mariadb`, dan `sqlite` di `config/database.php` semuanya merujuk pada `DB_HOST` (`127.0.0.1`) dan `DB_DATABASE` (`aquatara_test`), sehingga seluruh koneksi named lolos guard tanpa pengecualian.

---

## 5. Rincian Konfigurasi `backend/.env.testing`

File telah dibuat di: `backend/.env.testing`.

Daftar variabel yang dikonfigurasi (tanpa mengekspos secret):
| Variabel | Status Nilai | Keterangan |
| :--- | :--- | :--- |
| `APP_ENV` | `testing` | Menandakan environment pengujian aktif |
| `DB_CONNECTION` | `pgsql` | Driver database PostgreSQL |
| `DB_HOST` | `127.0.0.1` | Loopback IP lokal terisolasi |
| `DB_PORT` | `5432` | Port standar service PostgreSQL lokal |
| `DB_DATABASE` | `aquatara_test` | Dedicated database pengujian terisolasi |
| `DB_USERNAME` | `aquatara_test_user` | Dedicated role pengujian terisolasi |
| `DB_PASSWORD` | *(Dikosongkan)* | **Menunggu pengisian mandiri oleh pengguna secara lokal** |
| `DB_URL` | *(Kosong)* | Memastikan driver membaca host & port individual |
| `DB_SSLMODE` | `disable` | Non-aktif untuk koneksi localhost lokal |

---

## 6. Verifikasi Git Ignore

Verifikasi ignore dijalankan dengan perintah:
```bash
git check-ignore -v backend/.env.testing
```

**Hasil:**
```
backend/.gitignore:6:.env.testing    backend/.env.testing
```
Status: **Dikonfirmasi terabaikan (ignored)** oleh baris ke-6 dari `backend/.gitignore`. File `.env.testing` aman dari risiko tertarget commit atau terunggah ke repositori Git.

---

## 7. Panduan Langkah Pengguna Selanjutnya

Sebelum pengujian atau migrasi test di masa mendatang dapat diaktifkan:
1. Buka file `backend/.env.testing` pada editor lokal Anda.
2. Isi nilai `DB_PASSWORD` sesuai dengan password yang telah Anda set untuk role `aquatara_test_user` pada PostgreSQL lokal Anda:
   ```env
   DB_PASSWORD=password_lokal_anda
   ```
3. Simpan file tersebut secara lokal.
4. Jangan pernah meng-commit atau membagikan password tersebut di log percakapan.
