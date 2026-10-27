# STEP 4D.72 — COMPREHENSIVE DATASET INTEGRITY AUDIT (TURBINATOR + MANUAL DATASETS)

**Tanggal Audit:** 15 Oktober 2026  
**Auditor:** Tim Teknis AQUA-TARA / AI Assistant  
**Lingkup:** Audit integritas, kualitas, relevansi, dan reproduktibilitas seluruh dataset kandidat untuk eksperimen temporal turbidity AQUA-TARA. Mencakup dataset Turbinator (Zenodo 7753295), seluruh dataset di `ml/data/dataset/` (SKAB benchmark, Water Potability, Jupyter Notebook), serta perbandingan komparatif terhadap KU-MWQ (STEP 4D.69–4D.71).  
**Status Sistem Produksi:** Unchanged — Read-Only Audit (Zero Supabase, Zero Migration, Zero Production Code Mutation, Zero Git Commit).

---

## 1. RINGKASAN EKSEKUTIF & BATASAN

### 1.1 Ringkasan Eksekutif
Audit komprehensif berhasil menginventarisasi dan memeriksa secara langsung **40 file data**:
1. **Dataset Turbinator (Zenodo 7753295):** Berhasil diakuisisi secara terisolasi ke `ml/data/turbidity/turbinator/`. File berisi 19.012 baris observasi dari 5 sensor optik laser nir-kontak di jaringan drainase air limbah dan limpasan hujan (waste- & stormwater) di Gothenburg, Swedia. **Temuan Kritis:** Sebanyak **88,57% data turbidity bernilai missing (NaN)** (hanya 2.174 nilai valid). Data turbidity yang ada berkisar antara 1 hingga 63 NTU.
2. **Dataset Manual di `ml/data/dataset/` (37 file):**
   - **SKAB (Skoltech Anomaly Benchmark - 35 file CSV):** Terdiri dari 1 file baseline bebas anomali dan 34 file skenario anomali katup/pompa (total 46.860 baris, 13.241 label anomali fisik). Menyediakan variabel hidraulik/mekanik (Pressure, Volume Flow Rate, Temperature, Accelerometer, Voltage), namun **sama sekali tidak memiliki parameter Turbidity / kualitas air**.
   - **Water Potability Dataset (1 file CSV):** Berisi 3.276 baris metrik kualitas air statis (pH, Hardness, Solids, Chloramines, Sulfate, Conductivity, Organic Carbon, Trihalomethanes, Turbidity, Potability). **Tidak memiliki kolom waktu (bukan data time-series)**.
   - **Jupyter Notebook (`water-quality-data.ipynb`):** Notebook analisis eksploratori data potabilitas air tanpa artefak model produksi.
3. **Komparasi dengan KU-MWQ:**
   - KU-MWQ (Mendeley Data v4) menyediakan time-series kontinu resolusi tinggi (~1 menit) namun berasal dari kolam ikan tropis dengan baseline kekeruhan sangat tinggi (134–249 NTU) tanpa label anomali.
   - Turbinator merefleksikan pipa drainase perkotaan dengan rentang NTU realistis (1–63 NTU), namun memiliki tingkat kekosongan data turbidity ekstrem (88,6% missing) dan interval sampling median 1 jam.

### 1.2 Batasan Wajib (Guardrails Compliance)
- **Zero Production Modification:** Formula skor risiko `round(0.8 * Score_T + 0.2 * Score_S)`, clamp 0–100, dan threshold kategori `0–30 Jernih, 31–70 Waspada, 71–100 Bahaya` tidak dimodifikasi.
- **Zero Database Access:** Supabase tidak diakses, tidak ada migrasi, tidak ada seeder yang dijalankan.
- **Zero Git Commit:** Status repository dipertahankan read-only tanpa staging atau commit.
- **Zero Training:** Tidak ada fitting model, scaler, atau optimasi threshold pada tahap ini.

---

## 2. STATUS GIT REPOSITORY SEBELUM & SESUDAH AUDIT

### 2.1 Status Sebelum Audit
```text
?? .gitignore
?? backend/
?? frontend/
?? ml/
```
*(Branch master, No commits yet, seluruh direktori utama untracked).*

### 2.2 Status Sesudah Audit
```text
?? .gitignore
?? backend/
?? frontend/
?? ml/
```
*(Tidak ada file tracked yang dimodifikasi, tidak ada staging `git add`, tidak ada git commit).*

