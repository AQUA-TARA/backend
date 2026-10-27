# STEP 4D.74.3 — METRIC SEMANTICS AND EVENT-LEVEL CONSISTENCY AUDIT

**Tanggal Audit:** 15 Oktober 2026  
**Auditor:** Tim Teknis AQUA-TARA / AI Data & ML Auditor  
**Lingkup:** Audit read-only terhadap STEP 4D.74, STEP 4D.74.1, dan STEP 4D.74.2. Rekonsiliasi semantik definisi $y=0$ dan metrik hidraulik, verifikasi denominator matriks konfusi dan episode alarm, audit batas konsolidasi jeda (*gap bridging*), audit time-to-detection (TTD) raw dan terfilter persistensi, serta analisis alarm pra-onset dan crossing batas kejadian.  
**Status Sistem Produksi:** Read-Only & Unchanged (Zero Supabase, Zero Migration/Seeder, Zero Mutation pada Backend/Frontend/ML Production, Formula Risiko Produksi Tetap Utuh).

---

## 1. RINGKASAN EKSEKUTIF & STATUS PER BUTIR PEMERIKSAAN

| No | Butir Pemeriksaan | Status | Temuan Kritis & Hasil Audit Terverifikasi |
|:---|:---|:---:|:---|
| 1 | **Rekonsiliasi Semantik $y=0$, FAR, Precision, Recall, & F1** | **PASS** | Label $y=0$ direkonsiliasi resmi menjadi **“non-fixed-burst period with background leaks”** (karena 100% sampel pengujian mengandung kebocoran latar belakang aktif $0,01$–$6,91$ L/s). Seluruh rumus metrik didefinisikan dengan pembilang dan penyebut eksplisit. |
| 2 | **Verifikasi Confusion Matrix & Episode Alarm (Dual Denominator)** | **PASS** | 48 sel metrik dan matriks konfusi terverifikasi 100%. Episode alarm palsu per hari didokumentasikan dengan dua penyebut eksplisit: **Kalender Total Pengujian ($122,0$ hari)** dan **Durasi Efektif Non-Burst ($90,05$ hari)** (misal: IF Raw = $14,24$ vs $19,29$ eps/hari). |
| 3 | **Audit Gap Bridging (15m & 30m) & Crossing Batas Kejadian** | **PASS** | Tidak ada penggabungan alarm silang (*no silent cross-merging*) yang melintasi onset kejadian baru. Jeda 1 jam sebelum onset $p158$ dan $p369$ memiliki **0 alarm** pada semua model. Pada offset, filter $fp$ dibatasi ketat ke $y=0$ sehingga tidak mengontaminasi event burst. |
| 4 | **Perhitungan Ulang TTD Raw & Terfilter Persistensi ($p158$ & $p369$)** | **PASS** | TTD terverifikasi per timestep. Pada $p369$, Raw First Difference mendeteksi dalam 10 menit ($0,17$ jam), namun persistensi 15m meningkatkan TTD menjadi $4,17$ jam ($250$ menit) karena pola burst awal yang berdenyut (*intermittent pulse*). |
| 5 | **Rekonsiliasi Lintas Laporan & Discrepancy Log (4D.74 s.d. 4D.74.3)** | **PASS** | Seluruh variasi terminologi dan normalisasi antara 4D.74, 4D.74.1, dan 4D.74.2 telah dicatat, direkonsiliasi, dan diselaraskan tanpa menimpa artefak lama. |
| 6 | **Kepatuhan Guardrails Sistem Produksi** | **PASS** | Tidak ada pemilihan model final dari test set. Kode produksi backend/frontend/ML tidak tersentuh. Formula risiko $0.8 \cdot Score_T + 0.2 \cdot Score_S$ dan ambang batas tetap utuh. Model BattLeDIM tidak dinyatakan siap produksi. |

---

## 2. REKONSILIASI SEMANTIK DEFINISI $y=0$ DAN FORMULA METRIK

