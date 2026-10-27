# STEP 4D.74.4 — FINAL REPRODUCIBILITY AND ALARM EPISODE AUDIT

**Tanggal Audit:** 15 Oktober 2026  
**Auditor:** Tim Teknis AQUA-TARA / Lead AI Data & ML Auditor  
**Lingkup Audit:** Audit komparatif dan verifikasi independen read-only terhadap artefak STEP 4D.74, STEP 4D.74.1, STEP 4D.74.2, dan STEP 4D.74.3. Menghitung ulang seluruh matriks konfusi, presisi, recall, F1, dan FAR langsung dari file CSV prediksi dan ground truth mentah; memverifikasi kausalitas filter persistensi; mengaudit gap bridging dan membandingkan dual denominator normalisasi episode alarm; mengurai alarm pra-onset, selama kejadian, dan pasca-kejadian; mengaudit konsistensi timestep lintas file; serta menyusun discrepancy log komprehensif.  
**Status Sistem Produksi:** Read-Only & Unchanged (Zero Supabase, Zero Migration/Seeder, Zero Mutation pada Backend/Frontend/ML Production, Formula Risiko Produksi Tetap Utuh).

---

## 1. RINGKASAN EKSEKUTIF & STATUS PER BUTIR PEMERIKSAAN

| No | Butir Pemeriksaan | Status | Temuan Kritis & Bukti Terverifikasi |
|:---|:---|:---:|:---|
| 1 | **Hitung Ulang Metrik Klasifikasi Langsung dari CSV & Ground Truth** | **PASS** | Seluruh 48 sel metrik ($TP, FP, TN, FN$, Precision, Recall, F1, FAR) dihitung ulang langsung dari `battledim_test_predictions.csv.gz` dan `2018_Leakages.csv` tanpa mengandalkan teks laporan lama. Hasil kalkulasi independen cocok $100\%$. |
| 2 | **Verifikasi Episode Alarm & Dual Denominator Normalisasi** | **PASS** | Aturan Strict (0→1), Gap Bridging 15m, dan 30m terverifikasi. Normalisasi episode per hari dan per minggu disajikan dalam **Dual Denominator**: **Penyebut Kalender Total ($122,00$ hari)** vs **Penyebut Observasi Non-Burst Efektif ($90,05$ hari)** (misal IF Raw = $14,24$ vs $19,29$ eps/hari). Keterbatasan interpretasi masing-masing denominator didokumentasikan. |
| 3 | **Verifikasi Kausalitas Filter Persistensi & Rekalkulasi TTD ($p158$ & $p369$)** | **PASS** | Filter persistensi terverifikasi **bersifat kausal murni** (hanya melihat rentang mundur $[t - W + 1, t]$ tanpa data masa depan). TTD terverifikasi: pada $p369$, Raw First Diff mendeteksi dalam 10 menit ($0,17$ jam), namun persistensi 15m memerlukan $4,17$ jam ($250$ menit) karena pola burst awal yang berdenyut (*intermittent pulse*). |
| 4 | **Partisi Alarm Pra-Onset, Selama Kejadian, dan Pasca-Kejadian** | **PASS** | Terverifikasi bahwa pada $t-5$ menit sebelum onset dan pada jendela 1 jam sebelum onset, **seluruh model memiliki 0 alarm** untuk $p158$ dan $p369$. Deteksi onset baru terkonfirmasi murni (*valid new onset*) dan bukan kelanjutan alarm palsu pra-eksisting. |
| 5 | **Audit Konsistensi Timestep Lintas File & Artefak JSON** | **PASS** | Tepat $35.136$ baris ($2018\text{-}09\text{-}01\text{ }00:00:00$ s.d. $2018\text{-}12\text{-}31\text{ }23:55:00$) selaras $100\%$ antara CSV prediksi, irisan ground truth leakages, dan seluruh 4 berkas JSON (74, 74.1, 74.2, 74.3). |
| 6 | **Discrepancy Log Komprehensif** | **PASS** | 5 poin perbedaan terminologi, normalisasi, dan penemuan teknis telah dicatat lengkap dengan nilai lama, nilai hitung ulang, akar penyebab, dan status penyelesaian. |
| 7 | **Kepatuhan Guardrails Sistem Produksi** | **PASS** | Tidak ada pemilihan model dari test set. Supabase, migrasi, dan seeder tidak disentuh. Kode produksi dan formula risiko tetap utuh. Model BattLeDIM ditegaskan tidak siap untuk produksi. |

---

## 2. AUDIT KONSISTENSI DATASET, TIMESTEP, DAN RENTANG WAKTU

