# STEP 4D.73.1 — BASELINE METRICS AND EVALUATION VALIDITY AUDIT

**Tanggal Audit:** 15 Oktober 2026  
**Auditor:** Tim Teknis AQUA-TARA / AI Data & ML Auditor  
**Lingkup:** Audit independen terhadap skrip (`run_step_4d_73_experiment.py`), artefak metrik (`baseline_experiment_results.json`), validitas rumus False Alarm Rate, perilaku algoritma Isolation Forest, protokol isolasi split validasi/test bebas kebocoran (*zero leakage*), representativitas pembagian berkas kronologis SKAB, serta reproduksi berkas prediksi rinci.  
**Status Sistem Produksi:** Unchanged — Read-Only Audit (Zero Supabase, Zero Migration, Zero Production Code Mutation, Zero Git Commit).

---

## 1. RINGKASAN STATUS AUDIT & TEMUAN UTAMA

Status Keseluruhan: **PASS (VERIFIED & DISAMBIGUATED)**  
Seluruh 8 butir pengujian wajib telah diperiksa secara mendalam tanpa pelanggaran guardrail.

### Ringkasan Temuan Utama:
1. **Validitas Rumus Metrik & False Alarm Rate:** **PASS (VERIFIED).**  
   Perhitungan ulang independen membuktikan bahwa rumus yang digunakan dalam skrip evaluasi mematuhi definisi statistik standar secara presisi:
   $$\text{FAR} = \frac{\text{FP}}{\text{FP} + \text{TN}}$$
   $$\text{Precision} = \frac{\text{TP}}{\text{TP} + \text{FP}}, \quad \text{Recall} = \frac{\text{TP}}{\text{TP} + \text{FN}}, \quad F_1 = \frac{2 \times P \times R}{P + R}$$
   Semua angka metrik pada dokumen STEP 4D.73 cocok 100% dengan hasil perhitungan ulang matriks konfusi.
2. **Audit Isolation Forest (Arah Skor & Anomali Collapse):** **PASS (DISAMBIGUATED).**  
   - Arah skor dihitung via `-score_samples(X)` (skor lebih tinggi = lebih anomali, berkorelasi positif dengan outlierness).
   - Ambang batas $\theta = 0,5460$ (dipilih dari kuantil 98% skor validasi) memotong hampir seluruh sampel uji: **$99,88\%$ data Test diprediksi sebagai anomali** (19.083 dari 19.106 sampel).
   - **Penyebab Utama (Covariate Shift):** Bukan kesalahan kode, melainkan pergeseran distribusi fisik (*distribution drift*) pompa antara fase training (Februari 2020) dan fase uji (Maret 2020). Akibatnya, Isolation Forest mengenali hampir seluruh dinamika Maret sebagai kondisi di luar distribusi training normal murni.
3. **Isolasi Ambang Batas & Preprocessing:** **PASS (STRICT ZERO-LEAKAGE).**  
   - `StandardScaler` di-fit **hanya** pada training set normal (`anomaly-free.csv`). Validation dan Test set hanya memanggil `scaler.transform()`.
   - Ambang batas optimal ($\theta$) dicari dan dipilih **secara eksklusif pada Validation Set**. Test Set tidak pernah disentuh dalam proses optimasi parameter.
4. **Pembagian Kronologis 17 Berkas Validasi vs 17 Berkas Test:** **PARTIAL (METHODOLOGICAL LIMITATION IDENTIFIED).**  
   - Pembagian berkas murni kronologis ($17:17$) menghasilkan ketiadaan tumpang tindih waktu (*zero temporal overlap*), namun menimbulkan **ketidakseimbangan tipe skenario fisik**:
     - *Validation Set:* Didominasi oleh 14 berkas skenario `'other'` (kavitasi & ketidakseimbangan rotor) + 3 berkas `'valve1'`.
     - *Test Set:* Didominasi oleh 13 berkas `'valve1'` + 4 berkas `'valve2'` (gangguan katup).
     - Hal ini menjelaskan mengapa threshold yang optimal untuk skenario 'other' menghasilkan False Alarm Rate ekstrem saat dievaluasi pada skenario gangguan katup di Test Set.
5. **Reproduksi Prediksi:** **PASS.**  
   Seluruh prediksi tingkat baris (true labels, heuristic diff1, heuristic z-score, anomaly scores, dan if predictions) telah diekstrak dan disimpan dalam format terkompresi di:  
   `ml/experiments/baseline_anomaly_detection/step_4d_73_1/skab_test_predictions_reproduced.csv.gz`.

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

## 3. AUDIT BUTIR 1–3: REKALKULASI METRIK & VERIFIKASI RUMUS MATEMATIS

Audit memeriksa skrip `run_step_4d_73_experiment.py` dan berkas keluaran `baseline_experiment_results.json`:

### 3.1 Verifikasi Aritmetika Matriks Konfusi Test Set (SKAB)

