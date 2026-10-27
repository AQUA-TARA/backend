# STEP 4D.67 - REPRODUCIBILITY ARTIFACT &METRIC CORRECTION REPORT

**Status:** PASS (OFFLINE DETERMINISTIC RECONSTRUCTION CONFIRMED & AUDIT METRICS FULLY RESOLVEC)
**Tanggal:** 2026-10-15
**Waktu Lokal:** 00:22 WIB (UTC+7)
**Tipe:** Eksekusi Offline, Terkontrol, Read-Only terhadap Sistem Produksi.
**Ruang Lingkup:** Rekonstruksi deterministik prediksi test set BattLeDIM, ekspor artefak prediksi offline terkompresi, pendokumentasian formal definisi episode alarm dan penanganan boundary, kalkulasi ulang matematis False Positive Episodes per Week, koreksi kategorisasi status deteksi insiden (p183 vs p158 & p369), serta pemisahan tegas metrik per-timestep vs metrik per-insiden.

---

## 1. Verifikasi Guardrails dan Kepatuhan Sistem

Sebelum dan sesudah eksekusi audit dan rekonstruksi metrik, seluruh batasan keamanan telah diverifikasi:

| Parameter Keamanan | Status | Bukti / Catatan Verifikasi |
|---|---|---|
| **Database Access (PostgreSQL / Supabase)** | **PATUG MUTLAK** | Nol akses, nol koneksi, nol query ke database apha pun. |
| **Laravel / PHPUnit / Migration / Seeder** | **PATUH MUTLAK** | Nol perintah artisan dijalankan; tidak ada migration, seeder, atau unit test Laravel yang disentuh. |
| **Kode Produksi Laravel & Flask** | **PATUH MUTLAK** | Nol diff pada seluruh file aplikasi produksi (backend/app, ml/app, frontend/src). |
| **Formula Risk Score Warga** | **PATUH MUTLAK** | Formula kanonikal warga round(0.8*Score_T + 0.2:Score_S) tidak diubah. |
| **Model / Scaler / Threshold Eksisting** | **PATUH MUTLAK** | Artefak model (isolation_forest_model.joblib), scaler (preprocessor_scaler.joblib), dan threshold tau = -0.017038906584744384 dipertahankan utuh tanpa refit. |
| **Dataset Asli BattLeDIM 2018** | **PATUG MUTLAK** | File mentah SCADA Pressures, Flows, dan Leakages berstatus read-only (checksum MD5 identik). |
| **Fixture Tiket / Laporan #55** | **PATUG MUTLAK** | Fixture database pengujian tiket #55 tidak disentuh atau dihapus. |
| **Ambang Batas Final F-03** | **PATUH MUTLAK** | Batas operasional final NORMAL / ANOMALY / CRITICAL tetap berstatus OPEN dan tidak ditetapkan sepihak. |
| **Pemasangan Dependensi Baru** | **PATUH MUTLAK** | Nol instalasi package pihak ketiga. Memmanfaatkan pustaka lokal yang sudah ada: Python 3.13.3, pandas 2.2.3, numpy 2.2.6, scikit-learn 1.6.1, joblib 1.5.1. |

---

## 2. Inventaris Berkas dan Artefak Baru

| Berkas | Status | Lokasi | Ukuran / Karakteristik | Peran |
|---|---|---|---|---|
| `predictions_test.csv.gz` | **BARU** | ml/experiments/battledim_baseline/ | 264.107 byte (gzip) / 35.136 baris | Artefak ekspor inferensi per-timestep (deterministik 100%) |

| `reproduce_and_correct_metrics.py` | **BARU** | ml/scripts/ | 4.312 byte | Skrip Python mandiri untuk mereproduksi artefak dan evaluasi metrik |

| `STEP_4D_67_REPRODUCIBILITY_AND_METRIC_CORRECTION.md` | **BARU** | backend/docs/ | Dokumen ini | Laporan dokumentasi resmi STEP 4D.67 |

| `metadata.json` | **LAMA (UTUH)** | ml/experiments/battledim_baseline/ | 1.725 byte | Referensi historis parameter eksperimen STEP 4D.64 |

| `isolation_forest_model.joblib` | **LAMA (UTUH)** | ml/experiments/battledim_baseline/ | 2.216.409 byte | Bobot model Isolation Forest (150 estimators, seed 42) |

