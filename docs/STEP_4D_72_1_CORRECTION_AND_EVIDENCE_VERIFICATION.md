# STEP 4D.72.1 — AUDIT KOREKSI DAN VERIFIKASI BUKTI

**Tanggal Audit:** 15 Oktober 2026  
**Auditor:** Tim Teknis AQUA-TARA / AI Data & ML Auditor  
**Lingkup:** Verifikasi bukti empiris dan audit koreksi temuan STEP 4D.72 berdasarkan berkas aktual, metadata API sumber resmi (Zenodo), manifest inventarisasi, kalkulasi hash MD5 & SHA-256, breakdown missing turbidity per sensor, segmentasi temporal, serta pemisahan label SKAB benchmark.  
**Status Sistem Produksi:** Unchanged — Read-Only Audit (Zero Supabase, Zero Migration, Zero Production Code Mutation, Zero Git Commit).

---

## 1. RINGKASAN EKSEKUTIF & STATUS AUDIT

Status Akhir: **PASS**  
Seluruh 10 pertanyaan kritis telah diverifikasi secara independen melalui inspeksi langsung terhadap berkas lokal, API resmi, dan skrip reproduksi terisolasi.

### Ringkasan Temuan Kritis & Koreksi:
1. **Lisensi Turbinator:** **VERIFIED (CC BY 4.0).**  
   Metadata Zenodo REST API (`https://zenodo.org/api/records/7753295`) secara eksplisit menyatakan `license: {'id': 'cc-by-4.0'}`. Checksum MD5 dari server Zenodo (`409a6122766349bf29bd11a53fb47be6`) cocok 100% dengan hasil kalkulasi MD5 pada berkas lokal.
2. **Kekosongan Turbidity Turbinator (Missing Data Breakdown):** **VERIFIED & CORRECTED.**  
   Total baris adalah **19.012 baris**. Data turbidity valid adalah **2.174 baris (11,43%)**, dan missing adalah **16.838 baris (88,57%)**. Persentase kekosongan per sensor sangat timpang:
   - `ADN17712`: **99,07% missing** (hanya 56 nilai valid dari 6.039 baris).
   - `ADIN42988`: **97,08% missing** (hanya 60 nilai valid dari 2.054 baris).
   - `ADN4633`: **91,81% missing** (hanya 200 nilai valid dari 2.443 baris).
   - `ADIN43068`: **85,40% missing** (792 nilai valid dari 5.425 baris).
   - `ADIN4483`: **65,06% missing** (1.066 nilai valid dari 3.051 baris).
3. **Kelayakan Fitur Temporal Turbinator:** **SANGAT TERBATAS (CONDITIONAL HANYA UNTUK 1 SENSOR).**  
   Dengan proteksi gap kausal (reset segmentasi jika gap sampling valid $\Delta t > 2$ jam):
   - **Window $w=5$:** Memadai untuk 4 sensor (`ADIN4483`, `ADIN43068`, `ADN4633`, `ADIN42988`).
   - **Window $w=15$:** Memadai untuk 4 sensor (`ADIN4483` = 314 window, `ADIN43068` = 189 window, `ADN4633` = 46 window, `ADIN42988` = 4 window).
   - **Window $w=60$:** **HANYA 1 SENSOR YANG MEMADAI**, yaitu `ADIN4483` (memiliki 46 window valid; panjang segmen kontinu maksimum 85 jam). Empat sensor lainnya menghasilkan **0 window valid** pada $w=60$.
   - Sensor `ADN17712` sama sekali **TIDAK MEMADAI** untuk window apa pun (segmen kontinu maksimum hanya 4 sampel, $w=5$ menghasilkan 0 window).
4. **Distribusi & Struktur Label SKAB Benchmark:** **VERIFIED & DISAMBIGUATED.**  
   Struktur 35 berkas SKAB diaudit langsung dari disk:
   - Terdiri dari 1 berkas baseline bebas anomali (`anomaly-free.csv`) dan 34 berkas skenario gangguan fisik.
   - Kolom `anomaly` (status biner: 0 = kondisi normal, 1 = periode anomali aktif) mencakup **13.241 titik anomali** (~35,3% dari 37.459 baris pada file anomali).
   - Kolom `changepoint` (titik transisi impulsif onset/offset anomali) mencakup **130 titik transisi**.
   - **Koreksi Semantik:** Anomali SKAB adalah gangguan fisik mekanis katup dan kavitasi pompa laboratorium tertutup. Ini **bukan representasi kebocoran pipa air PDAM AQUA-TARA**, melainkan benchmark metodologi algoritma deteksi multivariat.
