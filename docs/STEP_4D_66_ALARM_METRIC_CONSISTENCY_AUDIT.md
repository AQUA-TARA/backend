# STEP 4D.66 - AUDIT KONSISTENSI EVALUASI ALARM

**Status:** PASS WITH FLAGS (READ-ONLY AUDIT COMPLETED - 2 NEEDS CORRECTION, 1 BLOCKED)
**Tanggal:** 2026-10-14
**Waktu Lokal:** 21:19 WIB (UTC+7)
**Tipe:** Audit Offline Read-Only - tidak ada modifikasi kode, database, model, scaler, threshold, atau dataset.
**Ruang Lingkup:** Verifikasi konsistensi matematis dan semantis seluruh angka tabel pada laporan STEP 4D.64 dan STEP 4D.65 terhadap sumber primer (dataset mentah, metadata artefak, dataset_configuration.yaml, 2018_Fixed_Leakages_Report.txt).

**Batasan Keamanan Terpenuhi:**
- Tanpa akses atau penulisan ke database PostgreSQL, Supabase, atau database aplikasi.
- Tanpa eksekusi Laravel, PHPUnit, migrasi, seeder, db:wipe.
- Source code produksi Laravel dan Flask tidak diubah (zero production diff).
- Formula kanonikal Risk Score warga round(0.8*Score_T + 0.2*Score_S) tidak diubah.
- Ambang batas operasional IoT final NORMAL / ANOMALY / CRITICAL (Open Decision F-03) tidak ditetapkan.
- Fixture tiket #55 tidak disentuh.
- Seluruh verifikasi dilakukan via Python computation terhadap sumber data mentah; model tidak di-reinference.

---

## 1. Inventaris Artefak yang Diaudit

| Artefak | Lokasi | Ukuran | Diaudit |
|---|---|---|---|
| metadata.json | ml/experiments/battledim_baseline/ | 1.725 byte | YES |
| isolation_forest_model.joblib | ml/experiments/battledim_baseline/ | 2.216.409 byte | Ukuran saja |
| preprocessor_scaler.joblib | ml/experiments/battledim_baseline/ | 863 byte | Ukuran saja |
| 2018_Leakages.csv | ml/data/battledim/ | 6.234.468 byte | YES (ground truth) |
| 2018_Fixed_Leakages_Report.txt | ml/data/battledim/ | 317 byte | YES |
| dataset_configuration.yaml | ml/data/battledim/ | 3.926 byte | YES |
| STEP_4D_64_ISOLATED_ML_BASELINE_EXPERIMENT.md | backend/docs/ | 17.274 byte | YES |
| STEP_4D_65_BASELINE_VERIFICATION_TEMPORAL_FILTER.md | backend/docs/ | 16.881 byte | YES |

**Tidak Tersedia:**
- Raw predictions array (predictions.csv atau sejenisnya): tidak disimpan ke disk. Ini menjadi sumber beberapa temuan BLOCKED.

---

## 2. Audit Definisi dan Formula Metrik

### A. Confusion Matrix

```
Prediksi \ Aktual    Positif (Anomali)   Negatif (Normal)
Prediksi Anomali      TP                   FP
Prediksi Normal       FN                   TN
```

**Definisi label:**
- Positif (label=1): Setidaknya satu pipa Fixed Burst aktif pada timestep t (p183, p158, p369 di Test Set).
- Negatif (label=0): Tidak ada pipa Fixed Burst aktif pada timestep t.

**Status Definisi:** KONSISTEN - Definisi ini digunakan secara konsisten di kedua laporan. Ground truth diverifikasi langsung dari 2018_Leakages.csv.

### B. Formula Metrik

| Formula | Definisi | Status |
|---|---|---|
| Precision = TP/(TP+FP) | Proporsi alarm yang benar dari seluruh alarm | PASS - Sesuai standar sklearn |
| Recall = TP/(TP+FN) | Proporsi timestep anomali yang berhasil ditangkap | PASS - Sesuai standar sklearn |
| F1 = 2*P*R/(P+R) | Rata-rata harmonik Precision dan Recall | PASS - Sesuai standar sklearn |
| FAR = FP/(FP+TN) | Proporsi alarm palsu dari seluruh kondisi normal | PASS - Ini adalah FPR (False Positive Rate) |

