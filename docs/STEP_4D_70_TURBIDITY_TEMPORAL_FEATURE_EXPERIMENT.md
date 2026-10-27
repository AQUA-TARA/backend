# STEP 4D.70 — TURBIDITY DATASET SUITABILITY & TEMPORAL FEATURE EXPERIMENT

**Tanggal Eksperimen:** 15 Oktober 2026  
**Auditor / Peneliti:** Tim Teknis AQUA-TARA / AI Assistant  
**Lingkup:** Penilaian kelayakan dataset KU-MWQ untuk eksperimen feature engineering temporal kekeruhan (turbidity), profil gap sampling, formulasi fitur kausal (strictly past-and-present), perbandingan kedalaman, dan uji respons simulasi shock.  
**Status Sistem Produksi:** Unchanged — Read-Only / Isolated Offline Experiment (Zero Supabase, Zero Migration, Zero Production Code Mutation).

---

## RINGKASAN EKSEKUTIF

Eksperimen offline terisolasi pada dataset KU-MWQ (`Sensor_Data_30cm.xlsx` dan `Sensor_Data_60cm.xlsx`) telah diselesaikan untuk mengevaluasi kelayakan sinyal turbidity dalam rekayasa fitur temporal (feature engineering).

### Temuan Kunci:
1. **Dukungan Prototipe Feature Engineering Temporal:** **MENDUKUNG (VALID).**  
   Formulasi fitur temporal kausal (first difference $\Delta t$, rolling mean, rolling standard deviation, deviation from rolling baseline, dan rolling Z-score) berhasil diimplementasikan tanpa lookahead bias (hanya memanfaatkan data saat ini dan masa lalu).
2. **Dukungan Evaluasi Model / Benchmark Akurasi Dunia Nyata:** **TIDAK MENDUKUNG (INSUFFICIENT EVIDENCE).**  
   Dataset ini **tidak memiliki ground truth label anomali** (kontaminasi pipa atau intrusi fisik) dan 100% datanya berasal dari kolam/tambak terbuka di Bangladesh dengan kekeruhan baseline sangat tinggi (134–249 NTU), jauh melampaui standar air minum perkotaan Permenkes 2/2023 (threshold bahaya AQUA-TARA > 25 NTU).
3. **Karakteristik Fisik Sensor & Gap Waktu:**  
   - Turbidity disimpan dalam bentuk diskrit (integer $1$ NTU step).
   - Terdapat **138 event gap waktu** (>120 detik) dengan gap terpanjang mencapai 1,01 jam (3.650 detik).
   - Ditemukan **2 anomali urutan waktu** (non-monotonic timestamps pada baris 256 dan 5842) di mana timestamp melompat mundur beberapa detik/menit.
   - Jendela bergerak (rolling window) yang melintasi gap tanpa reset menghasilkan distorsi historis (5,95% data terdistorsi pada $w=5$, 15,04% pada $w=15$, dan 37,87% pada $w=60$).
4. **Relasi Sensor Kedalaman 30 cm vs 60 cm:**  
   Keduanya diukur di badan air yang sama (Khan Jahan Ali Pond) pada titik koordinat yang sama. Nilai turbidity pada 60 cm secara konsisten **100% lebih rendah** daripada 30 cm (selisih rata-rata -73,63 NTU, korelasi Pearson $r = 0,6188$). Keduanya **bukan dua jalur pipa independen**, melainkan profil stratifikasi vertikal kolam.

---

## 1. SUMBER DATA & METADATA FILE

Eksperimen menggunakan file lokal terisolasi yang diakuisisi pada STEP 4D.69:

| File | Path Relatif | Ukuran File | SHA-256 Checksum |
|---|---|---|---|
| Sensor 30 cm | `ml/data/turbidity/ku_mwq/Sensor_Data_30cm.xlsx` | 471.696 bytes | `ca18eecbfef5fb14652c79f4ba262ff0b7ea13fc3aa882c9f5ae726c071d2b85` |
| Sensor 60 cm | `ml/data/turbidity/ku_mwq/Sensor_Data_60cm.xlsx` | 473.084 bytes | `9996e38289456565aa60b2ebcf9c8949b29c9efd95fa2d9d9ae6475752b047e1` |