5. **Koreksi Regulasi Permenkes No. 2 Tahun 2023:**  
   Peraturan Menteri Kesehatan RI No. 2 Tahun 2023 menetapkan Standar Baku Mutu Kesehatan Lingkungan untuk Media Air Minum dengan kadar kekeruhan maksimum **3 NTU** (sebelumnya pada Permenkes 492/2010 adalah 5 NTU). Threshold deteksi operasional AQUA-TARA (0–30 Jernih, 31–70 Waspada, 71–100 Bahaya) diklarifikasi sebagai ambang batas skoring risiko multi-parameter, bukan baku mutu laboratorium tunggal.

---

## 2. STATUS GIT REPOSITORY SEBELUM & SESUDAH AUDIT

### 2.1 Git Status Sebelum Audit
```text
?? .gitignore
?? backend/
?? frontend/
?? ml/
```
*(Branch master, No commits yet, seluruh direktori utama untracked).*

### 2.2 Git Status Sesudah Audit
```text
?? .gitignore
?? backend/
?? frontend/
?? ml/
```
*(Zero file tracked termodifikasi, zero staging area `git add`, zero commit Git, integritas file sumber 100% terjaga).*

---

## 3. AUDIT BAGIAN A: LISENSI DAN INTEGRITAS TURBINATOR

Sumber Resmi: `https://zenodo.org/records/7753295`  
Berkas Lokal: `ml/data/turbidity/turbinator/turbinator_water_quality_observed_202303211549.csv`

### 3.1 Metadata Terverifikasi Zenodo REST API
Permintaan langsung ke Zenodo API (`https://zenodo.org/api/records/7753295`) mengembalikan metadata primer berikut:
- **Judul:** Water quality data from Turbinator [VERIFIED]
- **Penulis:** Fredrik Hallgren, Joel Wanemark (IVL Swedish Environmental Research Institute) [VERIFIED]
- **DOI:** `10.5281/zenodo.7753295` [VERIFIED]
- **Tanggal Publikasi:** 21 Maret 2023 [VERIFIED]
- **Status Lisensi:** `license: {'id': 'cc-by-4.0'}` [VERIFIED]
  - Lisensi: Creative Commons Attribution 4.0 International (CC BY 4.0).
  - Hak Penggunaan: Bebas digunakan, dibagikan, dan diadaptasi untuk keperluan riset dan komersial dengan kewajiban atribusi penulis asli.
- **Checksum Server Zenodo:**
  - File: `turbinator_water_quality_observed_202303211549.csv`
  - Ukuran: `1.716.210` bytes
  - Hash MD5 Server: `409a6122766349bf29bd11a53fb47be6`

### 3.2 Pencocokan Hash Berkas Lokal
Hasil komputasi kriptografis independen pada berkas lokal:
- **Ukuran Lokal:** `1.716.210` bytes (Sesuai 100%)
- **Hash MD5 Lokal:** `409a6122766349bf29bd11a53fb47be6` (Cocok 100% dengan checksum Zenodo)
- **Hash SHA-256 Lokal:** `9df319d16b85a663aa29bed6f384a103bdd0a85d0c0e78c193b859cbf8a5638f`
- **Status Lisensi & Keaslian:** **VERIFIED (CC BY 4.0, Authentic Unmodified Mirror)**.

---

## 4. AUDIT BAGIAN B: MISSING TURBINATOR & STATISTIK DESKRIPTIF

### 4.1 Metodologi Perhitungan Missing
Perhitungan missing value dijalankan langsung pada kolom `turbidity` bertipe `float64`:
$$\text{Missing Percentage} = \frac{\text{Missing Count}}{\text{Total Rows}} \times 100\%$$
- String kosong di-parse otomatis menjadi `NaN` oleh parser CSV.
- Nilai tak hingga (`inf`, `-inf`) = 0 baris.
- Nilai bertipe NaN murni = **16.838 baris**.
- Nilai numerik terisi = **2.174 baris**.
- Persentase missing keseluruhan = **88,5651%** (dibulatkan menjadi **88,57%**).

### 4.2 Breakdown Per Sensor (Tercatat pada `turbinator_missing_by_sensor.csv`)

