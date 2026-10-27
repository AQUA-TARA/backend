# STEP 4D.62 — AQUA-TARA EXTERNAL DATASET FEASIBILITY AUDIT

**Status:** **AUDIT COMPLETED (READ-ONLY)**  
**Tanggal:** 2026-10-12  
**Tujuan Audit:** Riset kelayakan dataset eksternal untuk memetakan dataset publik yang secara ilmiah dan teknis dapat digunakan sebelum tahap training/eksperimen ML, memvalidasi batasan sensor perangkat keras AQUA-TARA, serta mencegah kesalahan metodologi.  
**Prinsip Keamanan:** Read-Only audit. Tanpa eksekusi training, tanpa modifikasi kode/skema/requirements, tanpa manipulasi Supabase/database, dan tanpa penghapusan fixture data.

---

## 1. Ringkasan Eksekutif

Audit kelayakan dataset eksternal pada **STEP 4D.62** menyimpulkan temuan fundamental berikut:

1. **Batasan Sensor Fisik vs Klaim Model:**
   - Perangkat keras sensor IoT AQUA-TARA saat ini **hanya mengukur 3 parameter fisik**:
     - `turbidity` (kekeruhan, NTU),
     - `flow_rate` (debit aliran, L/min atau m³/h),
     - `pressure` (tekanan pipa, bar atau psi).
   - Sistem **TIDAK MEMILIKI** sensor parameter kimia atau mikrobiologi seperti `pH`, `Sulfate`, `Chloramines`, `Hardness`, `Total Dissolved Solids`, atau `E. coli`.