Dalam audit STEP 4D.74 dan 4D.74.1, istilah "periode normal" atau "pure normal" sempat digunakan untuk merujuk pada kondisi tanpa fixed burst. Melalui audit mendalam ground truth BattLeDIM `2018_Leakages.csv` (105.120 baris total, 35.136 baris Test Set), ditemukan bahwa:
- Terdapat 4 titik kebocoran latar belakang (*background leaks*): `p257`, `p427`, `p654`, dan `p810`.
- Keempat pipa ini mengalirkan air bocor terus-menerus ($0,01$ hingga $6,91$ L/s) selama 365 hari penuh tanpa pernah diperbaiki.
- **Konsekuensi Ilmiah:** Pada Test Set (September–Desember 2018), tidak ada satu pun detik di mana debit kebocoran bernilai nol mutlak (*zero leakage* = $0$ baris / $0,00\%$).

### 2.1 Definisi Semantik Resmi
1. **Label Positif ($y=1$, $P = 9.201$ timestep / $26,19\%$):**  
   Periode aktifnya kebocoran pipa besar mendadak (*Fixed Burst Event*), mencakup sisa kejadian `p183` (207 langkah), kejadian `p158` (5.029 langkah), dan kejadian `p369` (3.965 langkah).
2. **Label Negatif ($y=0$, $N = 25.935$ timestep / $73,81\%$):**  
   Direkonsiliasi sebagai **"Non-fixed-burst period with background leaks"** (bukan "periode normal murni"). Kondisi ini mencerminkan kondisi dasar terdegradasi (*degraded baseline*) yang khas pada jaringan pipa distribusi tua.

### 2.2 Formulasi Metrik dengan Pembilang dan Penyebut Eksplisit
- **False Alarm Rate Timestep ($FAR$):**
  $$\text{FAR} = \frac{\text{FP}}{\text{FP} + \text{TN}} = \frac{\text{FP}}{25.935}$$
  *Interpretasi:* Proporsi timestep non-burst (dengan kebocoran latar) yang secara keliru dipicu sebagai alarm.
- **Precision:**
  $$\text{Precision} = \frac{\text{TP}}{\text{TP} + \text{FP}}$$
  *Interpretasi:* Proporsi timestep alarm yang benar-benar bertepatan dengan adanya fixed burst aktif.
- **Recall (Sensitivity):**
  $$\text{Recall} = \frac{\text{TP}}{\text{TP} + \text{FN}} = \frac{\text{TP}}{9.201}$$
  *Interpretasi:* Proporsi durasi fixed burst yang berhasil diidentifikasi oleh sistem deteksi.
- **F1-Score:**
  $$\text{F1} = 2 \cdot \frac{\text{Precision} \cdot \text{Recall}}{\text{Precision} + \text{Recall}}$$

---

## 3. VERIFIKASI CONFUSION MATRIX DAN EPISODE ALARM (DUAL DENOMINATOR)

### 3.1 Confusion Matrix Timestep Lengkap (35.136 Timestep Test Set)

| Model Evaluasi | Konfigurasi Sinyal | True Positive (TP) | False Positive (FP) | True Negative (TN) | False Negative (FN) | Precision | Recall | F1-Score | FAR Timestep |
|:---|:---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **First Difference** | **Raw (5m)** | 2.660 | 6.885 | 19.050 | 6.541 | 27,87% | 28,91% | 28,38% | 26,55% |
| | **Persist 15m** | 1.471 | 3.610 | 22.325 | 7.730 | 28,95% | 15,99% | 20,60% | 13,92% |
| **Rolling Z-Score** | **Raw (5m)** | 2.243 | 6.423 | 19.512 | 6.958 | 25,88% | 24,38% | 25,11% | 24,77% |
| | **Persist 15m** | 1.203 | 3.369 | 22.566 | 7.998 | 26,31% | 13,07% | 17,47% | 12,99% |
| **Isolation Forest** | **Raw (5m)** | 4.047 | 6.672 | 19.263 | 5.154 | 37,76% | 43,98% | 40,63% | 25,73% |
| | **Persist 15m** | 2.819 | 4.042 | 21.893 | 6.382 | 41,09% | 30,64% | 35,10% | 15,59% |