| Sensor ID | Lokasi WKT | Total Baris | Nilai Valid | Missing Baris | % Missing | Mean Turbidity | Median | Std Dev | Distance $<0$ |
|---|---|---|---|---|---|---|---|---|---|
| `urn:ivl.se:turbinator:ADN17712` | POINT (11.981038 57.712066) | 6.039 | 56 | 5.983 | **99,07%** | 23,75 NTU | 22,0 NTU | 10,46 NTU | 91 baris |
| `urn:ivl.se:turbinator:ADIN42988`| POINT (11.975122 57.713096) | 2.054 | 60 | 1.994 | **97,08%** | 18,77 NTU | 18,0 NTU | 7,12 NTU | 0 baris |
| `urn:ivl.se:turbinator:ADN4633` | POINT (11.989862 57.715244) | 2.443 | 200 | 2.243 | **91,81%** | 48,57 NTU | 51,5 NTU | 8,74 NTU | 0 baris |
| `urn:ivl.se:turbinator:ADIN43068`| POINT (11.977309 57.715376) | 5.425 | 792 | 4.633 | **85,40%** | 16,12 NTU | 12,0 NTU | 11,87 NTU | 0 baris |
| `urn:ivl.se:turbinator:ADIN4483` | POINT (11.985725 57.71457) | 3.051 | 1.066 | 1.985 | **65,06%** | 36,36 NTU | 33,0 NTU | 14,46 NTU | 2 baris |
| **TOTAL KESELURUHAN** | **5 Titik Sensor** | **19.012** | **2.174** | **16.838** | **88,57%** | **29,30 NTU** | **27,0 NTU** | **17,05 NTU** | **93 baris** |

### 4.3 Klarifikasi Fenomena Fisik Sensor
1. **Kekosongan Turbidity Tinggi (88,57%):** Sensor Turbinator beroperasi dengan kamera dan pancaran laser dari atas sumur inspeksi. Jika permukaan air terlalu tenang (pantulan specular tidak mengenai lensa) atau muka air turun di bawah batas fokus optik, modul mematikan estimasi kekeruhan dan hanya merekam jarak muka air (`distance`).
2. **Nilai Jarak Negatif (93 baris):** Terdapat 93 baris dengan nilai `distance < 0` (91 di antaranya pada sensor `ADN17712`, nilai terendah $-6,05$ meter). Ini merupakan artefak saturasi pantulan laser pada dinding sumur atau busa limbah di permukaan air, **bukan jarak fisik nyata**.

---

## 5. AUDIT BAGIAN C: KELAYAKAN TEMPORAL & SEGMENTASI WINDOW KAUSAL

### 5.1 Karakteristik Waktu & Timezone
- Kolom: `dateobserved`. Format: `'YYYY-MM-DD HH:MM:SS.000'`.
- Format datetime lokal bersifat naive, namun dokumentasi primer Zenodo menetapkan bahwa timestamp dicatat dalam **UTC**.
- Rentang waktu global: `2020-09-23 15:20:00` s/d `2022-03-14 10:04:00` UTC (~1,5 tahun).
- Duplikat timestamp per sensor: **0 baris** (seluruh observasi per sensor unik secara temporal).

### 5.2 Aturan Segmentasi Kausal & Analisis Window
Karena sampling nominal adalah per 1 jam ($3.600$ detik), kontinuitas deret waktu didefinisikan dengan ambang batas gap kausal:
$$\text{Aturan Gap:} \quad \Delta t > 2 \times \text{median interval nominal} = 7.200 \text{ detik (2 jam)}$$
Jika selisih waktu antar pembacaan turbidity valid berturut-turut melebihi 2 jam, deret waktu diputus dan segmen baru diinisialisasi. Fitur rolling ($w=5, 15, 60$) wajib di-reset pada batas segmen untuk mencegah bias extrapolasi data yang tidak pernah diukur.

Jumlah window valid yang dapat dihitung dalam segmen dengan panjang $L$ observasi adalah:
$$N_{\text{window}}(w) = \max(0, L - w + 1)$$

### 5.3 Hasil Segmentasi Temporal (Tercatat pada `turbinator_temporal_segments.csv`)