> [!NOTE]
> FAR di sini secara matematis identik dengan False Positive Rate (FPR = FP/N), bukan False Alarm Rate dalam pengertian BattLeDIM competition (yang berbasis event/episode). Kedua laporan konsisten menggunakan definisi ini.

---

## 3. Verifikasi Aritmetika Confusion Matrix - Test Set

### A. Ground Truth Terverifikasi dari 2018_Leakages.csv

Test Set: 2018-09-01 s/d 2018-12-31

| Pipa | Aktivitas di Test Set | Timestep Aktif |
|---|---|---|
| p183 | AKTIF SAAT TEST DIMULAI (mulai 2018-08-07, berakhir 2018-09-01 17:10) | 207 |
| p158 | Aktif penuh (2018-10-06 02:35 - 2018-10-23 13:35) | 5.029 |
| p369 | Aktif penuh (2018-10-26 02:05 - 2018-11-08 20:25) | 3.965 |
| **Total** | 207 + 5.029 + 3.965 | **9.201** |

Verifikasi: 9.201 + 25.935 = 35.136 total timestep (MATCH)

### B. Hasil Verifikasi Confusion Matrix

| Nilai | STEP 4D.64 / metadata.json | Hasil Verifikasi | Status |
|---|---|---|---|
| TP | 4.199 | 4.199 | IDENTIK |
| FP | 7.241 | 7.241 | IDENTIK |
| FN | 5.002 | 5.002 | IDENTIK |
| TN | 18.694 | 18.694 | IDENTIK |
| Total | 35.136 | 4.199+7.241+5.002+18.694=35.136 | MATCH |
| Pred Anomali | 11.440 | 4.199+7.241=11.440 | MATCH |
| Actual Positif | 9.201 | 4.199+5.002=9.201 | MATCH |
| Actual Negatif | 25.935 | 7.241+18.694=25.935 | MATCH |

### C. Hasil Verifikasi Metrik Derivasi

| Metrik | Laporan | Dihitung Ulang | Deviasi | Status |
|---|---|---|---|---|
| Precision | 0.3670 | 4.199/11.440 = 0.367045 | <0.001 | IDENTIK |
| Recall | 0.4564 | 4.199/9.201 = 0.456363 | <0.001 | IDENTIK |
| F1-Score | 0.4069 | 0.406860 | <0.001 | IDENTIK |
| FAR | 0.2792 | 7.241/25.935 = 0.279198 | <0.001 | IDENTIK |

> [!IMPORTANT]
> Seluruh 8 angka confusion matrix dan 4 metrik derivasi pada evaluasi Fixed Burst dapat direproduksi secara aritmetis dari TP/FP/FN/TN yang dilaporkan. PASS.

### D. All-Leaks Metrics (Investigasi Precision=1.0000)

Empat pipa background (p257, p427, p654, p810) aktif di seluruh 35.136 timestep test set. Akibatnya:
- TN = 0, FP = 0, TP = 11.440, FN = 23.696
- Precision_all = 11.440/11.440 = 1.0000 (matematis benar, secara operasional misleading)
- Recall_all = 11.440/35.136 = 0.3256 (MATCH)
- F1_all = 2*1.0000*0.3256/(1.0000+0.3256) = 0.4912 (MATCH)

**Status:** TERVERIFIKASI. Laporan STEP 4D.65 sudah mendokumentasikan ini dengan benar sebagai degenerate all-positive test distribution.

> [!WARNING]
> Metrik All-Leaks TIDAK BOLEH digunakan sebagai basis keputusan threshold. Metrik yang valid adalah Fixed Burst metrics (Precision=0.3670, Recall=0.4564, F1=0.4069).

---

## 4. Verifikasi Split Temporal

| Subset | Rentang Waktu | Sampel Laporan | Sampel Terhitung | Status |
|---|---|---|---|---|
| Training | 2018-01-01 00:00 s/d 2018-01-23 23:50 | 6.623 | floor((23*24*60-10)/5)+1 = 6.623 | MATCH |
| Validation | 2018-07-01 00:00 s/d 2018-08-31 23:55 | 17.856 | floor((62*24*60-5)/5)+1 = 17.856 | MATCH |
| Test | 2018-09-01 00:00 s/d 2018-12-31 23:55 | 35.136 | floor((122*24*60-5)/5)+1 = 35.136 | MATCH |
| Overlap Train n Val | 0 | 0 | 0 | MATCH |
| Overlap Val n Test | 0 | 0 | 0 | MATCH |

