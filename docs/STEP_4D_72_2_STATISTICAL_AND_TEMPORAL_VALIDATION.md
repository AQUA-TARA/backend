# STEP 4D.72.2 — AUDIT KOREKSI STATISTIK DAN VALIDITAS TEMPORAL

**Tanggal Audit:** 15 Oktober 2026  
**Auditor:** Tim Teknis AQUA-TARA / AI Data & ML Auditor  
**Lingkup:** Audit koreksi lanjutan terhadap konsistensi metrik statistik, proporsi label SKAB (resolusi penyebut global vs evaluasi), validasi segmentasi kausal Turbinator per sensor, perbandingan durasi waktu aktual vs jumlah sampel, verifikasi reset fitur pada batas gap, re-rekonsiliasi 40 berkas manifest, serta audit komparatif terhadap laporan STEP 4D.70, 4D.71, 4D.72, dan 4D.72.1.  
**Status Sistem Produksi:** Unchanged — Read-Only Audit (Zero Supabase, Zero Migration, Zero Production Code Mutation, Zero Git Commit).

---

## 1. RINGKASAN STATUS PEMERIKSAAN (VERIFICATION DASHBOARD)

| No | Komponen Audit | Status | Keterangan & Bukti Matematis |
|---|---|---|---|
| 1 | **Proporsi Label SKAB (Resolusi Denominator)** | **PASS** | Terverifikasi: Anomali = **28,26%** (Penyebut Global: 46.860 baris); Anomali = **35,35%** (Penyebut Evaluasi: 37.459 baris). Changepoint = **0,28%** (Global) dan **0,35%** (Evaluasi). |
| 2 | **Validitas Baris & Missing Turbinator** | **PASS** | 19.012 baris mentah. Valid turbidity = 2.174 baris (11,43%), Missing = 16.838 baris (88,57%). |
| 3 | **Segmentasi Temporal & Window Kausal** | **PASS** | Aturan pemutusan gap $\Delta t > 2$ jam ($7.200$ detik). Hanya `ADIN4483` yang memadai untuk $w=60$ (46 window, segmen terpanjang 85 sampel / 85 jam). `ADN17712` bernilai 0 window untuk seluruh window ($w \ge 5$). |
| 4 | **Kausalitas Fitur & Reset Batas Segmen** | **PASS** | Diuji langsung pada `ADIN4483`: Nilai First Difference, Rolling Mean ($w=5$), Rolling Std ($w=5$), dan Rolling Z-score **100% bernilai NaN pada titik batas segmen** (zero cross-gap contamination). |
| 5 | **Rekonsiliasi Lintas Laporan Historis** | **PASS** | Seluruh perbedaan angka antara 4D.70, 4D.71, 4D.72, dan 4D.72.1 berhasil dipetakan sumbernya dan terdokumentasi secara transparan. |
| 6 | **Re-Verifikasi Manifest & Checksum (40 Files)** | **PASS** | 40 dari 40 berkas (100%) cocok secara sempurna dalam ukuran bytes dan SHA-256 terhadap kondisi fisik disk. |

---

## 2. STATUS GIT REPOSITORY SEBELUM DAN SESUDAH AUDIT

### 2.1 Status Sebelum Audit
```text
?? .gitignore
?? backend/
?? frontend/
?? ml/
```
*(Branch master, No commits yet, direktori utama berstatus untracked).*

### 2.2 Status Sesudah Audit
```text
?? .gitignore
?? backend/
?? frontend/
?? ml/
```
*(Zero file tracked termodifikasi, zero staging area `git add`, zero commit Git, integritas file sumber 100% terjaga).*

---

## 3. AUDIT TUGAS 1: DISTRIBUSI LABEL ANOMALY & CHANGEPOINT SKAB

Audit mendalam terhadap 35 berkas di `ml/data/dataset/SKAB/` membedakan dua cara pandang penyebut (*denominator*):

### 3.1 Resolusi Matematika Dua Penyebut
1. **Penyebut Global ($N_{\text{global}} = 46.860$ baris):**
   Mencakup **seluruh 35 berkas**, termasuk berkas `anomaly-free/anomaly-free.csv` (9.401 baris kondisi normal tanpa injeksi anomali).
   - Total Titik Anomali (`anomaly = 1`): **13.241 baris**
   - Proporsi Anomali Global: $\frac{13.241}{46.860} \times 100\% = \mathbf{28,2565\% \approx 28,26\%}$
   - Total Titik Changepoint (`changepoint = 1`): **130 baris**
   - Proporsi Changepoint Global: $\frac{130}{46.860} \times 100\% = \mathbf{0,2774\% \approx 0,28\%}$

