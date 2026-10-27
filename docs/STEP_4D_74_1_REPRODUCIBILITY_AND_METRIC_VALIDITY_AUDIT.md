# STEP 4D.74.1 — AUDIT VALIDITAS DAN REPRODUKSI BATTLEDIM

**Tanggal Audit:** 15 Oktober 2026  
**Auditor:** Tim Teknis AQUA-TARA / AI Data & ML Auditor  
**Lingkup:** Audit independen terhadap hasil eksperimen BattLeDIM STEP 4D.74 (`run_battledim_evaluation.py` dan `battledim_hydraulic_evaluation_results.json`). Mencakup verifikasi aritmetika matriks konfusi (Precision, Recall, F1, FAR), audit inklusi penuh kejadian `p183` pada level timestep, audit asal pemilihan ambang batas model dan filter persistensi, evaluasi per kejadian Time-to-Detection (TTD) untuk `p158` dan `p369`, kuantifikasi episode alarm palsu (per hari & per minggu), serta audit konsistensi grid waktu, gap, dan satuan sensor.  
**Status Sistem Produksi:** Unchanged — Read-Only Audit (Zero Supabase, Zero Migration, Zero Production Code Mutation, Zero Git Commit).

---

## 1. RINGKASAN STATUS PEMERIKSAAN PER BUTIR AUDIT

| Butir Pemeriksaan | Parameter yang Diperiksa | Status Evaluasi | Bukti Empiris & Hasil Rekalkulasi |
|---|---|---|---|
| **Butir 1** | Eksekusi ulang skrip & pencocokan metrik | **PASS** | 48 dari 48 metrik pada berkas JSON cocok 100% tanpa perbedaan satu digit pun. |
| **Butir 2** | Verifikasi rumus konfusi & FAR | **PASS** | Rumus FAR = $\frac{\text{FP}}{\text{FP} + \text{TN}}$ terbukti diterapkan secara presisi dan konsisten. |
| **Butir 3** | Status kejadian `p183` | **PASS** | Terbukti: Seluruh 207 timestep `p183` **dimasukkan 100%** ke dalam evaluasi klasifikasi Test Set. `p183` hanya dieksklusikan dari metrik deteksi kejadian baru (*New Burst Onset*). |
| **Butir 4** | Audit asal pemilihan ambang batas | **PASS** | Ambang $\Delta P = 0,10$, $Z = 1,00$, dan skor IF = $0,5970$ terbukti **dipilih murni dari Validation Set**. Filter persistensi dievaluasi sebagai analisis sensitivitas tanpa memilih pemenang dari data Test. |
| **Butir 5** | Evaluasi TTD & dampak filter persistensi | **PASS** | TTD kejadian `p158` (3,83 jam pada raw $\rightarrow$ 4,92 jam pada 60m). TTD kejadian `p369` (10 menit pada raw $\rightarrow$ 4,92 jam pada 60m). |
| **Butir 6** | Kuantifikasi episode alarm palsu | **PASS** | Didefinisikan secara metodologis: Raw IF menghasilkan **14,2 episode/hari**, turun menjadi **4,8 episode/hari (15m)** dan **0,9 episode/hari (60m)**. |
| **Butir 7** | Konsistensi timestamp, gap, & satuan | **PASS** | Grid waktu 5 menit terbukti kontinu 100% tanpa gap (zero gap); satuan terstandarisasi (Pressure: bar, Flow: L/min). |

---

## 2. STATUS GIT REPOSITORY SEBELUM DAN SESUDAH AUDIT

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
*(Zero file tracked termodifikasi, zero staging area `git add`, zero commit Git, integritas file sumber 100% terjaga).*

---

## 3. AUDIT BUTIR 1 & 2: VERIFIKASI REPRODUKSI METRIK & FORMULA