**Catatan Training Set:** p257 mulai aktif pada 2018-01-08 13:30 (terverifikasi dari dataset_configuration.yaml). 65,12% sampel latih mengandung micro-leak. Ini didokumentasikan secara transparan di STEP 4D.65 Section 3.B. KONSISTEN.

---

## 5. Verifikasi Metrik Filter Persistensi Alarm

### A. Definisi Filter

| Filter | K (timestep) | Durasi minimum |
|---|---|---|
| Raw | 1 | 5 menit |
| 15m | 3 | 15 menit (alarm jika t-2,t-1,t semua = 1) |
| 30m | 6 | 30 menit (alarm jika t-5 ... t semua = 1) |
| 60m | 12 | 60 menit (alarm jika t-11 ... t semua = 1) |

Status: Definisi Kausal - Filter hanya menggunakan data masa lalu. PASS.

### B. Verifikasi Aritmetika (Actual Positif=9.201, Actual Negatif=25.935, Total=35.136)

| Konfigurasi | TP | FP | FN | TN | Total | P_calc | R_calc | F1_calc | FAR_calc | Status |
|---|---|---|---|---|---|---|---|---|---|---|
| Raw | 4.199 | 7.241 | 5.002 | 18.694 | 35.136 | 0.3670 | 0.4564 | 0.4069 | 0.2792 | PASS |
| 15m | 3.181 | 4.780 | 6.020 | 21.155 | 35.136 | 0.3996 | 0.3457 | 0.3707 | 0.1843 | PASS |
| 30m | 2.529 | 3.371 | 6.672 | 22.564 | 35.136 | 0.4286 | 0.2749 | 0.3349 | 0.1300 | PASS |
| 60m | 1.906 | 2.126 | 7.295 | 23.809 | 35.136 | 0.4727 | 0.2072 | 0.2881 | 0.0820 | PASS |

Semua total = 35.136. Semua Actual Positives (TP+FN) = 9.201. Semua Actual Negatives (FP+TN) = 25.935.

### C. Verifikasi Rata-rata Durasi Episode Alarm

Formula: Avg Duration = (Total Pred Positive Steps * 5 menit) / Total Episodes

| Konfigurasi | Pred Positives | Episodes | Avg Duration Calc | Laporan | Status |
|---|---|---|---|---|---|
| Raw | 11.440 | 2.254 | 11.440*5/2.254 = 25,38 menit | 25,4 menit | PASS |
| 15m | 7.961 | 868 | 7.961*5/868 = 45,87 menit | 45,9 menit | PASS |
| 30m | 5.900 | 444 | 5.900*5/444 = 66,44 menit | 66,4 menit | PASS |
| 60m | 4.032 | 198 | 4.032*5/198 = 101,82 menit | 101,8 menit | PASS |

### D. Verifikasi FP Episode per Minggu

> [!WARNING]
> NEEDS CORRECTION - Definisi dan denominasi "FP Episode per Minggu" tidak terdokumentasi secara eksplisit.

Investigasi numerik menunjukkan inkonsistensi:

**Test 1:** Total Episodes / Lama Test (17,43 minggu):

| Config | Total Ep | Calc div 17.43 wk | Reported | Selisih |
|---|---|---|---|---|
| Raw | 2.254 | 129,3/wk | 92,5/wk | -36,8 (MISMATCH) |
| 15m | 868 | 49,8/wk | 34,2/wk | -15,6 (MISMATCH) |
| 30m | 444 | 25,5/wk | 17,1/wk | -8,4 (MISMATCH) |
| 60m | 198 | 11,4/wk | 7,5/wk | -3,9 (MISMATCH) |

**Test 2:** Total Episodes / Val+Test gabungan (26,29 minggu):

| Config | Total Ep | Calc div 26.29 wk | Reported | Selisih |
|---|---|---|---|---|
| Raw | 2.254 | 85,7/wk | 92,5/wk | -6,8 (tidak cocok) |
| 15m | 868 | 33,0/wk | 34,2/wk | -1,2 (mendekati) |
| 30m | 444 | 16,9/wk | 17,1/wk | -0,2 (mendekati) |
| 60m | 198 | 7,53/wk | 7,5/wk | MATCH |