2. **Penyebut Evaluasi ($N_{\text{eval}} = 37.459$ baris):**
   Hanya mencakup **34 berkas skenario gangguan** (mengecualikan berkas referensi normal `anomaly-free.csv`). Berkas ini yang umum dijadikan set evaluasi algoritma deteksi.
   - Total Titik Anomali: **13.241 baris**
   - Proporsi Anomali Evaluasi: $\frac{13.241}{37.459} \times 100\% = \mathbf{35,3480\% \approx 35,35\%}$
   - Total Titik Changepoint: **130 baris**
   - Proporsi Changepoint Evaluasi: $\frac{130}{37.459} \times 100\% = \mathbf{0,3470\% \approx 0,35\%}$

### 3.2 Penjelasan Perbedaan Semantik
- **Mengapa Ada Perbedaan?** Pada laporan STEP 4D.72 dan 4D.72.1, angka 28,26% dan 35,35% sempat muncul berdampingan. Audit ini membuktikan bahwa kedua angka tersebut **sama-sama benar secara matematis**, tetapi menggunakan ruang sampel (*sample space*) yang berbeda.
- **Rekomendasi Pelaporan:** Untuk benchmark algoritma deteksi anomali semi-supervised (model dilatih pada data normal `anomaly-free.csv` dan diuji pada skenario gangguan), rasio anomali pada fase pengujian adalah **35,35%**. Jika seluruh dataset dianalisis sebagai satu kesatuan repositori data, rasio anomali adalah **28,26%**.

---

## 4. AUDIT TUGAS 2 & 3: BARIS, MISSING, SEGMEN, DAN DURASI TEMPORAL TURBINATOR

Sumber berkas: `ml/data/turbidity/turbinator/turbinator_water_quality_observed_202303211549.csv` (19.012 baris).

### 4.1 Definisi Batas Segmen Waktu
- **Nominal Sampling Rate:** 1 jam ($3.600$ detik).
- **Ambang Batas Gap Temporal ($\Delta t_{\text{threshold}}$):** **$7.200$ detik (2 jam)**, yaitu $2 \times$ median sampling nominal.
- **Logika Pemotongan:** Jika selisih waktu antar dua observasi kekeruhan valid berturut-turut melebihi 2 jam ($\Delta t > 7.200\text{ s}$), deret waktu diputus dan dihitung sebagai segmen baru.
- **Pembedaan Jumlah Sampel vs Durasi Aktual:**
  - *Jumlah Sampel ($N$):* Banyaknya baris observasi berurutan dalam segmen ($N$).
  - *Durasi Waktu Aktual ($\Delta T_{\text{actual}}$):* Selisih waktu kalender dari titik pertama hingga titik terakhir segmen: $(t_{\text{end}} - t_{\text{start}})$ dalam satuan jam.

### 4.2 Hasil Audit Rinci Per Sensor (Tercatat pada `turbinator_sensor_temporal_summary.csv`)

| Sensor ID | Baris Mentah | Valid Turbidity | % Missing | Rentang Waktu Observasi Mentah | Span Observasi Valid (Hari) | Total Segmen ($\Delta t > 2\text{h}$) | Sampel Kontinu Max ($L_{\max}$) | Durasi Kontinu Max (Jam) | Total Durasi Kontinu (Jam) | Window Valid $w=5$ | Window Valid $w=15$ | Window Valid $w=60$ |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `ADN17712` | 6.039 | 56 | **99,07%** | 2020-09-23 s/d 2022-02-15 (509,8 hari) | 354,7 hari | 43 | 4 | 5,0 jam | 19,0 jam | 0 | 0 | 0 |
| `ADIN42988`| 2.054 | 60 | **97,08%** | 2020-09-25 s/d 2021-05-11 (228,4 hari) | 68,3 hari | 14 | 18 | 19,0 jam | 50,0 jam | 26 | 4 | 0 |
| `ADN4633` | 2.443 | 200 | **91,81%** | 2021-03-31 s/d 2022-03-14 (347,9 hari) | 310,4 hari | 56 | 57 | 56,0 jam | 158,0 jam | 84 | 46 | 0 |
| `ADIN43068`| 5.425 | 792 | **85,40%** | 2021-03-19 s/d 2022-03-14 (359,7 hari) | 359,0 hari | 148 | 54 | 54,0 jam | 687,9 jam | 454 | 189 | 0 |
| `ADIN4483` | 3.051 | 1.066 | **65,06%** | 2021-04-17 s/d 2022-03-10 (327,5 hari) | 324,8 hari | 154 | **85** | **85,0 jam** | **963,9 jam** | **645** | **314** | **46** |
| **TOTAL** | **19.012** | **2.174** | **88,57%** | — | — | **415** | — | — | **1.878,8 jam** | **1.209** | **553** | **46** |