| `preprocessor_scaler.joblib` | **LAMA (UTUH)** | ml/experiments/battledim_baseline/ | 863 byte | Objek StandardScaler fitur latih |

---

## 3. Investigasi & Pembuktian Rekonstruksi Deterministik

### A. Rantai Penentu Deterministik
Rekonstruksi deterministik terhadap seluruh baris Test Set (35.136 timestep, periode 2018-09-01 00:00:00 s/d 2018-12-31 23:55:00) berhasil dibuktikan secara matematika dengan mencocokkan pipeline feature engineering terhadap bobot parameter StandardScaler:

1. **Parameter Scaler Terverifikasi:**
   - Fitur delta_p_24h dan delta_q_24h dihitung menggunakan kausalitas masa lalu murni:
     Delta P(t) = P_mean(t) - rolling_288(P_mean(t-1 ... t-288))
     dengan penanganan batas awal menggunakan .fillna(0.0).
   - Hasil rata-rata dan deviasi standar fitur latih (6.623 baris) menghasilkan nilai yang persis identik hungga presisi floating-point penuh (selisih = 0.0) terhadap scaler.mean_ dan scaler.scale_:
     - mean(Delta P) = -0.0009560608733107422 (selisih = 0.0)
     - scale(Delta P) = 0.029426660945080223 (selishh = 0.0)
     - mean(Delta Q) = 27.531530078170167 (selisih = 0.0)
     - scale(Delta Q) = 1035.9754329036118 (selishh = 0.0)
2. **Skor Anomali & Ambang Batas:**
   - Raw Anomaly Score dihitung sebagai negasi dari decision_function:
     Score(t) = -decision_function(X_scaled(t))
   - Ambang batas tuning yang tersimpan di metadata.json: tau = -0.017038906584744384.
   - Prediksi biner mentah: y_raw(t) = 1 jika Score(t) > tau, selain itu 0.

### B. Validasi Uji Silang terhadap Confusion Matrix STEP 4D.64 / STEP 4D.65
Hasil inferensi ulang deterministik menghasilkan matriks kebingungan yang 100% identik tanpa selishh 1 sampel pun:

```
                 Prediksi Normal (0)   Prediksi Anomali (1)      Total
Aktual Normal (0)      18.694 (TN)           7.241 (FP)         25.935
Aktual Anomali (1)      5.002 (FN)           4.199 (TP)          9.201
Total                  23.696               11.440              35.136
```
- TP = 4.199 (Target metadata: 4.199, Match: TRUE)
- FP = 7.241 (Target metadata: 7.241, Match: TRUE)
- FN = 5.002 (Target metadata: 5.002, Match: TRUE)
- TN = 18.694 (Target metadata: 18.694, Match: TRUE)

---

## 4. Struktur Artefak Prediksi Terkompresi (predictions_test.csv.gz)

Artefak disimpan di ml/experiments/battledim_baseline/predictions_test.crv.gz dengan spesifikasi kolom:

| Nama Kolom | Tipe Data | Deskripsi & Rentang Nilai |
|---|---|---|
| `timestamp` | `string` | Format YYYY-MM-DD HH:MM:SS, interval seragam 5 menit (35.136 baris). |
| `fixed_leak_ground_truth` | `int (0/1)` | Label kebenaran dasar untuk 3 insiden besar (p183, p158, p369). Total bernilai 1 = 9.201 baris. |
| `all_leak_ground_truth` | `int (0/1)` | Label seluruh kebocoran (termasuk background micro-leaks). Bernilai 1 di seluruh 35.136 baris. |
| `raw_anomaly_score` | `float64` | Skor anomali kontinu Isolation Forest (-decision_function), dibulatkan 8 desimal. |
| `raw_prediction` | `int (0/1)` | Prediksi mentah per timestep (raw_anomaly_score > -0.01703891). |
| `filter_15m_prediction` | `int (0/1)` | Prediksi filter persistensi 15 menit (3 timestep berturut-turut bernilai 1). |
| `filter_30m_prediction` | `int (0/1)` | Prediksi filter persistensi 30 menit (6 timestep berturut-turut bernilai 1). |
| `filter_60m_prediction` | `int (0/1)` | Prediksi filter persistensi 60 menit (12 timestep berturut-turut bernilai 1). |