| Sensor ID | Sampel Valid | Total Segmen ($\Delta t > 2\text{ jam}$) | Rata-rata Panjang Segmen | Segmen Terpanjang ($L_{\max}$) | Valid Window $w=5$ | Valid Window $w=15$ | Valid Window $w=60$ | Status Kelayakan |
|---|---|---|---|---|---|---|---|---|
| `ADIN4483` | 1.066 | 154 | 6,92 sampel | **85 sampel (85 jam)** | **645** | **314** | **46** | **MEMADAI ($w=5, 15, 60$)** |
| `ADIN43068`| 792 | 148 | 5,35 sampel | 54 sampel (54 jam) | 454 | 189 | 0 | **TERBATAS ($w=5, 15$)** |
| `ADN4633` | 200 | 56 | 3,57 sampel | 57 sampel (57 jam) | 84 | 46 | 0 | **TERBATAS ($w=5, 15$)** |
| `ADIN42988`| 60 | 14 | 4,29 sampel | 18 sampel (18 jam) | 26 | 4 | 0 | **SANGAT TERBATAS ($w=5$)** |
| `ADN17712` | 56 | 43 | 1,30 sampel | 4 sampel (4 jam) | 0 | 0 | 0 | **TIDAK MEMADAI** |

### 5.4 Implikasi Rekayasa Fitur:
- **Eksperimen Window Panjang ($w=60$ jam / 2,5 hari):** **HANYA DAPAT DILAKUKAN PADA SENSOR `ADIN4483`**. Hanya sensor ini yang memiliki segmen kontinu melampaui 60 jam (segmen terpanjang 85 sampel menghasilkan 46 window).
- **Sensor `ADN17712` Tidak Dapat Digunakan:** Dengan 99,07% missing dan panjang segmen maksimum 4 jam, sensor ini tidak menghasilkan satu pun window valid bahkan untuk $w=5$.

---

## 6. AUDIT BAGIAN D: DISTRIBUSI & SEMANTIK LABEL SKAB BENCHMARK

Folder: `ml/data/dataset/SKAB/` (35 berkas CSV, total 46.860 baris)

### 6.1 Struktur Berkas & Keaslian Label
- **`anomaly-free/anomaly-free.csv` (1 file, 9.401 baris):** Hanya memiliki 9 kolom sensor hidraulik tanpa kolom label (`anomaly` atau `changepoint`). Digunakan sebagai data pelatihan kondisi murni normal.
- **34 Berkas Anomali (37.459 baris):** Memiliki 11 kolom: 9 sensor hidraulik + 2 kolom label ground truth bawaan repository:
  1. `anomaly`: Variabel biner interval waktu ($0.0$ = normal, $1.0$ = anomali aktif berlangsung).
  2. `changepoint`: Variabel biner titik impuls ($1.0$ = saat onset anomali dimulai atau selesai, $0.0$ = kondisi stasioner).

### 6.2 Ringkasan Distribusi Label (Tercatat pada `skab_label_distribution.csv`)

| Sub-Folder / Kategori Gangguan | Jumlah File | Total Baris | Jumlah Titik Anomali (`anomaly=1`) | Proporsi Anomali (%) | Jumlah Changepoint (`changepoint=1`) |
|---|---|---|---|---|---|
| `anomaly-free` | 1 | 9.401 | 0 | 0,00% | 0 |
| `valve1` (Kerusakan Katup 1) | 16 | 17.755 | 6.009 | 33,84% | 61 |
| `valve2` (Kerusakan Katup 2) | 4 | 4.398 | 1.638 | 37,24% | 16 |
| `other` (Ketidakseimbangan Rotor/Kavitasi)| 14 | 15.306 | 5.594 | 36,55% | 53 |
| **TOTAL KESELURUHAN SKAB** | **35** | **46.860** | **13.241** | **28,26% (35,35% di anomali)** | **130** |

### 6.3 Disambiguasi Domain & Batasan Metodologis
- **Bukan Kebocoran Pipa PDAM:** Label anomali pada SKAB merepresentasikan gangguan elektromekanis katup tertutup, kebocoran segel pompa sirkulasi tertutup, dan ketidakseimbangan rotor pada testbed Skoltech.
- **Nilai untuk AQUA-TARA:** SKAB **tidak dapat digunakan untuk mengevaluasi kualitas air (tanpa turbidity)**. Kegunaan SKAB terbatas pada pengujian metodologi algoritma deteksi anomali multivariat hidraulik (misal Isolation Forest atau Autoencoder pada sensor tekanan dan debit) yang kemudian ditransfer polanya ke dataset hidraulik BattLeDIM.

---

## 7. AUDIT BAGIAN E: KONSISTENSI MANIFEST, HASH, DAN SKRIP AUDIT