---

## 5. AUDIT TUGAS 4: KAUSALITAS FITUR TEMPORAL & VERIFIKASI RESET PADA GAP

Audit memverifikasi implementasi matematis fitur temporal kausal pada deret waktu tak teratur (*irregular time-series*):

### 5.1 Pengujian Langsung pada Sensor `ADIN4483`
Sensor `ADIN4483` memiliki 154 segmen waktu kontinu (dengan 153 titik batas gap). Evaluasi dilakukan terhadap:
1. **First Difference Kausal ($\Delta y_t = y_t - y_{t-1}$):**
   - Pada baris pertama dari setiap segmen baru (tepat setelah gap $\Delta t > 2$ jam), $\Delta y_t$ **WAJIB bernilai NaN**.
   - *Hasil Uji Diskrit:* Seluruh 153 titik batas segmen menghasilkan nilai **NaN** (`isna().all() == True`) [PASS].
2. **Rolling Mean & Rolling Std Kausal ($w=5$ jam, `min_periods=5`):**
   - Pada 4 observasi pertama dari setiap segmen baru, fitur rolling belum mencukupi panjang jendela kausal.
   - *Hasil Uji Diskrit:* Seluruh observasi pada indeks $t \in [t_{\text{start}}, t_{\text{start}}+3]$ bernilai **NaN** (`isna().all() == True`) [PASS].
3. **Causal Z-Score ($Z_{t, w} = \frac{y_t - \mu_{t, w}}{\sigma_{t, w}}$):**
   - Z-score hanya terdefinisi setelah jendela terisi penuh ($\ge 5$ sampel kontinu) dan varians lokal $\sigma > 10^{-6}$.
   - Tidak ada data sebelum gap yang bocor ke dalam kalkulasi segmen berikutnya (*Zero Lookback Leakage*).
   - Tidak ada fungsi interpolasi maju (*forward-fill*) atau lookahead (*shift negatif*) yang digunakan.

---

## 6. AUDIT TUGAS 5: REKONSILIASI KONSISTENSI LINTAS LAPORAN

Audit membandingkan klaim numerik pada keempat dokumen laporan sebelumnya (STEP 4D.70, 4D.71, 4D.72, 4D.72.1):

| Parameter / Metrik | STEP 4D.70 | STEP 4D.71 | STEP 4D.72 | STEP 4D.72.1 | Nilai Terverifikasi STEP 4D.72.2 | Status Rekonsiliasi |
|---|---|---|---|---|---|---|
| **Baris KU-MWQ per file** | 9.623 | 9.623 | 9.623 | 9.623 | **9.623 baris** | **100% Konsisten** |
| **Turbidity Mean 30cm** | 215,4852 NTU | 215,4852 NTU | 215,49 NTU | 215,49 NTU | **215,4852 NTU** | **100% Konsisten** |
| **Turbidity Mean 60cm** | 141,8540 NTU | 141,8540 NTU | 141,85 NTU | 141,85 NTU | **141,8540 NTU** | **100% Konsisten** |
| **SHA-256 KU-MWQ 30cm** | N/A | `524f6cdecf...` | `524f6cdecf...` | `524f6cdecf...` | `524f6cdecf15cb83e2e82d5db7432330...` | **100% Konsisten** |
| **SHA-256 KU-MWQ 60cm** | N/A | `b4495c7860...` | `b4495c7860...` | `b4495c7860...` | `b4495c7860d614ff9487bb656514e70...` | **100% Konsisten** |
| **Total Baris Turbinator** | Belum Diaudit | Belum Diaudit | 19.012 | 19.012 | **19.012 baris** | **100% Konsisten** |
| **Valid Turbidity Turbinator** | Belum Diaudit | Belum Diaudit | 2.174 (11,43%) | 2.174 (11,43%) | **2.174 baris (11,43%)** | **100% Konsisten** |
| **Missing Turbidity Turbinator**| Belum Diaudit | Belum Diaudit | 16.838 (88,57%) | 16.838 (88,57%) | **16.838 baris (88,57%)**| **100% Konsisten** |
| **Total Baris SKAB** | N/A | N/A | 46.860 | 46.860 | **46.860 baris** | **100% Konsisten** |
| **Total Anomali SKAB** | N/A | N/A | 13.241 | 13.241 | **13.241 titik** | **100% Konsisten** |
| **Proporsi Anomali SKAB** | N/A | N/A | 28,26% | 28,26% & 35,35% | **28,26% (Global) / 35,35% (Eval)**| **Terekonsiliasi Sempurna** |
| **Total Changepoint SKAB** | N/A | N/A | Belum Terinci | 130 titik | **130 titik (0,28% / 0,35%)** | **Terekonsiliasi Sempurna** |
| **Baku Mutu Permenkes 2/2023**| Dikutip 5 NTU | Dikutip 5 NTU | Dikutip 5 NTU | Dikoreksi 3 NTU | **3,0 NTU (Standar Air Minum)** | **Terkoreksi Sempurna** |

