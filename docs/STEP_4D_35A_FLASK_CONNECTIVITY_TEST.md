# LAPORAN STEP 4D.35A: CONTROLLED FLASK RUNTIME CONNECTIVITY TEST

**Tanggal Pengujian:** 2026-10-12 13:41 WIB  
**Status Pengujian:** BERHASIL (PASS)  
**Tujuan:** Membuktikan bahwa server Flask ML dapat dijalankan, berhasil melakukan socket binding ke `127.0.0.1:5000`, dan dapat dihentikan secara bersih tanpa melibatkan Laravel, PHPUnit, maupun akses database.

---

## 1. Ringkasan Eksekutif Hasil Pengujian

| Tahap | Pemeriksaan | Status | Bukti / Catatan |
|:---|:---|:---:|:---|
| **1** | Interpreter & Dependensi Lokal | **PASS** | Python `3.13.3`, packages `flask`, `sklearn`, `numpy` terverifikasi dapat diimpor secara lokal. |
| **2** | Pemeriksaan Awal Port 5000 (Konflik) | **PASS** | `netstat -ano \| findstr :5000` tidak menghasilkan proses aktif (exit code 1, port bersih). |
| **3 & 4** | Eksekusi Sementara & Binding Address | **PASS** | Menjalankan `python -m flask --app app:create_app run --host 127.0.0.1 --port 5000` dari direktori `ml/`. Log startup membuktikan binding berhasil ke `http://127.0.0.1:5000` (PID 22776). |
| **5** | Uji Respons HTTP / Health Endpoint | **NOT VERIFIED** | Tidak ada endpoint health check khusus pada rute Flask. Sesuai batasan eksplisit, payload kosong tidak dikirim ke `/internal-ml/analyze` dan respons HTTP ditandai `NOT VERIFIED`. |
| **6** | Penghentian Proses Terkontrol | **PASS** | Proses Flask dihentikan via `manage_task (kill)`. Verifikasi pasca-terminasi (`netstat -ano \| findstr :5000`) mengonfirmasi port 5000 telah dilepaskan dan kembali bersih. |
| **7** | Kepatuhan Batasan Nol Database | **PASS** | Tidak ada proses PHPUnit, Artisan, Laravel server, SQL, migrasi, seeder, atau koneksi ke PostgreSQL / Supabase yang dijalankan. |

---

## 2. Rincian Eksekusi & Bukti per Langkah

### Langkah 1: Pemeriksaan Interpreter dan Dependensi Lokal
- **Perintah:**
  ```powershell
  python -c "import sys, flask, sklearn, numpy; print('PYTHON:', sys.version); print('FLASK:', flask.__name__); print('SKLEARN:', sklearn.__name__); print('NUMPY:', numpy.__name__)"
  ```
- **Output:**
  ```text
  PYTHON: 3.13.3 (tags/v3.13.3:6280bb5, Apr  8 2025, 14:47:33) [MSC v.1943 64 bit (AMD64)]
  FLASK: flask
  SKLEARN: sklearn
  NUMPY: numpy
  ```
- **Hasil:** **PASS** — Lingkungan Python dan pustaka ML dasar siap digunakan.

---

### Langkah 2: Pemeriksaan Port 5000 (Pra-Startup)
- **Perintah:**
  ```powershell
  netstat -ano | findstr :5000
  ```
- **Output:**
  *(Output kosong, exit code 1)*
- **Hasil:** **PASS** — Tidak ada proses yang sedang mendengarkan pada port 5000. Port bebas dari konflik.

---

### Langkah 3 & 4: Startup Sementara & Log Binding Address
- **Temuan PYTHONPATH / Working Directory:**
  - Upaya eksekusi dari root workspace (`--app ml.app:create_app`) menghasilkan `ModuleNotFoundError: No module named 'app'` karena modul `ml/app/__init__.py` melakukan import absolut `from app.routes.analyze import analyze_bp`.
  - Sesuai struktur aplikasi, eksekusi dilakukan dari direktori kerja `ml/` menggunakan `--app app:create_app`:
    ```powershell
    # Working Directory: e:\TUGAS\pengkoding\anti\aqua-tara\ml
    python -m flask --app app:create_app run --host 127.0.0.1 --port 5000
    ```
- **Log Startup Aktual:**
  ```text
   * Serving Flask app 'app:create_app'
   * Debug mode: off
  WARNING: This is a development server. Do not use it in a production deployment. Use a production WSGI server instead.
   * Running on http://127.0.0.1:5000
  Press CTRL+C to quit
  ```
- **Verifikasi Binding Port:**
  ```powershell
  netstat -ano | findstr :5000
  ```
  *Output:*
  ```text
    TCP    127.0.0.1:5000         0.0.0.0:0              LISTENING       22776
  ```
- **Hasil:** **PASS** — Layanan Flask berhasil melakukan binding ke IP lokal `127.0.0.1` dan port `5000` dengan PID `22776`.

---

### Langkah 5: Status Endpoint HTTP
- **Pemeriksaan Route:**
  - Endpoint yang terdaftar di `ml/app/routes/analyze.py` adalah `POST /internal-ml/analyze`.
  - Tidak ada endpoint GET terpisah seperti `/health` atau `/ping`.
  - Sesuai instruksi batasan: *"Jangan kirim payload kosong ke POST /internal-ml/analyze. Gunakan health endpoint hanya jika memang tersedia dan tidak mengakses database. Jika tidak ada health endpoint, tandai respons HTTP sebagai NOT VERIFIED."*
- **Hasil:** **NOT VERIFIED** — Tidak ada request HTTP yang dikirimkan untuk menjaga kemurnian protokol konektivitas.

---

### Langkah 6: Penghentian Proses dan Verifikasi Pelepasan Port
- **Tindakan:** Menghentikan proses task server Flask yang dibuat pada langkah ini (PID 22776).
- **Verifikasi Status Pasca-Terminasi:**
  ```powershell
  netstat -ano | findstr :5000
  ```
  *Output:*
  *(Output kosong, exit code 1)*
- **Hasil:** **PASS** — Proses Flask berhasil dihentikan secara bersih dan port 5000 telah dilepaskan sepenuhnya. Tidak ada proses residual atau proses asing yang dihentikan.

---

## 3. Catatan Teknis untuk Uji Integrasi Berikutnya

Untuk menjalankan Flask ML pada pengujian terintegrasi Laravel ↔ Flask mendatang:
- **Working Directory Penting:** Perintah menjalankan Flask harus dieksekusi dari direktori `ml/` (atau menyetel `PYTHONPATH=ml` jika dijalankan dari root repository) agar import internal `from app...` dapat diselesaikan dengan benar oleh Python:
  ```powershell
  cd e:\TUGAS\pengkoding\anti\aqua-tara\ml
  python -m flask --app app:create_app run --host 127.0.0.1 --port 5000
  ```

---

## 4. Kesimpulan & Status Akhir

Uji konektivitas runtime Flask telah berhasil membuktikan bahwa server Python Flask lokal dapat melakukan bind ke `127.0.0.1:5000` dan dihentikan dengan aman tanpa menyentuh database atau layer Laravel.

**STATUS: PASSED — BERHENTI DAN MENUNGGU INSTRUKSI PENGGUNA.**