---

## 3. INVENTARISASI SELURUH `ml/data/dataset/` (MANUAL DATASETS)

Inventarisasi rekursif menemukan **37 file**:
- **35 file CSV** pada `ml/data/dataset/SKAB/` (Struktur folder: `anomaly-free/`, `valve1/`, `valve2/`, `other/`).
- **1 file CSV** pada `ml/data/dataset/water_potability.csv`.
- **1 file IPYNB** pada `ml/data/dataset/water-quality-data.ipynb`.
- **Tidak ada file arsip (ZIP/TAR)** atau file biner tersembunyi.

### 3.1 Profil File Utama di `ml/data/dataset/`

| File | Format / Delimiter | Baris | Kolom | Ukuran (Bytes) | SHA-256 | Kategori |
|---|---|---|---|---|---|---|
| `water_potability.csv` | CSV (`,`) | 3.276 | 10 | 425.104 | `79ff73d09a47565e386ebc0ba57adfb2ad73f5509939e6a0d24e54854580fb35` | Tabular Statis |
| `water-quality-data.ipynb`| JSON / IPYNB | 62 cells | N/A | 382.493 | `5f2ca1fb789fe811f0088923bc65727f7db520286820c78a05953046bc28c0b9` | Notebook Riset |
| `SKAB/anomaly-free/anomaly-free.csv` | CSV (`;`) | 9.401 | 9 | 1.157.069 | `96a1e0eb7aa18d53d9e830ee77b47c9451ba28bb9eb6a1329c6691459a9a08eb` | Time-Series Hidraulik |
| `SKAB/valve1/0.csv` s/d `15.csv` (16 file)| CSV (`;`) | ~1.140 per file | 11 | ~140 KB/file | Terlampir di Manifest | Anomali Katup 1 |
| `SKAB/valve2/0.csv` s/d `3.csv` (4 file) | CSV (`;`) | ~1.140 per file | 11 | ~140 KB/file | Terlampir di Manifest | Anomali Katup 2 |
| `SKAB/other/9.csv` s/d `23.csv` (14 file) | CSV (`;`) | ~1.140 per file | 11 | ~140 KB/file | Terlampir di Manifest | Anomali Rotor/Pompa |

*Catatan: Seluruh 37 file tercatat lengkap beserta SHA-256 di `ml/experiments/dataset_integrity_audit/step_4d_72/dataset_inventory_manifest.json` [VERIFIED].*

---

## 4. INVENTARISASI TURBINATOR & KU-MWQ

### 4.1 Dataset Turbinator (Zenodo 7753295)
- **File:** `ml/data/turbidity/turbinator/turbinator_water_quality_observed_202303211549.csv`
- **Ukuran File:** 1.716.210 bytes (~1,63 MB) [VERIFIED]
- **SHA-256 Checksum:** `9df319d16b85a663aa29bed6f384a103bdd0a85d0c0e78c193b859cbf8a5638f` [VERIFIED]
- **Format:** CSV UTF-8 dengan pemisah koma (`,`).
- **Dimensi:** 19.012 baris data dan 5 kolom: `['id', 'dateobserved', 'location', 'turbidity', 'distance']`.

### 4.2 Dataset KU-MWQ (Mendeley Data v4)
- **File 30 cm:** `ml/data/turbidity/ku_mwq/Sensor_Data_30cm.xlsx` (285.192 bytes, SHA-256 `524f6cdecf15cb83e2e82d5db743233034e9e212b4e46c3e1b57cc0b305eb3e8`).
- **File 60 cm:** `ml/data/turbidity/ku_mwq/Sensor_Data_60cm.xlsx` (228.702 bytes, SHA-256 `b4495c7860d614ff9487bb656514e70c53357bb05a94c980f9845ad8a620ce8e`).
- **Dimensi:** 9.623 baris per file. Format Excel OpenXML.

---

## 5. SUMBER RESMI, DOI, LISENSI, & VERIFIKASI METADATA

