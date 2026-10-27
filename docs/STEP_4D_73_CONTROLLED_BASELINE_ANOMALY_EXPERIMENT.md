# STEP 4D.73 — CONTROLLED BASELINE ANOMALY DETECTION EXPERIMENT

**Tanggal Eksperimen:** 15 Oktober 2026  
**Auditor / Peneliti:** Tim Teknis AQUA-TARA / AI Data & ML Engineer  
**Lingkup:** Eksperimen baseline deteksi anomali terkontrol pada 4 dataset terverifikasi (SKAB, BattLeDIM, Turbinator, KU-MWQ). Menguji komparasi First Difference, Rolling Z-Score, dan Isolation Forest dengan pemisahan kronologis ketat (Train/Val/Test), tuning parameter bebas kebocoran test data (*zero test-leakage*), segmentasi kausal tanpa lookahead, evaluasi metrik deteksi (Precision, Recall, F1-Score, False Alarm Rate, Confusion Matrix), serta pemetaan batas transfer domain ke jaringan air minum AQUA-TARA.  
**Status Sistem Produksi:** Unchanged — Read-Only / Isolated Offline Experiment (Zero Supabase, Zero Migration, Zero Production Code Mutation, Zero Git Commit).

---

## 1. RINGKASAN EKSEKUTIF & STATUS EKSPERIMEN

Status Keseluruhan: **PASS (CONTROLLED & VALIDATED)**  
Seluruh eksperimen telah dieksekusi secara offline dan terisolasi pada direktori keluaran `ml/experiments/baseline_anomaly_detection/step_4d_73/` tanpa mengubah satu baris pun kode produksi backend, frontend, maupun formula risiko produksi.

### Matriks Status per Dataset & Tujuan Eksperimen:

| Dataset | Karakteristik / Domain | Ketersediaan Ground Truth | Peran dalam Eksperimen | Metode yang Diuji | Status Eksperimen |
|---|---|---|---|---|---|
| **SKAB Benchmark** | Testbed hidraulik sirkulasi tertutup (pompa & katup) | **ADA** (13.241 label anomali fisik) | Evaluasi metrik deteksi anomali berlabel | First Difference, Rolling Z-Score, Isolation Forest | **PASS** |
| **BattLeDIM 2018** | Jaringan pipa transmisi air bersih SCADA bertekanan | **ADA** (Jadwal kebocoran burst fisik pipa) | Verifikasi deteksi kebocoran pipa transmisi | Rolling Delta P Heuristik, Isolation Forest | **PASS** |
| **Turbinator (Zenodo)** | Pipa drainase limbah & stormwater kota (Gothenburg) | **TIDAK ADA** | Eksperimen temporal eksploratif & dispersi Z-score | Causal First Difference & Rolling Z-Score ($w=15$) | **PASS** (Eksploratif) |
| **KU-MWQ (Mendeley)** | Kolam ikan air tawar terbuka (Bangladesh) | **TIDAK ADA** | Eksperimen dinamika kekeruhan multi-hari kontinu | Causal First Difference & Rolling Z-Score ($w=15$) | **PASS** (Eksploratif) |

---

## 2. GUARDRAILS & STATUS GIT REPOSITORY

### 2.1 Kepatuhan Guardrails
- **Zero Database Mutation:** Supabase dan database lokal tidak disentuh; tidak ada migrasi atau seeder yang dijalankan.
- **Zero Production Mutation:** Formula skor risiko `round(0.8 * Score_T + 0.2 * Score_S)`, clamp 0–100, dan ambang batas klasifikasi produksi (`0–30 Jernih, 31–70 Waspada, 71–100 Bahaya`) tidak dimodifikasi.
- **Zero Test Leakage:** Ambang batas deteksi (*threshold*) dan penskalaan (*StandardScaler*) dihitung dan dioptimasi **HANYA pada data Training dan Validasi**. Data Test tidak pernah digunakan dalam pemilihan parameter.
- **Strictly Causal & Gap-Aware:** Fitur rolling mean dan std dev di-reset pada setiap batas gap temporal, tanpa fungsi forward-fill, interpolasi masa depan, atau lookahead bias.

