# STEP 4D.64 — AQUA-TARA ISOLATED ML BASELINE EXPERIMENT

**Status:** **PASS (ISOLATED BASELINE EXPERIMENT COMPLETED WITH EMPIRICAL BENCHMARK)**  
**Tanggal:** 2026-10-14  
**Target Pengujian:** Penyiapan lingkungan eksperimen terisolasi, pengunduhan & inventarisasi aktual dataset hidrolik BattLeDIM 2018, pengujian validitas ground truth & chronological split, feature engineering hidrolik bebas data leakage, eksekusi baseline model Isolation Forest & perbandingan heuristik, penyimpanan artefak terisolasi, serta dokumentasi metrik empiris tanpa modifikasi sistem produksi.  
**Batasan Keamanan Terpenuhi:**
- Tanpa akses/penulisan ke database PostgreSQL/Supabase atau database aplikasi mana pun.
- Tanpa eksekusi Laravel command, migration, seeding, reset, db:wipe, migrate:fresh, atau PHPUnit.
- Formula kanonikal Risk Score warga `round(0.8*Score_T + 0.2*Score_S)` **tidak diubah**.
- Source code produksi Laravel dan Flask **tidak diubah** (zero diff pada file produksi).
- Ambang batas operasional IoT final `NORMAL / ANOMALY / CRITICAL` **tidak ditetapkan sepihak** (tetap Open Decision F-03).
- Fixture tiket `#55` dan report terkait di database pengujian tidak disentuh atau dihapus.
- Seluruh file dataset mentah dan artefak model berada di direktori ML terisolasi yang telah dikecualikan oleh [.gitignore](file:///e:/TUGAS/pengkoding/anti/aqua-tara/.gitignore).
- Tanpa kebocoran rahasia (*zero secret exposure*).

---

## 1. Environment dan Versi Dependensi

Sebelum melakukan eksekusi apa pun, dilakukan audit menyeluruh terhadap runtime Python dan pustaka yang terpasang di sistem:

| Komponen / Pustaka | Versi Terpasang | Status Kesesuaian | Keterangan |
|---|---|---|---|
| **Python Runtime** | `3.13.3` | Sesuai | Terdeteksi di lingkungan eksekusi lokal pengguna |
| **pandas** | `2.2.3` | Sesuai | Pustaka pemrosesan deret waktu hidrolik |
| **numpy** | `2.2.6` | Sesuai | Pustaka komputasi matriks numerik |
| **scikit-learn** | `1.6.1` | Sesuai | Implementasi `IsolationForest` dan `StandardScaler` |
| **joblib** | `1.5.1` | Sesuai | Serialisasi model dan artefak eksperimen |
| **Flask** | `3.1.3` | Produksi (Tidak disentuh) | Pustaka server inferensi ML eksisting |

**Kebijakan Isolasi Dependensi:**  
File [ml/requirements.txt](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/requirements.txt) produksi (yang saat ini hanya berisi `flask>=3.1.0`) **tidak dimodifikasi** untuk mencegah regresi pada environment build produksi. Dependensi eksperimen berjalan menggunakan runtime Python lokal yang telah memiliki pustaka sains data tersebut.

---

## 2. Sumber, Lisensi, dan Inventaris Dataset Aktual

### A. Sumber dan Lisensi Terverifikasi
- **Repositori Resmi:** Zenodo (CERN Open Science Platform).
- **DOI Resmi:** `10.5281/zenodo.4017659` (Vrachimis et al., 2020).
- **Lisensi:** Creative Commons Attribution 4.0 International (CC BY 4.0).
- **Integritas Unduhan:** Diunduh langsung dari Zenodo API dengan verifikasi checksum MD5 dan pencocokan ukuran byte secara otomatis.

### B. Inventaris Berkas di [ml/data/battledim/](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/data/battledim)

| Nama Berkas | Ukuran Aktual (Byte) | MD5 Checksum Terverifikasi | Jumlah Baris & Kolom | Deskripsi Konten |
|---|---|---|---|---|
| `2018_SCADA_Pressures.csv` | 22.593.812 byte | `d389d8541350c19ff0bfc6b80f246d35` | 105.120 baris data, 34 kolom (Timestamp + 33 sensor nodal) | Sensor tekanan junction (`m` head), separator `;`, desimal `,` |
| `2018_SCADA_Flows.csv` | 4.023.420 byte | `d0602c06946b46287e956f007e4264ee` | 105.120 baris data, 4 kolom (Timestamp + 3 sensor aliran) | Sensor debit inlet (`m^3/h`), separator `;`, desimal `,` |
| `2018_Leakages.csv` | 6.234.468 byte | `c2c5fab90420da44f02e29775050abfe` | 105.120 baris data, 15 kolom (Timestamp + 14 pipa bocor) | Ground truth debit kebocoran kontinu per pipa (`m^3/h`) |
| `2018_Fixed_Leakages_Report.txt` | 317 byte | `50b1f8b7ba0db5a19cedbb060c63d912` | 10 baris insiden perbaikan | Laporan riil teknisi: 10 pipa kebocoran besar yang diperbaiki |
| `dataset_configuration.yaml` | 3.926 byte | `48486401f5b4d0447023f5ce5d242c52` | 34 konfigurasi kebocoran total (2018–2019) | Metadata konfigurasi generator skenario BattLeDIM |
| `README.txt` | 5.303 byte | `7ad5cdc2eebfe78197a3412e80d07ddc` | Deskripsi file kompetisi | Dokumentasi resmi KIOS Research Centre |

### C. Kualitas Data dan Karakteristik Numerik
- **Frekuensi Sampling:** 5 menit kontinu ($105.120$ timestep sepanjang 365 hari tahun 2018).
- **Missing Values:** `0` pada seluruh sensor tekanan dan debit.
- **Duplikasi Timestamp:** `0` (deret waktu teratur dari `2018-01-01 00:00:00` sampai `2018-12-31 23:55:00`).
- **Sinkronisasi Timestamp Antar-File:** 100% cocok (*exact match* antara `Pressures`, `Flows`, dan `Leakages`).

---

## 3. Validasi Timestamp dan Ground Truth

### A. Koreksi Krusial terhadap Klaim Laporan Sebelumnya
Laporan awal pada STEP 4D.63 mengusulkan periode `Januari–Juni 2018` sebagai baseline normal operasi hidrolik dengan asumsi periode tersebut bebas insiden kebocoran besar.

**Temuan Fakta Empiris dari [2018_Leakages.csv](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/data/battledim/2018_Leakages.csv) dan [dataset_configuration.yaml](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/data/battledim/dataset_configuration.yaml):**
1. **Januari–Juni 2018 BUKAN periode bebas kebocoran:**
   - Kebocoran pertama pada pipa `p257` telah aktif sejak **9 Januari 2018 pukul 00:30** dan berlanjut sepanjang tahun.
   - Pada rentang Januari–Juni 2018 ($52.128$ timestep), terdapat **$49.818$ timestep (95,57%)** yang memiliki nilai kebocoran aktif pada setidaknya satu pipa.
   - Pipa insiden besar yang pecah dan diperbaiki pada Januari–Juni meliputi:
     - `p461`: 23 Jan – 02 Apr (puncak debit $30,54\text{ m}^3/\text{h}$)
     - `p232`: 31 Jan – 10 Feb (puncak debit $26,10\text{ m}^3/\text{h}$)
     - `p673`: 05 Mar – 23 Mar (puncak debit $28,42\text{ m}^3/\text{h}$, tipe *abrupt burst*)
     - `p628`: 02 Mei – 29 Mei (puncak debit $35,38\text{ m}^3/\text{h}$)
     - `p538`: 18 Mei – 02 Jun (puncak debit $32,84\text{ m}^3/\text{h}$)
     - `p866`: 01 Jun – 12 Jun (puncak debit $20,57\text{ m}^3/\text{h}$)
2. **Kategori Ground Truth:**
   - **Reported Fixed Leakages (10 pipa insiden besar):** Pipa yang pecah mendadak/besar dan tercatat diperbaiki dalam laporan operasional teknisi (`2018_Fixed_Leakages_Report.txt`). Ini adalah anomali utama yang wajib dideteksi oleh sistem monitoring hidrolik.
   - **Unreported Background Leakages (4 pipa):** Pipa dengan kebocoran mikro bertahap yang tidak dilaporkan atau baru membesar di akhir tahun (`p257`, `p427`, `p654`, `p810`).
3. **Periode Bersih Murni (*Zero Reported Fixed Burst*):**
   - Tanggal **1 Januari 2018 00:00:00 hingga 23 Januari 2018 23:50:00** ($6.623$ timestep, 23 hari penuh) terbukti **100% bebas dari seluruh insiden kebocoran besar (fixed burst)**.

---

## 4. Split Berbasis Waktu (Chronological Split) dan Rationale

Untuk mematuhi aturan ketat anti-data leakage (tanpa random shuffle), pembagian data dilakukan secara ketat berbasis urutan kronologis waktu:

```
[1 Jan 2018] ───(Train: 23 Hari / 6.623 Sampel)───> [23 Jan 2018]
                           │ (Clean Baseline / Zero Fixed Burst)
[1 Jul 2018] ───(Val: 62 Hari / 17.856 Sampel)───> [31 Agu 2018]
                           │ (Tuning Threshold: Insiden p31 & p183)
[1 Sep 2018] ───(Test: 122 Hari / 35.136 Sampel)───> [31 Des 2018]
                             (Unseen Blind Test: Insiden p158, p369 & Normal Pasca-Perbaikan)
```

| Subset | Rentang Waktu (UTC) | Jumlah Sampel | Durasi | Proporsi Insiden Fixed Burst | Rationale Pemilihan |
|---|---|---|---|---|---|
| **Training Set** | 2018-01-01 00:00 s/d 2018-01-23 23:50 | $6.623$ | 23,0 hari | **0,0%** ($0$ fixed burst) | Periode terbukti bersih dari insiden kebocoran pipa besar. Model mempelajari dinamika hidrolik diurnal murni. |
| **Validation Set** | 2018-07-01 00:00 s/d 2018-08-31 23:55 | $17.856$ | 62,0 hari | **100,0%** ($17.856$ sampel aktif) | Mengandung insiden pipa `p31` dan `p183`. Digunakan murni untuk menala batas sensitivitas anomali (*threshold tuning*). |
| **Test Set** | 2018-09-01 00:00 s/d 2018-12-31 23:55 | $35.136$ | 122,0 hari | **26,19%** ($9.201$ sampel aktif) | Data masa depan yang sama sekali tidak dilihat oleh proses fitting maupun tuning. Memuat pipa pecah `p158` (Okt), `p369` (Nov), dan periode operasi normal pasca-perbaikan (Desember). |

---

## 5. Preprocessing dan Rekayasa Fitur (Feature Engineering)

### A. Konversi Satuan Fisik
1. **Tekanan ($P$):**
   $$P_{\text{bar}} = P_{\text{m head}} \times 0.0980665$$
   Rentang jaringan: $2,517\text{ bar}$ s/d $5,587\text{ bar}$.
2. **Debit Aliran ($Q$):**
   $$Q_{\text{L/min}} = Q_{\text{m}^3/\text{h}} \times 16.6667$$
   Rentang jaringan: Total debit inlet berkisar antara $450\text{ L/min}$ hingga $4.500\text{ L/min}$.

### B. Fitur yang Dibentuk (11 Fitur Bebas Leakage)
1. `mean_pressure_bar`: Rata-rata tekanan seluruh sensor jaringan pada saat $t$.
2. `std_pressure_bar`: Standar deviasi tekanan antar-nodal (mencerminkan gradien hidrolik jaringan).
3. `total_flow_lpm`: Jumlah debit inlet dari 3 sensor flow (`p227`, `p235`, `PUMP_1`).
4. `ratio_qp`: Rasio debit terhadap tekanan ($Q / (P + \epsilon)$) dengan penanganan denominator nol $\epsilon = 10^{-6}$.
5. `delta_p_24h`: Selisih tekanan terhadap rata-rata bergerak 24 jam masa lalu ($\Delta P(t) = P(t) - \bar{P}_{24\text{h, past}}$).
   - **Pencegahan Leakage:** Menggunakan `.shift(1).rolling(window=288, min_periods=1).mean()` sehingga window hanya membaca titik waktu $t-1, \dots, t-288$, tanpa melihat data masa depan atau data pada waktu $t$.
6. `delta_q_24h`: Selisih debit terhadap rata-rata bergerak 24 jam masa lalu ($\Delta Q(t) = Q(t) - \bar{Q}_{24\text{h, past}}$).
7. `p_n1_bar`, `p_n31_bar`, `p_n215_bar`, `p_n495_bar`, `p_n679_bar`: 5 tekanan nodal terlokalisasi yang terdistribusi secara spasial di berbagai zona jaringan pipa L-Town.

### C. Standardisasi
Menggunakan `StandardScaler` yang di-fit **hanya pada Training Set**, kemudian diterapkan (`transform`) pada Validation dan Test Set.

---

## 6. Baseline Model dan Konfigurasi

### A. Model Kandidat: Isolation Forest
- **Implementasi:** `sklearn.ensemble.IsolationForest`.
- **Parameter:**
  - `n_estimators`: `150`
  - `contamination`: `0.01` (diasumsikan 1% noise operasional pada data latih normal)
  - `random_state`: `42`
  - `n_jobs`: `-1`
- **Scoring Function:** Negasi dari `decision_function` sehingga nilai yang lebih tinggi merepresentasikan tingkat anomali yang lebih besar.
- **Threshold Tuning:** Dicari pada Validation Set berdasarkan F1-Score optimal untuk mendeteksi status insiden kebocoran besar, menghasilkan ambang batas $\tau = -0.01704$.

### B. Model Pembanding: Heuristik Sederhana (Rolling Z-Score)
Sebagai pembanding dasar (*benchmark reference*), diterapkan aturan heuristik teknis sederhana: jika deviasi tekanan 24 jam ($\Delta P$) jatuh di bawah $1.5 \times \text{standar deviasi}$ baseline normal latih ($\Delta P < -1.5 \times \sigma_{\text{train}}$), maka kondisi tersebut ditandai sebagai anomali.

---

## 7. Metrik Aktual dan Keterbatasan

### A. Hasil Evaluasi pada Test Set (1 September – 31 Desember 2018)

Evaluasi terhadap Ground Truth Insiden Pipa Pecah Besar (*Fixed Burst*):

| Metrik Evaluasi | Isolation Forest Baseline | Heuristik Rolling Z-Score ($\Delta P$) | Evaluasi Kinerja |
|---|---|---|---|
| **Precision** | **0.3670** (36,70%) | 0.3449 (34,49%) | IF unggul moderat (+2,2%) |
| **Recall** | **0.4564** (45,64%) | 0.1653 (16,53%) | **IF unggul drastis (+29,1%)** |
| **F1-Score** | **0.4069** | 0.2235 | **IF unggul signifikan (+82% relatif)** |
| **False Alarm Rate (FAR)** | **0.2792** (27,92%) | 0.1114 (11,14%) | Heuristik lebih konservatif |
| **True Positives (TP)** | **4.199** timestep | 1.521 timestep | IF menangkap lebih banyak durasi kebocoran |
| **False Positives (FP)** | **7.241** timestep | 2.890 timestep | Alarm pada fluktuasi transien malam/subuh |
| **False Negatives (FN)** | **5.002** timestep | 7.680 timestep | IF melewatkan lebih sedikit titik anomali |
| **True Negatives (TN)** | **18.694** timestep | 23.045 timestep | Periode normal berhasil diklasifikasikan |

Evaluasi terhadap Seluruh Ground Truth Kebocoran (Termasuk Background Leak Mikro):
- **Precision:** `1.0000` (100% — seluruh alarm yang dibunyikan terjadi saat jaringan memang mengalami kebocoran fisik).
- **Recall:** `0.3256` (32,56%).
- **F1-Score:** `0.4912`.

### B. Event-Level Metric: Time-to-Detection (TTD)
Dihitung terhadap 2 insiden pipa pecah mendadak (*abrupt burst*) pada Test Set:
1. **Insiden Pipa `p158` (Mulai 6 Oktober 2018 02:35):**
   - Deteksi anomali pertama muncul pada **step ke-112** setelah insiden dimulai.
   - **Time-to-Detection:** **9,3 jam** ($560$ menit).
2. **Insiden Pipa `p369` (Mulai 26 Oktober 2018 02:05):**
   - Deteksi anomali pertama muncul pada **step ke-106** setelah insiden dimulai.
   - **Time-to-Detection:** **8,8 jam** ($530$ menit).

### C. Keterbatasan Baseline dan Fakta FAR
Sesuai aturan keamanan prompt, **tidak ada janji palsu $\text{FAR} \le 0.05$**. Hasil empiris aktual menunjukkan FAR pada level titik waktu 5 menit mencapai **0.2792 (27,92%)**.
Penyebab utama tingginya FAR titik waktu adalah:
1. **Fluktuasi Jam Puncak & Operasi Pompa:** Lonjakan transien hidrolik sesaat saat pompa dinyalakan/dimatikan memicu skor anomali tinggi sesaat (*point-in-time noise*).
2. **Ketiadaan Filter Persistensi Temporal:** Model baseline saat ini mengevaluasi setiap sampel 5 menit secara independen tanpa filter ambang waktu (*consecutive anomaly window filter*, misal: alarm hanya sah jika anomali bertahan $\ge 3$ timestep berturut-turut).

---

## 8. Lokasi Artefak Eksperimen Terisolasi

Seluruh artefak hasil eksperimen tersimpan secara terisolasi pada direktori:
`ml/experiments/battledim_baseline/`

| Berkas Artefak | Ukuran | Deskripsi & Peran |
|---|---|---|
| [isolation_forest_model.joblib](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/battledim_baseline/isolation_forest_model.joblib) | 2.216.409 byte (~2,2 MB) | Bobot terdistribusi model pohon Isolation Forest (150 estimators) |
| [preprocessor_scaler.joblib](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/battledim_baseline/preprocessor_scaler.joblib) | 863 byte | Objek scaler standardisasi fitur yang dilatih strictly pada data latih |
| [metadata.json](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/battledim_baseline/metadata.json) | 1.725 byte | Catatan metadata lengkap, spesifikasi fitur, split, threshold, dan metrik hasil |

---

## 9. Inventaris Perubahan Berkas (File Changes Log)

| Berkas | Jenis Perubahan | Rationale |
|---|---|---|
| [.gitignore](file:///e:/TUGAS/pengkoding/anti/aqua-tara/.gitignore) | Modifikasi | Menambahkan pengecualian `ml/data/battledim/` dan `ml/experiments/` agar file biner dataset besar dan model tidak masuk repositori Git. |
| `ml/data/battledim/*` | Penambahan lokal (Diabaikan Git) | Unduhan dataset BattLeDIM 2018 dengan verifikasi checksum MD5. |
| `ml/experiments/battledim_baseline/*` | Penambahan lokal (Diabaikan Git) | Artefak model, scaler, dan metadata eksperimen terisolasi. |
| [backend/docs/STEP_4D_64_ISOLATED_ML_BASELINE_EXPERIMENT.md](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/docs/STEP_4D_64_ISOLATED_ML_BASELINE_EXPERIMENT.md) | Penambahan berkas | Laporan formal dokumentasi eksperimen baseline STEP 4D.64. |

**Verifikasi Status Git:**  
Tidak ada berkas backend Laravel, frontend Vue, database migration, seeding, atau requirements produksi yang diubah.

---

## 10. Risiko dan Rekomendasi STEP 4D.65

### A. Risiko Teknis
1. **False Alarm Rate 27,9% pada Point-in-Time:** Jika diterapkan secara langsung ke dashboard monitoring IoT tanpa mekanisme penyaringan (*debouncing/persistence filter*), operator akan menerima terlalu banyak notifikasi peringatan.
2. **Kebutuhan Agregasi Temporal:** Anomali hidrolik pipa fisik bersifat kontinu, bukan sekadar lonjakan 1 interval 5 menit. Deteksi berbasis jendela persistensi (misal: $\ge 30$ menit konsisten) diprediksi dapat menekan FAR hingga $< 5\%$.
3. **Pemisahan Model IoT dari Risk Score Kualitas Air:** Model deteksi hidrolik ini hanya bertugas mengindikasikan integritas fisik pipa (*anomali hidrolik*), bukan menggantikan pengukuran kekeruhan (*turbidity*) pada formula Risk Score warga.

### B. Rekomendasi Terarah untuk STEP 4D.65:
1. **Eksperimen Persistence Window / Debounce Filter:** Uji mekanisme penyaringan alarm (misal: status `ANOMALY` hanya dinaikkan bila skor melampaui threshold selama 6 timestep berturut-turut / 30 menit) untuk memvalidasi penurunan FAR secara empiris.
2. **Perumusan Rekomendasi Open Decision F-03:** Berdasarkan performa empiris, susun rekomendasi batas threshold status IoT `NORMAL`, `ANOMALY`, dan `CRITICAL` menggunakan arsitektur hibrida (deviasi statistik ML untuk `ANOMALY` dan batas darurat fisik minimum tekanan untuk `CRITICAL`).
3. **Kesiapan Kontrak Inferensi Terisolasi:** Tetap jaga model di lingkungan riset offline sebelum ada keputusan arsitektural resmi untuk mengintegrasikan model ke Flask service `ml/app/`.