| Dataset | Sumber Resmi | DOI / URL | Lisensi | Status Verifikasi | Konteks Lingkungan |
|---|---|---|---|---|---|
| **Turbinator** | Zenodo (CERN) | `10.5281/zenodo.7753295` | CC BY 4.0 | **VERIFIED** | Pipa drainase limbah & limpasan hujan perkotaan (Gothenburg, Swedia) |
| **KU-MWQ** | Mendeley Data | `10.17632/34rczh25kc.4` | CC BY 4.0 | **VERIFIED** | Kolam ikan air tawar terbuka (Khulna University, Bangladesh) |
| **SKAB** | Skoltech / GitHub | `10.5281/zenodo.4275727` | MIT License | **VERIFIED** | Testbed sirkulasi air industri tertutup (katup & pompa) |
| **Water Potability** | Kaggle | Kaggle Community | CC0 / Open Database | **UNVERIFIED** (Sumber primer lab belum terlacak) | Parameter kualitas air minum agregat tanpa konteks geografis |

---

## 6. INTEGRITAS & PROFIL STATISTIK SETIAP DATASET

### 6.1 Profil Integritas Turbinator
- **Missing Values:**
  - `id`: 0 (0,0%)
  - `dateobserved`: 0 (0,0%)
  - `location`: 0 (0,0%)
  - `turbidity`: **16.838 baris missing (88,57%)** [TEMUAN KRITIS]
  - `distance`: 0 (0,0%)
- **Statistik Turbidity Valid (2.174 baris valid / 11,43%):**
  - Min: 1,0 NTU
  - Max: 63,0 NTU
  - Mean: 29,30 NTU
  - Median: 27,0 NTU
  - Std Dev: 17,05 NTU
  - Q1 (P25): 14,0 NTU | Q3 (P75): 44,0 NTU | IQR: 30,0 NTU
  - Kuantil Ekstrem: P1 = 3,0 NTU, P5 = 6,0 NTU, P95 = 58,0 NTU, P99 = 61,0 NTU
  - Nilai Negatif: 0
- **Statistik Distance (Jarak Sensor ke Muka Air dalam meter):**
  - Min: -6,05 m *(Ditemukan **93 baris bernilai negatif**, anomali pengukuran pantulan laser optik)*
  - Max: 4,14 m
  - Mean: 2,21 m
  - Median: 2,41 m
  - Std Dev: 1,04 m

### 6.2 Profil Integritas Water Potability (`water_potability.csv`)
- **Missing Values:**
  - `ph`: 491 missing (14,99%)
  - `Sulfate`: 781 missing (23,84%)
  - `Trihalomethanes`: 162 missing (4,95%)
  - `Turbidity`: 0 missing (100% lengkap)
- **Statistik Turbidity:**
  - Min: 1,45 NTU
  - Max: 6,74 NTU
  - Mean: 3,97 NTU
  - Median: 3,96 NTU
  - Std Dev: 0,78 NTU
- **Karakteristik Temporal:** **TIDAK MEMILIKI TIMESTAMP**. Merupakan snapshot tabular observasi laboratorium independen (i.i.d.). Tidak dapat digunakan untuk pemodelan deret waktu atau filter temporal.

### 6.3 Profil Integritas SKAB Benchmark (35 file)
- **Total Baris:** 46.860 baris.
- **Missing Values:** 0 (0,0% pada seluruh sensor).
- **Variabel:** `datetime`, `Accelerometer1RMS`, `Accelerometer2RMS`, `Current`, `Pressure`, `Temperature`, `Thermocouple`, `Voltage`, `Volume Flow RateRMS`.
- **Ground Truth Anomali:** Tersedia kolom `anomaly` (0/1) dan `changepoint` (0/1) pada seluruh 34 skenario pengujian (total 13.241 titik anomali fisik tervalidasi lab).

---

## 7. PROFIL GAP, TIMESTAMP, SENSOR, & GEOSPASIAL

### 7.1 Karakteristik Temporal Turbinator Per-Sensor

Data berasal dari **5 unit sensor independen** yang ditempatkan di 5 sumur inspeksi (manhole drainase) di Gothenburg:

| Sensor ID | Koordinat WKT | Baris Total | Baris Turbidity Valid (%) | Rentang Tanggal Observasi (UTC) | Median $\Delta t$ | Gap Terbesar |
|---|---|---|---|---|---|---|
| `ADN17712` | POINT (11.981038 57.712066) | 6.039 | 56 (0,93%) | 23-09-2020 s/d 15-02-2022 | 3.600 s (1 jam) | 51,5 hari |
| `ADIN43068`| POINT (11.977309 57.715376) | 5.425 | 792 (14,60%) | 19-03-2021 s/d 14-03-2022 | 3.600 s (1 jam) | 26,6 hari |
| `ADIN4483` | POINT (11.985725 57.71457) | 3.051 | 1.066 (34,94%) | 17-04-2021 s/d 10-03-2022 | 3.600 s (1 jam) | 54,1 hari |
| `ADN4633` | POINT (11.989862 57.715244) | 2.443 | 200 (8,19%) | 31-03-2021 s/d 14-03-2022 | 3.600 s (1 jam) | 161,0 hari |
| `ADIN42988`| POINT (11.975122 57.713096) | 2.054 | 60 (2,92%) | 25-09-2020 s/d 11-05-2021 | 3.600 s (1 jam) | 37,2 hari |