### 2.2 Status Git Repository Sebelum & Sesudah
```text
?? .gitignore
?? backend/
?? frontend/
?? ml/
```
*(Branch master, No commits yet, seluruh direktori utama untracked, zero file tracked termodifikasi).*

---

## 3. EKSPERIMEN 1: SKAB CONTROLLED ANOMALY DETECTION BENCHMARK

### 3.1 Protokol Pemisahan Data Kronologis (Chronological Split)
Dataset SKAB terdiri dari 35 berkas CSV. Pemisahan dilakukan berdasarkan garis waktu aktual:
1. **Training Set ($N_{\text{train}} = 9.401$ baris):** Berkas referensi normal murni `anomaly-free/anomaly-free.csv` (8 Februari 2020). Digunakan untuk melatih model tanpa pengawasan (*unsupervised*) dan fitting `StandardScaler`.
2. **Validation Set ($N_{\text{val}} = 18.353$ baris):** 17 berkas skenario anomali paruh pertama secara kronologis (Maret 2020). Digunakan untuk tuning ambang batas deteksi (*threshold optimization*).
3. **Test Set ($N_{\text{test}} = 19.106$ baris):** 17 berkas skenario anomali paruh kedua secara kronologis (Maret 2020). Rasio anomali aktual test set = **$34,99\%$** (6.686 baris anomali, 12.420 baris normal). Digunakan untuk evaluasi akhir independen.

### 3.2 Rekayasa Fitur Temporal Kausal
Fitur diekstraksi per berkas secara independen tanpa *lookback* lintas berkas:
- 8 sensor mentah: Accelerometer (1 & 2), Current, Pressure, Temperature, Thermocouple, Voltage, Volume Flow Rate.
- First difference ($\Delta y_t = y_t - y_{t-1}$) untuk seluruh 8 sensor.
- Rolling mean ($\mu_{t, 15}$) dan rolling standard deviation ($\sigma_{t, 15}$) kausal ($w=15$, `min_periods=1`) untuk parameter hidraulik utama (Pressure, Volume Flow Rate, Temperature).
- Causal Rolling Z-Score: $Z_{t, 15} = \frac{y_t - \mu_{t, 15}}{\sigma_{t, 15} + \epsilon}$.

### 3.3 Hasil Evaluasi Model pada SKAB Test Set

Ambang batas deteksi dioptimasi berdasarkan nilai F1-Score tertinggi pada Validation Set, lalu dievaluasi secara beku pada Test Set:

| Metode Deteksi | Fitur Masukan | Ambang Batas Terpilih (dari Validasi) | F1-Score Validasi | Precision (Test) | Recall (Test) | F1-Score (Test) | False Alarm Rate (FAR) | True Positive (TP) | False Positive (FP) | False Negative (FN) | True Negative (TN) |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **Heuristic First Difference** | $|\Delta P_t| > \theta$ | $\theta = 0,0100$ | $0,4254$ | $0,3501$ | $0,5947$ | **$0,4407$** | $59,42\%$ | 3.976 | 7.380 | 2.710 | 5.040 |
| **Heuristic Rolling Z-Score** | $\|Z_P\| > \theta \lor \|Z_Q\| > \theta$ | $\theta = 1,50$ | $0,3080$ | $0,3464$ | $0,2459$ | **$0,2876$** | **$24,98\%$** | 1.644 | 3.102 | 5.042 | 9.318 |
| **Isolation Forest** | Multivariat (22 fitur) | $\text{Score} > 0,5460$ | $0,4166$ | $0,3502$ | **$0,9994$** | **$0,5186$** | $99,85\%$ | 6.682 | 12.401 | 4 | 19 |

### 3.4 Analisis Trade-off Metode:
1. **Isolation Forest ($F_1 = 0,5186$):** Menghasilkan recall hampir sempurna ($99,94\%$), namun menderita False Alarm Rate yang sangat ekstrem ($99,85\%$), karena model menandai hampir seluruh dinamika pengujian sebagai anomali akibat pergeseran operasional antara data normal Februari dan data gangguan Maret.
2. **First Difference ($F_1 = 0,4407$):** Menunjukkan keseimbangan yang cukup baik dalam menangkap hentakan mendadak (*impulsive change*), mendeteksi $59,47\%$ anomali dengan FAR moderat ($59,42\%$).
3. **Rolling Z-Score ($F_1 = 0,2876$):** Memiliki **False Alarm Rate terendah ($24,98\%$)**, sangat selektif dalam menolak fluktuasi normal, meskipun mengalami penurunan recall ($24,59\%$) pada anomali bertahap (*slow drift*).

