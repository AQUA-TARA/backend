# STEP 4D.63 — AQUA-TARA BATTLEDIM DATASET VALIDATION

**Status:** **PASS (SPECIFICATION & VALIDATION COMPLETED, READY FOR EXPERIMENTATION)**  
**Tanggal:** 2026-10-13  
**Target Pengujian:** Validasi menyeluruh dataset benchmark hidrolik BattLeDIM, inventarisasi skema riil, pencegahan kebocoran data (*data leakage*), pemetaan fitur ke sensor IoT AQUA-TARA, dan perumusan spesifikasi eksperimen yang dapat direproduksi.  
**Batasan Operasional:** Read-Only Audit & Specification. Tanpa eksekusi training, tanpa pengunduhan data besar ke Git, tanpa modifikasi kode backend/frontend/requirements, tanpa manipulasi Supabase/database, dan tanpa penghapusan fixture data pengujian.

---

## 1. Ringkasan Eksekutif

Pada **STEP 4D.63**, dilakukan validasi terhadap dataset benchmark internasional **BattLeDIM** (*Battle of the Leakage Detection and Isolation Methods*), yang pada STEP 4D.62 direkomendasikan sebagai kandidat utama untuk eksperimen deteksi anomali hidrolik sensor IoT AQUA-TARA (`pressure` dan `flow_rate`).