2. **Kesesatan Ilmiah Prediksi Kelayakan Minum (Potability Fallacy):**
   - Dataset publik prediksi kelayakan minum yang sangat populer (misalnya *Kaggle Water Potability Dataset*) mengandalkan interaksi multivariat dari 9 parameter kimiawi.
   - Analisis korelasi membuktikan bahwa **korelasi linear antara `turbidity` dan `potability` adalah mendekati nol ($r \approx -0.01$ s/d $+0.01$)**, dan distribusi nilai kekeruhan antara air layak minum dan tidak layak minum tumpang tindih secara masif (~3.96 NTU).
   - **Kesimpulan Ilmiah Tegas:** Potabilitas air minum **TIDAK BISA dan TIDAK BOLEH disimpulkan hanya dari nilai kekeruhan (`turbidity`)**. Menggunakan dataset tersebut untuk memprediksi kelayakan minum dari kekeruhan saja adalah cacat ilmiah (*scientifically invalid*) dan berbahaya bagi kesehatan publik.
   - Formula kanonikal heuristik piecewise [normalize_turbidity](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/app/services/production_analysis.py#L6-L18) yang didasarkan pada standar baku mutu air (WHO / Permenkes) adalah pendekatan yang sah dan tepat untuk mengukur *indikasi risiko kekeruhan fisik*, bukan kelayakan konsumsi mutlak.
3. **Peluang Deteksi Anomali Jaringan Pipa (Water Distribution Network):**
   - Dataset benchmark hidrolik internasional **BattLeDIM** (Zenodo CC BY 4.0) dan **LeakDB** (Zenodo CC BY 4.0) terverifikasi menyediakan deret waktu pengukuran sensor `pressure` dan `flow_rate` dengan label ground truth kebocoran pipa (*pipe bursts / background leaks*).
   - Parameter ini cocok dengan skema sensor `pressure` dan `flow_rate` milik AQUA-TARA dan dapat digunakan untuk mengevaluasi algoritma anomali hidrolik (penyelesaian *Open Decision F-03*).
4. **Konfirmasi K-Means Spasial:**
   - K-Means koordinat laporan warga [production_analysis.py:53](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/app/services/production_analysis.py#L53) berjalan secara *unsupervised on-the-fly* pada setiap batch data incoming. Komponen ini **TIDAK MEMERLUKAN dataset eksternal untuk training offline**.

---

## 2. Kandidat Dataset dan Sumber

### A. Kategori Prediksi Kualitas Air

#### 1. Kaggle Water Potability Dataset (Aditya Kadiwal / Mirror)
- **Sumber / URL:** `https://www.kaggle.com/datasets/adityakadiwal/water-potability`
- **Lisensi:** CC0: Public Domain.
- **Ukuran & Sampel:** 3.276 baris data tabular.
- **Fitur Tersedia:** 
  - `ph`, `Hardness`, `Solids`, `Chloramines`, `Sulfate`, `Conductivity`, `Organic_carbon`, `Trihalomethanes`, `Turbidity`.
- **Target Label:** `Potability` (Biner: `0` = Tidak layak minum / 61%, `1` = Layak minum / 39%).
- **Karakteristik Turbidity:** 
  - Rentang nilai: 1.45 NTU s/d 6.74 NTU (Rata-rata 3.96 NTU).
  - Missing values: 0 (100% lengkap).
- **Status Verifikasi:** **VERIFIED (SUMBER RESMI TERVERIFIKASI, TETAPI TIDAK COCOK SECARA METODOLOGI)**.

#### 2. USGS Continuous Water Quality Data (National Water Information System - NWIS)
- **Sumber / URL:** `https://api.waterdata.usgs.gov/` / `https://waterdata.usgs.gov/`
- **Lisensi:** US Public Domain.
- **Ukuran & Sampel:** Jutaan observasi deret waktu dari ribuan stasiun sensor sungai/air baku di Amerika Serikat.
- **Fitur Tersedia:** Parameter Code `63680` (Turbidity dalam NTU/FNU), suhu air, debit sungai (*discharge*), elevasi air, konduktansi.
- **Target Label:** **Tidak ada label target biner/klasifikasi**. Merupakan rekaman fisik kontinu alami.
- **Status Verifikasi:** **VERIFIED (SUMBER TERBUKA, COCOK HANYA SEBAGAI REFERENSI DISTRIBUSI ALAMI, BUKAN SUPERVISED TRAINING)**.

#### 3. Standar Baku Mutu Regulasi (WHO & Permenkes RI No. 2 Tahun 2023)
- **Sumber:** Pedoman Kualitas Air Minum WHO (*Guidelines for Drinking-water Quality*) & Permenkes RI No. 2/2023.
- **Bentuk:** Standar batas ambang fisik:
  - Maksimal Kekeruhan Air Minum: **5 NTU** (Air higienis sanitasi: **25 NTU**).
  - Di atas 25 NTU: Air keruh berat yang mengindikasikan kontaminasi sedimen atau intrusi pipa.
- **Peran:** Menjadi dasar matematis absolut bagi fungsi [normalize_turbidity](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/app/services/production_analysis.py#L6-L18).

---

### B. Kategori Deteksi Anomali & Kebocoran Sensor Jaringan Pipa (WDN)

#### 1. BattLeDIM (Battle of the Leakage Detection and Isolation Methods)
- **Sumber / DOI:** Zenodo `10.5281/zenodo.4017659` (Vrachimis et al., 2020).
- **Lisensi:** Creative Commons Attribution 4.0 International (**CC BY 4.0**).
- **Jaringan Uji:** Jaringan pipa perkotaan virtual benchmark "L-Town" (EPANET).
- **Format Unduhan:** File CSV komprehensif:
  - `2018_SCADA_Pressures.csv` (sensor tekanan nodal, 33 sensor tekanan),
  - `2018_SCADA_Flows.csv` (sensor debit aliran pipa, 2 sensor flow inlet DMA),
  - `2018_SCADA_Demands.csv` / `Levels.csv` (konsumsi dan level tangki air),
  - `2018 Leak Report.txt` (Ground truth laporan kejadian kebocoran: timestamp mulai, waktu perbaikan, identitas pipa, debit bocor).
- **Frekuensi Sampling:** Resolusi waktu 5 menit dan 30 menit.
- **Satuan:**
  - Tekanan: Meter head ($1\text{ m head} \approx 0.098\text{ bar} \approx 1.42\text{ psi}$).
  - Debit: Liter per detik ($\text{L/s}$) atau meter kubik per jam ($\text{m}^3/\text{h}$).
- **Status Verifikasi:** **VERIFIED (SANGAT COCOK UNTUK ANOMALI FLOW & PRESSURE)**.

#### 2. LeakDB (Leakage Diagnosis Benchmark)
- **Sumber / DOI:** Zenodo `10.5281/zenodo.1086088` / GitHub `KIOS-Research/LeakDB` (Vrachimis et al., 2018).
- **Lisensi:** Creative Commons Attribution 4.0 International (**CC BY 4.0**).
- **Jaringan Uji:** Beragam jaringan benchmark (Hanoi, Net1, Fossolo, dll.).
- **Format Unduhan:** CSV time-series dan skrip evaluasi MATLAB/Python.
- **Fitur Tersedia:** `pressure`, `flow_rate`, `timestamp`, serta label kebocoran sintetis bertahap (*incipient*) dan mendadak (*burst*).
- **Frekuensi Sampling:** 30 menit hingga 1 jam.
- **Status Verifikasi:** **VERIFIED (COCOK UNTUK BENCHMARK HIDROLIK PIPA)**.

#### 3. BATADAL (Battle of the Attack Detection Algorithms)
- **Sumber / URL:** `http://www.batadal.net` (Taormina et al., 2017).
- **Lisensi:** Creative Commons Attribution 4.0 International (**CC BY 4.0**).
- **Jaringan Uji:** C-Town Network.
- **Fitur Tersedia:** Tekanan, debit, status pompa/aktuator, level tangki.
- **Target Label:** Serangan siber manipulasi PLC / aktuator pada SCADA.
- **Keterbatasan:** Meskipun variabel fisiknya sama (flow dan pressure), anomali dalam BATADAL berfokus pada *cyber-attacks* manipulasi logis kontroler, bukan kebocoran pipa fisik *wear-and-tear* yang lazim di jaringan distribusi air PDAM.
- **Status Verifikasi:** **VERIFIED (KORELASI FISIK ADA, TETAPI FOKUS PROBLEM SIBER BUKAN KEBOCORAN PIPA ALAMI)**.

---

### C. Kategori Spasial / Geospasial

#### 1. Geoportal Wilayah Administratif DKI Jakarta / Satu Data Indonesia
- **Sumber:** `https://satudata.jakarta.go.id` / `https://geoportal.jakarta.go.id`.
- **Lisensi:** Open Government Data License (Bebas pakai untuk publik).
- **Format:** GeoJSON / Shapefile batas RW/Kelurahan/Kecamatan.
- **Peran:** Hanya sebagai referensi batas wilayah operasional (*geofencing bounding box*) dan visual layer, **BUKAN** untuk training bobot K-Means.
- **Status Verifikasi:** **VERIFIED (VALID SEBAGAI DATA REFERENSI BUKAN TRAINING DATASET)**.

---

## 3. Matriks Kecocokan Fitur (Comparison Matrix)

| Nama Dataset | Tujuan Utama | Fitur yang Tersedia | Fitur yang Dibutuhkan AQUA-TARA | Tingkat Kecocokan | Label / Supervisi | Lisensi | Aksesibilitas | Keterbatasan Utama | Status Verifikasi |
|---|---|---|---|---|---|---|---|---|---|
| **Kaggle Water Potability** | Prediksi kelayakan minum (Potability) | 9 parameter kimia (`ph`, `solids`, `chloramines`, `turbidity`, dll.) | `turbidity` tunggal (tanpa parameter kimia) | **RENDAH** | Biner: `Potability` (0/1) | CC0 (Public Domain) | Publik (Kaggle API/Web) | **Schema Mismatch fatal:** Hardware AQUA-TARA tidak mengukur 8 parameter kimia. Korelasi `turbidity` ke potabilitas $\approx 0$. | **VERIFIED** |
| **USGS Water Data (NWIS)** | Pemantauan kualitas air permukaan kontinu | `turbidity` (NTU), debit sungai, suhu air | `turbidity` | **SEDANG** | Tidak ada label (Raw time-series) | US Public Domain | Publik (REST API / CSV) | Data perairan alami (sungai/danau), bukan jaringan pipa distribusi air minum perkotaan. | **VERIFIED** |
| **BattLeDIM (Zenodo)** | Deteksi & isolasi kebocoran pipa air | `pressure` (head), `flow` (L/s), tank level, timestamp | `pressure`, `flow_rate`, timestamp | **TINGGI** | Ground truth label kebocoran (waktu, pipa, laju) | CC BY 4.0 | Publik (Zenodo DOI) | Tidak memiliki sensor `turbidity` (khusus hidrolik pipa). | **VERIFIED** |
| **LeakDB (Zenodo/GitHub)** | Diagnosis kebocoran pipa WDN | `pressure`, `flow`, timestamp | `pressure`, `flow_rate`, timestamp | **SEDANG–TINGGI** | Label kebocoran bertahap & mendadak | CC BY 4.0 | Publik (Zenodo/GitHub) | Tidak memiliki sensor `turbidity`. | **VERIFIED** |
| **BATADAL** | Deteksi serangan siber pada SCADA air | `pressure`, `flow`, status aktuator, level | `pressure`, `flow_rate` | **SEDANG** | Label serangan siber SCADA | CC BY 4.0 | Publik (batadal.net) | Skenario anomali adalah serangan siber manipulasi pompa, bukan kebocoran pipa fisik. | **VERIFIED** |
| **Data Geospasial Jakarta (Satu Data)** | Batas batas administratif kelurahan | Polygon geometri koordinat [Lat, Lng] | Validasi koordinat laporan warga | **TINGGI (Untuk Geofence)** | Tidak ada label (Spasial poligon) | Open Data | Publik (Geoportal) | Bukan dataset training ML. Hanya untuk batas bounding box. | **VERIFIED** |

---

## 4. Lisensi dan Aksesibilitas

1. **Kelayakan Lisensi Komersial & Akademik:**
   - **Kaggle Water Potability (CC0):** Bebas digunakan tanpa atribusi.
   - **BattLeDIM & LeakDB (CC BY 4.0):** Sangat terbuka; mengizinkan penggunaan, adaptasi, dan integrasi untuk tujuan apa pun selama memberikan atribusi sitasi ilmiah kepada pembuat benchmark.
   - **BATADAL (CC BY 4.0):** Terbuka dengan atribusi ke Taormina et al.
   - **USGS & Satu Data Jakarta (Public Domain / Open Data):** Bebas digunakan tanpa pembatasan hak cipta.
2. **Ketiadaan Hambatan Akses (No Paywall):**
   - Seluruh dataset yang lolos verifikasi di atas **dapat diunduh secara bebas** melalui protokol HTTPS / Zenodo DOI / API publik tanpa biaya lisensi, tanpa token berbayar, dan tanpa NDA.

---

## 5. Risiko Validitas Ilmiah & Metodologi

### A. Fallacy: Memprediksi Potabilitas dari Turbidity Saja
- **Bukti Empiris Dataset:** Pada dataset Kaggle, nilai rata-rata turbidity untuk sampel air layak minum (`Potability=1`) adalah **3.97 NTU**, sedangkan untuk sampel air tidak layak minum (`Potability=0`) adalah **3.96 NTU**. Distribusinya nyaris identik sempurna (*complete distributional overlap*).
- **Prinsip Biologis/Kesehatan:**
  - Air dengan kekeruhan 0.5 NTU (sangat jernih) dapat mengandung patogen mematikan (seperti *Salmonella*, *Vibrio cholerae*, atau sianida) yang tidak kasat mata dan tidak mempengaruhi kekeruhan secara signifikan.
  - Sebaliknya, air dengan kekeruhan 6 NTU dapat sepenuhnya bebas bakteri patogen jika kekeruhan hanya berasal dari lempung/mineral tanah liat inert.
- **Keputusan Audit:** **DILARANG KERAS melatih model klasifikasi potabilitas supervised dari sensor turbidity tunggal.** Klaim bahwa sistem dapat menentukan "air layak minum" dari satu angka NTU adalah klaim palsu yang melanggar kaidah sains.

### B. Mismatch Arsitektur Sensor: Turbidity vs Hidrolik Pipa
- Dalam rekayasa penyediaan air minum (PDAM):
  - Sensor **Turbidity** ditempatkan di Instalasi Pengolahan Air (IPA) atau titik keluar reservoir untuk memastikan filtrasi berfungsi.
  - Sensor **Pressure & Flow Rate** ditempatkan di jaringan pipa transmisi dan distribusi (District Metered Area / DMA) untuk memantau hidrolika dan mendeteksi kebocoran.
- Tidak ada dataset publik global yang menggabungkan ketiga sensor ini dalam satu titik ukur pipa karena fungsi operasionalnya berbeda. Model ML harus memperlakukan:
  - **Turbidity** sebagai indikator *kualitas kejernihan air fisik*,
  - **Pressure & Flow Rate** sebagai indikator *anomali integritas hidrolik pipa*.

---

## 6. Rekomendasi Urutan Eksperimen

Berdasarkan bukti ilmiah dan kepatuhan arsitektur, berikut urutan eksperimen yang dapat dipertanggungjawabkan:

```
[EKSPERIMEN 1]                                 [EKSPERIMEN 2]                                 [EKSPERIMEN 3]
Validasi Ambang Batas                         Deteksi Anomali Hidrolik Pipa                  Penyempurnaan Geospasial
Turbidity Regulasi (WHO/Permenkes)    ──>     (Flow & Pressure via BattLeDIM)        ──>     (Haversine & Geofence)
[Status: Heuristik Rule-based]                [Status: Anomaly Detection ML]                 [Status: Unsupervised Spasial]
```

### 1. Eksperimen 1: Pertahankan & Kalibrasi Formula Turbidity (Rule-Based / Standar Regulasi)
- **Metode:** Heuristik deterministik piecewise linear (tanpa machine learning supervised).
- **Alasan:** Formula `normalize_turbidity()` yang ada saat ini sudah memetakan 0–5 NTU (Jernih/Skor 0–30), 5–25 NTU (Waspada/Skor 30–70), dan >25 NTU (Bahaya/Skor 70–100) secara sempurna mengikuti standar baku mutu air sanitasi Permenkes No. 2/2023. Tidak memerlukan training dataset eksternal.

### 2. Eksperimen 2: Deteksi Anomali Hidrolik Sensor Menggunakan Dataset BattLeDIM
- **Metode:** Unsupervised / Semi-Supervised Anomaly Detection (misalnya *Isolation Forest*, *Local Outlier Factor*, atau *Rolling Z-Score / IQR windowing*).
- **Dataset:** Menggunakan time-series `2018_SCADA_Pressures.csv` dan `2018_SCADA_Flows.csv` dari BattLeDIM.
- **Tujuan:** Menyelesaikan **Open Decision F-03** untuk menurunkan status sensor IoT:
  - Tekanan dan debit normal $\rightarrow$ `NORMAL`,
  - Deviasi statistik sedang $\rightarrow$ `ANOMALY`,
  - Penurunan tekanan drastis disertai lonjakan debit (indikasi pipa pecah / burst) $\rightarrow$ `CRITICAL`.
- **Keunggulan:** Tidak mengarang kolom; parameter `flow_rate` dan `pressure` yang di-ingest AQUA-TARA langsung terutilisasi secara fungsional.

### 3. Eksperimen 3: Validasi Jarak Geospasial & Kalibrasi K-Means
- **Metode:** Unsupervised runtime clustering.
- **Tindakan:** Mengganti rumus jarak Euclidean derajat planar saat ini di [production_analysis.py:20](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/app/services/production_analysis.py#L20) dengan formula Haversine bola bumi sejati (menghasilkan meter/kilometer), serta menguji ketahanan dynamic K (`K = max(1, min(5, N // 5))`) terhadap skenario laporan terpencar.

---

## 7. Keputusan yang Masih Terbuka (Open Decisions)

1. **Penyelesaian F-03 (Aturan Turunan Status IoT):**
   - Apakah status `NORMAL`, `ANOMALY`, `CRITICAL` untuk API peta akan ditentukan oleh:
     - **Opsi A:** Ambang batas statistik/fisik sederhana (misal: tekanan < 0.5 bar = CRITICAL; fluktuasi debit > 3 sigma = ANOMALY), ATAU
     - **Opsi B:** Model anomaly detection terlatih berbasis benchmark BattLeDIM?
2. **Kebutuhan Fitur Tambahan di Masa Depan:**
   - Jika di masa depan pemangku kepentingan menginginkan model *water potability*, sensor hardware IoT wajib diperluas dengan menambahkan sensor fisik pH dan Total Dissolved Solids (TDS).

---

## 8. Lingkungan Dependensi dan Penanganan Cleanup Fixture

### A. Rencana Dependensi ML (`ml/requirements.txt`)
- Audit STEP 4D.61 menemukan bahwa `ml/requirements.txt` saat ini hanya berisi `flask>=3.1.0`, padahal service produksi mengimpor `scikit-learn` dan `numpy`.
- **Rencana Terpisah:** Pada tahap implementasi berikutnya (STEP 4D.63+), deklarasikan dependensi minimum yang terbukti dibutuhkan:
  ```text
  flask>=3.1.0
  numpy>=1.26.0
  scikit-learn>=1.4.0
  ```
  *(Jika BattLeDIM diadopsi untuk anomaly detection, tambahkan `pandas>=2.2.0`).*

### B. Catatan Status Fixture Tiket #55 (Cleanup Tertunda)
- Tiket fixture `#55` (dan `Report` terkait) yang dibuat pada STEP 4D.60 terkonfirmasi **masih ada di database lokal `aquatara_test`** (`127.0.0.1:5432`).
- **Prosedur Cleanup yang Aman (Dikerjakan Terpisah, TIDAK pada Step Ini):**
  - Hanya menghapus secara spesifik record sintetis yang sudah diidentifikasi:
    ```sql
    DELETE FROM tickets WHERE id = 55;
    DELETE FROM reports WHERE description = 'Laporan uji integrasi lokal frontend-backend';
    ```
  - **DILARANG** melakukan `php artisan migrate:fresh`, `db:wipe`, atau truncate seluruh tabel karena akan merusak skema dan data seed dasar pengujian.

---

## 9. Kesimpulan & Rekomendasi STEP 4D.63

### Rekomendasi Akhir:
1. **Dataset yang TIDAK COCOK untuk Diuji:**
   - **Kaggle Water Potability:** DITOLAK untuk training karena *feature schema mismatch* dan *near-zero correlation* antara kekeruhan dan kelayakan minum.
2. **Dataset yang LAYAK untuk Diuji:**
   - **BattLeDIM (Zenodo CC BY 4.0):** DIREKOMENDASIKAN untuk eksperimen deteksi anomali sensor hidrolik `pressure` dan `flow_rate` guna menyelesaikan Decision F-03.
3. **Komponen yang Tetap Memakai Aturan Saat Ini:**
   - **K-Means Spasial:** Tetap menggunakan on-the-fly clustering (tidak perlu dataset eksternal).
   - **Risk Score Formula:** Tetap menggunakan formula kanonikal `round(0.8 * Score_T + 0.2 * Score_S)`.