- **Jumlah Baris Data:** 9.623 baris per file.
- **Kolom 30 cm:** `['Date and Time', 'Temperature (°C) ', 'pH', 'Turbidity (NTU)']` (4 kolom).
- **Kolom 60 cm:** `['Date and Time', 'Temperature (°C) ', 'Turbidity (NTU)']` (3 kolom, tanpa sensor pH).

---

## 2. VERIFIKASI TIMESTAMP, INTERVAL SAMPLING, & GAP (TUGAS A)

### 2.1 Parsing & Rentang Waktu
- **Format Timestamp:** String gabungan `'YYYY-MM-DD HH:MM:SS'` pada kolom `Date and Time`.
- **Rentang Waktu Aktual:** `2020-01-15 16:00:35` hingga `2020-01-22 16:25:32` (Durasi: ~7 hari, Januari 2020).
- **Kesesuaian Timestamp Lintas Kedalaman:** Timestamp pada `Sensor_Data_30cm` dan `Sensor_Data_60cm` **100% identik** di setiap baris.

### 2.2 Distribusi Interval Sampling ($\Delta t$)
Interval sampling teoritis adalah 60 detik (1 menit). Namun, analisis interval aktual menunjukkan variasi jitter:
- **Mean $\Delta t$:** 63,01 detik.
- **Median $\Delta t$:** 58,00 detik.
- **Modus:** 55 detik (3.159 baris) dan 67 detik (2.261 baris).
- **Min $\Delta t$:** 1 detik.
- **Max $\Delta t$:** 3.650 detik (~1,01 jam).

### 2.3 Analisis Gap Waktu
Definisi gap: $\Delta t > 120$ detik (kehilangan lebih dari 1 siklus sampling).
- **Total Gap Event:** **138 event**.
- **Top 5 Gap Terbesar:**
  1. `2020-01-17 09:08:26`: 3.650 detik (1,01 jam)
  2. `2020-01-16 08:21:55`: 872 detik (14,5 menit)
  3. `2020-01-16 07:59:37`: 599 detik (10,0 menit)
  4. `2020-01-21 09:25:41`: 515 detik (8,6 menit)
  5. `2020-01-16 05:46:23`: 441 detik (7,3 menit)

### 2.4 Anomali Urutan Waktu (Non-Monotonicity)
Ditemukan bahwa urutan baris file mentah tidak sepenuhnya monoton naik:
- **Baris 256:** `2020-01-15 20:22:56` muncul setelah baris 255 (`20:30:51`), melompat mundur ~8 menit sebelum kembali ke `20:33:31` pada baris 257.
- **Baris 5842:** `2020-01-19 17:19:35` muncul setelah baris 5841 (`17:19:38`), mundur 3 detik.
- **Implikasi Rekayasa Fitur:** Pipeline data wajib melakukan *time-sorting* eksplisit sebelum menghitung fitur lag/rolling untuk menghindari time-travel artifisial.

---

## 3. STATISTIK DESKRIPTIF TURBIDITY (TUGAS B)

Data kekeruhan disimpan sebagai integer (DFRobot analog sensor yang dipetakan via mikrokontroler):

| Metrik Statistik | Sensor 30 cm | Sensor 60 cm | Satuan |
|---|---|---|---|
| **Nilai Minimum** | 194 | 134 | NTU |
| **Nilai Maksimum** | 249 | 154 | NTU |
| **Rentang (Max - Min)** | 55 | 20 | NTU |
| **Rata-rata (Mean)** | 215,4852 | 141,8540 | NTU |
| **Median** | 212,0000 | 140,0000 | NTU |
| **Standar Deviasi ($\sigma$)**| 16,1794 | 6,4797 | NTU |
| **Kuantil 25% (Q1)** | 202,0000 | 137,0000 | NTU |
| **Kuantil 75% (Q3)** | 224,0000 | 147,0000 | NTU |
| **Interquartile Range (IQR)**| 22,0000 | 10,0000 | NTU |
| **Jumlah Nilai Unik** | 56 | 21 | nilai |
| **Null / Missing / NaN** | 0 (0,0%) | 0 (0,0%) | baris |
| **Infinity** | 0 (0,0%) | 0 (0,0%) | baris |