Pemeriksaan baris-ke-baris (*row-by-row strict equality*) antara file prediksi tersimpan dan file sumber ground truth BattLeDIM 2018:

- **File Prediksi:** `ml/experiments/baseline_anomaly_detection/step_4d_74/battledim_test_predictions.csv.gz` (35.136 baris, 12 kolom).
- **File Ground Truth:** `ml/data/battledim/2018_Leakages.csv` (105.120 baris total, irisan uji = 35.136 baris).
- **Rentang Waktu:** `2018-09-01 00:00:00` hingga `2018-12-31 23:55:00` (Interval sampling tepat 5 menit / 300 detik).
- **Hasil Alignment Timestamp:** **100% Identik** (`timestamps_exact_alignment = True`).
- **Verifikasi Kolom $y$:** Sinyal `y_fixed_burst` pada berkas prediksi terbukti identik $100\%$ dengan ekspresi boolean `(df_test_leaks[fixed_burst_cols] > 0).any(axis=1)`.
- **Komposisi Fisik Data Pengujian (35.136 Timestep):**
  - Timestep Fixed Burst Aktif ($P$): **9.201 langkah (26,19%)**
  - Timestep Non-Fixed Burst ($N$): **25.935 langkah (73,81%)**
  - Timestep dengan Background Leak Aktif: **35.136 langkah (100,00%)**
  - Timestep Bebas Kebocoran Mutlak (*Zero Leakage*): **0 langkah (0,00%)**

---

## 3. HASIL HITUNG ULANG INDEPENDEN: CONFUSION MATRIX & METRIK KLASIFIKASI

Seluruh metrik dihitung ulang langsung dari array biner CSV prediksi terhadap array biner ground truth `y_fixed`:
- $\text{Precision} = \frac{TP}{TP + FP}$
- $\text{Recall} = \frac{TP}{TP + FN} = \frac{TP}{9.201}$
- $\text{F1-Score} = 2 \cdot \frac{\text{Precision} \cdot \text{Recall}}{\text{Precision} + \text{Recall}}$
- $\text{FAR Timestep} = \frac{FP}{FP + TN} = \frac{FP}{25.935}$

| Model Evaluasi | Konfigurasi Sinyal | True Positive (TP) | False Positive (FP) | True Negative (TN) | False Negative (FN) | Precision | Recall | F1-Score | FAR Timestep | Status Verifikasi |
|:---|:---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **First Difference** | **Raw (5m)** | 2.660 | 6.885 | 19.050 | 6.541 | 27,87% | 28,91% | 28,38% | 26,55% | **PASS (Exact)** |
| | **Persist 15m** | 1.471 | 3.610 | 22.325 | 7.730 | 28,95% | 15,99% | 20,60% | 13,92% | **PASS (Exact)** |
| **Rolling Z-Score** | **Raw (5m)** | 2.243 | 6.423 | 19.512 | 6.958 | 25,88% | 24,38% | 25,11% | 24,77% | **PASS (Exact)** |
| | **Persist 15m** | 1.203 | 3.369 | 22.566 | 7.998 | 26,31% | 13,07% | 17,47% | 12,99% | **PASS (Exact)** |
| **Isolation Forest** | **Raw (5m)** | 4.047 | 6.672 | 19.263 | 5.154 | 37,76% | 43,98% | 40,63% | 25,73% | **PASS (Exact)** |
| | **Persist 15m** | 2.819 | 4.042 | 21.893 | 6.382 | 41,09% | 30,64% | 35,10% | 15,59% | **PASS (Exact)** |

*(Catatan: Penjumlahan baris konsisten mutlak: $TP+FN = 9.201$ dan $FP+TN = 25.935$).*

---

## 4. VERIFIKASI EPISODE ALARM & DUAL DENOMINATOR NORMALISASI

Dalam evaluasi operasional utilitas air minum, jumlah alarm palsu disajikan dalam unit "episode" (insiden dispatch tim lapangan) bukan sekadar timestep diskrit.

### 4.1 Definisi Aturan Episode
1. **Strict Transitions (0→1):** Setiap transisi status sinyal dari 0 ke 1 dihitung sebagai episode mandiri baru tanpa jeda toleransi.
2. **Gap Bridging 15 Menit ($\le 3$ timestep):** Jeda senyap antar-alarm $\le 15$ menit digabungkan menjadi 1 episode tunggal berkelanjutan.
3. **Gap Bridging 30 Menit ($\le 6$ timestep):** Jeda senyap antar-alarm $\le 30$ menit digabungkan menjadi 1 episode investigasi tunggal.