*(Catatan: $TP+FN = 9.201$ dan $FP+TN = 25.935$ konsisten pada seluruh baris).*

---

### 3.2 Audit Episode Alarm Palsu dengan Dual Denominator

Untuk menghindari bias interpretasi utilitas lapangan, jumlah episode alarm palsu dinormalisasi menggunakan dua penyebut yang didefinisikan secara eksplisit:
1. **Penyebut A (Total Test Calendar Duration):** $35.136 \text{ langkah} \times 5 \text{ menit} = 175.680 \text{ menit} = \mathbf{122,00 \text{ hari}}$ ($17,43$ minggu).
2. **Penyebut B (Effective Non-Burst Observation Duration):** $25.935 \text{ langkah} \times 5 \text{ menit} = 129.675 \text{ menit} = \mathbf{90,05 \text{ hari}}$ ($12,86$ minggu).

| Model & Sinyal | Definisi Konsolidasi Gap | Total Episode | Eps / Hari (Penyebut A: Total 122 Hari) | Eps / Hari (Penyebut B: Efektif 90 Hari) | Eps / Minggu (Penyebut A) | Rata-rata Durasi Episode |
|:---|:---|:---:|:---:|:---:|:---:|:---:|
| **Isolation Forest (Raw)** | **Strict (0→1)** | 1.737 | **14,24** | **19,29** | 99,67 | 19,2 menit |
| | **Bridging $\le 15$m** | 426 | **3,49** | **4,73** | 24,44 | 100,5 menit |
| | **Bridging $\le 30$m** | 258 | **2,11** | **2,87** | 14,80 | 181,6 menit |
| **Isolation Forest (Persist 15m)** | **Strict (0→1)** | 585 | **4,80** | **6,50** | 33,57 | 34,5 menit |
| | **Bridging $\le 15$m** | 366 | **3,00** | **4,06** | 21,00 | 64,2 menit |
| | **Bridging $\le 30$m** | 235 | **1,93** | **2,61** | 13,48 | 114,0 menit |
| **Rolling Z-Score (Raw)** | **Strict (0→1)** | 2.248 | **18,43** | **24,96** | 128,99 | 14,3 menit |
| | **Bridging $\le 15$m** | 911 | **7,47** | **10,12** | 52,27 | 47,3 menit |
| | **Bridging $\le 30$m** | 513 | **4,21** | **5,70** | 29,44 | 102,7 menit |
| **Rolling Z-Score (Persist 15m)** | **Strict (0→1)** | 427 | **3,50** | **4,74** | 24,50 | 39,4 menit |
| | **Bridging $\le 15$m** | 332 | **2,72** | **3,69** | 19,05 | 55,0 menit |
| | **Bridging $\le 30$m** | 281 | **2,30** | **3,12** | 16,12 | 69,5 menit |
| **First Diff (Raw)** | **Strict (0→1)** | 2.462 | **20,18** | **27,34** | 141,27 | 14,0 menit |
| | **Bridging $\le 15$m** | 990 | **8,11** | **10,99** | 56,80 | 47,4 menit |
| | **Bridging $\le 30$m** | 525 | **4,30** | **5,83** | 30,12 | 110,9 menit |
| **First Diff (Persist 15m)** | **Strict (0→1)** | 419 | **3,43** | **4,65** | 24,04 | 43,1 menit |
| | **Bridging $\le 15$m** | 339 | **2,78** | **3,76** | 19,45 | 56,8 menit |
| | **Bridging $\le 30$m** | 296 | **2,43** | **3,29** | 16,98 | 68,6 menit |

---

## 4. AUDIT GAP BRIDGING & ANALISIS BATAS TRANSISI GROUND TRUTH