> **[PENTING] Diskrititas Data:** Sinyal turbidity melangkah dalam bilangan bulat bulat ($1$ NTU). Sensor pada kedalaman 60 cm hanya merekam 21 variasi nilai selama 7 hari, menunjukkan resolusi kuantisasi kasar dan variabilitas lokal yang sangat teredam dibanding kedalaman 30 cm.

---

## 4. FORMULASI FITUR TEMPORAL KAUSAL (TUGAS C & D)

Seluruh fitur dirancang **kausal secara ketat**: hanya mengamati indeks saat ini ($t$) dan indeks masa lalu ($t-k$), tanpa operasi interpolasi masa depan (no forward-looking imputations).

### 4.1 Definisi Fitur
1. **First Difference ($\Delta y_t$):**
   $$\Delta y_t = y_t - y_{t-1}$$
   Mengukur laju perubahan mendadak antar sampel berurutan.
2. **Persentase Perubahan ($\% \Delta y_t$):**
   $$\% \Delta y_t = \frac{y_t - y_{t-1}}{y_{t-1}} \times 100\%$$
   *Validitas penyebut:* Terpenuhi secara aman karena nilai minimum turbidity adalah 134 NTU ($y_{t-1} \gg 0$).
3. **Rolling Mean ($\mu_{t, w}$):**
   $$\mu_{t, w} = \frac{1}{w} \sum_{i=0}^{w-1} y_{t-i}$$
   Dihitung untuk window $w \in \{5, 15, 60\}$ menit.
4. **Rolling Standard Deviation ($\sigma_{t, w}$):**
   $$\sigma_{t, w} = \sqrt{\frac{1}{w-1} \sum_{i=0}^{w-1} (y_{t-i} - \mu_{t, w})^2}$$
5. **Deviasi Baseline & Rolling Z-Score ($Z_{t, w}$):**
   $$d_{t, w} = y_t - \mu_{t, w}, \quad Z_{t, w} = \frac{y_t - \mu_{t, w}}{\sigma_{t, w} + \epsilon}$$
   Mengukur deviasi kekeruhan relatif terhadap dispersi lokal terkini.

### 4.2 Hasil Profil Fitur & Sensitivitas Terhadap Gap

| Metrik Fitur | Sensor 30 cm | Sensor 60 cm | Catatan Perilaku |
|---|---|---|---|
| **Mean First Difference** | $+0,0016$ NTU | $+0,0019$ NTU | Stabil di sekitar nol (stationer lokal) |
| **Std First Difference** | $0,1285$ NTU | $0,0520$ NTU | Fluktuasi per menit sangat kecil |
| **Max Absolute Difference** | $1,0$ NTU | $1,0$ NTU | Sinyal asli tidak pernah melonjak $>1$ NTU/menit |
| **Std Percentage Change** | $0,06\%$ | $0,04\%$ | Volatilitas normal sangat rendah |
| **Rolling Std Mean ($w=15$)**| $0,0991$ NTU | $0,0162$ NTU | Dispersi baseline sangat sempit |
| **Rolling Std Max ($w=15$)** | $0,5164$ NTU | $0,5164$ NTU | Terbatas oleh kuantisasi integer |

### 4.3 Sensitivitas Fitur Terhadap Gap Waktu (Temuan Kritis)
Ketika terjadi gap waktu $>120$ detik (misal gap 1 jam), menghitung fitur baris-ke-baris secara naif menimbulkan **distorsi batas (boundary contamination)**:
- Pada window $w=5$: **573 baris (5,95%)** data mencampurkan riwayat sebelum gap dengan data sesudah gap.
- Pada window $w=15$: **1.447 baris (15,04%)** data terdistorsi oleh data pra-gap.
- Pada window $w=60$: **3.644 baris (37,87%)** data jendela melintasi gap waktu.
- **Rekomendasi Rekayasa Fitur:** Sistem produksi tidak boleh menggunakan window berbasis jumlah indeks murni jika terdapat gap waktu. Buffer rolling harus **direset (invalidated / re-initialized)** setiap kali selisih waktu $\Delta t$ melebihi ambang batas toleransi sampling (misal $\Delta t > 2$ menit).