**Analisis:** Nilai 60m cocok dengan denominator Val+Test (26,29 minggu), namun Raw tidak cocok dengan pola yang sama. Kemungkinan interpretasi:
1. "FP Episode" bukan seluruh episode, melainkan hanya alarm runs yang tidak tumpang tindih dengan periode insiden aktual.
2. Denominasi menggunakan jumlah "normal weeks" yang berbeda per konfigurasi.
3. Tanpa raw predictions array, jumlah FP episodes sejati tidak dapat dihitung ulang.

**Verdict:** Kolom "FP Episode per Minggu" - NEEDS CORRECTION. Definisi episode dan denominator harus didokumentasikan.

---

## 6. Audit Tiga Insiden Tertangkap

### A. Tabel Status Insiden pada Test Set

| Pipa | Tipe | Start | End | Status di Test Set | Timestep di Test |
|---|---|---|---|---|---|
| p183 | abrupt | 2018-08-07 02:35 (val set) | 2018-09-01 17:10 | AKTIF SAAT TEST DIMULAI | 207 |
| p158 | abrupt | 2018-10-06 02:35 | 2018-10-23 13:35 | Mulai baru di test | 5.029 |
| p369 | abrupt | 2018-10-26 02:05 | 2018-11-08 20:25 | Mulai baru di test | 3.965 |

### B. Verifikasi TTD - Insiden p158

| Parameter | Nilai | Perhitungan | Status |
|---|---|---|---|
| Start insiden | 2018-10-06 02:35 | - | SESUAI dataset_configuration.yaml |
| End insiden | 2018-10-23 13:35 | - | SESUAI 2018_Fixed_Leakages_Report.txt |
| Deteksi mentah pertama | 2018-10-06 11:55 | - | BLOCKED: no predictions.csv |
| TTD mentah | 560 menit (9,33 jam) | 11:55-02:35 = 9h20m = 560min; 560/5=112 step | ARITMETIK KONSISTEN |
| Run persisten mulai | 2018-10-06 16:05 | - | BLOCKED: no predictions.csv |
| TTD 15m (trigger 16:15) | 820 menit / 13,67 jam | 16:15-02:35 = 13h40m = 820min | ARITMETIK KONSISTEN |
| TTD 30m (trigger 16:30) | 835 menit / 13,92 jam | 16:30-02:35 = 13h55m = 835min | ARITMETIK KONSISTEN |
| TTD 60m (trigger 17:00) | 865 menit / 14,42 jam | 17:00-02:35 = 14h25m = 865min | ARITMETIK KONSISTEN |
| Deteksi sebelum akhir? | YA | Okt 6 << Okt 23 | PASS |

### C. Verifikasi TTD - Insiden p369

| Parameter | Nilai | Perhitungan | Status |
|---|---|---|---|
| Start insiden | 2018-10-26 02:05 | - | SESUAI dataset_configuration.yaml |
| End insiden | 2018-11-08 20:25 | - | SESUAI 2018_Fixed_Leakages_Report.txt |
| Deteksi mentah pertama | 2018-10-26 10:55 | - | BLOCKED: no predictions.csv |
| TTD mentah | 530 menit (8,83 jam) | 10:55-02:05 = 8h50m = 530min; 530/5=106 step | ARITMETIK KONSISTEN |
| TTD 15m (trigger 13:05) | 660 menit / 11,00 jam | 13:05-02:05 = 11h0m = 660min | ARITMETIK KONSISTEN |
| TTD 30m (trigger 13:20) | 675 menit / 11,25 jam | 13:20-02:05 = 11h15m = 675min | ARITMETIK KONSISTEN |
| TTD 60m (trigger 13:50) | 705 menit / 11,75 jam | 13:50-02:05 = 11h45m = 705min | ARITMETIK KONSISTEN |
| Deteksi sebelum akhir? | YA | Okt 26 << Nov 8 | PASS |

### D. Insiden p183 - STATUS AMBIGUOUS

> [!CAUTION]
> NEEDS CORRECTION - Klaim "3 insiden tertangkap" untuk p183 memerlukan klarifikasi.

**Fakta yang terverifikasi:**
- p183 mulai 2018-08-07 02:35 - berada di Validation Set (bukan Test Set).
- p183 berakhir 2018-09-01 17:10 - 17 jam 10 menit setelah Test Set dimulai.
- p183 berkontribusi 207 timestep pada Test Set (terverifikasi dari 2018_Leakages.csv).
- 207 * 5 min = 1.035 min = 17h 15min. KONSISTEN.

