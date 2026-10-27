# STEP 4D.71 — REPRODUCIBILITY & REPOSITORY STATE AUDIT

**Tanggal Audit:** 15 Oktober 2026  
**Auditor:** Tim Teknis AQUA-TARA / AI Assistant  
**Lingkup:** Audit konsistensi data antara STEP 4D.69 dan STEP 4D.70, verifikasi reproduksibilitas numerik artefak eksperimen, dan inspeksi status Git repository.  
**Status Sistem Produksi:** Unchanged — Read-Only Audit (Zero Supabase, Zero Migration, Zero Code Mutation, Zero Git Commit).

---

## RINGKASAN EKSEKUTIF

1. **Konsistensi Data STEP 4D.69 & STEP 4D.70:**
   - **Terkonfirmasi Konsisten Penuh Pada Tingkat Data:** Kedua laporan menggunakan file dataset lokal yang sama (`Sensor_Data_30cm.xlsx` dan `Sensor_Data_60cm.xlsx`).
   - Nilai statistik pada body laporan STEP 4D.69 (Tabel 7.1) dan STEP 4D.70 (Tabel B) adalah **identik 100%**:
     - Sensor 30 cm: Min=194, Max=249, Mean=215,49, Median=212,0, Std=16,18.
     - Sensor 60 cm: Min=134, Max=154, Mean=141,85, Median=140,0, Std=6,48.
   - **Koreksi Discrepansi Ringkasan Awal:** Pada ringkasan eksekutif awal STEP 4D.69 sempat tercatat rentang agregat "134-249 NTU" dan estimasi mean global (177,61 & 179,35 NTU) yang menggabungkan seluruh rentang tanpa membedakan kedalaman; namun detail per file di Bab 7.1 STEP 4D.69 dan seluruh STEP 4D.70 mencatat angka per kedalaman yang tepat dan terverifikasi secara matematis.
2. **Reproduksibilitas Artefak Eksperimen:**
   - File `experiment_summary.json` terbukti **100% cocok dan terverifikasi** dengan perhitungan ulang independen dari file mentah.
   - Perhitungan ulang mengonfirmasi bahwa setelah pengurutan (*sorting* waktu), timestamp pada sensor 30 cm dan 60 cm **100% identik dan berpasangan sempurna**.
   - Analisis non-monotonik menemukan **2 timestamp melompat mundur** pada file mentah (baris 256 dan 5842).
   - Analisis gap membuktikan bahwa fitur rolling time series yang mematuhi proteksi kausal dengan reset segment pada $\Delta t > 120$ detik menghasilkan 139 segmen independen, membuang kontaminasi batas silang gap.
3. **Status Git Repository:**
   - Root Git berada di `E:/TUGAS/pengkoding/anti/aqua-tara`.
   - Branch: `master` (No commits yet).
   - Seluruh direktori proyek (`.gitignore`, `backend/`, `frontend/`, `ml/`) berstatus *untracked* dalam Git repository lokal.
   - Tidak ada modifikasi pada file tracked, tidak ada staging (`git add`), dan tidak ada commit yang dilakukan.
4. **Keputusan Kesiapan Eksperimen:** **SIAP DILANJUTKAN** dengan catatan teknis yang jelas.

---

## TUGAS A — KONSISTENSI DATA & INTEGRITAS WORKBOOK

### A.1 Spesifikasi Fisik & Checksum Workbook Aktual

| File Workbook | Path Absolut / Relatif | Ukuran (Bytes) | SHA-256 Checksum Aktual |
|---|---|---|---|
| Sensor 30 cm | `ml/data/turbidity/ku_mwq/Sensor_Data_30cm.xlsx` | 285.192 bytes | `524f6cdecf15cb83e2e82d5db743233034e9e212b4e46c3e1b57cc0b305eb3e8` |
| Sensor 60 cm | `ml/data/turbidity/ku_mwq/Sensor_Data_60cm.xlsx` | 228.702 bytes | `b4495c7860d614ff9487bb656514e70c53357bb05a94c980f9845ad8a620ce8e` |

> **Catatan Audit Ukuran & Hash:**
> SHA-256 dan ukuran file di atas cocok 100% dengan Bab 3.2 pada laporan STEP 4D.69. Tidak ada mutasi, penimpaan, atau pengunduhan ulang file sumber.

### A.2 Verifikasi Struktur Kolom, Tipe Data, & Rentang

- **Sensor 30 cm:** 9.623 baris, 4 kolom:
  - `Date and Time`: object (di-parse ke datetime naive format `YYYY-MM-DD HH:MM:SS`)
  - `Temperature (°C) `: float64 (memiliki trailing space pada nama kolom)
  - `pH`: float64 (hanya ada pada sensor 30 cm)
  - `Turbidity (NTU)`: int64 (nilai bulat, rentang [194, 249])