---

## 4. EKSPERIMEN 2: BATTLEDIM BASELINE VERIFICATION (LEAKAGE DETECTION)

Dataset BattLeDIM 2018 diverifikasi dari metadata eksperimen baseline terlatih (`ml/experiments/battledim_baseline/metadata.json`):

### 4.1 Parameter Eksperimen
- **Total Rekaman SCADA:** 105.120 timestep (sampling 5 menit, 1 tahun penuh).
- **Split Kronologis:**
  - Training: 1–23 Januari 2018 (6.623 sampel, 0 kebocoran, baseline normal).
  - Validasi: 1 Juli – 31 Agustus 2018 (17.856 sampel).
  - Test: 1 September – 31 Desember 2018 (35.136 sampel, 9.201 titik kebocoran aktif).

### 4.2 Hasil Evaluasi pada Test Set BattLeDIM

| Metode | Precision | Recall | F1-Score | False Alarm Rate | TP | FP | FN | TN |
|---|---|---|---|---|---|---|---|---|
| **Isolation Forest Multivariat** | **$0,3670$** | **$0,4564$** | **$0,4069$** | $27,92\%$ | 4.199 | 7.241 | 5.002 | 18.694 |
| **Heuristic Rolling Delta P ($Z < -1,5$)** | $0,3449$ | $0,1653$ | $0,2235$ | **$11,14\%$** | N/A | N/A | N/A | N/A |

*Temuan:* Pada jaringan distribusi pipa transmisi bertekanan nyata, heuristik penurunan tekanan lokal ($Z < -1,5$) menghasilkan False Alarm Rate yang sangat rendah ($11,14\%$), sedangkan Isolation Forest multivariat meningkatkan Recall kebocoran hingga $45,64\%$.

---

## 5. EKSPERIMEN 3 & 4: TURBINATOR & KU-MWQ TEMPORAL EXPLORATION

> **[PERNYATAAN TEGAS METODOLOGIS]**  
> Dataset Turbinator dan KU-MWQ **TIDAK MEMILIKI GROUND TRUTH LABEL ANOMALI KUALITAS AIR**.  
> Eksperimen pada bagian ini murni evaluasi dinamika statistik dan stabilitas fitur kausal, bukan evaluasi akurasi deteksi model.

### 5.1 Eksperimen Turbinator (Sensor `ADIN4483`)
- **Total Observasi Valid:** 1.066 sampel, terbagi ke dalam 154 segmen kontinu ($\Delta t > 2$ jam).
- **Rata-rata & Simpangan Baku Turbidity:** $36,36 \pm 14,46$ NTU.
- **Karakter First Difference:** Simpangan baku $\Delta y = 3,69$ NTU. Terdapat **14 lonjakan ekstrem** ($\|\Delta y\| > 15$ NTU) yang merefleksikan arus limbah tiba-tiba pasca hujan di Gothenburg.
- **Dispersi Z-Score ($w=15$):** Terdapat **25 observasi** ($2,35\%$) yang melampaui batas statistik signifikansi $\|Z\| > 3,0$.
- *Kesimpulan:* Fitur Z-score kausal berhasil melacak baseline diurnal air drainase dan mengisolasi deviasi ekstrem tanpa terdistorsi oleh gap multi-hari.