### 4.2 Analisis Dual Denominator dan Keterbatasannya
- **Penyebut Kalender Total (Penyebut A = 122,00 Hari / 17,43 Minggu):**  
  *Kelebihan:* Menggambarkan beban kerja riil operator sepanjang rentang kalender 4 bulan penuh.  
  *Keterbatasan:* Denominator ini mencakup $31,95$ hari periode burst aktif di mana false alarm tidak dapat terjadi (karena alarm pada burst dihitung sebagai TP), sehingga angka frekuensi per hari tampak lebih rendah secara semu.
- **Penyebut Observasi Non-Burst Efektif (Penyebut B = 90,05 Hari / 12,86 Minggu):**  
  *Kelebihan:* Secara matematis murni menghitung *hazard rate* kemunculan alarm palsu hanya saat sistem berada dalam kondisi non-burst (25.935 timestep).  
  *Keterbatasan:* Tidak mencerminkan hari kalender aktual di utilitas jika fixed burst sedang berlangsung lama.

### 4.3 Tabel Komparasi Episode Alarm Palsu Terverifikasi

| Model & Sinyal | Aturan Konsolidasi | Total Episode FP | Eps / Hari (Penyebut A: Total 122 Hari) | Eps / Hari (Penyebut B: Efektif 90 Hari) | Eps / Minggu (Penyebut A) | Durasi Rata-rata Episode |
|:---|:---|:---:|:---:|:---:|:---:|:---:|
| **Isolation Forest (Raw)** | **Strict (0→1)** | 1.737 | **14,24** | **19,29** | 99,67 | 19,2 menit |
| | **Bridging $\le 15$m** | 426 | **3,49** | **4,73** | 24,44 | 100,5 menit |
| | **Bridging $\le 30$m** | 258 | **2,11** | **2,87** | 14,80 | 181,6 menit |
| **Isolation Forest (Persist 15m)** | **Strict (0→1)** | 585 | **4,80** | **6,50** | 33,57 | 34,5 menit |
| | **Bridging $\le 15$m** | 366 | **3,00** | **4,06** | 21,00 | 64,2 menit |
| | **Bridging $\le 30$m** | 235 | **1,93** | **2,61** | 13,48 | 114,0 menit |
| **Rolling Z-Score (Raw)** | **Strict (0→1)** | 2.248 | **18,43** | **24,96** | 128,99 | 14,3 menit |
| | **Bridging $\le 15$m** | 911 | **7,47** | **10,12** | 52,27 | 47,3 menit |
| **Rolling Z-Score (Persist 15m)** | **Strict (0→1)** | 427 | **3,50** | **4,74** | 24,50 | 39,4 menit |
| | **Bridging $\le 15$m** | 332 | **2,72** | **3,69** | 19,05 | 55,0 menit |
| **First Diff (Raw)** | **Strict (0→1)** | 2.462 | **20,18** | **27,34** | 141,27 | 14,0 menit |
| | **Bridging $\le 15$m** | 990 | **8,11** | **10,99** | 56,80 | 47,4 menit |
| **First Diff (Persist 15m)** | **Strict (0→1)** | 419 | **3,43** | **4,65** | 24,04 | 43,1 menit |
| | **Bridging $\le 15$m** | 339 | **2,78** | **3,76** | 19,45 | 56,8 menit |

---

## 5. AUDIT KAUSALITAS FILTER PERSISTENSI & TIME-TO-DETECTION (TTD)

### 5.1 Verifikasi Kausalitas Filter
Implementasi filter persistensi diperiksa untuk memastikan tidak ada kebocoran masa depan (*future data lookahead*):
$$\text{Persist}_W(t) = \mathbb{I}\left( \sum_{i=0}^{W-1} \text{Alarm}(t - i) = W \right)$$
Filter diimplementasikan menggunakan sliding window kausal ke belakang (backward-looking). Nilai pada waktu $t$ hanya ditentukan oleh nilai pada interval $[t - W + 1, t]$. Tidak ada data $t+1$ atau sesudahnya yang digunakan. Status: **PASS (Strictly Causal)**.

### 5.2 Rekalkulasi TTD pada Kejadian Baru ($p158$ dan $p369$)