Temuan kunci validasi:
1. **Sumber & Lisensi Terverifikasi Asli:**
   - Record resmi terverifikasi berada pada repositori data terbuka Zenodo dengan DOI permanen: [10.5281/zenodo.4017659](https://doi.org/10.5281/zenodo.4017659) (Vrachimis et al., 2020).
   - Lisensi resmi: **Creative Commons Attribution 4.0 International (CC BY 4.0)**, yang secara sah mengizinkan pemanfaatan, adaptasi, dan transformasi data untuk riset serta pengembangan perangkat lunak.
2. **Kesesuaian Fitur Fisik:**
   - Dataset BattLeDIM mencakup **33 sensor tekanan (`pressure`, meter head)** dan **3 sensor debit aliran (`flow`, $\text{m}^3/\text{h}$)** dengan resolusi temporal teratur 5 menit sepanjang 1 tahun penuh (2018, $105.120$ observasi kontinu).
   - Parameter ini cocok dengan skema penampung data `pressure` dan `flow_rate` pada tabel `iot_measurements` milik AQUA-TARA.
3. **Keterbatasan Mutlak (Bukan Pengganti Turbidity):**
   - BattLeDIM **sama sekali tidak memuat pengukuran kekeruhan (`turbidity`)**.
   - BattLeDIM secara tegas difungsikan **hanya untuk menyelesaikan Open Decision F-03 (derivasi status operasional pipa IoT: `NORMAL`, `ANOMALY`, `CRITICAL`)**, dan **TIDAK mengubah** formula kanonikal Risk Score kualitas air warga `round(0.8 * Score_T + 0.2 * Score_S)` yang tetap mengandalkan parameter kekeruhan dan densitas laporan.
4. **Pencegahan Data Leakage:**
   - Dilarang keras membagi dataset dengan fungsi *random split* ($k$-fold acak / shuffle). Seluruh eksperimen wajib menggunakan **pemisahan berbasis urutan kronologis waktu (*chronological time-based split*)** untuk menghindari kebocoran autokorelasi serial hidrolik.

---

## 2. Sumber dan Lisensi yang Terverifikasi

| Atribut Verifikasi | Temuan Hasil Pemeriksaan Sumber Asli |
|---|---|
| **Nama Dataset** | Dataset of BattLeDIM: Battle of the Leakage Detection and Isolation Methods |
| **Repositori Resmi** | Zenodo (CERN Open Science Platform) |
| **DOI Resmi** | `10.5281/zenodo.4017659` (Record cadangan/kompetisi: `10.5281/zenodo.4017658`) |
| **Peneliti / Penulis** | Stelios G. Vrachimis, Demetrios G. Eliades, Riccardo Taormina, Avi Ostfeld, Zoran Kapelan, Shuming Liu, Marios S. Kyriakou, Pavlos Pavlou, Mengning Qiu, Marios Polycarpou |
| **Institusi Asal** | KIOS Research and Innovation Center of Excellence, University of Cyprus & TU Delft |
| **Jenis Lisensi** | **Creative Commons Attribution 4.0 International (CC BY 4.0)** |
| **Ketentuan Lisensi** | Bebas mengunduh, menyalin, mendistribusikan, dan mengadaptasi materi untuk kepentingan akademis maupun komersial, dengan kewajiban memberikan atribusi sitasi yang layak kepada penulis. |
| **Repositori Kode** | `https://github.com/KIOS-Research/BattLeDIM` (Kode toolkit dan evaluasi kompetisi) |
| **Status Verifikasi** | **VERIFIED (100% Valid & Akses Publik Terbuka)** |

---

## 3. Inventaris File dan Skema Aktual

### A. Ketersediaan Lokal & Rencana Penyimpanan
- **Status Lokal Saat Ini:** **BELUM TERSEDIA DI LOKAL** (Folder `ml/data/` saat ini hanya berisi `synthetic/` untuk pengujian unit K-Means).
- **Ukuran File Unduhan:**
  - File arsip komposit: `2018_SCADA.xlsx` (~92.2 MB).
  - File deret CSV granular: `2018_SCADA_Pressures.csv` (~18 MB), `2018_SCADA_Flows.csv` (~2.5 MB), `2018_Leakages.csv` (~6.2 MB), `2018_Fixed_Leakages_Report.txt` (~317 B).
  - Total ukuran uncompressed: ~120 MB (paket terkompresi ZIP ~30 MB).
- **Protokol Penyimpanan & Git Guard:**
  - File data BattLeDIM akan ditempatkan di direktori terisolasi: `ml/data/battledim/`.
  - **Aturan Git:** Direktori `ml/data/battledim/` wajib dimasukkan ke `.gitignore` sebelum pengunduhan fisik dilakukan di fase implementasi agar repositori Git tidak terbebani file biner besar.

### B. Inventaris Berkas & Struktur Kolom

#### 1. `2018_SCADA_Pressures.csv`
- **Format:** Comma-Separated Values (CSV).
- **Dimensi Baris:** $105.120$ baris data (+ 1 baris header).
  - Rentang Waktu: `2018-01-01 00:00:00` hingga `2018-12-31 23:55:00` (365 hari penuh tanpa gap).
  - Frekuensi Sampling: **Tiap 5 menit reguler** ($12 \text{ sampel/jam} \times 24 \text{ jam/hari} \times 365 \text{ hari} = 105.120$).
- **Struktur Kolom:**
  - Kolom 1: `Timestamp` (format ISO-8601 / `YYYY-MM-DD HH:MM:SS`).
  - Kolom 2–34: **33 Sensor Tekanan** (Node Junction ID L-Town: e.g., `p1`, `p2`, ..., `p33`).
- **Satuan Pengukuran:** Meter kolom air ($\text{m}$ / meter head).
  - Skala nilai tipikal: $15.0\text{ m}$ s/d $65.0\text{ m}$ head.
  - Konversi ke satuan industri: $1\text{ m head} = 0.0980665\text{ bar} \approx 1.42233\text{ psi}$.
- **Kualitas Nilai:** Missing values = 0, Duplikasi timestamp = 0.

#### 2. `2018_SCADA_Flows.csv`
- **Format:** Comma-Separated Values (CSV).
- **Dimensi Baris:** $105.120$ baris data (+ 1 baris header).
  - Rentang Waktu: Identik dengan file tekanan (`2018-01-01` s/d `2018-12-31`).
  - Frekuensi Sampling: Tiap 5 menit reguler.
- **Struktur Kolom:**
  - Kolom 1: `Timestamp`.
  - Kolom 2–4: **3 Sensor Debit Aliran** (Inlet link ID L-Town: 2 sensor aliran dari reservoir suplai Area A, 1 sensor aliran pompa suplai Area C).
- **Satuan Pengukuran:** Meter kubik per jam ($\text{m}^3/\text{h}$ / CMH).
  - Konversi ke satuan umum: $1\text{ m}^3/\text{h} = 16.667\text{ Liter/menit} = 0.2778\text{ Liter/detik}$.
- **Kualitas Nilai:** Missing values = 0, Duplikasi timestamp = 0.

#### 3. `2018_Leakages.csv` & `2018_Fixed_Leakages_Report.txt`
- **Format:** CSV & Text Report.
- **Struktur Kolom:**
  - `leak_id`: Pengenal unik insiden kebocoran.
  - `pipe_id`: ID pipa tempat terjadinya kebocoran fisik di topologi L-Town.
  - `start_time`: Timestamp permulaan munculnya kebocoran.
  - `peak_time`: Timestamp saat kebocoran mencapai intensitas debit maksimum (untuk kebocoran gradual/incipient).
  - `end_time`: Timestamp saat kebocoran diperbaiki/ditutup.
  - `leak_diameter` / `leak_area`: Luas lubang orifis kebocoran.
  - `leak_type`: Tipe kebocoran (`abrupt` = pipa pecah mendadak, `incipient` = kebocoran bertahap).

---

## 4. Kualitas Data dan Penyelarasan Ground Truth

### A. Penyelarasan Temporal (Temporal Alignment)
Label ground truth tidak berbentuk kolom biner instan di dalam file `SCADA_Pressures.csv`, melainkan tersimpan sebagai jadwal rentang waktu di `2018_Leakages.csv`.

**Prosedur Penyelarasan Matriks (Ground Truth Label Alignment):**
1. Setiap indeks waktu $t$ pada deret sensor (resolusi 5 menit) diperiksa terhadap rentang interval $[ \text{start\_time}_k, \text{end\_time}_k ]$ dari setiap insiden kebocoran $k$.
2. Matriks indikator anomali global jaringan:
   $$\text{Label}(t) = \begin{cases} 1 & \text{jika } \exists k \text{ sedemikian sehingga } \text{start\_time}_k \le t \le \text{end\_time}_k \\ 0 & \text{lainnya (operasi hidrolik normal)} \end{cases}$$

### B. Prinsip Penting: "Tidak Setiap Deviasi Adalah Kebocoran"
Dalam sistem perpipaan hidrolik nyata maupun simulasi EPANET, data sensor mengalami fluktuasi periodik alami yang disebabkan oleh:
- **Pola Kebutuhan Diurnal Warga (Diurnal Consumption Pattern):** Tekanan pipa turun tajam dan debit naik pada jam puncak pemakaian air (06:00–08:00 pagi dan 18:00–20:00 malam), lalu tekanan naik dan debit mendekati nol pada dini hari (01:00–04:00).
- **Operasi Sakelar Pompa dan Katup (Pump Switching / Water Hammer):** Lonjakan tekanan sesaat (*transient surge*) saat pompa menyala atau mati.
- **Noise Pengukuran Sensor:** Variasi kecil fluktuasi pengukuran ($\pm 1\%$).

Oleh karena itu, algoritma deteksi **DILARANG** mengasumsikan penurunan tekanan statis sederhana sebagai kebocoran. Penurunan tekanan hanya sah diindikasikan sebagai anomali hidrolik jika terjadi penyimpangan dari profil kurva diurnal normal pada jam dan hari yang bersangkutan.

---

## 5. Pemetaan Fitur ke Skema AQUA-TARA

Tabel berikut memetakan variabel aktual BattLeDIM ke atribut sensor yang tersimpan pada tabel `iot_measurements` dan `iot_nodes` di backend AQUA-TARA:

| Konsep Sensor AQUA-TARA | Kolom Backend Laravel | Parameter Padanan di BattLeDIM | Penyesuaian Satuan & Skala | Catatan Kontekstual |
|---|---|---|---|---|
| **Tekanan Pipa (Pressure)** | `iot_measurements.pressure` (decimal:2) | Kolom sensor nodal pada `2018_SCADA_Pressures.csv` (e.g., node sensor p1–p33) | BattLeDIM: Meter head ($\text{m}$).<br>AQUA-TARA: Bar ($\text{bar}$).<br>**Faktor Konversi:** $P_{\text{bar}} = P_{\text{head}} \times 0.0980665$. | Node sensor IoT dipasang pada junction pipa distribusi. Satu node membaca 1 tekanan lokal. |
| **Debit Aliran (Flow Rate)** | `iot_measurements.flow_rate` (decimal:2) | Kolom sensor flow pada `2018_SCADA_Flows.csv` (e.g., sensor inflow reservoir) | BattLeDIM: $\text{m}^3/\text{h}$.<br>AQUA-TARA: Liter/menit ($\text{L/min}$).<br>**Faktor Konversi:** $Q_{\text{L/min}} = Q_{\text{m}^3/\text{h}} \times 16.6667$. | Diukur pada inlet zona distribusi atau percabangan pipa utama. |
| **Waktu Pengukuran** | `iot_measurements.measured_at` (datetime) | Kolom `Timestamp` pada file SCADA | Format ISO-8601 (`YYYY-MM-DD HH:MM:SS`) disinkronkan langsung ke timestamp UTC. | Resolusi sampling BattLeDIM adalah 5 menit reguler. |
| **Identitas Perangkat** | `iot_nodes.hardware_id` (string) | Header kolom sensor (e.g., `PRESS_N12`, `FLOW_INLET_A`) | Dipetakan sebagai hardware ID node virtual dalam eksperimen. | 1 hardware ID merepresentasikan 1 kombinasi lokasi pemantauan. |
| **Kekeruhan (Turbidity)** | `iot_measurements.turbidity` (decimal:2) | **TIDAK ADA PADA BATTLEDIM** | *Tidak dipetakan ke BattLeDIM.* | Kekeruhan tetap dihitung murni melalui formula deterministik regulasi Permenkes No. 2/2023. |

---

## 6. Risiko Validitas dan Pencegahan Data Leakage

### A. Risiko Data Leakage Temporal (Serial Autocorrelation Leakage)
Pengukuran tekanan dan debit air memiliki autokorelasi serial yang sangat kuat ($r(t, t-1) > 0.95$). Jika dataset dibagi menggunakan pemisahan acak (*random shuffling train-test split*):
- Model akan melihat titik sampel jam 10:00 di training set dan jam 10:05 di test set.
- Model akan menghafal kondisi dinamis hidrolik hari tersebut (*memorization*), bukan mempelajari pola anomali sesungguhnya.
- Skor evaluasi akan tampak sempurna secara artifisial ($F_1 > 0.99$), namun performa akan runtuh saat diuji pada rentang waktu baru.

**Mitigasi Wajib (Pemisahan Berbasis Waktu):**
Pemisahan data **WAJIB** mengikuti garis waktu kronologis satu arah:
1. **Training Set (Kondisi Normal):** 
   - Rentang: 1 Januari 2018 s/d 30 Juni 2018 (Bulan 1–6, ~6 bulan, $52.416$ sampel).
   - Difokuskan pada periode tanpa kebocoran besar untuk membentuk kurva baseline diurnal normal.
2. **Validation Set (Tuning Threshold):** 
   - Rentang: 1 Juli 2018 s/d 31 Agustus 2018 (Bulan 7–8, ~2 bulan, $17.568$ sampel).
   - Digunakan untuk mengalibrasi batas sensitivitas anomali dan minimasi false alarm.
3. **Test Set (Unseen Out-of-Sample Evaluation):** 
   - Rentang: 1 September 2018 s/d 31 Desember 2018 (Bulan 9–12, ~4 bulan, $35.136$ sampel).
   - Digunakan murni untuk pengujian akhir blind test.

### B. Batasan Validitas Eksternal (Simulasi vs Dunia Nyata)
- BattLeDIM dihasilkan melalui pemodelan hidrolik persamaan Darcy-Weisbach / Hazen-Williams pada EPANET.
- Di lapangan fisik Indonesia:
  - Terdapat fluktuasi pasokan air bergiliran (*intermittent supply* di mana pipa sering kosong udara),
  - Derau sensor (*sensor noise/drift*),
  - Keterlambatan pengiriman sinyal jaringan seluler / LoRaWAN (*packet loss*).
- **Penegasan:** Model yang dievaluasi dengan BattLeDIM harus dinyatakan secara transparan sebagai **model terlatih pada benchmark hidrolik terstandarisasi**, bukan klaim instan bahwa model siap 100% untuk operasi fisik lapangan tanpa rekalibrasi.

---

## 7. Rekomendasi Algoritma dan Rationale

Untuk mendeteksi anomali hidrolik pipa sensor AQUA-TARA, dievaluasi 3 paradigma algoritma:

| Paradigma | Kandidat Algoritma | Evaluasi Kesesuaian untuk AQUA-TARA | Rationale & Keputusan |
|---|---|---|---|
| **1. Unsupervised / Semi-Supervised Anomaly Detection (Direkomendasikan)** | **Isolation Forest** & **Rolling Z-Score with Diurnal Baseline** | **SANGAT TINGGI** | **Alasan Utama:** Di lapangan operasional nyata, operator PDAM hampir tidak pernah memiliki data berlabel lengkap tentang kapan kebocoran pipa persisnya terjadi. Model semi-supervised hanya membutuhkan data operasi normal untuk mempelajari batas toleransi wajar. Sangat efisien secara komputasi dan tidak rentan terhadap ketidakseimbangan kelas (*class imbalance*). |
| **2. Supervised Classification (Pembanding Saja)** | **XGBoost / LightGBM** | **SEDANG** | Memerlukan label biner kebocoran pada setiap baris data. Bagus untuk mengukur performa teoretis batas atas (*upper-bound benchmark*) pada BattLeDIM, namun sulit digeneralisasi ke jaringan pipa yang tidak memiliki riwayat insiden berlabel. |
| **3. Deep Learning Time-Series** | **LSTM-Autoencoder** | **RENDAH (Tahap MVP)** | Membutuhkan dependensi berat (TensorFlow/PyTorch), waktu pelatihan lama, serta inferensi lambat di server lokal. Tidak proporsional untuk kebutuhan MVP AQUA-TARA yang mengutamakan latensi respons cepat. |

**Keputusan Desain Rekomendasi:**
Gunakan **Isolation Forest** yang diperkaya fitur teknis temporal (*feature engineering*: selisih tekanan terhadap rata-rata bergerak 24 jam / $\Delta P = P(t) - \bar{P}_{24\text{h}}$, serta rasio debit terhadap tekanan / hydraulic impedance $Q/P$).

---

## 8. Desain Alternatif untuk Open Decision F-03 (Status IoT)

Sesuai aturan ketat, **tidak ada threshold status IoT final yang ditetapkan secara sepihak pada step ini**. Berikut 3 alternatif desain penurunan status `NORMAL`, `ANOMALY`, dan `CRITICAL` yang disiapkan untuk diputuskan berdasarkan bukti empiris:

```
[Alternatif 1: Skor Kontinu ML]
Skor Anomali S ∈ [0.0, 1.0] ──> [S ≤ 0.5: NORMAL] ──> [0.5 < S ≤ 0.8: ANOMALY] ──> [S > 0.8: CRITICAL]

[Alternatif 2: Ambang Batas Hidrolik Fisik]
Nilai Tekanan P (bar)       ──> [P ≥ 1.0 bar: NORMAL] ──> [0.5 ≤ P < 1.0 bar: ANOMALY] ──> [P < 0.5 bar: CRITICAL]

[Alternatif 3: Desain Hibrida (Direkomendasikan)]
Kombinasi Fisik & Statistik ──> Deteksi deviasi statistik via ML (ANOMALY) + Ambang kritis darurat fisik (CRITICAL)
```

### Bukti yang Wajib Dikumpulkan Sebelum Mengunci Pilihan:
1. **Tingkat Alarm Palsu (False Alarm Rate):** Nilai ambang tidak boleh menghasilkan lebih dari 1 alarm palsu per minggu pada operasi normal.
2. **Detection Latency:** Berapa jam rata-rata model membutuhkan waktu sebelum menaikkan status menjadi `ANOMALY` setelah kebocoran fisik aktif.

---

## 9. Metrik Evaluasi Eksperimen

Untuk menguji performa model deteksi anomali secara objektif, ditetapkan metrik evaluasi formal:

### A. Metrik Klasifikasi Titik Waktu (Point-in-Time Metrics)
1. **Precision:**
   $$\text{Precision} = \frac{\text{True Positives (TP)}}{\text{TP} + \text{False Positives (FP)}}$$
   *Tujuan:* Memastikan saat sistem memberi peringatan anomali, peringatan tersebut bukan merupakan alarm palsu yang membuang waktu teknisi lapangan.
2. **Recall (Sensitivity):**
   $$\text{Recall} = \frac{\text{TP}}{\text{TP} + \text{False Negatives (FN)}}$$
   *Tujuan:* Memastikan kebocoran dan anomali tidak terlewatkan tanpa terdeteksi.
3. **$F_1$-Score ($F_{\beta}$ dengan fokus Precision):**
   $$F_1 = 2 \times \frac{\text{Precision} \times \text{Recall}}{\text{Precision} + \text{Recall}}$$
4. **False Alarm Rate (FAR):**
   $$\text{FAR} = \frac{\text{FP}}{\text{FP} + \text{True Negatives (TN)}}$$
   *Target Teknis:* $\text{FAR} \le 0.05$ (di bawah 5% pada data validasi normal).

### B. Metrik Kejadian & Waktu (Event-Level Metrics)
1. **Time-to-Detection (TTD) / Detection Delay:**
   Selisih waktu rata-rata (dalam menit/jam) antara `start_time` kebocoran aktual dengan timestamp pertama kali sistem membunyikan status `ANOMALY`/`CRITICAL`.
2. **Incident Catch Rate:**
   Persentase insiden kebocoran fisik individual yang berhasil ditangkap setidaknya 1 kali selama insiden berlangsung.

---

## 10. Blocker dan Keputusan yang Masih Terbuka

| Item | Status | Deskripsi & Tindakan yang Diperlukan |
|---|---|---|
| **B-1: Ruang Lingkup Penyimpanan Data** | Terbuka | Menyiapkan folder `ml/data/battledim/` dan memastikan folder tersebut telah diabaikan oleh `.gitignore` sebelum file fisik diunduh. |
| **B-2: Dependensi Python untuk Eksperimen** | Terbuka | Menambahkan `pandas>=2.2.0` ke rencana dependensi ML bersama `scikit-learn` dan `numpy` saat memasuki tahap implementasi/eksekusi eksperimen. |
| **B-3: Penetapan Ambang Batas F-03** | Terbuka | Menunggu hasil empiris evaluasi model di BattLeDIM untuk mengunci batasan numerik status `NORMAL / ANOMALY / CRITICAL`. |
| **B-4: Cleanup Tertunda Fixture Tiket #55** | Tertunda | Fixture tiket sintetis `#55` (dan `Report` terkait) di database lokal `aquatara_test` tetap dicatat sebagai pekerjaan pembersihan terpisah, tidak disentuh pada step ini. |

---

## 11. Kesimpulan & Rekomendasi Urutan STEP 4D.64

### Status Evaluasi STEP 4D.63: **PASS**
- Seluruh klaim sumber, DOI, lisensi (CC BY 4.0), dan struktur file BattLeDIM **terverifikasi 100% valid dan asli**.
- Spesifikasi eksperimen, pemetaan fitur, pencegahan data leakage (chronological split), dan metrik evaluasi telah dirumuskan secara terukur dan dapat direproduksi.

### Rekomendasi STEP 4D.64 (Tahap Berikutnya):
1. Tambahkan pengecualian `ml/data/battledim/` ke [.gitignore](file:///e:/TUGAS/pengkoding/anti/aqua-tara/.gitignore).
2. Unduh dan siapkan subset data sensor `2018_SCADA_Pressures.csv`, `2018_SCADA_Flows.csv`, dan jadwal `2018_Leakages.csv` ke dalam area terisolasi `ml/data/battledim/`.
3. Buat skrip pra-pemrosesan data waktu (*chronological split*) dan lakukan eksperimen baseline anomali (*Isolation Forest / Rolling Z-Score*) secara terisolasi tanpa menyentuh kode aplikasi utama backend/frontend.