---

## 7. AUDIT TUGAS 6: RE-VERIFIKASI MANIFEST DAN CHECK HASH (40 BERKAS)

Manifest inventarisasi `ml/experiments/dataset_integrity_audit/step_4d_72/dataset_inventory_manifest.json` diverifikasi ulang terhadap kondisi fisik disk aktual tanpa mengubah atau memodifikasi file sumber apa pun:

### 7.1 Hasil Verifikasi Kriptografis
- **Total Berkas Terdaftar:** 40 berkas.
- **Pencocokan Ukuran Byte:** **40 dari 40 berkas cocok 100%**.
- **Pencocokan SHA-256 Checksum:** **40 dari 40 berkas cocok 100%**.
- **Jumlah Berkas per Kelompok:**
  - `ml/data/dataset/SKAB/`: 35 berkas CSV (1 berkas normal + 34 berkas anomali).
  - `ml/data/dataset/water_potability.csv`: 1 berkas CSV (425.104 bytes, SHA-256 `79ff73d09a47565e386ebc0ba57adfb2ad73f5509939e6a0d24e54854580fb35`).
  - `ml/data/dataset/water-quality-data.ipynb`: 1 berkas IPYNB (382.493 bytes, SHA-256 `5f2ca1fb789fe811f0088923bc65727f7db520286820c78a05953046bc28c0b9`).
  - `ml/data/turbidity/turbinator/turbinator_water_quality_observed_202303211549.csv`: 1 berkas CSV (1.716.210 bytes, SHA-256 `9df319d16b85a663aa29bed6f384a103bdd0a85d0c0e78c193b859cbf8a5638f`).
  - `ml/data/turbidity/ku_mwq/`: 2 berkas XLSX (`Sensor_Data_30cm.xlsx` = 285.192 bytes, `Sensor_Data_60cm.xlsx` = 228.702 bytes).
- **Hasil:** **PASS (Zero Mismatch, Zero File Drift)**.

---

## 8. ARTEFAK AUDIT & VERIFIKASI STEP 4D.72.2

Seluruh artefak keluaran disimpan secara terisolasi pada:  
`ml/experiments/dataset_integrity_audit/step_4d_72_2/`

### Berkas yang Dihasilkan:
1. `run_validation_4d_72_2.py`: Skrip mandiri read-only untuk komputasi verifikasi statistik dan temporal.
2. `skab_detailed_audit.csv`: Rincian per-berkas dari 35 file SKAB (jumlah baris, kategori gangguan, jumlah anomali, jumlah changepoint, dan rate per-file).
3. `turbinator_sensor_temporal_summary.csv`: Rangkuman komprehensif per sensor Turbinator memuat sampel mentah, sampel valid, rentang tanggal, durasi actual jam, jumlah segmen, dan window valid ($w=5, 15, 60$).
4. `statistical_temporal_validation.json`: Berkas JSON konsolidasi hasil audit untuk kebutuhan reproduksibilitas otomatis.

---

## 9. KESIMPULAN AUDIT & PERNYATAAN KEPATUHAN SISTEM

### 9.1 Status Akhir Audit
Status: **PASS (VALIDATED & RECONCILED)**  
Tidak ada klaim statistik atau temporal yang tidak didukung bukti empiris berkas lokal.

### 9.2 Pernyataan Kepatuhan Guardrails
1. Kode produksi backend Laravel, frontend Vue, dan modul `ml/app/` **sama sekali tidak diubah**.
2. Database Supabase dan database lokal **tidak pernah diakses atau dimodifikasi**.
3. Formula skor risiko `round(0.8 * Score_T + 0.2 * Score_S)` dan ambang batas klasifikasi produksi **tetap utuh**.
4. Seluruh dataset asli **tidak ditimpa, dihapus, atau dimodifikasi**.
5. Tidak ada commit Git yang dibuat.
6. Tidak ada pelatihan atau integrasi model yang dimulai pada tahap ini.