| Model Deteksi | Pipa / Kejadian | Konfigurasi Sinyal | Onset Ground Truth | Timestamp Deteksi Pertama | TTD (Menit) | TTD (Jam) | Kausalitas Terverifikasi |
|:---|:---:|:---|:---:|:---:|:---:|:---:|:---:|
| **First Difference** | **`p158`** | Raw (5m) | 2018-10-06 02:35:00 | 2018-10-06 06:25:00 | 230,0 m | **3,83 jam** | Ya |
| | | Persist 15m | 2018-10-06 02:35:00 | 2018-10-06 06:45:00 | 250,0 m | **4,17 jam** | Ya |
| | | Persist 30m | 2018-10-06 02:35:00 | 2018-10-06 07:00:00 | 265,0 m | **4,42 jam** | Ya |
| | | Persist 60m | 2018-10-06 02:35:00 | 2018-10-06 07:30:00 | 295,0 m | **4,92 jam** | Ya |
| | **`p369`** | Raw (5m) | 2018-10-26 02:05:00 | 2018-10-26 02:15:00 | 10,0 m | **0,17 jam** | Ya |
| | | Persist 15m | 2018-10-26 02:05:00 | 2018-10-26 06:15:00 | 250,0 m | **4,17 jam** | Ya |
| | | Persist 30m | 2018-10-26 02:05:00 | 2018-10-26 06:30:00 | 265,0 m | **4,42 jam** | Ya |
| | | Persist 60m | 2018-10-26 02:05:00 | 2018-10-26 07:00:00 | 295,0 m | **4,92 jam** | Ya |
| **Rolling Z-Score** | **`p158`** | Raw (5m) | 2018-10-06 02:35:00 | 2018-10-06 06:25:00 | 230,0 m | **3,83 jam** | Ya |
| | | Persist 15m | 2018-10-06 02:35:00 | 2018-10-06 06:45:00 | 250,0 m | **4,17 jam** | Ya |
| | **`p369`** | Raw (5m) | 2018-10-26 02:05:00 | 2018-10-26 05:45:00 | 220,0 m | **3,67 jam** | Ya |
| | | Persist 15m | 2018-10-26 02:05:00 | 2018-10-26 06:05:00 | 240,0 m | **4,00 jam** | Ya |
| **Isolation Forest** | **`p158`** | Raw (5m) | 2018-10-06 02:35:00 | 2018-10-06 10:55:00 | 500,0 m | **8,33 jam** | Ya |
| | | Persist 15m | 2018-10-06 02:35:00 | 2018-10-06 16:05:00 | 810,0 m | **13,50 jam** | Ya |
| | **`p369`** | Raw (5m) | 2018-10-26 02:05:00 | 2018-10-26 10:55:00 | 530,0 m | **8,83 jam** | Ya |
| | | Persist 15m | 2018-10-26 02:05:00 | 2018-10-26 12:25:00 | 620,0 m | **10,33 jam** | Ya |

---

## 6. PARTISI ALARM: PRA-ONSET, SELAMA KEJADIAN, DAN PASCA-KEJADIAN

Audit memastikan bahwa alarm yang terjadi tidak berupa alarm warisan (*pre-existing false alarm*) yang kebetulan menyala saat onset:

### 6.1 Pemeriksaan Batas Kejadian $p158$ (Onset: 2018-10-06 02:35:00 | Durasi: 5.029 Timestep)
- **First Difference:**
  - Jendela 6 jam pra-onset: 2 alarm (terakhir pada 05-Okt 22:10, berjarak 4 jam 25 menit sebelum onset).
  - Jendela 1 jam pra-onset ($01:35$–$02:30$): **0 alarm**.
  - Sesaat sebelum onset ($02:30$): **0 alarm**.
  - Tepat saat onset ($02:35$): **0 alarm**.
  - Selama kejadian ($02:35$ s.d. 23-Okt $13:35$): **1.434 alarm**.
  - Jendela 6 jam pasca-kejadian: 19 alarm.
- **Rolling Z-Score:** Jendela 6h pra = 0; Jendela 1h pra = 0; $t-5$m = 0; Selama kejadian = 1.203 alarm; Pasca 6h = 16 alarm.
- **Isolation Forest:** Jendela 6h pra = 15 alarm (terakhir 05-Okt 22:25, berjarak 4 jam 10 menit); Jendela 1h pra = 0; $t-5$m = 0; Selama kejadian = 2.363 alarm; Pasca 6h = 63 alarm.