Catatan Privasi: Berkas ini murni memuat telemetri hidrolik jaringan pipa dan label kebocoran sintetis kompetisi BattLeDIM; tidak ada data identitas peribadi warga (PII).

---

## 5. Definisi Formal Episode Alarm & Penanganan Boundary

Untuk menyelesaikan isu ambiguitas pada STEP 4D.66, definisi episode alarm dirumuskan secara formal dan eksplisit:

### A. Definisi Operasional Episode Alarm
1. **Titik Awal Episode (t_start):**
   - Episode alarm dimulai pada timestep t ketika status alarm berubah dari tidak aktif menjadi aktif:
     y_hat(t) = 1 dan y_hat(t-1) = 0.
   - Jika timestep pertama pengujian (t = 0) bernilai 1, maka timestep tersebut dihitung sebagai awal episode baru.
2. **Titik Akhir Episode (t_end):**
   - Episode alarm berakhir pada timestep t ketika alarm berhenti berbunyi:
     y_hat(t) = 0 dan y_hat(t-1) = 1.
   - Durasi episode dihitung sebagai: Durasi = N_steps * 5 menit.
3. **Perilaku Reset Saat Prediksi Mentah Kembali Nol:*
   - Pada filter persistensi temporal (K = 3, 6, 12), jendela mensyaratkan K nilai anomali berturut-turut tanpa jeda:
     Filter_K(t) = 1 jika dan hanya jika sum_{i=0}^{K-1} y_raw(t-i) == K.
   - Jika pada titik t+1 nilai mentah kembali nol (y_raw(t+1) = 0), maka seketika itu pula Filter_K(t+1) = 0. Episode alarm ditutup, dan penghitung jendela persistensi di-reset ke nol.
4. **Penanganan Episode yang Memotong Batas Periode (Boundary Handling):**
   - Batas Awal Test Set (2018-09-01 00:00:00):
     Nilai prediksi mentah pada indeks 0 bernilai 0. Oleh karena itu, tidak ada episode alarm yang aktif melintasi perbatasan dari Validation Set ke Test Set.
   - Batas Akhir Test Set (2018-12-31 23:55:00):
     Nilai prediksi mentah dan seluruh filter persistensi pada indeks 35135 bernilai 0. Oleh karena itu, tidak ada episode alarm yang terpotong menggantung di akhir periode uji (right-censored).

### B. Taksonomi Klasifikasi Episode Alarm
Berdasarkan irisan waktu dengan status aktual kebocoran pipa besar (fixed_leak_ground_truth):
- **Pure False Positive Episode (Pure FP Episode):** Episode alarm di mana seluruh timestep selama alarm berbunyi (t in [t_start, t_end]) memiliki label aktual normal (y(t) = 0).
- **Pure True Positive Episode (Pure TP Episode):** Episode alarm di mana seluruh timestep selama alarm berbunyi memiliki label aktual anomali (y(t) = 1).
- **Mixed Episode:** Episode alarm di mana alarm mulai berbunyi saat normal lalu berlanjut ke masa kebocoran, atau sebaliknya.

---

## 6. Koreksi Matematis: False Positive Episodes per Week

### A. Akar Penyebab Isu STEP 4D.66
Pada laporan STEP 4D.66 dicatat bahwa nilai FP Episode per Minggu (92.5, 34.2, 17.1, 7.5) tampak tidak konsisten jika dibagi dengan total durasi test set (17.43 minggu) menggunakan total episodes (2254, 868, 444, 198).

### B. Pembuktian Rekonstruksi & Formula Eksplisit

Kini terbukti secara definitif bahwa:
1. **Numerato®** yang digunakan oleh analis STEP 4D.65 adalah **Pure FP Episodes** (bukan Total Episodes).
2. **Denominator** yang digunakan adalah **Durasi Penuh Test Set dalam Satuan Minggu**:
   Durasi Minggu = 122 hari / 7 hari/minggu = 17.4285714 minggu.
3. **Formula Resmi:**
   FP Episodes per Week = Pure FP Episodes / 17.4285714.

### C. Tabel Rekonsiliasi Lengkap