Berkas yang diverifikasi ulang:
- `ml/experiments/dataset_integrity_audit/step_4d_72/dataset_inventory_manifest.json`
- `ml/experiments/dataset_integrity_audit/step_4d_72/dataset_statistical_summary.json`
- `ml/experiments/dataset_integrity_audit/step_4d_72/run_comprehensive_audit.py`

### 7.1 Hasil Verifikasi Kriptografis (`verified_file_hashes.json`)
- **Total Berkas Terdaftar di Manifest:** 40 berkas.
- **Pencocokan Ukuran & SHA-256 Aktual:** **40 dari 40 berkas (100%) cocok sempurna**.
  - 35 berkas SKAB: Ukuran dan SHA-256 identik baris-ke-baris.
  - 1 berkas `water_potability.csv` (425.104 bytes, SHA-256 `79ff73d09a47565e386ebc0ba57adfb2ad73f5509939e6a0d24e54854580fb35`): Cocok.
  - 1 berkas `water-quality-data.ipynb` (382.493 bytes, SHA-256 `5f2ca1fb789fe811f0088923bc65727f7db520286820c78a05953046bc28c0b9`): Cocok.
  - 1 berkas `turbinator_water_quality_observed_202303211549.csv` (1.716.210 bytes, SHA-256 `9df319d16b85a663aa29bed6f384a103bdd0a85d0c0e78c193b859cbf8a5638f`): Cocok.
  - 2 berkas KU-MWQ (`Sensor_Data_30cm.xlsx` & `Sensor_Data_60cm.xlsx`): Cocok dengan baseline STEP 4D.71.
- **Audit Statis Skrip `run_comprehensive_audit.py`:**
  - Skrip beroperasi secara read-only murni tanpa perintah modifikasi atau penghapusan berkas.
  - Skrip membaca seluruh subfolder rekursif dan format data secara akurat.

---

## 8. AUDIT BAGIAN F: KLARIFIKASI REGULASI BAKU MUTU AIR

### 8.1 Verifikasi Permenkes No. 2 Tahun 2023
- **Dasar Hukum:** Peraturan Menteri Kesehatan Republik Indonesia Nomor 2 Tahun 2023 tentang Peraturan Pelaksanaan Peraturan Pemerintah Nomor 66 Tahun 2014 tentang Kesehatan Lingkungan (Lampiran Standar Baku Mutu Kesehatan Lingkungan untuk Media Air Minum).
- **Nilai Ambang Batas Parameter Kekeruhan (Turbidity):**
  - **Maksimum 3 NTU** [VERIFIED].
  - *(Catatan: Nilai 5 NTU yang sering dikutip merupakan standar lama dari Permenkes No. 492/MENKES/PER/IV/2010 yang telah diperbarui oleh Permenkes 2/2023).*
- **Disambiguasi Baku Mutu vs Threshold Deteksi AQUA-TARA:**
  - Baku mutu 3 NTU adalah ambang batas kepatuhan uji laboratorium untuk kelayakan konsumsi manusia.
  - Threshold klasifikasi AQUA-TARA (0–30 Jernih, 31–70 Waspada, 71–100 Bahaya) adalah ambang batas komposit kalkulasi risiko operasional sensor IoT di lapangan, bukan acuan absolut laboratorium kimiawi air minum.

---

## 9. JAWABAN WAJIB 10 PERTANYAAN AUDIT KOREKSI

1. **Apakah lisensi Turbinator terbukti?**  
   **YA, TERBUKTI (VERIFIED).** Lisensi resmi dari Zenodo API adalah **Creative Commons Attribution 4.0 International (CC BY 4.0)** (`license.id: cc-by-4.0`).
2. **Berapa persentase missing aktual keseluruhan dan per sensor?**  
   - Keseluruhan: **88,57% missing** (16.838 NaN dari 19.012 baris).
   - Per Sensor: `ADN17712` (99,07%), `ADIN42988` (97,08%), `ADN4633` (91,81%), `ADIN43068` (85,40%), `ADIN4483` (65,06%).
3. **Sensor mana yang cukup untuk ukuran window 5/15/60?**  
   - Window 5 ($w=5$): `ADIN4483`, `ADIN43068`, `ADN4633`, `ADIN42988`.
   - Window 15 ($w=15$): `ADIN4483`, `ADIN43068`, `ADN4633`, `ADIN42988` (sangat tipis).
   - Window 60 ($w=60$): **HANYA SENSOR `ADIN4483`** (memiliki 46 window valid). Empat sensor lainnya bernilai 0.