### 6.2 Pemeriksaan Batas Kejadian $p369$ (Onset: 2018-10-26 02:05:00 | Durasi: 3.965 Timestep)
- **First Difference:** Jendela 6h pra = 2 alarm (terakhir 25-Okt 23:25, berjarak 2 jam 40 menit); Jendela 1h pra = 0; $t-5$m = 0; Selama kejadian = 1.154 alarm; Pasca 6h = 1 alarm.
- **Rolling Z-Score:** Jendela 6h pra = 0; Jendela 1h pra = 0; $t-5$m = 0; Selama kejadian = 978 alarm; Pasca 6h = 0 alarm.
- **Isolation Forest:** Jendela 6h pra = 21 alarm (terakhir 25-Okt 23:05, berjarak 3 jam); Jendela 1h pra = 0; $t-5$m = 0; Selama kejadian = 1.644 alarm; Pasca 6h = 25 alarm.

**Kesimpulan Auditor:** Pada kedua kejadian kebocoran baru ($p158$ dan $p369$), **kondisi sinyal pada saat $t-5$ menit dan 1 jam sebelum onset adalah nol mutlak pada semua model**. Tidak ada alarm yang sudah aktif sebelum onset yang dihitung sebagai deteksi kejadian baru.

---

## 7. EXHAUSTIVE DISCREPANCY LOG (REKONSILIASI PENUH)

| ID | Parameter Audit | Nilai / Klaim Terdahulu (4D.74 / 4D.74.1) | Nilai Hitung Ulang Mandiri (4D.74.4) | Akar Penyebab Perbedaan | Status Penyelesaian |
|:---:|:---|:---|:---|:---|:---:|
| **DISC-01** | **Semantik Definisi Label $y=0$** | Menyebut "pure normal" atau "normal period". | **100% sampel pengujian memiliki background leaks.** Zero-leakage fisik = 0 baris (0,00%). | Laporan awal tidak memeriksa 4 kolom kebocoran background dalam matriks 14 kolom kebocoran. | **PASS (Resolved)** — Distandardisasi sebagai *"non-fixed-burst period with background leaks"*. |
| **DISC-02** | **Normalisasi Episode False Alarm per Hari** | 14,24 eps/hari pada IF Raw (berbasis 122 hari). | **Dual Denominator:** 14,24 eps/hari (Basis 122 hari kalender) vs **19,29 eps/hari** (Basis 90,05 hari non-burst). | Denominator kalender menyertakan 31,95 hari masa burst aktif di mana false alarm tidak dapat terjadi. | **PASS (Documented)** — Kedua angka dicantumkan bersama keterbatasannya. |
| **DISC-03** | **Dinamika Latensi Sinyal $p369$** | Hanya melaporkan TTD Raw First Diff = 0,17 jam (10 menit). | TTD Raw = 0,17 jam, namun **Persist 15m = 4,17 jam (250m)**, Persist 30m = 4,42 jam, Persist 60m = 4,92 jam. | Burst $p369$ diawali denyut awal terputus sebelum stabil, sehingga persistensi kausal menunda alarm konfirmasi. | **PASS (Verified)** — Tabel sensitivitas TTD disajikan lengkap. |
| **DISC-04** | **Integritas Batas Gap Bridging** | Belum dianalisis per batas indeks onset. | Terverifikasi: 0 alarm dalam 1h sebelum onset pada seluruh model; tidak ada penggabungan silang melewati onset. | Pemeriksaan indeks mikro $[idx-3:idx+3]$ membuktikan pemisahan bersih antara masa non-burst dan burst. | **PASS (Confirmed Safe)** — Zero silent cross-merging terbukti empiris. |
| **DISC-05** | **Konsistensi Timestep Lintas File** | 35.136 baris pengujian. | Tepat 35.136 baris ($122,0$ hari) cocok 100% antara CSV prediksi, ground truth slice, dan 4 file JSON. | Pipeline evaluasi mempertahankan interval 5 menit yang konsisten tanpa interpolasi liar. | **PASS (100% Consistent)** |

---

## 8. PERNYATAAN PENUTUP & KEPATUHAN GUARDRAIL

Audit STEP 4D.74.4 menegaskan integritas penuh dari seluruh hasil evaluasi baseline hidraulik:
1. Seluruh metrik matriks konfusi, episode alarm, dan TTD telah terbukti dapat direproduksi secara mandiri (*independently reproducible*).
2. Tidak ada model yang dipilih untuk implementasi produksi dari test set.
3. Seluruh guardrail sistem produksi dipatuhi secara ketat: kode produksi backend Laravel, frontend Vue, database Supabase, formula risiko produksi $0.8 \cdot Score_T + 0.2 \cdot Score_S$, dan ambang batas risiko tidak dimodifikasi.
4. Model benchmark BattLeDIM tidak dinyatakan siap untuk produksi di jaringan pipa AQUA-TARA.