- **Sensor 60 cm:** 9.623 baris, 3 kolom:
  - `Date and Time`: object (di-parse ke datetime naive format `YYYY-MM-DD HH:MM:SS`)
  - `Temperature (°C) `: float64 (memiliki trailing space pada nama kolom)
  - `Turbidity (NTU)`: int64 (nilai bulat, rentang [134, 154])
- **Rentang Waktu Aktual:** `2020-01-15 16:00:35` hingga `2020-01-22 16:25:32` (Durasi ~7 hari, Januari 2020).

### A.3 Rekalkulasi Ulang Statistik Independen

Hasil eksekusi rekalkulasi independen (`verify_reproducibility.py`):

| Metrik Statistik | Sensor 30 cm (Aktual) | Sensor 60 cm (Aktual) | Kesesuaian STEP 4D.70 |
|---|---|---|---|
| **Count** | 9.623 | 9.623 | 100% Cocok |
| **Minimum** | 194 NTU | 134 NTU | 100% Cocok |
| **Maksimum** | 249 NTU | 154 NTU | 100% Cocok |
| **Mean** | 215,485192 NTU | 141,853996 NTU | 100% Cocok (215,4852 & 141,8540) |
| **Median** | 212,000000 NTU | 140,000000 NTU | 100% Cocok (212,0 & 140,0) |
| **Std Dev** | 16,179374 NTU | 6,479665 NTU | 100% Cocok (16,1794 & 6,4797) |
| **Q25 (Q1)** | 202,000000 NTU | 137,000000 NTU | 100% Cocok |
| **Q75 (Q3)** | 224,000000 NTU | 147,000000 NTU | 100% Cocok |
| **Duplikat Timestamp** | 0 baris | 0 baris | 100% Bebas Duplikat |
| **Timestamp Non-Monotonik** | 2 baris | 2 baris | Terkonfirmasi |

### A.4 Rekonsiliasi Perbedaan Narasi Antara STEP 4D.69 dan STEP 4D.70

Audit memeriksa mengapa ada persepsi perbedaan angka:
1. **Dalam STEP 4D.69 Bab 7.1 (Tabel Data Terperinci):**
   - 30 cm tertulis: Min 194, Max 249, Mean 215,49, Median 212,0, Std 16,18.
   - 60 cm tertulis: Min 134, Max 154, Mean 141,85, Median 140,0, Std 6,48.
   - **Kesimpulan:** Angka detail di Bab 7.1 STEP 4D.69 adalah **identik** dengan STEP 4D.70.
2. **Dalam Ringkasan Respon Chat STEP 4D.69:**
   - Sempat muncul angka rerata gabungan 177,61 & 179,35 NTU akibat kalkulasi script awal yang menyatukan kedua sensor secara unstratified.
   - **Resolusi:** Laporan resmi STEP 4D.70 dan Bab 7.1 STEP 4D.69 menyajikan pemisahan per kedalaman secara presisi dan terbukti benar.

### A.5 Pemasangan Timestamp Antar-Kedalaman & Kausalitas Rolling

1. **Pemasangan Timestamp:**
   - Pengurutan `df.sort_values('Date and Time')` dijalankan secara mandiri pada kedua dataframe.
   - Pengecekan kesamaan `(df30_sorted['dt'] == df60_sorted['dt']).all()` bernilai `True`.
   - Hal ini membuktikan bahwa kedua file memiliki grid waktu yang sinkron dan berpasangan satu-ke-satu tanpa ketergantungan urutan indeks baris mentah.
2. **Kausalitas Fitur & Reset Gap:**
   - Seluruh rolling mean, rolling std, dan rolling Z-score hanya menggunakan indeks $[t-(w-1), t]$. Tidak ada forward-looking function (`shift(-k)` atau lookahead interpolations).
   - Ketika aturan proteksi gap diterapkan (memecah deret waktu jika $\Delta t > 120$ detik), terbentuk **139 segmen waktu kontinu independen**.
   - Pada window $w=15$, terdapat 1.383 titik data (14,37%) yang berstatus warm-up (NaN) setelah gap, menjamin tidak ada pembacaan masa lalu lintas gap yang mengkontaminasi kalkulasi lokal.

---

## TUGAS B — REPRODUKSIBILITAS EKSPERIMEN

### B.1 Verifikasi `experiment_summary.json`

File artefak [ml/experiments/ku_mwq_turbidity_features/experiment_summary.json](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/ku_mwq_turbidity_features/experiment_summary.json) diperiksa terhadap hasil kalkulasi ulang langsung:

```json
{
  "df30_rows": 9623,
  "df60_rows": 9623,
  "time_start": "2020-01-15 16:00:35",
  "time_end": "2020-01-22 16:25:32",
  "gaps_count_gt_120s": 138,
  "cross_depth_metrics": {
    "mean_difference": -73.6312,
    "std_difference": 13.1914,
    "pearson": 0.6188,
    "spearman": 0.6108
  }
}
```

- **Selisih Kedalaman ($60\text{ cm} - 30\text{ cm}$):**
  - Mean diff: $-73,631196$ (dibulatkan menjadi $-73,6312$ pada JSON). Cocok.
  - Std diff: $13,191371$ (dibulatkan menjadi $13,1914$). Cocok.
  - Pearson correlation: $0,618799$ (dibulatkan menjadi $0,6188$). Cocok.
  - Spearman correlation: $0,610775$ (dibulatkan menjadi $0,6108$). Cocok.
- **Artefak Gambar:**
  - `turbidity_timeseries_30_vs_60.png` (Ada, ukuran 363.386 bytes).
  - `temporal_features_decomposition.png` (Ada, ukuran 892.422 bytes).
  - `synthetic_shock_demonstration.png` (Ada, ukuran 185.340 bytes).
  - Ketiga gambar berhasil direproduksi dan tersimpan di `ml/experiments/ku_mwq_turbidity_features/`.

### B.2 Penilaian Klaim yang Tidak Dapat Direproduksi
- **Klaim Independensi Pipa:** Klaim bahwa kedalaman 30 cm dan 60 cm dapat diperlakukan sebagai dua pipa independen **dinyatakan tidak dapat direproduksi / tidak valid**, karena metadata ilmiah Mendeley Data secara tegas menyatakan kedua sensor ditenggelamkan berdampingan pada satu platform terapung di Khan Jahan Ali Hall Pond.
- **Klaim Ground Truth Anomali:** Tidak ada artefak ground truth anomali nyata dalam dataset KU-MWQ; simulasi shock buatan (Task F) ditegaskan berstatus `SYNTHETIC DEMONSTRATION` dan bukan bukti deteksi anomali dunia nyata.

---

## TUGAS C — STATUS REPOSITORY GIT

### C.1 Output Perintah Git Aktual

1. **Current Working Directory:**  
   `E:\TUGAS\pengkoding\anti\aqua-tara`
2. **Git Root Directory (`git rev-parse --show-toplevel`):**  
   `E:/TUGAS/pengkoding/anti/aqua-tara`  
   *(Root repository valid dan berada tepat di direktori workspace).*
3. **Status Branch (`git status --branch`):**
   ```text
   On branch master
   No commits yet
   Untracked files:
     (use "git add <file>..." to include in what will be committed)
   	.gitignore
   	backend/
   	frontend/
   	ml/

   nothing added to commit but untracked files present (use "git add" to track)
   ```
4. **Status Ringkas (`git status --short`):**
   ```text
   ?? .gitignore
   ?? backend/
   ?? frontend/
   ?? ml/
   ```

### C.2 Analisis Status Tracked vs Untracked
- Repository Git berada pada kondisi inisiasi awal (`master` branch tanpa histori commit sebelumnya).
- Semua direktori kerja (`backend/`, `frontend/`, `ml/`) berstatus *untracked*.
- **Guardrail Terpenuhi Penuh:**
  - Tidak ada modifikasi pada staging area (`git add` tidak dijalankan).
  - File `.gitignore` tidak diubah.
  - Tidak ada commit Git yang dibuat.
  - Seluruh file eksperimen baru terisolasi di dalam `ml/experiments/ku_mwq_turbidity_features/` dan `backend/docs/`.

---

## KEPUTUSAN & KESIAPAN EKSPERIMEN LANJUTAN

| Evaluasi | Status | Alasan |
|---|---|---|
| **Integritas File Sumber** | **TERVALIDASI** | Ukuran dan SHA-256 identik dengan audit awal. |
| **Reproduksibilitas Metrik** | **TERVALIDASI** | 100% angka deskriptif dan korelasi cocok dengan perhitungan ulang. |
| **Logika Feature Temporal** | **TERVALIDASI** | Kausalitas masa lalu dan reset gap waktu terbukti efektif. |
| **Status Repository** | **BERSIH / STABIL** | Zero commit, zero staging, zero produksi database mutation. |
| **Kesiapan Tahap Lanjutan** | **SIAP** | Pembuktian fitur selesai; siap untuk perumusan spesifikasi arsitektur atau evaluasi data sintetis terkalibrasi. |