---

## 5. PERBANDINGAN DESKRIPTIF KEDALAMAN 30 CM VS 60 CM (TUGAS E)

Dua sensor ditempatkan di badan air yang sama pada kedalaman 30 cm dan 60 cm dari permukaan:

1. **Selisih Magnitudo Kekeruhan ($\Delta_{60-30}$):**
   - Rata-rata selisih: **$-73,6312$ NTU** (Sensor 60 cm rata-rata 73,6 NTU lebih rendah).
   - Standar deviasi selisih: $13,1914$ NTU.
   - Rentang selisih: $-101$ NTU s/d $-56$ NTU.
   - Proporsi waktu $60\text{ cm} < 30\text{ cm}$: **100,00%** dari seluruh sampel. Tidak pernah sekalipun sensor 60 cm melampaui atau menyamai sensor 30 cm.
2. **Korelasi Linier & Monoton:**
   - **Korelasi Pearson ($r$):** $+0,6188$ (korelasi moderat-kuat positif).
   - **Korelasi Spearman ($\rho$):** $+0,6108$.
3. **Interpretasi Fisik vs Asumsi Sistem:**
   - Kekeruhan yang lebih tinggi pada 30 cm kemungkinan dipicu oleh paparan cahaya matahari langsung yang memicu ledakan fitoplankton/alga di lapisan epilimnion, serta turbulensi permukaan angin.
   - **Batasan Tegas:** Kedua seri data ini **bukan dua sensor pada dua cabang pipa PDAM independen**. Korelasi $0,61$ mencerminkan dinamika diurnal lingkungan terbuka yang sama, bukan redundansi topologi jaringan tertutup.

---

## 6. SIMULASI SHOCK ANOMALI TERISOLASI (TUGAS F — SYNTHETIC DEMONSTRATION)

> **[SYNTHETIC DEMONSTRATION — BUKAN GROUND TRUTH DUNIA NYATA]**  
> Percobaan ini sepenuhnya terisolasi pada salinan memori untuk mengevaluasi respons teoretis fitur lag/rolling terhadap lonjakan kekeruhan mendadak (sudden pulse contamination).

### Skenario Injeksi:
- Segmen terpilih: Data kontinu stabil (indeks 2000 s/d 2500 pada sensor 30 cm, tanpa gap sampling).
- Injeksi anomali: Lonjakan pulsa $+40$ NTU disuntikkan pada rentang sampel indeks 250 hingga 255 (durasi 6 menit).

### Respons Fitur Temporal:
1. **Kondisi Normal (Indeks 248):**
   - Turbidity: $212$ NTU
   - First Difference ($\Delta y$): $0,0$ NTU
   - Rolling Z-Score ($w=15$): $0,00$
2. **Saat Onset Kejutan (Indeks 250):**
   - Turbidity: $252$ NTU
   - First Difference ($\Delta y$): $+40,0$ NTU (melompat drastis dari baseline fluktuasi normal yang hanya $\le 1$ NTU).
   - Rolling Z-Score ($w=15$): $+3,61$ (menembus ambang batas signifikansi statistik $3\sigma$).
3. **Saat Pemulihan / Shock Recovery (Indeks 256):**
   - Turbidity kembali ke $212$ NTU.
   - First Difference ($\Delta y$): $-40,0$ NTU (mendeteksi pemulihan simetris).
   - Rolling Z-Score ($w=15$): $-0,79$ (adaptasi kembali ke baseline).
4. **Pasca-Pemulihan (Indeks 270, setelah $w=15$ sampel):**
   - Window rolling membersihkan seluruh riwayat anomali; Z-score kembali ke $0,00$.

### Kesimpulan Eksperimen Simulasi:
Kombinasi antara **First Difference ($\Delta y$)** dan **Rolling Z-Score** sangat efektif membedakan perubahan mendadak (spike) dari tren alami lambat (diurnal drift), asalkan jendela waktu bebas dari kontaminasi gap sampling.

---

## 7. PENILAIAN KELAYAKAN DATASET UNTUK AQUA-TARA (TUGAS G & H)