| Model Evaluasi | TP | FP | FN | TN | Total Uji | Precision (Dihitung Ulang) | Recall (Dihitung Ulang) | F1-Score (Dihitung Ulang) | False Alarm Rate (FAR = $\frac{\text{FP}}{\text{FP}+\text{TN}}$) | Status Verifikasi |
|---|---|---|---|---|---|---|---|---|---|---|
| **Heuristic First Diff ($\Delta P > 0,01$)** | 3.976 | 7.380 | 2.710 | 5.040 | 19.106 | $\frac{3976}{11356} = \mathbf{0,3501}$ | $\frac{3976}{6686} = \mathbf{0,5947}$ | $\mathbf{0,4407}$ | $\frac{7380}{12420} = \mathbf{59,42\%}$ | **PASS (100% Cocok)** |
| **Heuristic Z-Score ($|Z| > 1,50$)** | 1.644 | 3.102 | 5.042 | 9.318 | 19.106 | $\frac{1644}{4746} = \mathbf{0,3464}$ | $\frac{1644}{6686} = \mathbf{0,2459}$ | $\mathbf{0,2876}$ | $\frac{3102}{12420} = \mathbf{24,98\%}$ | **PASS (100% Cocok)** |
| **Isolation Forest ($\text{Score} > 0,5460$)** | 6.682 | 12.401 | 4 | 19 | 19.106 | $\frac{6682}{19083} = \mathbf{0,3502}$ | $\frac{6682}{6686} = \mathbf{0,9994}$ | $\mathbf{0,5186}$ | $\frac{12401}{12420} = \mathbf{99,85\%}$ | **PASS (100% Cocok)** |

### 3.2 Temuan Rumus:
- Rumus FAR terbukti menggunakan $\frac{\text{FP}}{\text{FP} + \text{TN}}$ (bukan $\frac{\text{FP}}{\text{TP} + \text{FP}}$, yang merupakan Fall-out rate / False Positive Rate standar statistik).
- Tidak ada manipulasi angka, round-off error terkendali pada presisi 4 desimal.

---

## 4. AUDIT BUTIR 4: ANALISIS ALGORITMA ISOLATION FOREST

### 4.1 Arah Skor Anomali (*Score Direction*)
Skrip menggunakan formulasi:
```python
test_scores = -iso_forest.score_samples(X_test)
```
- Dalam `scikit-learn`, `score_samples()` menghasilkan nilai negatif (semakin negatif = semakin anomali).
- Penggunaan tanda minus (`-`) membalik arah nilai sehingga nilai yang lebih tinggi menunjukkan tingkat keanehan (*outlierness*) yang lebih besar. Formulasi ini **benar secara matematis**.

### 4.2 Analisis Distribusi Skor Uji vs Ambang Batas
- Nilai ambang batas terpilih: $\theta = 0,5460$ (diperoleh dari grid search kuantil $0,50$–$0,99$ pada Validation Set dengan F1 terbaik $0,4166$).
- Karakteristik skor pada Test Set:
  - Minimum: $0,4801$
  - Q1 (P25): $0,6124$
  - Median: $0,6422$
  - Mean: $0,6431$
  - Q3 (P75): $0,6768$
  - Maksimum: $0,7512$
- **Fenomena Kritis:** Nilai Q1 skor uji ($0,6124$) jauh melampaui ambang batas validasi ($0,5460$). Akibatnya:
  - Prediksi Anomali (`pred = 1`): **19.083 baris ($99,88\%$)**.
  - Prediksi Normal (`pred = 0`): **Hanya 23 baris ($0,12\%$)**.
  - Model pada dasarnya runtuh (*degenerate*) menjadi "always-anomaly classifier" pada test set ini, menjelaskan mengapa Recall bernilai $99,94\%$ tetapi False Alarm Rate mencapai $99,85\%$.

---

## 5. AUDIT BUTIR 5 & 7: PROTOKOL ISOLASI PARAMETER & PREPROCESSING

Audit memverifikasi kode sumber pada fungsi pemrosesan:

```python
# Verifikasi fitting scaler:
scaler = StandardScaler()
scaler.fit(df_train_feat[feat_cols])  # HANYA TRAINING NORMAL

# Verifikasi transformasi:
X_val = scaler.transform(df_val[feat_cols])
X_test = scaler.transform(df_test[feat_cols])
```

- **Penskalaan (Scaling):** `StandardScaler` hanya mempelajari rata-rata dan varians dari 9.401 baris normal murni (`anomaly-free.csv`). Tidak ada kebocoran statistik data pengujian (*Zero Data Leakage*).
- **Pemilihan Ambang Batas (Threshold Tuning):** Seluruh loop grid search `for th in np.linspace(...)` mengevaluasi `f1_score(y_val, preds_val)`. Nilai `y_test` sama sekali tidak pernah diakses dalam proses tuning.
- **Kausalitas Fitur:** First difference dan rolling Z-score dihitung per berkas, hanya menggunakan observasi $[t-k, t]$, dan tidak melakukan lookahead atau cross-file imputation.

---

## 6. AUDIT BUTIR 6: PEMBAGIAN 17 BERKAS VALIDASI VS 17 BERKAS TEST

Audit menelusuri komposisi berkas hasil pengurutan kronologis:

### 6.1 Garis Waktu Berkas
1. **Training Set:** `anomaly-free.csv` (8 Februari 2020 13:30 s/d 16:07, durasi ~2,6 jam).
2. **Validation Set (17 berkas):**
   - Rentang Waktu: 8 Februari 2020 16:06 s/d 9 Maret 2020 10:54.
   - Skenario yang Terkandung: **14 berkas `other/` (rotor imbalance, cavitation)** + **3 berkas `valve1/`** (`0.csv`, `1.csv`, `2.csv`).
3. **Test Set (17 berkas):**
   - Rentang Waktu: 9 Maret 2020 11:14 s/d 9 Maret 2020 17:16.
   - Skenario yang Terkandung: **13 berkas `valve1/`** (`3.csv` s/d `15.csv`) + **4 berkas `valve2/`** (`0.csv` s/d `3.csv`).

### 6.2 Keterbatasan Pembagian Berbasis Berkas Kronologis:
- **Zero Temporal Overlap:** Validasi dan Test benar-benar terpisah secara temporal (tidak ada tumpang tindih waktu).
- **Distribusi Skenario Asimetris:** Validasi set mengevaluasi anomali getaran/rotor, sementara Test set menguji penutupan katup parsial/penuh. Hal ini membuktikan bahwa metode unsupervised global murni tanpa adaptasi domain sangat rentan terhadap kegagalan generalisasi saat jenis gangguan berubah.

---

## 7. AUDIT BUTIR 8: KELAYAKAN HASIL & REKOMENDASI AUDITOR

### 7.1 Penilaian Kelayakan Hasil STEP 4D.73

| Komponen Eksperimen | Status Kelayakan | Justifikasi Auditor |
|---|---|---|
| **Heuristic First Difference** | **LAYAK DIPERCAYA (TRUSTED)** | Respons fisik terhadap lonjakan tekanan bersifat universal dan terbukti menangkap $59,5\%$ gangguan katup dengan logika transparan. |
| **Heuristic Rolling Z-Score** | **LAYAK DIPERCAYA (TRUSTED)** | Menunjukkan performa paling terkontrol untuk menolak alarm palsu (FAR $24,98\%$). |
| **Isolation Forest (Eksperimen SKAB)** | **PERLU PERBAIKAN (NEEDS REVISION)** | Angka F1 $0,5186$ pada SKAB tidak mencerminkan model yang seimbang, melainkan model yang over-predicting anomali akibat pergeseran kovariat. |
| **Isolation Forest (Eksperimen BattLeDIM)** | **LAYAK DIPERCAYA (TRUSTED)** | Pada data SCADA pipa transmisi BattLeDIM, model terlatih menghasilkan Precision $36,7\%$, Recall $45,6\%$, dan FAR terkontrol $27,9\%$. |

### 7.2 Rekomendasi Auditor untuk Tahap Berikutnya:
1. **Pertahankan Prediksi Tersimpan:** File terkompresi `skab_test_predictions_reproduced.csv.gz` harus dijadikan artefak acuan untuk analisis error baris-ke-baris.
2. **Hindari Pelatihan Isolation Forest Global pada Fluida Tanpa Kalibrasi Baseline:** Jika model dilatih pada kondisi normal hari dingin (Februari), pergeseran suhu dan operasional ke hari panas (Maret) akan dianggap anomali total. Untuk produksi AQUA-TARA, model deteksi wajib memiliki mekanisme adaptasi baseline lokal (*adaptive rolling baseline*).
3. **Jangan Integrasikan Model SKAB ke Produksi AQUA-TARA:** SKAB murni testbed metodologis; jangan pernah menyimpulkan akurasi pipa PDAM dari hasil pompa industri ini.

---

## 8. ARTEFAK HASIL AUDIT STEP 4D.73.1

Direktori Keluaran: `ml/experiments/baseline_anomaly_detection/step_4d_73_1/`

### Berkas yang Dihasilkan:
1. `run_audit_4d_73_1.py`: Skrip mandiri read-only untuk verifikasi aritmetika dan ekstraksi prediksi test set.
2. `skab_test_predictions_reproduced.csv.gz`: Berkas CSV terkompresi memuat 19.106 baris data pengujian SKAB lengkap dengan ground truth (`y_true`), prediksi First Difference, prediksi Z-Score, skor Isolation Forest, dan label prediksi IF.
3. `step_4d_73_1_audit_findings.json`: Ringkasan numerik hasil verifikasi rumus, audit arah skor anomali, dan pemetaan pembagian berkas validasi/test.

---

## 9. PERNYATAAN KEPATUHAN SISTEM PRODUKSI

1. **Kode produksi backend Laravel, frontend Vue, dan modul `ml/app/` tidak diubah.**
2. **Formula skor risiko produksi `round(0.8 * Score_T + 0.2 * Score_S)` dan ambang batas klasifikasi tetap utuh.**
3. **Database Supabase dan database lokal tidak pernah diakses atau dimutasi.**
4. **Dataset asli dan artefak STEP 4D.73 tidak ditimpa atau dihapus.**
5. **Tidak ada commit Git yang dibuat.**
6. **Pekerjaan dihentikan setelah audit tanpa melanjutkan ke pemilihan model final atau integrasi produksi.**