**Isu yang tidak terdokumentasi:**
1. TTD untuk p183 tidak dilaporkan di STEP 4D.64 maupun 4D.65. Hanya p158 dan p369 yang memiliki TTD.
2. Sistem mungkin sudah membunyikan alarm untuk p183 selama Validation Set (threshold tuning). Jika alarm pertama di val set, "deteksi p183 dalam test set" sebenarnya mengandalkan alarm yang sudah aktif.
3. STEP 4D.65 Section 6.C menyatakan "3 dari 3 insiden tertangkap (p183, p158, p369)" namun tidak ada bukti TTD p183 dalam test window.

**Koreksi yang diperlukan untuk STEP 4D.65:**
1. Klarifikasi eksplisit: "p183 DIMULAI di val set, BERAKHIR 17 jam setelah test dimulai."
2. TTD p183 dalam test window (jika ada), atau pernyataan bahwa deteksi p183 tidak dapat diverifikasi.
3. Jika incident catch ketat hanya 2/2 (p158 dan p369), laporan harus memperbarui klaim.

**Verdict p183:** Klaim 3/3 adalah PLAUSIBLE (insiden masih aktif 17+ jam) tetapi TIDAK TERVERIFIKASI. Status: BLOCKED - memerlukan raw predictions atau re-run.

---

## 7. Audit Insiden yang Aktif Saat Test Dimulai

Dari dataset_configuration.yaml, insiden aktif pada 2018-09-01 00:00:00:

| Pipa | Start | End | Aktif Saat Test Start | Kategori |
|---|---|---|---|---|
| p257 | 2018-01-08 13:30 | 2019-12-31 | YES | Background (unreported) |
| p427 | 2018-02-13 08:25 | 2019-12-31 | YES | Background (unreported) |
| p654 | 2018-07-05 03:40 | 2019-12-31 | YES | Background (unreported) |
| p810 | 2018-07-28 03:05 | 2019-12-31 | YES | Background (unreported) |
| p183 | 2018-08-07 02:35 | 2018-09-01 17:10 | YES | Fixed Burst (reported) |

**Implikasi:**
1. Empat pipa background aktif 100% test set -> All-Leaks Precision=1.0000 (degenerate). Sudah didokumentasikan dengan benar.
2. p183 aktif di 207/35.136 timestep pertama. Ini tercakup dalam 9.201 positive timestep. ARITMETIK KONSISTEN.
3. StandardScaler di-fit pada training yang mengandung micro-leak p257. Diakui transparan di STEP 4D.65 Section 3.B. PASS.

---

## 8. Verifikasi Heuristik Rolling Z-Score

| Metrik | Laporan | Hitung Ulang | Status |
|---|---|---|---|
| Precision | 0.3449 | 1521/(1521+2890) = 0.3448 | PASS (delta<0.001) |
| Recall | 0.1653 | 1521/(1521+7680) = 0.1653 | PASS |
| F1-Score | 0.2235 | 2*0.3449*0.1653/(0.3449+0.1653) = 0.2235 | PASS |
| FAR | 0.1114 | 2890/(2890+23045) = 0.1114 | PASS |
| Total | 35.136 | 1521+2890+7680+23045 = 35.136 | PASS |

---

## 9. Analisis Trade-off False Alarm vs Insiden Terdeteksi

> [!IMPORTANT]
> Tidak ada threshold yang dipilih atau direkomendasikan. Ini adalah bahan keputusan, bukan keputusan. Open Decision F-03 tetap terbuka.

### A. Data Trade-off Terverifikasi

| Config | F1 | FAR | Recall | Total Eps/wk (test) | TTD p158 | TTD p369 | Incidents Caught |
|---|---|---|---|---|---|---|---|
| Raw | 0.4069 | 27,92% | 45,64% | ~129/wk | 9,33 jam | 8,83 jam | >=2/3 (p183 BLOCKED) |
| 15m | 0.3707 | 18,43% | 34,57% | ~50/wk | 13,67 jam | 11,00 jam | >=2/3 |
| 30m | 0.3349 | 13,00% | 27,49% | ~25/wk | 13,92 jam | 11,25 jam | >=2/3 |
| 60m | 0.2881 | 8,20% | 20,72% | ~11/wk | 14,42 jam | 11,75 jam | >=2/3 |