4. **Berapa jumlah segmen dan window valid per sensor?**  
   Tercatat lengkap pada tabel Bab 5.3 laporan ini dan berkas `turbinator_temporal_segments.csv`. `ADIN4483` menghasilkan 154 segmen dengan 645 window ($w=5$), 314 window ($w=15$), dan 46 window ($w=60$).
5. **Bagaimana distribusi dan keaslian label sumber SKAB?**  
   Label berasal asli dari dataset Skoltech (bukan hasil generasi skrip). Terdapat 1 berkas baseline normal (9.401 baris) dan 34 berkas gangguan dengan 13.241 titik anomali fisik.
6. **Apakah anomaly dan changepoint dibedakan dengan benar?**  
   **YA, DIBEDAKAN DENGAN BENAR.** `anomaly` adalah label status durasi gangguan fisik (13.241 titik), sedangkan `changepoint` adalah indikator onset/offset transisi instan (130 titik).
7. **Apakah manifest, hash, dan ringkasan statistik konsisten?**  
   **YA, 100% KONSISTEN.** 40 berkas di manifest cocok secara identik dalam ukuran byte dan SHA-256 terhadap kondisi fisik disk.
8. **Klaim mana dari STEP 4D.72 yang perlu dikoreksi?**  
   - *Koreksi 1:* Tingkat kelayakan temporal Turbinator. STEP 4D.72 menyatakan Turbinator "CONDITIONAL", namun audit presisi 4D.72.1 membuktikan bahwa kondisi kelayakan ini **hanya berlaku untuk 1 sensor (`ADIN4483`)**, sedangkan sensor `ADN17712` sepenuhnya **REJECT**.
   - *Koreksi 2:* Standar Baku Mutu Permenkes 2/2023 adalah **3 NTU**, bukan 5 NTU.
9. **Dataset mana yang sesuai untuk fitur temporal dan benchmark metodologi?**  
   - Fitur Temporal Kekeruhan Resolusi Tinggi: **KU-MWQ** (sampling 1 menit kontinu).
   - Fitur Temporal Kekeruhan Lingkungan Pipa Riil (Resolusi Rendah): **Turbinator Sensor `ADIN4483`**.
   - Benchmark Metodologi Anomali Hidraulik Berlabel: **SKAB Benchmark** & **BattLeDIM 2020**.
10. **Apa yang belum dapat disimpulkan untuk jaringan air AQUA-TARA?**  
    Belum ada dataset publik yang menggabungkan **secara simultan** parameter kualitas air (Turbidity NTU) dan dinamika hidraulik jaringan transmisi pipa PDAM bertekanan (Pressure & Flow) yang dilengkapi ground truth kebocoran fisik terverifikasi.

---

## 10. ARTEFAK AUDIT & VERIFIKASI STEP 4D.72.1

Direktori Keluaran: `ml/experiments/dataset_integrity_audit/step_4d_72_1/`

### Berkas yang Dihasilkan:
1. `run_audit_4d_72_1.py`: Skrip mandiri audit dan verifikasi bukti matematis.
2. `turbinator_missing_by_sensor.csv`: Tabel breakdown persentase missing, jumlah valid, dan nilai ekstrem per sensor Turbinator.
3. `turbinator_temporal_segments.csv`: Tabel partisi segmen temporal, panjang segmen kontinu, dan kalkulasi window valid ($w=5, 15, 60$).
4. `skab_label_distribution.csv`: Tabel distribusi kolom `anomaly` dan `changepoint` untuk seluruh 35 berkas SKAB.
5. `verified_file_hashes.json`: Matriks verifikasi 40 berkas membandingkan manifest STEP 4D.72 terhadap SHA-256 dan MD5 fisik aktual.
6. `corrected_statistics.json`: Ringkasan konsolidasi angka statistik terkoreksi.

---

## 11. PERNYATAAN KEPATUHAN SISTEM PRODUKSI

Auditor menyatakan secara tegas:
1. Tidak ada modifikasi kode pada backend Laravel, frontend Vue, maupun modul `ml/app/`.
2. Formula skor risiko `round(0.8 * Score_T + 0.2 * Score_S)` dan ambang batas klasifikasi produksi tetap tidak berubah.
3. Database Supabase dan database lokal tidak pernah diakses atau dimutasi.
4. Seluruh berkas dataset mentah tetap utuh dan tidak ditimpa.
5. Tidak ada commit Git yang dibuat selama proses audit.