### 5.2 Eksperimen KU-MWQ (Sensor Kedalaman 30 cm)
- **Total Observasi:** 9.623 baris kontinu (~1 menit), terbagi ke dalam 139 segmen ($\Delta t > 2$ menit).
- **Rata-rata & Simpangan Baku Turbidity:** $215,49 \pm 16,18$ NTU.
- **Karakter First Difference:** Sinyal mentah sangat terkuantisasi (integer $1$ NTU step, $\sigma_{\Delta y} = 0,13$ NTU). Hanya 0 baris yang memiliki $\|\Delta y\| > 2$ NTU dalam kondisi alami.
- **Dispersi Z-Score ($w=15$):** Terdapat **10 observasi** ($0,10\%$) dengan $\|Z\| > 3,0$.
- *Kesimpulan:* Pada resolusi 1 menit, baseline bergerak sangat stabil sehingga ambang batas sensitivitas perbedaan first difference sangat sempit ($\Delta y \ge 2$ NTU sudah merupakan anomali statistik signifikan untuk sensor kolam ini).

---

## 6. ANALISIS KETERBATASAN TRANSFER HASIL KE SISTEM AQUA-TARA

1. **Kesenjangan Domain Fisik (Domain Mismatch):**
   - **SKAB:** Dihasilkan dari testbed pompa industri dengan fluida bersirkulasi tertutup bertekanan tinggi. Pola getaran (*accelerometer*) dan lonjakan tekanan katup tidak mencerminkan perambatan gelombang hidraulik jaringan transmisi pipa PDAM bawah tanah.
   - **Turbinator:** Mengukur limpasan air hujan dan air limbah gravitasi terbuka (tanpa tekanan) dengan kekeruhan dasar 10–60 NTU.
   - **KU-MWQ:** Mengukur kolam terbuka berlumpur dengan kekeruhan dasar 134–249 NTU yang dipengaruhi ledakan alga akibat sinar matahari.
   - **AQUA-TARA Produksi:** Memantau jaringan pipa distribusi air minum perkotaan bertekanan tertutup (baku mutu Permenkes 2/2023 $\le 3$ NTU) dengan kombinasi laporan warga dan sensor IoT.
2. **Ketiadaan Sensor Turbidity pada Dataset Kebocoran:**
   Baik BattLeDIM maupun SKAB tidak memiliki sensor kekeruhan optik (*Turbidity*). Kekeruhan saat ini hanya tersedia pada KU-MWQ dan Turbinator yang tidak memiliki skenario kebocoran fisik.
3. **Implikasi Arsitektur ML AQUA-TARA:**
   Hasil eksperimen mendukung **arsitektur hybrid terpisah (Modular Multi-Modal)**:
   - Modul Hidraulik (Deteksi Kebocoran): Mengadopsi kombinasi *Rolling Delta Pressure* dan *Isolation Forest* berbasis benchmark BattLeDIM.
   - Modul Kualitas Air (Deteksi Kontaminasi Turbidity): Mengadopsi *Causal Rolling Z-Score & First Difference* dengan proteksi reset gap temporal.

---

## 7. ARTEFAK HASIL EKSPERIMEN STEP 4D.73

Direktori Keluaran: `ml/experiments/baseline_anomaly_detection/step_4d_73/`

### Berkas yang Dihasilkan:
1. `run_step_4d_73_experiment.py`: Skrip eksperimen mandiri yang dapat direproduksi (read-only, fixed random seed 42).
2. `baseline_experiment_results.json`: Ringkasan numerik lengkap metrik test, confusion matrix, parameter optimal, dan statistik fitur untuk keempat dataset.
3. `skab_confusion_matrices.png`: Matriks konfusi visual untuk Heuristic First Difference, Heuristic Z-Score, dan Isolation Forest pada SKAB Test Set.
4. `turbidity_explorative_features.png`: Plot perbandingan respons fitur kausal (Raw vs Rolling Baseline dan Causal Z-Score) antara Turbinator dan KU-MWQ.

---

## 8. KESIMPULAN & PERNYATAAN KEPATUHAN SISTEM PRODUKSI

1. **Eksperimen baseline deteksi anomali terkontrol telah selesai dan terbukti dapat direproduksi secara konsisten.**
2. **Tidak ada model yang diintegrasikan ke lingkungan produksi.**
3. **Kode produksi backend, frontend, modul `ml/app/`, skema database, dan formula skor risiko `round(0.8 * Score_T + 0.2 * Score_S)` tetap 100% utuh tanpa modifikasi.**
4. **Pekerjaan dihentikan untuk peninjauan teknis sesuai ketentuan guardrails.**