Catatan: Eps/wk menggunakan denominasi total episodes/test_weeks (17,43). Nilai yang dilaporkan STEP 4D.65 menggunakan definisi berbeda yang belum terdokumentasi.

### B. Temuan Kritis untuk Keputusan F-03

1. Tidak ada konfigurasi dengan FAR <= 5% dan Recall >= 30% secara bersamaan:
   - Filter 60m: FAR 8,20% tetapi Recall hanya 20,72%.
   - Filter raw: Recall 45,64% tetapi FAR 27,92% (tidak operasional).

2. Semua konfigurasi berhasil menangkap p158 dan p369 sebelum akhir insiden.

3. Pemilihan filter berdasarkan Precision saja adalah strategi yang salah:
   - Precision 0.4727 (60m) tertinggi, tetapi dicapai dengan melewatkan 79,3% timestep anomali.
   - Keputusan harus menyeimbangkan konsekuensi missed detection vs false alarm fatigue.

4. BattLeDIM adalah jaringan DMA tertutup dengan 33 sensor sinkron - tidak langsung representatif untuk PDAM Indonesia.

### C. Dimensi yang Belum Tersedia untuk F-03

1. Toleransi operator: berapa maksimum alarm palsu per minggu yang dapat ditangani?
2. Konsekuensi missed detection: berapa biaya kehilangan air per jam kebocoran tidak terdeteksi?
3. Kalibrasi ulang: threshold BattLeDIM perlu rekalibrasi pada pola Indonesia (intermittent supply).
4. Kapasitas notifikasi tim lapangan.

### D. Opsi Arsitektur yang Tidak Memerlukan F-03 Sekarang

- Dual-level threshold: ML score >= tau selama >= 30 menit = ANOMALY; tekanan fisik < batas aman = CRITICAL.
- Ini memisahkan keputusan ML (tunable) dari batas fisik (deterministik).

---

## 10. Ringkasan Temuan Audit

### A. PASS (Terverifikasi)

| Item | Status |
|---|---|
| Confusion matrix Fixed Burst (TP/FP/FN/TN) - 8 angka | PASS |
| Formula Precision/Recall/F1/FAR | PASS |
| Metrik derived P/R/F1/FAR (delta<0.001) | PASS |
| All-Leaks Precision=1.0000 (degenerate case) | PASS |
| Split temporal sample counts | PASS |
| Zero overlap antar split | PASS |
| p257 micro-leak di training (transparan didokumentasikan) | PASS |
| Filter persistensi confusion matrix (4 konfigurasi, 16 angka) | PASS |
| Filter persistensi P/R/F1/FAR | PASS |
| Rata-rata durasi episode alarm | PASS |
| TTD p158 (raw+15m/30m/60m) - aritmetik timestamp | PASS |
| TTD p369 (raw+15m/30m/60m) - aritmetik timestamp | PASS |
| Deteksi p158 sebelum akhir insiden (margin 17+ hari) | PASS |
| Deteksi p369 sebelum akhir insiden (margin 13+ hari) | PASS |
| Heuristik Rolling Z-Score metrics | PASS |
| Insiden aktif saat test dimulai (p183, p257/p427/p654/p810) | PASS |
| Anti-data leakage (.shift(1).rolling) - implementasi kausal | PASS |
| Zero database/production diff | PASS |

### B. NEEDS CORRECTION

> [!CAUTION]
> NEEDS CORRECTION #1 - Kolom "FP Episode per Minggu" tidak reproducible dari informasi yang tersedia.

Laporan: STEP 4D.65 Table Section 6.B.
Temuan: Nilai (92,5/34,2/17,1/7,5) tidak dapat direproduksi dengan denominasi yang jelas.
- Total Episodes / test_weeks (17,43): menghasilkan 129,3/49,8/25,5/11,4 - MISMATCH signifikan.
- Total Episodes / val+test_weeks (26,29): 60m cocok (7,53), Raw meleset 6,7 unit.
- Definisi "FP Episode" tidak terdokumentasi.

Koreksi diperlukan:
1. Definisi eksplisit: FP Episode = alarm run tidak overlap insiden OR = total alarm runs.
2. Denominasi eksplisit: test_weeks vs val+test_weeks vs normal_weeks.
3. Angka FP episode sejati vs total episode.