### 7.1 Mengapa Tidak Memilih Threshold Operasional dari Dataset Ini (Tugas G)
1. **Ketidaksesuaian Domain & Regulasi:**
   - Air minum perkotaan (Permenkes 2/2023) mewajibkan batas kekeruhan $< 5$ NTU, dan threshold bahaya AQUA-TARA ditetapkan pada $> 25$ NTU.
   - Seluruh data KU-MWQ berada pada rentang **134 – 249 NTU** (secara permanen berada pada zona ekstrem). Menyetel threshold operasional dari dataset ini akan merusak kalibrasi sistem deteksi risiko air minum AQUA-TARA.
2. **Kuantisasi Diskrit 1 NTU:**
   Sensor analog DFRobot yang digunakan tidak memiliki sertifikasi kalibrasi presisi ISO 7027 / EPA 180.1 untuk air perpipaan.

### 7.2 Evaluasi Komprehensif: Rekayasa Fitur vs Evaluasi Model (Tugas H)

| Aspek Penggunaan | Status Evaluasi | Penjelasan & Bukti |
|---|---|---|
| **Prototipe Feature Engineering Temporal** | **DIDUKUNG (SUFFICIENT)** | Dataset menyediakan deret waktu multi-hari dengan resolusi per menit yang sangat berguna untuk menguji kode matematis fitur kausal (lag, diff, rolling stats, Z-score, dan logika penanganan gap waktu). |
| **Evaluasi / Benchmark Model Anomali** | **TIDAK DIDUKUNG (INSUFFICIENT)** | Dataset **tidak memiliki ground truth**. Evaluasi model (seperti akurasi, presisi, recall, F1-score) mustahil dilakukan tanpa label anomali fisik nyata yang terverifikasi. |
| **Training Model Produksi AQUA-TARA** | **DITOLAK (UNSUITABLE)** | Karakteristik fisik kolam terbuka tropis tidak merefleksikan dinamika hidraulik pipa transmisi air bersih bertekanan. |

---

## 8. ARTEFAK VISUAL & HASIL REPRODUSIBEL

Seluruh artefak hasil eksperimen disimpan secara terisolasi pada direktori `ml/experiments/ku_mwq_turbidity_features/`:

1. **[turbidity_timeseries_30_vs_60.png](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/ku_mwq_turbidity_features/turbidity_timeseries_30_vs_60.png)**: Visualisasi time series lengkap perbandingan kedalaman 30 cm dan 60 cm selama 7 hari.
2. **[temporal_features_decomposition.png](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/ku_mwq_turbidity_features/temporal_features_decomposition.png)**: Dekomposisi 4 panel fitur temporal (Raw Turbidity vs Rolling Mean, First Difference, Rolling Std Dev, dan Causal Z-Score).
3. **[synthetic_shock_demonstration.png](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/ku_mwq_turbidity_features/synthetic_shock_demonstration.png)**: Visualisasi pengujian respons fitur terhadap simulasi shock buatan (+40 NTU pulse).
4. **[experiment_summary.json](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/ku_mwq_turbidity_features/experiment_summary.json)**: Ringkasan numerik lengkap untuk verifikasi dan audit reproduktibilitas.

---

## 9. REKOMENDASI LANGKAH BERIKUTNYA

1. **Adopsi Logika Feature Engineering Temporal pada Pipeline:**
   Formulasi fitur temporal kausal (khususnya $\Delta y_t$ dan rolling Z-score ber-window pendek dengan proteksi gap reset) dapat diadopsi sebagai utilitas rekayasa fitur data IoT turbidity pada modul `ml/app/services/`.
2. **Tetap Gunakan BattLeDIM untuk Benchmark Anomali Jaringan Pipa:**
   Untuk evaluasi deteksi kebocoran dan anomali jaringan perpipaan, BattLeDIM tetap menjadi satu-satunya sumber benchmark yang valid karena memiliki topologi jaringan tertutup dan ground truth kebocoran fisik.
3. **Hindari Pelatihan Model Supervised pada KU-MWQ:**
   Jangan melatih classifier atau detector pada dataset KU-MWQ karena risiko bias domain kolam dan ketiadaan label.