**Analisis Geospasial & Waktu Turbinator:**
- Seluruh koordinat berada di Gothenburg, Swedia (Bounding Box: Longitude 11.9751 – 11.9899 E, Latitude 57.7121 – 57.7154 N).
- Sensor tidak berpindah tempat (lokasi statis per ID).
- Interval observasi didesain per 1 jam (3.600 detik). Namun, transmisi data sangat sporadis dengan gap berbulan-bulan akibat kegagalan baterai atau pemeliharaan.
- Hanya 1 sensor (`ADIN4483`) yang memiliki densitas turbidity memadai (1.066 sampel valid) untuk analisis lanjutan.

---

## 8. PERBANDINGAN SELURUH DATASET KANDIDAT

| Parameter Komparasi | KU-MWQ (30cm & 60cm) | Turbinator (Zenodo) | SKAB Benchmark | Water Potability | BattLeDIM 2020 |
|---|---|---|---|---|---|
| **Domain Fisik** | Kolam ikan air tawar | Pipa drainase & stormwater | Testbed pompa industri | Sampel air minum acak | Jaringan pipa PDAM bertekanan |
| **Parameter Turbidity** | Ada (134–249 NTU) | Ada (1–63 NTU) | **Tidak Ada** | Ada (1,4–6,7 NTU) | **Tidak Ada** |
| **Parameter Hidraulik** | Tidak Ada | Ada (Muka air / Distance) | Ada (Pressure, Flow) | Tidak Ada | Ada (Pressure, Flow SCADA) |
| **Karakter Waktu** | Time-series (~1 menit) | Time-series (~1 jam) | Time-series (~1 detik) | **Bukan time-series** | Time-series (~5 menit) |
| **Kelengkapan Turbidity**| 100% lengkap (0 missing) | 11,4% lengkap (88,6% NaN) | N/A | 100% lengkap | N/A |
| **Ground Truth Anomali** | **Tidak Ada** | **Tidak Ada** | **Ada (Fisik katup/pompa)**| Ada (Label Potability statis)| **Ada (Label kebocoran pipa)**|
| **Kesesuaian Distribusi**| Ekstrem Bahaya (>134 NTU)| Realistis Pipa (1–63 NTU) | N/A | Sangat Bersih (<7 NTU)| N/A |

---

## 9. PENILAIAN KECOCOKAN PER TUJUAN SISTEM AQUA-TARA

| Tujuan Evaluasi | KU-MWQ | Turbinator | SKAB | Water Potability | Keputusan & Justifikasi |
|---|---|---|---|---|---|
| **1. Eksperimen Feature Engineering Temporal** | **ACCEPT** | **CONDITIONAL** | **REJECT** | **REJECT** | **KU-MWQ** paling solid untuk memvalidasi algoritma rolling mean/std/Z-score berkat sampling kontinu 1 menit. **Turbinator** hanya dapat digunakan bersyarat (CONDITIONAL) pada sensor `ADIN4483` setelah membuang nilai missing. SKAB dan Potability tidak cocok. |
| **2. Eksperimen Deteksi Anomali Berlabel (Ground Truth)** | **REJECT** | **REJECT** | **ACCEPT** (Domain Hidraulik) | **REJECT** | Baik KU-MWQ maupun Turbinator **tidak memiliki ground truth anomali**. **SKAB** adalah satu-satunya dataset berlabel fisik terverifikasi untuk anomali hidraulik (bersama BattLeDIM). |
| **3. Eksperimen Sensor IoT AQUA-TARA** | **CONDITIONAL** | **CONDITIONAL** | **CONDITIONAL** | **REJECT** | Tidak ada satu dataset tunggal yang mencakup Turbidity + Pressure + Flow secara simultan. Evaluasi sensor harus dilakukan secara multi-modal terpisah. |
| **4. Kalibrasi Threshold Operasional Produksi** | **REJECT** | **REJECT** | **REJECT** | **REJECT** | **Seluruh dataset ditolak (REJECT)** untuk penentuan threshold produksi. Threshold AQUA-TARA (0–30 Jernih, 31–70 Waspada, 71–100 Bahaya) wajib mengacu pada standar baku mutu air minum nasional (Permenkes 2/2023). |
| **5. Klaim Kualitas Air Minum** | **REJECT** | **REJECT** | **REJECT** | **REJECT** | Kekeruhan rendah tidak menjamin air aman diminum tanpa pengujian mikrobiologis (E. coli) dan kimiawi lengkap. |