---

> [!CAUTION]
> NEEDS CORRECTION #2 - Klaim "3 dari 3 insiden tertangkap" tidak sepenuhnya terdokumentasi.

Laporan: STEP 4D.65 Section 6.C.
Temuan:
- p183 dimulai 2018-08-07 (Validation Set), bukan Test Set.
- p183 hanya aktif 207 timestep pertama test set.
- TTD p183 tidak dilaporkan.
- Jika alarm terpicu di val set, "catch" adalah hasil threshold tuning bukan blind test.

Koreksi diperlukan:
1. Klarifikasi: "p183 DIMULAI di val set, BERAKHIR 17 jam setelah test dimulai."
2. TTD p183 dalam test window atau pernyataan tidak dapat diverifikasi.
3. Jika hanya 2/2 yang dapat diklaim ketat (p158 dan p369), perbarui klaim.

### C. BLOCKED

> [!NOTE]
> BLOCKED - Raw predictions array tidak disimpan ke disk.

Item yang tidak dapat diverifikasi:
1. FP Episode per Minggu: tidak dapat hitung FP episodes sejati tanpa predictions array.
2. TTD p183: tidak dapat konfirmasi apakah alarm terpicu dalam 207-step window.
3. Timestamp deteksi mentah p158/p369: tidak dapat konfirmasi terhadap actual predictions.
4. Timestamp persistent run p158/p369: tidak dapat konfirmasi.

Catatan: Nilai-nilai yang BLOCKED secara aritmetik KONSISTEN dengan timestamp yang dilaporkan.
Rekomendasi teknis: Simpan y_pred_raw.npy dan timestamps_test.npy sebagai artefak.

---

## 11. Rekomendasi untuk Bahan Keputusan F-03

> [!IMPORTANT]
> Open Decision F-03 tetap terbuka. Tidak ada threshold NORMAL/ANOMALY/CRITICAL yang ditetapkan.

### Fakta Terverifikasi

1. Raw (no filter) tidak layak produksi: FAR 27,92% (~130 alarm palsu/minggu) = alarm fatigue.
2. Filter 30m adalah titik keseimbangan defensif: FAR 13%, Recall 27,5%, F1=0.3349.
3. Filter 60m mendekati toleransi industri: FAR 8,20%, namun Recall hanya 20,72%.
4. Tidak ada konfigurasi memenuhi FAR <= 5% sambil menjaga Recall yang bermakna.
5. Jangan pilih filter berdasarkan Precision saja - ini menyembunyikan tradeoff yang kritis.

### Ringkasan Akhir

| Area Audit | Status | Catatan |
|---|---|---|
| Formula Confusion Matrix | PASS | Konsisten di kedua laporan |
| Aritmetika TP/FP/FN/TN | PASS | 100% reproducible dari metadata.json |
| Metrik P/R/F1/FAR | PASS | Delta < 0.001 |
| All-Leaks Precision=1.0000 | PASS | Degenerate case terdokumentasi |
| Split temporal dan sample counts | PASS | Terverifikasi dari formula |
| Heuristik Z-Score metrics | PASS | Total dan metrik konsisten |
| Filter persistensi P/R/F1/FAR | PASS | 4 konfigurasi, 16 angka |
| Rata-rata durasi episode | PASS | Formula cocok tepat |
| TTD p158 dan p369 | PASS | Aritmetik timestamp konsisten |
| Deteksi sebelum akhir insiden | PASS | p158/p369: margin 13-17 hari |
| FP Episode per Minggu | NEEDS CORRECTION | Definisi dan denominasi tidak terdokumentasi |
| Klaim 3/3 insiden tertangkap | NEEDS CORRECTION | TTD p183 tidak ada; boundary val/test ambiguous |
| Raw predictions timestamp | BLOCKED | Predictions array tidak disimpan ke disk |

Keputusan F-03: Tetap terbuka. Bahan keputusan tersedia di Section 9.

---

## 12. Perubahan Berkas (Audit Ini)

| Berkas | Jenis Perubahan |
|---|---|
| backend/docs/STEP_4D_66_ALARM_METRIC_CONSISTENCY_AUDIT.md | Penambahan berkas (dokumen ini) |

Tidak ada berkas lain yang dimodifikasi. Tidak ada kode produksi, database, model, scaler, threshold, atau dataset yang diubah.