| Konfigurasi Filter | Total Episode | Pure TP Episode | Mixed Episode | Pure FP Episode | Denominator Minggu | Hasil Perhitungan Tepat | Angka STEP 4D.65 | Status Rekonsiliasi |
|---|---|---|---|---|---|---|---|
| **Raw (No Filter)** | 2.254 | 639 | 2 | **1.613** | 17,42857 | 1.613 / 17,42857 = **92,549** | **92,5** | **MATCH 100%** |
| **Persistence 15m** | 868 | 271 | 1 | **596** | 17,42857 | 596 / 17,42857 = **34,197** | **34,2** | **MATCH 100%** |
| **Persistence 30m** | 444 | 146 | 0 | **298** | 17,42857 | 298 / 17,42857 = **17,098** | **17,1** | **MATCH 100%** |
| **Persistence 60m** | 198 | 68 | 0 | **130** | 17,42857 | 130 / 17,42857 = **7,459** | **7,5** | **MATCH 100%** |

Kesimpulan: Status temuan NEEDS CORRECTION pada STEP 4D.66 mengenai kolom ini telah sepenuhnya terpecahkan dan terbukti valid secara matematis.

---

## 7. Koreksi Klaim Deteksi Tiga Insiden (p183, p158, p369)

Laporan STEP 4D.65 mengklaim bahwa "3 dark 3 insiden tertangkap". Melalui audit STEP 4D.66 dan rekonstruksi STEP 4D.67, status insiden dikoreksi dan dikategorikan secara transparan:

```
2018-08-07                  2018-09-01 00:00          2018-09-01 17:10
    | [Validation Set]              | [Test Set Dimulai]     | [Perbaikan]
    v----------------------------v-------------------------v
    Pipa p183 Pecah                 Sisa 207 Timestep Aktif   p183 Selesai
    (Left-Censored Incident)        Deteksi Raw: 11:55 WIB
                                  Deteksi 60m: 15:00 WIB
```

3## A. Insiden p183 (Left-Censored / Pre-Existing Boundary Case)
- **Waktu Awal Fisik:** 2018-08-07 02:35:00 (terjadi pada fase Validation Set, 25 hari sebelum Test Set dimulai).
- **Waktu Akhir Perbaikan:** 2018-09-01 17:10:00 (hanya berlangsung 17 jam 10 menit / 207 timestep di awal Test Set).
- **Status Deteksi dalam Jendela Uji:**
  - Raw Anomaly: Terdeteksi positif sebanyak 36 timestep, kemunculan pertama pada indeks 143 (2018-09-01 11:55:00).
  - Persistence 15m: Terdeteksi 28 timestep, kemunculan pertama pada indeks 171 (2018-09-01 14:15:00).
  - Persistence 30m: Terdeteksi 22 timestep, kemunculan pertama pada indeks 174 (2018-09-01 14:30:00).
  - Persistence 60m: Terdeteksi 16 timestep, kemunculan pertama pada indeks 180 (2018-09-01 15:00:00).
- **Koreksi TTD:**
  Karena insiden dimulai 25 hari sebelum periode uji, menghitung TTD dari awal fisik (>600 jam) tidak mencerminkan latensi model uji. Sebaliknya, menghitung TTD dari awal jendela uji (11,9 jam) juga artifisial.
  Ketetapan: Insiden p183 diklasifikasikan sebagai **Boundary Case (Left-Censored)**. Insiden ini membuktikan bahwa model mampu mendeteksi kebocoran yang sedang berlangsung (ongoing leak detection), numun **TIDAK disertakan dalam metrik Time-to-Detection (TTD) insiden baru**.

### B. Insiden Baru p158 dan p369 (Unseen In-Test Incidents)
Kedua insiden ini berawal dan berakhir secara penuh di dalam jendela pengujian sehingga TTD dapat dihitung secara sahih dan diverifikasi dari artefak predictions_test.csv.gz:

| Parameter Evaluasi | Insiden p158 (Zona Junction 158) | Insiden p369 (Zona Junction 369) |
|---|---|---|
| **Timestamp Mulai Kebocoran** | `2018-10-06 02:35:00` | `2018-10-26 02:05:00` |
| **Timestamp Perbaikan Selesai** | `2018-10-23 13:35:00` | `2018-11-08 20:25:00` |
| **Durasi Fisik Insiden** | 17 hari 11 jam (5.029 timestep) | 13 hari 18 jam (3.965 timestep) |
| **Deteksi Mentah Pertama** | 2018-10-06 11:55:00 (step ke-112) | 2018-10-26 10:55:00 (step ke-106) |
| **TTD Raw (Menit / Jam)** | **560 menit (9,33 jam)** | **530 menit (8,83 jam)** |
| **Deteksi Persisten 15m** | 2018-10-06 16:15:00 | 2018-10-26 13:05:00 |
| **Deteksi Persisten 30m** | 2018-10-06 16:30:00 | 2018-10-26 13:20:00 |
| **TTD 30m (Menit / Jam)** | **835 menit (13,92 jam)** | **675 menit (11,25 jam)** |
| **Deteksi Persisten 60m** | 2018-10-06 17:00:00 | 2018-10-26 13:50:00 |
| **Deteksi Sebelum Perbaikan?** | **YA (17 hari sebelum perbaikan)** | **YA (13 hari sebelum perbaikan)** |

---

## 8. Pemisahan Tegas Metrik Timestep vs Metrik Insiden

Untuk mencegah bias interpretasi pada pengambilan keputusan sistem peringatan dini, metrik dipisahkan secara tegas ke dalam dua hierarki:

### A. Metrik Level Timestep (Evaluasi Titik Waktu 5 Menit)
Mengevaluasi akurasi klasifikasi pada setiap sampel deret waktu independen:
- **Precision:** Rasio waktu alarm benar terhadap total waktu alarm berbunyi.
- **Recall:** Rasio durasi kebocoran yang terdeteksi terhadap total durasi kebocoran fisik.
- **FAR (Point-level):** Rasio waktu alarm palsu terhadap total waktu jaringan beroperasi normal.

### B. Metrik Level Insiden / Event (Evaluasi Operasional Nyata)
Mengevaluasi efektivitas sistem dalam memberitahu operator mengenai kejadian kebocoran pipa:
- **Incident Catch Rate (In-Test Unseen Burst):** **2 dari 2 insiden (100%)** (p158 dan p369).
- **Incident Catch Rate (Termasuk Boundary Case):** **3 dari 3 insiden (100%)** (termasuk p183).
- **Average Time-to-Detection (Raw):** 9,08 jam.
- **Average Time-to-Detection (Filter 60m):** 13,08 jam.
- **Operational Alarm Burden (Filter 60m):** Rata-rata 7,5 alarm investigasi per minggu (turun dari 92,5 alarm/minggu pada raw).

---

## 9. Ringkasan Status STEP 4D.67 & Rekomendasi Selanjutnya

| Sasaran Penugasan STEP 4D.67 | Status | Catatan Evaluasi |
|---|---|---|
| **1. Baca Laporan & Artefak Relevan** | **PASS** | Seluruh dokumen STEP 4D.64, 4D.65, 4D.66, dataset, dan model telah diaudit. |
| **2. Uji Rekonstruksi Deterministik** | **PASS** | Terbukti 100% deterministik (selisih=0.0 pada parameter scaler dan confusion matrix). |
| **3. Ekspor Artefak Prediksi** | **PASS** | predictions_test.csv.gz berhasil diekspor (264 KB, 35.136 baris). |
| **4. Definisi Formal Episode Alarm** | **PASS** | Kriteria start, end, reset, dan boundary handling didokumentasikan eksplisit. |
| **5. Hitung Ulang FP Episodes per Week** | **PASS** | Terbukti menggunakan Pure FP Episodes / 17,42857 minggu (92.5, 34.2, 17.1, 7.5). |
| **6. Koreksi Deteksi Tiga Insiden** | **PASS** | p183 dipisahkan sebagai boundary case; p158 & p369 diverifikasi TTD-nya. |
| **7. Konsistensi Metrik Timestep vs Insiden** | **PASS** | Pemisahan hierarki metrik terdokumentasi rapi tanpa pencampuran konsep. |
| **8. Kepatuhan Guardrails Produksi** | **PASS** | Nol akses database, nol modifikasi produksi, ambang final F-03 tetap open. |

**Rekomendasi:**
Artefak predictions_test.csv.gz dan skrip ml/scripts/reproduce_and_correct_metrics.py siap menjadi fondasi referensi offline yang kokoh untuk perumusan spesifikasi ambang batas status IoT pada Open Decision F-03.