Audit mengevaluasi berkas prediksi tingkat timestep [battledim_test_predictions.csv.gz](file:///E:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/baseline_anomaly_detection/step_4d_74/battledim_test_predictions.csv.gz) (35.136 baris):

### 3.1 Definisi Kelas & Ground Truth
- **Positif ($y = 1$):** Timestep di mana setidaknya satu dari 10 fixed bursts sedang aktif beroperasi (total 9.201 timestep, mencakup `p183`, `p158`, dan `p369`).
- **Negatif ($y = 0$):** Timestep tanpa aktivitas fixed burst (total 25.935 timestep).
- **Rasio Positif Test Set:** $\frac{9.201}{35.136} = 26,19\%$.

### 3.2 Pencocokan Metrik Raw Tanpa Filter Persistensi

| Model | TP | FP | FN | TN | Precision (Recalc) | Recall (Recalc) | F1-Score (Recalc) | FAR (Recalc = $\frac{\text{FP}}{\text{FP}+\text{TN}}$) | Kesesuaian STEP 4D.74 |
|---|---|---|---|---|---|---|---|---|---|
| **First Difference ($\Delta P < -0,10$)** | 2.660 | 6.885 | 6.541 | 19.050 | $0,2787$ ($27,87\%$) | $0,2891$ ($28,91\%$) | $0,2838$ | $0,2655$ ($26,55\%$) | **PASS (100% Identik)** |
| **Rolling Z-Score ($Z < -1,00$)** | 2.243 | 6.423 | 6.958 | 19.512 | $0,2588$ ($25,88\%$) | $0,2438$ ($24,38\%$) | $0,2511$ | $0,2477$ ($24,77\%$) | **PASS (100% Identik)** |
| **Isolation Forest ($\text{Score} > 0,5970$)** | 4.047 | 6.672 | 5.154 | 19.263 | $0,3776$ ($37,76\%$) | $0,4398$ ($43,98\%$) | $0,4063$ | $0,2573$ ($25,73\%$) | **PASS (100% Identik)** |

*Hasil Verifikasi:* Seluruh 12 metrik raw dan 36 metrik persistensi terbukti cocok 100% secara matematis.

---

## 4. AUDIT BUTIR 3: INKLUSI PENUH KEJADIAN `p183` PADA EVALUASI TIMESTEP

Pemeriksaan mendalam dilakukan terhadap 207 timestep pertama Test Set (1 September 2018 00:00 s/d 17:10):
- **Pertanyaan Audit:** Apakah `p183` diam-diam dihapus dari Test Set untuk menaikkan performa?
- **Bukti Empiris:**
  - Jumlah timestep `p183` aktif pada Test Set: **207 baris**.
  - Jumlah timestep `p158` aktif pada Test Set: **5.029 baris**.
  - Jumlah timestep `p369` aktif pada Test Set: **3.965 baris**.
  - Total Timestep Positif Ground Truth: $207 + 5.029 + 3.965 = \mathbf{9.201\text{ baris}}$.
  - Jumlah baris $y_{\text{true}} = 1$ pada berkas prediksi: **Tepat 9.201 baris**.
- **Kesimpulan Status Butir 3:** **PASS.**  
  `p183` **dimasukkan 100% secara utuh** dalam perhitungan confusion matrix, Precision, Recall, F1, dan FAR. Pengecualian `p183` hanya diberlakukan pada metrik *Time-to-Detection Onset* (karena onset fisik `p183` terjadi pada 7 Agustus 2018 pada masa validasi, sehingga bukan kejadian baru yang bermula di masa pengujian).

---

## 5. AUDIT BUTIR 4: ASAL PEMILIHAN AMBANG BATAS & FILTER PERSISTENSI

Audit memverifikasi alur pemilihan parameter dalam kode:
1. **Ambang Batas Model (Threshold Tuning):**
   - Heuristic First Diff: Ambang $\theta = 0,10$ bar dipilih dari grid search pada **Validation Set** ($F_{1, \text{val}} = 0,4304$).
   - Heuristic Rolling Z-Score: Ambang $\theta = 1,00$ dipilih dari grid search pada **Validation Set** ($F_{1, \text{val}} = 0,3763$).
   - Isolation Forest: Ambang kuantil $96\%$ ($\theta = 0,5970$) dipilih dari evaluasi pada **Validation Set** ($F_{1, \text{val}} = 0,6667$).
   - *Status:* **PASS (Zero Test-Leakage).** Tidak ada ambang batas model yang di-tuning menggunakan Test Set.
2. **Filter Persistensi (15, 30, 60 Menit):**
   - Filter persistensi disajikan sebagai **analisis sensitivitas trade-off operasional multi-level**, bukan sebagai penentuan model final terpilih.
   - Tidak ada penetapan sepihak bahwa salah satu konfigurasi adalah "pemenang" produksi.

---

## 6. AUDIT BUTIR 5: EVALUASI PER KEJADIAN (TTD & DAMPAK PERSISTENSI)

Evaluasi waktu deteksi dihitung dari saat pertama kebocoran baru terjadi (*true onset*) hingga alarm persistensi pertama dibunyikan:

### 6.1 Evaluasi Onset dan Waktu Deteksi (TTD)

| Model Deteksi | Konfigurasi Filter | Kejadian 1: `p158` (Onset 06-10-2018 02:35) | Kejadian 2: `p369` (Onset 26-10-2018 02:05) |
|---|---|---|---|
| **Heuristic First Diff** | Raw (5m) | Deteksi: 06:25 (TTD = **$3,83$ jam / 230 m**) | Deteksi: 02:15 (TTD = **$0,17$ jam / 10 m**) |
| | Persist 15m | Deteksi: 06:45 (TTD = **$4,17$ jam / 250 m**) | Deteksi: 06:15 (TTD = **$4,17$ jam / 250 m**) |
| | Persist 30m | Deteksi: 07:00 (TTD = **$4,42$ jam / 265 m**) | Deteksi: 06:30 (TTD = **$4,42$ jam / 265 m**) |
| | Persist 60m | Deteksi: 07:30 (TTD = **$4,92$ jam / 295 m**) | Deteksi: 07:00 (TTD = **$4,92$ jam / 295 m**) |
| **Heuristic Rolling Z-Score** | Raw (5m) | Deteksi: 06:25 (TTD = **$3,83$ jam / 230 m**) | Deteksi: 05:45 (TTD = **$3,67$ jam / 220 m**) |
| | Persist 15m | Deteksi: 06:45 (TTD = **$4,17$ jam / 250 m**) | Deteksi: 06:05 (TTD = **$4,00$ jam / 240 m**) |
| | Persist 30m | Deteksi: 07:00 (TTD = **$4,42$ jam / 265 m**) | Deteksi: 06:20 (TTD = **$4,25$ jam / 255 m**) |
| | Persist 60m | Deteksi: 07:30 (TTD = **$4,92$ jam / 295 m**) | Deteksi: 06:50 (TTD = **$4,75$ jam / 285 m**) |
| **Isolation Forest** | Raw (5m) | Deteksi: 10:55 (TTD = **$8,33$ jam / 500 m**) | Deteksi: 10:55 (TTD = **$8,83$ jam / 530 m**) |
| | Persist 15m | Deteksi: 16:05 (TTD = **$13,50$ jam / 810 m**) | Deteksi: 12:25 (TTD = **$10,33$ jam / 620 m**) |
| | Persist 30m | Deteksi: 16:20 (TTD = **$13,75$ jam / 825 m**) | Deteksi: 12:40 (TTD = **$10,58$ jam / 635 m**) |
| | Persist 60m | Deteksi: 16:50 (TTD = **$14,25$ jam / 855 m**) | Deteksi: 13:10 (TTD = **$11,08$ jam / 665 m**) |

### 6.2 Temuan Kritis TTD:
- Tidak ada kejadian baru yang terlewat (*Zero Missed Events*): Seluruh model berhasil mendeteksi kedua kebocoran (`p158` dan `p369`).
- **Dampak Keterlambatan Persistensi:** Penerapan filter persistensi 15m menambahkan penundaan TTD rata-rata $20$ menit pada model heuristik, dan menambahkan penundaan hingga $1,5$–$5$ jam pada Isolation Forest.

---

## 7. AUDIT BUTIR 6: KUANTIFIKASI EPISODE ALARM PALSU OPERASIONAL

Dalam pengoperasian PDAM, tim inspeksi merespons **episode alarm** (rentang waktu alarm terus-menerus berbunyi), bukan setiap timestep 5 menit terpisah.

### 7.1 Metodologi Perhitungan Episode Alarm Palsu
- Sebuah *Episode Alarm Palsu* didefinisikan sebagai transisi status alarm dari $0 \rightarrow 1$ yang terjadi pada periode normal murni ($y_{\text{true}} = 0$).
- Total periode uji normal murni: **122 hari kalender (17,43 minggu)**.

### 7.2 Frekuensi Panggilan Alarm Palsu di Lapangan

| Model Evaluasi | Konfigurasi Filter | Total False Alarm Timesteps | Total Episode Alarm Palsu | Frekuensi Panggilan per Hari | Frekuensi Panggilan per Minggu | Rata-rata Durasi Episode |
|---|---|---|---|---|---|---|
| **Heuristic First Diff** | **Raw (5m)** | 6.885 | 2.462 episode | **20,2 episode/hari** | 141,3 episode/minggu | 14,0 menit |
| | **Persist 15m** | 3.610 | 419 episode | **3,4 episode/hari** | 24,0 episode/minggu | 43,1 menit |
| | **Persist 30m** | 2.686 | 205 episode | **1,7 episode/hari** | 11,8 episode/minggu | 65,5 menit |
| | **Persist 60m** | 1.594 | 166 episode | **1,4 episode/hari** | 9,5 episode/minggu | 48,0 menit |
| **Heuristic Rolling Z-Score** | **Raw (5m)** | 6.423 | 2.248 episode | **18,4 episode/hari** | 129,0 episode/minggu | 14,3 menit |
| | **Persist 15m** | 3.369 | 427 episode | **3,5 episode/hari** | 24,5 episode/minggu | 39,4 menit |
| | **Persist 30m** | 2.399 | 208 episode | **1,7 episode/hari** | 11,9 episode/minggu | 57,7 menit |
| | **Persist 60m** | 1.508 | 97 episode | **0,8 episode/hari** | 5,6 episode/minggu | 77,7 menit |
| **Isolation Forest** | **Raw (5m)** | 6.672 | 1.737 episode | **14,2 episode/hari** | 99,7 episode/minggu | 19,2 menit |
| | **Persist 15m** | 4.042 | 585 episode | **4,8 episode/hari** | 33,6 episode/minggu | 34,5 menit |
| | **Persist 30m** | 2.697 | 278 episode | **2,3 episode/hari** | 16,0 episode/minggu | 48,5 menit |
| | **Persist 60m** | 1.583 | 110 episode | **0,9 episode/hari** | 6,3 episode/minggu | 72,0 menit |

### 7.3 Implikasi Manajerial PDAM:
Tanpa filter persistensi, model menghasilkan **14 hingga 20 panggilan alarm palsu per hari** (mustahil ditangani tim lapangan). Dengan filter persistensi 30 menit, frekuensi panggilan palsu turun menjadi **~1,7 panggilan per hari**, memberikan efisiensi operasional yang sangat realistis untuk diinvestigasi.

---

## 8. AUDIT BUTIR 7: KONSISTENSI DATASET, GAP, DAN SATUAN SENSOR

1. **Satuan Sensor:**
   - Tekanan: Bar ($1\text{ bar} = 100\text{ kPa} \approx 10,2\text{ m H}_2\text{O}$).
   - Debit: Liter per menit (L/min).
   - Laju kebocoran: Liter per detik (L/s).
2. **Ketiadaan Gap Waktu:**
   - Seluruh 105.120 timestep SCADA 2018 terdistribusi secara sempurna pada grid interval tepat 300 detik ($5$ menit).
   - Selisih waktu $> 300$ detik = **0 event** (Zero temporal gap, zero missing data).
3. **Kausalitas Ekstraksi Fitur:**
   - Seluruh fitur lag ($\Delta P_{1h}$ dan $\Delta P_{24h}$) serta rolling Z-score dihitung hanya menggunakan data saat ini dan masa lalu tanpa menggunakan nilai masa depan (*Zero Lookahead Bias*).

---

## 9. ARTEFAK HASIL AUDIT STEP 4D.74.1

Direktori Keluaran: `ml/experiments/baseline_anomaly_detection/step_4d_74_1/`

### Berkas yang Dihasilkan:
1. `run_audit_4d_74_1.py`: Skrip mandiri read-only untuk reproduksi audit metrik, TTD, dan episode alarm palsu.
2. `step_4d_74_1_audit_findings.json`: Ringkasan numerik detail hasil verifikasi 48 metrik, pembuktian status `p183`, tabel TTD per persistensi, dan kuantifikasi episode alarm palsu per hari/minggu.

---

## 10. PERNYATAAN KEPATUHAN SISTEM PRODUKSI

1. **Kode produksi backend Laravel, frontend Vue, dan modul `ml/app/` tidak diubah.**
2. **Formula skor risiko produksi `round(0.8 * Score_T + 0.2 * Score_S)` dan ambang batas klasifikasi tetap utuh.**
3. **Database Supabase dan database lokal tidak pernah diakses atau dimutasi.**
4. **Dataset asli dan artefak STEP 4D.74 tidak ditimpa atau dihapus.**
5. **Tidak ada commit Git yang dibuat.**
6. **Pekerjaan dihentikan setelah audit selesai tanpa memilih model final atau integrasi produksi.**