Kekhawatiran utama audit operasional adalah apakah penggabungan jeda (*gap bridging*) menggabungkan alarm pra-kejadian secara diam-diam (*silent cross-merging*) ke dalam kejadian kebocoran baru.

### 4.1 Pemeriksaan Onset Kejadian Baru ($p158$ dan $p369$)
- **Kejadian $p158$ (Onset: 2018-10-06 02:35:00):**
  - Pada jendela 1 jam sebelum onset ($01:35$ hingga $02:30$), seluruh model mencatatkan **0 alarm**.
  - Tepat pada saat onset ($02:35$), seluruh model bernilai **0 alarm**.
  - Deteksi pertama baru muncul pada pukul $06:25$ (First Diff & Z-Score) dan $10:55$ (IsoForest).
  - *Kesimpulan:* Tidak ada episode alarm pra-onset yang digabungkan ke dalam kejadian $p158$.
- **Kejadian $p369$ (Onset: 2018-10-26 02:05:00):**
  - Pada jendela 1 jam sebelum onset ($01:05$ hingga $02:00$), seluruh model mencatatkan **0 alarm**.
  - Tepat pada saat onset ($02:05$), seluruh model bernilai **0 alarm**.
  - Deteksi pertama muncul pada pukul $02:15$ (First Diff Raw, selang 10 menit).
  - *Kesimpulan:* Tidak ada episode alarm pra-onset yang digabungkan ke dalam kejadian $p369$.

### 4.2 Pemeriksaan Offset (Akhir Kejadian Kebocoran)
- Pada akhir kebocoran $p183$, $p158$, dan $p369$, model Isolation Forest sempat mempertahankan status alarm melewati batas waktu perbaikan fisik.
- Dalam implementasi audit, runtutan false alarm dihitung menggunakan filter masker `fp_arr = (y_fixed == 0) & (pred == 1)`. Hal ini secara ketat mempartisi alarm menjadi dua ranah:
  1. Alarm sebelum offset dihitung sebagai **True Positive (TP)**.
  2. Alarm setelah offset dihitung sebagai **False Positive (FP)** baru.
- *Aturan Terverifikasi:* Bridging dalam penghitungan episode false alarm tidak pernah melintasi batas fixed burst karena interval burst bernilai ribuan langkah nol pada array `fp_arr`.

---

## 5. AUDIT SENSITIVITAS TIME-TO-DETECTION (TTD) PER KEJADIAN

Evaluasi TTD dihitung secara eksak dari timestamp onset resmi ground truth hingga timestamp pertama kali alarm berstatus positif pada kejadian tersebut:

| Model Evaluasi | Kejadian Kebocoran | Konfigurasi Filter | Timestamp Onset Ground Truth | Timestamp Deteksi Pertama | TTD (Menit) | TTD (Jam) | Alarm Pra-Onset (6 Jam Sebelum) |
|:---|:---:|:---|:---:|:---:|:---:|:---:|:---:|
| **First Difference** | **`p158`** | Raw (5m) | 2018-10-06 02:35:00 | 2018-10-06 06:25:00 | 230,0 m | **3,83 jam** | 2 langkah (terakhir 05-Okt 22:10) |
| | | Persist 15m | 2018-10-06 02:35:00 | 2018-10-06 06:45:00 | 250,0 m | **4,17 jam** | 0 langkah |
| | | Persist 30m | 2018-10-06 02:35:00 | 2018-10-06 07:00:00 | 265,0 m | **4,42 jam** | 0 langkah |
| | | Persist 60m | 2018-10-06 02:35:00 | 2018-10-06 07:30:00 | 295,0 m | **4,92 jam** | 0 langkah |
| | **`p369`** | Raw (5m) | 2018-10-26 02:05:00 | 2018-10-26 02:15:00 | 10,0 m | **0,17 jam** | 2 langkah (terakhir 25-Okt 23:25) |
| | | Persist 15m | 2018-10-26 02:05:00 | 2018-10-26 06:15:00 | 250,0 m | **4,17 jam** | 0 langkah |
| | | Persist 30m | 2018-10-26 02:05:00 | 2018-10-26 06:30:00 | 265,0 m | **4,42 jam** | 0 langkah |
| | | Persist 60m | 2018-10-26 02:05:00 | 2018-10-26 07:00:00 | 295,0 m | **4,92 jam** | 0 langkah |
| **Rolling Z-Score** | **`p158`** | Raw (5m) | 2018-10-06 02:35:00 | 2018-10-06 06:25:00 | 230,0 m | **3,83 jam** | 0 langkah |
| | | Persist 15m | 2018-10-06 02:35:00 | 2018-10-06 06:45:00 | 250,0 m | **4,17 jam** | 0 langkah |
| | | Persist 30m | 2018-10-06 02:35:00 | 2018-10-06 07:00:00 | 265,0 m | **4,42 jam** | 0 langkah |
| | | Persist 60m | 2018-10-06 02:35:00 | 2018-10-06 07:30:00 | 295,0 m | **4,92 jam** | 0 langkah |
| | **`p369`** | Raw (5m) | 2018-10-26 02:05:00 | 2018-10-26 05:45:00 | 220,0 m | **3,67 jam** | 0 langkah |
| | | Persist 15m | 2018-10-26 02:05:00 | 2018-10-26 06:05:00 | 240,0 m | **4,00 jam** | 0 langkah |
| | | Persist 30m | 2018-10-26 02:05:00 | 2018-10-26 06:20:00 | 255,0 m | **4,25 jam** | 0 langkah |
| | | Persist 60m | 2018-10-26 02:05:00 | 2018-10-26 06:50:00 | 285,0 m | **4,75 jam** | 0 langkah |
| **Isolation Forest** | **`p158`** | Raw (5m) | 2018-10-06 02:35:00 | 2018-10-06 10:55:00 | 500,0 m | **8,33 jam** | 15 langkah (terakhir 05-Okt 22:25) |
| | | Persist 15m | 2018-10-06 02:35:00 | 2018-10-06 16:05:00 | 810,0 m | **13,50 jam** | 7 langkah (terakhir 05-Okt 21:45) |
| | | Persist 30m | 2018-10-06 02:35:00 | 2018-10-06 16:20:00 | 825,0 m | **13,75 jam** | 0 langkah |
| | | Persist 60m | 2018-10-06 02:35:00 | 2018-10-06 16:50:00 | 855,0 m | **14,25 jam** | 0 langkah |
| | **`p369`** | Raw (5m) | 2018-10-26 02:05:00 | 2018-10-26 10:55:00 | 530,0 m | **8,83 jam** | 21 langkah (terakhir 25-Okt 23:05) |
| | | Persist 15m | 2018-10-26 02:05:00 | 2018-10-26 12:25:00 | 620,0 m | **10,33 jam** | 6 langkah (terakhir 25-Okt 21:30) |
| | | Persist 30m | 2018-10-26 02:05:00 | 2018-10-26 12:40:00 | 635,0 m | **10,58 jam** | 0 langkah |
| | | Persist 60m | 2018-10-26 02:05:00 | 2018-10-26 13:10:00 | 665,0 m | **11,08 jam** | 0 langkah |

### Catatan Penting Fenomena Sinyal $p369$:
Pada model First Difference Raw, kebocoran $p369$ terdeteksi sangat cepat (10 menit / $0,17$ jam) karena adanya denyut awal (*transient pulse*). Namun, sinyal tersebut sempat kembali bernilai 0 sebelum menetap secara konsisten. Akibatnya, filter persistensi 15 menit (yang mensyaratkan 3 alarm berurutan tanpa jeda) baru terkonfirmasi aktif pada pukul $06:15$ (TTD melonjak ke $4,17$ jam). Fakta ini menjelaskan trade-off teknis antara filter penekan false alarm dan latensi konfirmasi insiden.

---

## 6. LOG KETIDAKSESUAIAN & REKONSILIASI LINTAS LAPORAN (4D.74 S.D. 4D.74.3)