---

## 10. KETERBATASAN & RISIKO METODOLOGIS

1. **Missing Data Turbinator (88,57%):** Pemanfaatan data turbidity Turbinator tidak boleh dilakukan dengan interpolasi linier naif melintasi gap mingguan/bulanan.
2. **Ketiadaan Sensor Turbidity pada Dataset Hidraulik:** BattLeDIM dan SKAB memiliki label ground truth hidraulik yang sempurna tetapi tidak merekam turbidity. KU-MWQ dan Turbinator memiliki turbidity tetapi tidak memiliki label anomali pipa.
3. **Pemisahan Domain Pengukuran:** Data kolam ikan (KU-MWQ), air drainase jalan (Turbinator), dan air minum pipa bertekanan memiliki dinamika fisik yang sangat berbeda. Menggabungkan data-data ini ke dalam satu model tunggal tanpa kalibrasi domain akan merusak generalisasi model.

---

## 11. REKOMENDASI EKSPERIMEN BERIKUTNYA

1. **Gunakan KU-MWQ Khusus Prototipe Feature Engineering Temporal:**  
   Algoritma deteksi lonjakan lokal (first difference, causal rolling Z-score, gap-reset logic) telah terbukti valid pada STEP 4D.70 dan dapat diimplementasikan sebagai pustaka utilitas modular.
2. **Eksplorasi Sensor `ADIN4483` Turbinator untuk Karakterisasi Pipa Riil:**  
   Gunakan 1.066 sampel valid pada sensor `ADIN4483` Turbinator untuk mengamati rentang dinamika turbidity di lingkungan perpipaan drainase kota (1–63 NTU) sebagai pembanding variabilitas terhadap KU-MWQ.
3. **Pertahankan BattLeDIM untuk Arsitektur Deteksi Kebocoran Pipa:**  
   Model deteksi kebocoran dan anomali jaringan pipa transmisi tetap menggunakan benchmark BattLeDIM 2020 yang telah terkalibrasi.

---

## 12. ARTEFAK & CARA MENJALANKAN ULANG AUDIT

Seluruh artefak audit disimpan secara terisolasi di:  
`ml/experiments/dataset_integrity_audit/step_4d_72/`

### Berkas yang Dihasilkan:
1. `run_comprehensive_audit.py`: Skrip audit mandiri (read-only).
2. `dataset_inventory_manifest.json`: Manifest inventarisasi 40 file (path, ukuran byte, SHA-256, format, kolom, status).
3. `dataset_statistical_summary.json`: Ringkasan statistik detail Turbinator, Water Potability, SKAB, dan KU-MWQ.
4. `turbinator_timeseries.png`: Plot time-series turbidity dan muka air Turbinator per-sensor.
5. `cross_dataset_turbidity_comparison.png`: Histogram perbandingan distribusi kekeruhan lintas dataset terhadap standar Permenkes 2/2023 dan threshold AQUA-TARA.

### Cara Menjalankan Ulang:
```bash
python ml/experiments/dataset_integrity_audit/step_4d_72/run_comprehensive_audit.py
```

---

## 13. PERNYATAAN EKSPLISIT KEPATUHAN SISTEM

Kami menyatakan secara eksplisit bahwa selama pelaksanaan STEP 4D.72:
1. **Kode produksi** (backend Laravel, frontend Vue, dan modul `ml/app/`) **tidak diubah**.
2. **Formula skor risiko**, parameter bobot, dan threshold klasifikasi **tidak dimodifikasi**.
3. **Database Supabase dan database lokal** tidak pernah diakses, dimutasi, atau di-reset.
4. File `.env` dan `.env.testing` **tidak diubah dan tidak dibaca**.
5. File dataset asli tidak ada yang dipindahkan, diubah namanya, atau ditimpa.
6. Tidak ada commit Git yang dibuat.
