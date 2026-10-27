# LAPORAN STEP 4D.17: MINIMAL TESTCASE LIFECYCLE FIX REPORT

**Tanggal:** 2026-10-12  
**Target File Diubah:** `backend/tests/TestCase.php`  
**Jenis Perubahan:** Perbaikan Urutan Lifecycle Method `setUp()` (Minimal Bug Fix)  
**Status Eksekusi:** **SELESAI (Menunggu Persetujuan Pengguna Sebelum Eksekusi Test)**  

---

## 1. Ringkasan Perubahan

Bug fatal lifecycle pada [backend/tests/TestCase.php](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/TestCase.php) yang menyebabkan error:
`"Trying to access array offset on null"` dan `"Call to a member function get() on null"` telah diperbaiki dengan penyesuaian minimal 2 baris kode di dalam method `setUp()`.

### Akar Masalah Sebelumnya:
Method `setUp()` memanggil `$this->assertSafeTestEnvironment($this->app)` sebelum `parent::setUp()`. Namun pada framework testing Laravel (`Illuminate\Foundation\Testing\TestCase`), properti `$this->app` baru diinisialisasi di dalam `parent::setUp()` (melalui `setUpTheTestEnvironment()` -> `refreshApplication()`). Memanggil guard sebelum `parent::setUp()` menyebabkan `$this->app` masih bernilai `null`.

---

## 2. Diff Minimal Perubahan

```diff
--- a/backend/tests/TestCase.php
+++ b/backend/tests/TestCase.php
@@ -25,9 +25,9 @@
      */
     protected function setUp(): void
     {
-        $this->assertSafeTestEnvironment($this->app);
-
         parent::setUp();
+
+        $this->assertSafeTestEnvironment($this->app);
     }
```

---

## 3. Mengapa Guard Tetap Bekerja Sebelum Trait Database?

Arsitektur pertahanan isolasi database pada AQUA-TARA bekerja secara berlapis (defense-in-depth):

### 1. Gate Primer (Paling Utama): `createApplication()`
- **Rujukan:** [backend/tests/TestCase.php:14-21](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/TestCase.php#L14-L21)
  ```php
  public function createApplication()
  {
      $app = parent::createApplication();

      $this->assertSafeTestEnvironment($app);

      return $app;
  }
  ```
- **Mekanisme Eksekusi:**
  1. Base class Laravel memanggil `$this->createApplication()` untuk me-refresh instance aplikasi.
  2. Guard dijalankan **segera** setelah container di-boot dan sebelum method `createApplication()` mengembalikan objek `$app`.
  3. Di dalam siklus hidup Laravel (`InteractsWithTestCaseLifecycle::setUpTheTestEnvironment()`), trait database seperti `RefreshDatabase`, `DatabaseMigrations`, dan `DatabaseTransactions` dijalankan pada baris 106 (`$this->setUpTraits()`).
  4. Panggilan `$this->setUpTraits()` baru dieksekusi **setelah** `$this->refreshApplication()` (yang memanggil `createApplication()`) selesai dengan sukses.
  5. **Kesimpulan:** Jika konfigurasi tidak aman (misal mengarah ke Supabase atau non-localhost), guard di `createApplication()` akan melempar `RuntimeException` dan **langsung menghentikan proses sebelum trait database (`setUpTraits`) dipanggil**.

### 2. Gate Sekunder (Pertahanan Berlapis): `setUp()`
- **Rujukan:** [backend/tests/TestCase.php:26-31](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/tests/TestCase.php#L26-L31)
  ```php
  protected function setUp(): void
  {
      parent::setUp();

      $this->assertSafeTestEnvironment($this->app);
  }
  ```
- **Mekanisme Eksekusi:**
  1. Memverifikasi ulang bahwa setelah seluruh trait Laravel selesai di-boot, konfigurasi database yang melekat pada `$this->app` masih tetap aman sebelum baris pertama method pengujian dijalankan.
  2. Karena `parent::setUp()` telah dipanggil, `$this->app` terisi penuh dengan instance `Illuminate\Foundation\Application`, sehingga `$app['config']` dapat diakses secara valid tanpa warning atau fatal error.

---

## 4. Kepatuhan Batasan & Integritas File

- [x] Tidak ada file lain yang diubah (`backend/.env`, `backend/.env.testing`, `backend/phpunit.xml`, `config/database.php`, migrations, dan test classes tetap tidak tersentuh).
- [x] Tidak ada test yang dijalankan (`phpunit`, `artisan test`, dsb.).
- [x] Tidak ada koneksi database atau query yang dibuat.
- [x] Tidak ada kredensial atau rahasia yang dibaca, dicetak, atau diekspos.
- [x] Guard tidak dilemahkan; seluruh validasi `TestEnvironmentGuard` tetap utuh 100%.

---

## 5. Pernyataan Berhenti

Perbaikan minimal telah selesai diterapkan. Sistem berhenti di sini dan menunggu konfirmasi/persetujuan dari pengguna sebelum pengujian ulang dijalankan.