| ID | Parameter Audit | STEP 4D.74 & 4D.74.1 | STEP 4D.74.2 | Rekonsiliasi Definitif STEP 4D.74.3 | Status |
|:---:|:---|:---|:---|:---|:---:|
| **DISC-01** | **Semantik Label $y=0$** | Menyebut "normal period" atau "pure normal". | Mengklarifikasi ketiadaan pure normal secara hidraulik. | **Direkonsiliasi:** Wajib menggunakan istilah **“non-fixed-burst period with background leaks”**. Istilah "pure normal" dihentikan karena secara fisik tidak ada zero-leakage di test set. | **PASS** |
| **DISC-02** | **Denominator Episode / Hari** | Dinormalisasi berbasis 122 hari kalender. | Melaporkan 14,24 eps/hari (IF Raw). | **Diperjelas:** Menyediakan **Dual Denominator**: Basis Total Test (122 hari = 14,24 eps/hari) dan Basis Efektif Non-Burst (90,05 hari = 19,29 eps/hari). | **PASS** |
| **DISC-03** | **Kenaikan TTD $p369$ pada Persistensi** | Hanya menyajikan TTD Raw ($0,17$ jam). | Menyajikan perbandingan raw vs persist 15m. | **Terdokumentasi Lengkap:** Menyajikan matriks TTD menyeluruh untuk 5m, 15m, 30m, dan 60m untuk seluruh model. | **PASS** |
| **DISC-04** | **Batas Gap Bridging terhadap Onset** | Belum dianalisis per lintasan batas. | Melaporkan konsolidasi 15m & 30m. | **Terbukti Aman:** 0 alarm dalam 1 jam sebelum onset $p158$ dan $p369$. Tidak ada silent merging yang melintasi onset kebocoran baru. | **PASS** |

---

## 7. ARTEFAK AUDIT & STATUS KEPATUHAN SISTEM PRODUKSI

### 7.1 Berkas Audit yang Dihasilkan
- Skrip audit mandiri: [ml/experiments/baseline_anomaly_detection/step_4d_74_3/run_step_4d_74_3_audit.py](file:///E:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/baseline_anomaly_detection/step_4d_74_3/run_step_4d_74_3_audit.py)
- Data numerik audit: [ml/experiments/baseline_anomaly_detection/step_4d_74_3/metric_semantics_event_consistency_audit.json](file:///E:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/baseline_anomaly_detection/step_4d_74_3/metric_semantics_event_consistency_audit.json)
- Laporan komprehensif: [backend/docs/STEP_4D_74_3_METRIC_SEMANTICS_AND_EVENT_CONSISTENCY_AUDIT.md](file:///E:/TUGAS/pengkoding/anti/aqua-tara/backend/docs/STEP_4D_74_3_METRIC_SEMANTICS_AND_EVENT_CONSISTENCY_AUDIT.md)

### 7.2 Pernyataan Kepatuhan Sistem Produksi
1. **Pemisahan Eksperimen:** Tidak ada model atau konfigurasi ambang batas yang dipilih untuk produksi berdasarkan evaluasi Test Set.
2. **Kepatuhan Guardrail:** Kode produksi backend Laravel, frontend Vue, dan modul `ml/app/` tidak disentuh.
3. **Integritas Database:** Supabase tidak pernah diakses atau dimutasi; skema database tidak berubah.
4. **Formula Risiko Tetap:** Formula produksi $0.8 \cdot Score_T + 0.2 \cdot Score_S$ beserta klasifikasi Jernih (0–30), Waspada (31–70), dan Bahaya (71–100) tidak diubah.
5. **Integritas Artefak:** Seluruh artefak STEP 4D.74, STEP 4D.74.1, dan STEP 4D.74.2 tetap utuh dan tidak ditimpa.
6. **Batas Domain Transfer:** Model hidraulik benchmark BattLeDIM ditegaskan tidak siap untuk langsung diintegrasikan ke lingkungan produksi jaringan pipa AQUA-TARA.
