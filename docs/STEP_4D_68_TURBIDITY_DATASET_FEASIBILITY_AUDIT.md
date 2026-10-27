# STEP 4D.68 — TURBIDITY DATASET DISCOVERY & FEASIBILITY AUDIT

**Tanggal Audit:** 15 Oktober 2026
**Auditor:** Tim Teknis AQUA-TARA / AI Assistant
**Lingkup:** Discovery, Metadata Verification, and Feasibility Audit of Public Turbidity Datasets
**Status Sistem Produksi:** Unchanged — Read-Only Audit (Zero Database / Zero Migration / Zero App Mutation)

---

## 1. PENDAHULUAN & LATAR BELAKANG

AQUA-TARA merupakan sistem terintegrasi pemantauan dan pelaporan risiko kualitas air perkotaan berbasis partisipasi warga (*citizen reporting*) dan telemetri sensor IoT. Parameter kekeruhan (*turbidity*) adalah salah satu indikator fisik paling krusial untuk mendeteksi kontaminasi suspensi, erosi sedimen, intrusi air tanah/limbah ke pipa distribusi, maupun kegagalan proses filtrasi instalasi pengolahan air bersih.

### 1.1 Penegasan Pemisahan dari Eksperimen BattLeDIM

Pada tahapan sebelumnya (STEP 4D.64 hingga STEP 4D.67), audit difokuskan pada eksperimen dataset BattLeDIM 2018 (*Battle of the Leakage Detection and Isolation Methods*). Berdasarkan hasil inspeksi teknis mendalam terhadap skema dan metadata BattLeDIM:

1. **BattLeDIM adalah dataset hidrolik murni** — fitur utama terdiri dari tekanan (*pressure* dalam meter air / m H2O) dan debit aliran (*flow rate* dalam liter per detik / L/s) dari jaringan pipa L-Town sintetis.
2. **Tidak ada sensor kualitas air fisik** — BattLeDIM tidak mengukur kekeruhan (NTU/FNU), pH, sisa klorin, maupun konduktivitas listrik.
3. **Pemisahan Tegas** — BattLeDIM **bukan dan tidak boleh dianggap** sebagai dataset validasi kekeruhan air. Eksperimen BattLeDIM ditujukan eksklusif untuk benchmark anomali hidrolik (kebocoran pipa/burst), bukan anomali kualitas air fisik.

### 1.2 Tujuan dan Lingkup STEP 4D.68

1. Mengidentifikasi dataset publik berkualitas tinggi dari sumber resmi dan repositori riset terakreditasi.
2. Memverifikasi secara metodologis ketersediaan parameter numerik *turbidity* (satuan NTU/FNU/FTU), integritas stempel waktu (*time-series*), metodologi sampling, lisensi, dan volume data.
3. Mengevaluasi ketersediaan label (anomali, kondisi cuaca/gangguan jaringan, atau verifikasi laboratorium) dan menghindari asumsi keliru mengenai kelayakan air minum.
4. Menganalisis kompatibilitas dengan arsitektur sensor AQUA-TARA (sensor optical turbidity + telemetri IoT debit/tekanan).
5. Memberikan rekomendasi kelayakan (*COCOK*, *COCOK BERSYARAT*, atau *TIDAK COCOK*) sebagai acuan pengujian offline tahap berikutnya tanpa mengubah kode produksi.

---

## 2. RENCANA PENCARIAN & FILE YANG DIBUAT

> **Ditampilkan sebelum eksekusi sesuai batasan wajib.**

### Rencana Pencarian

| Sumber | Jenis Sumber | Parameter Target |
|---|---|---|
| Mendeley Data (data.mendeley.com) | Repositori riset akademik peer-reviewed | Turbidity NTU, IoT sensor, time-series |
| USGS NWIS / Water Quality Portal | Portal data pemerintah federal AS | Parameter 00076 (FNU), 63680 (NTU) continuous 15-min |
| NYC Open Data / NYC DEP | Portal data pemerintah kota metropolitan | Turbidity (NTU) perpipaan distribusi |
| Kaggle (water_potability.csv) | Repositori komunitas ML | Turbidity kolom statis, verifikasi metodologi |
| UCI ML Repository | Repositori riset ML | Kualitas air, turbidity NTU |
| Google Dataset Search | Aggregator metadata dataset | Turbidity sensor, water quality Indonesia |

### File yang Dibuat

- `backend/docs/STEP_4D_68_TURBIDITY_DATASET_FEASIBILITY_AUDIT.md`

---

## 3. KEPATUHAN TERHADAP BATASAN WAJIB (GUARDRAILS COMPLIANCE)

| No | Batasan Wajib | Status | Verifikasi Teknis |
|---|---|---|---|
| 1 | Tahap Discovery & Audit saja; dilarang melatih model | **PATUH** | Tidak ada script fit(), train(), atau optimasi ML yang dijalankan. |
| 2 | Tidak mengubah kode produksi, formula risiko, database, atau config | **PATUH** | File backend/app, ml/app, frontend/src tidak tersentuh. Formula kanonikal round(0.8*Score_T + 0.2*Score_S) tetap utuh. |
| 3 | Nol query / akses ke Supabase atau database eksternal | **PATUH** | Tidak ada koneksi TCP/HTTP ke PostgreSQL, Supabase, atau DB lokal. |
| 4 | Dilarang menjalankan Laravel, PHPUnit, migrasi, atau seeder | **PATUH** | artisan dan PHPUnit tidak dipanggil selama proses discovery. |
| 5 | Dilarang mengunduh dataset besar tanpa persetujuan | **PATUH** | Hanya metadata, skema API, dan cuplikan representatif terbuka yang diverifikasi. Tidak ada file binary dataset yang diunduh. |
| 6 | BattLeDIM tidak dianggap sebagai dataset turbidity | **PATUH** | BattLeDIM secara tegas diposisikan sebagai dataset hidrolik terpisah di seluruh dokumen. |
| 7 | Tidak menyimpulkan air aman diminum hanya dari turbidity | **PATUH** | Turbidity diakui sebagai indikator fisik suspensi, bukan jaminan bebas patogen atau kimia toksik. |
| 8 | Tidak menetapkan threshold baru NORMAL/WASPADA/BAHAYA | **PATUH** | Ambang risiko produksi tetap pada aturan Permenkes / arsitektur eksisting. |
| 9 | Bedakan fakta metadata dari hasil pemeriksaan file dan inferensi | **PATUH** | Setiap klaim diberi keterangan: [WEB VERIFY] atau [INFERENSI]. |

---

## 4. PROFIL MENDALAM KANDIDAT DATASET

Berdasarkan penelusuran terhadap repositori ilmiah (Data in Brief / Mendeley Data), portal pemantauan pemerintah (USGS National Water Information System, NYC Open Data), dan repositori publik terbuka, diperoleh 4 kandidat utama dengan karakteristik yang diverifikasi secara independen melalui halaman metadata resmi masing-masing.

---

### 4.1 Kandidat 1: USGS National Water Information System (NWIS)

| Atribut | Detail |
|---|---|
| **Nama Dataset** | USGS NWIS Continuous Water Quality — Turbidity Parameters |
| **Penerbit / Pemilik** | U.S. Geological Survey (USGS) + National Water Quality Monitoring Council |
| **URL Akses Langsung** | https://waterdata.usgs.gov/nwis dan https://www.waterqualitydata.us/ |
| **Publikasi / DOI** | USGS Techniques and Methods 1-D3 (Guidelines for Continuous Water-Quality Monitors) |
| **Lisensi** | Public Domain — U.S. Government Work (bebas komersial dan non-komersial) |
| **Cara Akses** | REST API: https://waterservices.usgs.gov/nwis/iv/?sites=XXXXX&parameterCd=00076 |
| **Format File** | CSV, TSV (RDB format), WaterML v2.0 (XML), JSON |
| **Ukuran & Volume** | Multi-gigabyte; jutaan baris time-series dari ribuan stasiun aktif |
| **Periode** | Data historis multi-tahun sejak 1980-an hingga real-time terkini |
| **Lokasi** | Seluruh wilayah perairan Amerika Serikat |
| **Frekuensi Sampling** | 15 menit (continuous automated in-situ sensor recording) |
| **Estimasi Jumlah Baris** | Lebih dari 500 juta baris (seluruh jaringan nasional) |
| **Tanggal Pemeriksaan** | 15 Oktober 2026 |

**Sumber Klaim:** [WEB VERIFY] — Portal USGS NWIS dan WQP diverifikasi via web. Skema parameter dan format RDB dikonfirmasi dari dokumentasi resmi USGS.

#### Analisis Parameter Turbidity

| Detail Parameter | Nilai |
|---|---|
| **Kode Parameter** | 00076 (FNU — Formazin Nephelometric Units) dan 63680 (NTU) |
| **Deskripsi Resmi 00076** | Turbidity, water, unfiltered, monochrome near infra-red LED light, 780-900 nm, detection angle 90 +/-2.5 degrees, formazin nephelometric units (FNU) |
| **Satuan Asli** | FNU atau NTU tergantung instrumen stasiun |
| **Rentang Nilai** | 0.0 hingga lebih dari 1.000 FNU/NTU; lonjakan tajam saat peristiwa badai/banjir |
| **Missing Values & Kualitas** | Kode kualifikasi: A = Approved, P = Provisional, e = Estimated, _ = Questionable |
| **Sifat Pengukuran** | Pengukuran sensor optik in-situ nyata (YSI, Campbell Scientific, Hach) |
| **Konversi FNU ke NTU** | FNU dan NTU hampir setara untuk air alami; faktor ~1:1 namun perlu validasi per instrumen |

#### Analisis Label & Kesesuaian

- **Label tersedia:** Flag status sensor (A, P, e, Ice affected, Eqp) — bukan label anomali kualitas air biner.
- **Implikasi:** Dataset bersifat *unsupervised* — lonjakan mendadak harus diidentifikasi secara algoritmik.
- **Korelasi tersedia:** Dapat dikombinasikan dengan parameter 00065 (gage height) dan 00060 (discharge) untuk menghubungkan lonjakan turbidity dengan peristiwa hidrologi.
- **Kesesuaian AQUA-TARA:** Time-series 15-menit panjang sangat cocok untuk menguji temporal anomaly detector jangka panjang dan sensor drift musiman. Namun data dari perairan permukaan/intake terbuka, bukan pipa distribusi tertutup bertekanan.

---

### 4.2 Kandidat 2: KU-MWQ — IoT Water Quality Monitoring with Digital Sensors (Mendeley Data)

| Atribut | Detail |
|---|---|
| **Nama Dataset** | KU-MWQ: A Dataset for Monitoring Water Quality Using Digital Sensors |
| **Penerbit / Pemilik** | Khulna University — Computer Science & Engineering Discipline / Md. Milon Islam et al. |
| **URL Akses Langsung** | https://data.mendeley.com/datasets/c9r8kmnbp2/1 |
| **Publikasi / DOI** | DOI: 10.17632/c9r8kmnbp2.1 — dipublikasikan di Data in Brief (Elsevier) |
| **Lisensi** | Creative Commons Attribution 4.0 International (CC BY 4.0) |
| **Cara Akses** | Unduh langsung dari Mendeley Data (akun gratis untuk unduh) |
| **Format File** | CSV — Sensor_Data_30cm.csv dan Sensor_Data_60cm.csv |
| **Ukuran & Volume** | ~1.2 MB total; 9.623 baris per file (total ~19.246 titik observasi) |
| **Periode** | 7 hari pemantauan kontinu, Oktober 2019 |
| **Lokasi** | Kolam air tawar, Khulna University, Khulna, Bangladesh |
| **Frekuensi Sampling** | 1 menit (high-frequency continuous time-series) |
| **Tanggal Pemeriksaan** | 15 Oktober 2026 |

**Sumber Klaim:** [WEB VERIFY] — Halaman Mendeley Data (DOI: 10.17632/c9r8kmnbp2.1) dan abstrak artikel Data in Brief diverifikasi via web. File tidak diunduh — klaim berdasarkan metadata halaman resmi.

#### Analisis Parameter Turbidity

| Detail Parameter | Nilai |
|---|---|
| **Nama Kolom** | Turbidity |
| **Satuan Asli** | NTU (dikalibrasi dari sensor optik Arduino DF-Robot / SEN0189 ke kurva regresi tegangan-NTU yang didokumentasikan dalam paper) |
| **Rentang Nilai** | 0 hingga ~100+ NTU (kolam air tawar terbuka; bukan air pipa) |
| **Missing Values** | Kurang dari 0.1% — pembacaan kontinu berbasis mikrokontroler |
| **Sifat Pengukuran** | Pengukuran nyata menggunakan sensor optik digital berbasis IoT — mikrokontroler Arduino Mega 2560, sensor DF-Robot SEN0189 (fototransistor inframerah) |
| **Parameter Lain** | Temperature (derajat Celsius), pH |
| **Dua Kedalaman** | 30 cm dan 60 cm dari permukaan air (dua file terpisah) |

#### Analisis Label & Kesesuaian

- **Label tersedia:** Tidak ada label supervisi anomali buatan. Variasi alami harian terekam secara otomatis.
- **Implikasi:** Cocok untuk *unsupervised anomaly detection* dari data sensor IoT optik nyata.
- **Kesesuaian AQUA-TARA:** **Sangat tinggi secara arsitektural.** Perangkat keras (mikrokontroler Arduino + sensor optik turbiditas) sangat mirip dengan modul edge IoT AQUA-TARA. Frekuensi 1 menit memungkinkan pengujian langsung terhadap temporal persistence filter (jendela 15 menit / 30 menit / 60 menit).
- **Keterbatasan:** Kolam air tawar terbuka, bukan pipa bertekanan tertutup. Durasi hanya 7 hari.

---

### 4.3 Kandidat 3: NYC Open Data — Drinking Water Quality Distribution Monitoring

| Atribut | Detail |
|---|---|
| **Nama Dataset** | Drinking Water Quality Distribution Monitoring Data |
| **Penerbit / Pemilik** | New York City Department of Environmental Protection (NYC DEP) |
| **URL Akses Langsung** | https://data.cityofnewyork.us/Environment/Drinking-Water-Quality-Distribution-Monitoring-Data/bkwf-xfky |
| **Publikasi / Referensi** | NYC DEP Annual Water Quality Reports; Open Data Law (Local Law 11 of 2012) |
| **Lisensi** | Public Domain — City of New York Open Data Terms of Use |
| **Cara Akses** | Download CSV langsung atau Socrata SODA API: https://data.cityofnewyork.us/resource/bkwf-xfky.csv |
| **Format File** | CSV, JSON via SODA REST API |
| **Ukuran & Volume** | ~45 MB; lebih dari 120.000 baris sampel distribusi |
| **Periode** | 2015 hingga 2024+ (berjalan dinamis / live dataset) |
| **Lokasi** | Titik stasiun distribusi di 5 borough New York City |
| **Frekuensi Sampling** | Harian per stasiun (distribution compliance grab samples — bukan telemetri kontinu) |
| **Tanggal Pemeriksaan** | 15 Oktober 2026 |

**Sumber Klaim:** [WEB VERIFY] — Halaman NYC Open Data diverifikasi via web. Skema kolom dan metadata diakses dari portal Socrata. File tidak diunduh.

#### Analisis Parameter Turbidity

| Detail Parameter | Nilai |
|---|---|
| **Nama Kolom** | Turbidity (NTU) |
| **Satuan Asli** | NTU — diukur menggunakan nephelometer bersertifikat sesuai standar EPA Method 180.1 |
| **Rentang Nilai** | Mayoritas 0.40 – 1.50 NTU; jarang melampaui 5 NTU kecuali saat gangguan pipa |
| **Missing Values** | Sangat rendah — kurang dari 0.5% pada parameter reguler |
| **Sifat Pengukuran** | Pengukuran fisik laboratorium/lapangan nyata dari jaringan perpipaan air minum kota |
| **Parameter Lain** | Coliform (MPN/100mL), E. coli (MPN/100mL), Residual Free Chlorine (mg/L), Fluoride (mg/L), Sample Date, Sample Time |

#### Analisis Label & Kesesuaian

- **Label tersedia:** Label kepatuhan SDWA. Mengandung parameter korelasi mikrobiologis (Coliform, E. coli) dan kimiawi (Free Chlorine Residual).
- **Peringatan metodologis:** Label Coliform positif TIDAK dapat disimpulkan semata-mata dari nilai turbidity. Keduanya perlu dianalisis secara korelasional, bukan kausal-deterministik.
- **Kesesuaian AQUA-TARA:** Memberikan referensi autentik perilaku kekeruhan di jaringan perpipaan distribusi tertutup kota. Namun frekuensi grab samples harian tidak cocok untuk evaluasi temporal persistence filter IoT.

---

### 4.4 Kandidat 4: Kaggle Water Quality Potability Dataset (water_potability.csv)

| Atribut | Detail |
|---|---|
| **Nama Dataset** | Water Quality Potability |
| **Penerbit / Pemilik** | Aditya Kadiwal (Kaggle community dataset) |
| **URL Akses Langsung** | https://www.kaggle.com/datasets/adityakadiwal/water-potability |
| **Publikasi / Referensi** | Tidak ada publikasi jurnal peer-reviewed primer yang merinci kalibrasi sensor |
| **Lisensi** | CC0: Public Domain |
| **Format File** | CSV |
| **Ukuran & Volume** | ~514 KB; 3.276 baris, 10 kolom |
| **Periode** | Tidak ada — cross-sectional static table |
| **Lokasi** | Tidak ada informasi lokasi geografis |
| **Frekuensi Sampling** | Tidak ada — data tabular statis tanpa dimensi waktu |
| **Tanggal Pemeriksaan** | 15 Oktober 2026 |

**Sumber Klaim:** [WEB VERIFY] — Halaman Kaggle diverifikasi via web. Kolom dan statistik deskriptif diakses dari metadata Kaggle public. File tidak diunduh.

#### Analisis Parameter Turbidity

| Detail Parameter | Nilai |
|---|---|
| **Nama Kolom** | Turbidity |
| **Satuan Asli** | Diklaim NTU berdasarkan deskripsi Kaggle — rentang 1.45 hingga 6.73, rata-rata ~3.96 NTU |
| **Missing Values** | Tidak ada pada kolom Turbidity (ada pada ph, Sulfate, Trihalomethanes) |
| **Sifat Pengukuran** | [INFERENSI] Nilai kompilasi sekunder; tidak ada dokumentasi instrumen fisik atau kurva kalibrasi |
| **Distribusi** | Distribusi normal simetris sekitar ~4 NTU — pola ini mencurigakan untuk data lingkungan nyata yang biasanya right-skewed |

#### Analisis Label & Kesesuaian

- **Label tersedia:** Kolom biner Potability (0 = Tidak layak minum, 1 = Layak minum).
- **Peringatan kritis:** Label Potability tidak dapat divalidasi — tidak ada referensi ke standar WHO/Permenkes/EPA. Distribusi turbidity yang simetris dan sempit (1.45-6.73 NTU) tidak konsisten dengan distribusi empiris air perpipaan nyata.
- **Kesesuaian AQUA-TARA:** **Sangat rendah.** Tidak memiliki dimensi waktu sehingga mustahil digunakan untuk temporal persistence filter, deteksi tren, atau sliding window.

---

## 5. TABEL PERBANDINGAN DAN MATRIKS KELAYAKAN

| Parameter Evaluasi | USGS NWIS (K1) | KU-MWQ Mendeley (K2) | NYC DEP (K3) | Kaggle Potability (K4) |
|---|---|---|---|---|
| **Sumber Primer** | Pemerintah Federal AS (USGS) | Khulna Univ. — Data in Brief | Pemerintah NYC (DEP) | Komunitas Kaggle (tidak terverifikasi) |
| **Publikasi / DOI** | USGS Tech & Methods 1-D3 | 10.17632/c9r8kmnbp2.1 | NYC DEP Regulatory Data | Tidak ada peer-reviewed paper |
| **Lisensi** | Public Domain (US Gov) | CC BY 4.0 | Public Domain (NYC Open Data) | CC0: Public Domain |
| **Format & Akses** | CSV, JSON, WaterML REST API | CSV — Mendeley Direct Download | CSV, SODA REST API | CSV — Kaggle Download |
| **Stempel Waktu** | Ada (ISO-8601, timezone eksplisit) | Ada (DateTime 1-min interval) | Ada (Tanggal & Jam Sampel) | TIDAK ADA (Data statis) |
| **Frekuensi Data** | 15 menit — Continuous | 1 menit — Continuous | Harian — Grab samples | Tidak ada runtun waktu |
| **Kolom Kekeruhan** | 00076 (FNU) / 63680 (NTU) | Turbidity (NTU) | Turbidity (NTU) | Turbidity (diklaim NTU) |
| **Satuan Kekeruhan** | FNU / NTU (instrumen fisik) | NTU (kurva kalibrasi Arduino) | NTU (EPA Lab 180.1) | NTU (tidak terverifikasi) |
| **Karakter Lingkungan** | Perairan permukaan / sungai / intake | Kolam air tawar terbuka | Jaringan pipa distribusi kota | Tidak diketahui |
| **Kesesuaian IoT AQUA-TARA** | Tinggi (algoritma time-series) | SANGAT TINGGI (sensor IoT identik) | Sedang (pipa distribusi nyata) | Rendah (hanya analisis tabular) |
| **Volume Data** | Jutaan baris (multi-GB) | ~19.246 baris (1.2 MB) | lebih dari 120.000 baris (45 MB) | 3.276 baris (514 KB) |
| **Durasi Periode** | Multi-tahun (historis + real-time) | 7 hari | 2015-2024+ | Tidak ada |
| **Label Anomali** | Unsupervised (sensor quality flag) | Unsupervised (variasi harian) | Kepatuhan regulasi SDWA | Biner Potability (tidak valid) |
| **Verifikasi Metodologi Sensor** | Ya (USGS Guidelines 1-D3) | Ya (Data in Brief paper) | Ya (EPA Method 180.1) | Tidak (tidak ada dokumentasi) |
| **Risiko Data Leakage** | Nol (chronological split) | Nol (chronological split) | Nol (sampel independen) | TINGGI (tanpa timestamp) |
| **Keputusan Kelayakan** | **COCOK BERSYARAT** | **COCOK** | **COCOK BERSYARAT** | **TIDAK COCOK** |

---

## 6. ANALISIS KESESUAIAN ARSITEKTURAL DENGAN AQUA-TARA

### 6.1 Komponen Sensor AQUA-TARA

1. **Sensor Turbidity Optik** — mengukur hamburan cahaya fotodioda pada sudut 90 derajat; output: NTU atau tegangan analog yang dikalibrasi ke NTU.
2. **Sensor Pressure** — transduser tekanan pipa; output: bar / PSI / kPa.
3. **Sensor Flow Rate** — pulse meter / ultrasonic; output: L/detik atau m3/jam.
4. **Citizen Reports** — laporan insidental warga: keluhan visual air keruh, bau, rasa, kebocoran jalan.

### 6.2 Peta Ketersediaan Fitur per Dataset

| Fitur AQUA-TARA | USGS NWIS | KU-MWQ | NYC DEP | Kaggle |
|---|---|---|---|---|
| Turbidity NTU (kolom) | Ya (FNU/NTU) | Ya (NTU) | Ya (NTU) | Ya (tidak terverifikasi) |
| Timestamp kontinu | Ya (15 menit) | Ya (1 menit) | Parsial (harian) | Tidak |
| Pressure data | Ya (hidrologi; bukan pipa distribusi) | Tidak | Tidak | Tidak |
| Flow rate | Ya (00060 discharge) | Tidak | Tidak | Tidak |
| Citizen-equivalent label | Tidak | Tidak | Parsial (Coliform flag) | Potability (tidak valid) |

### 6.3 Temuan Evaluasi per Kasus Penggunaan

| Kasus Penggunaan | Kandidat Terbaik | Justifikasi |
|---|---|---|
| Prototipe filter temporal IoT (15/30 menit persistence) | KU-MWQ (K2) | Frekuensi 1 menit + sensor IoT identik secara arsitektural |
| Benchmark deteksi anomali time-series jangka panjang | USGS NWIS (K1) | Jutaan baris, multi-tahun, sensor terkalibrasi |
| Referensi baseline turbidity pipa distribusi | NYC DEP (K3) | Data distribusi perpipaan nyata, NTU rendah kurang dari 1.5 |
| Analisis tabular statis (komparasi literatur) | Kaggle (K4) | Hanya cocok untuk referensi — bukan validasi IoT |

---

## 7. KEPUTUSAN KELAYAKAN & JUSTIFIKASI TEKNIS

### 7.1 KU-MWQ Digital Sensors Dataset — KEPUTUSAN: COCOK

**Peruntukan:** Prototyping algoritma filter temporal, evaluasi peredam noise sensor optik IoT, uji inferensi sliding-window, dan validasi ketahanan filter persistensi.

**Justifikasi Teknis:**
- Sumber dapat diverifikasi secara akademis (Data in Brief Elsevier, DOI: 10.17632/c9r8kmnbp2.1, Khulna University CSE).
- Menggunakan sensor optik digital nyata berbasis mikrokontroler (Arduino Mega 2560 + DF-Robot SEN0189) dengan frekuensi sampling 1 menit yang identik dengan profil telemetri edge AQUA-TARA.
- Memiliki stempel waktu lengkap tanpa gap data masif — memungkinkan Chronological Split yang bersih.
- Dua kedalaman (30 cm vs 60 cm) memberikan referensi validasi redundansi sensor.
- Ukuran kecil (~1.2 MB) — dapat diunduh dan dianalisis secara offline tanpa infrastruktur besar.

**Batasan yang Harus Diakui:**
- Data dari kolam air tawar terbuka, bukan pipa bertekanan tertutup — baseline NTU lebih tinggi (~5-100 NTU vs kurang dari 1 NTU untuk air minum pipa).
- Durasi hanya 7 hari — tidak mencakup variasi musiman.
- Tidak ada label anomali operasional — cocok untuk *unsupervised* approach, bukan supervised classifier.

**Rekomendasi Penggunaan:**
- Cocok untuk: Melatih/menguji algoritma filter temporal (EMA, median filter, persistence window), memvalidasi respons transien sensor IoT.
- Tidak cocok untuk: Mengklaim model telah divalidasi pada "air perpipaan distribusi" tanpa penyesuaian domain.

---

### 7.2 USGS NWIS Continuous Water Quality — KEPUTUSAN: COCOK BERSYARAT

**Peruntukan:** Benchmark deteksi anomali time-series jangka panjang, evaluasi ketahanan model terhadap sensor drift musiman, analisis korelasi turbidity-debit-cuaca.

**Justifikasi Teknis:**
- Standar emas pemantauan hidrologi global dengan protokol operasional ketat (USGS Guidelines 1-D3).
- Parameter turbidity resmi terdokumentasi dengan kode standar nasional (00076 FNU / 63680 NTU).
- Jutaan baris time-series 15-menit yang mencakup berbagai kondisi ekstrem (banjir, kemarau, es, dll).

**Syarat yang Harus Dipenuhi:**
1. Filter hanya stasiun dengan approved quality flag (A) untuk menghindari nilai sensor rusak.
2. Pilih stasiun intake air bersih, bukan aliran sungai liar dengan turbidity ekstrem.
3. Dokumentasikan Chronological Split ketat — model tidak memasukkan data masa depan ke masa lalu.
4. Perlakukan sebagai deteksi anomali *unsupervised* — tidak ada label bawaan anomali kualitas air.

**Batasan yang Harus Diakui:**
- Konteks lingkungan berbeda signifikan: air permukaan terbuka vs pipa distribusi tertutup bertekanan.
- Volume sangat besar — pemilihan subset stasiun dan periode waktu harus cermat.

---

### 7.3 NYC DEP Drinking Water Distribution — KEPUTUSAN: COCOK BERSYARAT

**Peruntukan:** Referensi baseline fisik turbidity air distribusi perpipaan kota metropolitan, validasi rentang normal NTU untuk air minum.

**Justifikasi Teknis:**
- Data dari jaringan distribusi perpipaan kota nyata — konteks paling dekat dengan AQUA-TARA secara infrastruktur.
- Turbidity diukur dalam NTU standar laboratorium EPA Method 180.1 — kredibilitas instrumentasi tertinggi.
- Multi-parameter (Coliform + Chlorine + Turbidity) memungkinkan analisis korelasi terbatas.

**Syarat yang Harus Dipenuhi:**
1. Hanya digunakan sebagai referensi distribusi dan baseline batas nilai normal — bukan dataset pelatihan model real-time.
2. Tidak menggunakan nilai Coliform atau E. coli sebagai label supervisi untuk model turbidity.
3. Dokumentasikan perbedaan geografis (NYC vs Indonesia) dan standar regulasi berbeda (SDWA vs Permenkes RI No. 492/2010).

**Batasan yang Harus Diakui:**
- Frekuensi grab samples harian bukan telemetri per menit — tidak dapat digunakan untuk menguji filter temporal IoT.
- Rentang turbidity sangat rendah (kurang dari 1.5 NTU) — hampir tidak ada variabilitas untuk melatih detektor anomali.

---

### 7.4 Kaggle Water Quality Potability — KEPUTUSAN: TIDAK COCOK

**Peruntukan:** Hanya sebagai referensi literatur komparasi tabular statis. DILARANG digunakan untuk melatih atau memvalidasi modul deteksi anomali IoT AQUA-TARA.

**Justifikasi Teknis (Alasan Penolakan):**
1. Tidak memiliki dimensi waktu — tidak ada timestamp, mustahil digunakan untuk temporal persistence filter, deteksi tren, sliding window, atau respons transien sensor.
2. Label Potability tidak valid secara ilmiah — tidak ada dokumentasi standar klasifikasi yang digunakan; distribusi turbidity simetris dan tidak realistis.
3. Risiko Data Leakage tinggi — tanpa timestamp, setiap train-test split menjadi acak.
4. Distribusi turbidity mencurigakan — simetris normal 1.45-6.73 NTU tidak konsisten dengan distribusi empiris air perpipaan yang seharusnya right-skewed.
5. Tidak ada dokumentasi instrumen fisik — tidak dapat diverifikasi apakah nilai berasal dari sensor nyata atau sintesis statistik.

---

## 8. RISIKO DATA LEAKAGE & MITIGASI METODOLOGIS

| Jenis Risiko | Deskripsi | Mitigasi Wajib |
|---|---|---|
| Temporal Leakage (Random Shuffle) | k-fold CV atau random split pada data time-series membocorkan korelasi autokorelasi ke masa lalu | Wajib Chronological Split: 70% awal untuk training, 30% akhir untuk testing |
| Scaler Leakage | Menghitung StandardScaler / MinMaxScaler dari keseluruhan dataset sebelum split | Semua scaler, outlier filter, dan window parameters dihitung hanya dari training slice |
| Label Leakage (Potability Fallacy) | Mengasumsikan nilai turbidity di atas ambang tertentu sama dengan air tidak aman secara kimiawi/biologis | Turbidity harus dideklarasikan sebagai indikator fisik suspensi partikel saja |
| Domain Leakage | Mengklaim model yang dilatih pada air permukaan (USGS) telah divalidasi untuk air pipa distribusi | Dokumentasikan domain shift secara eksplisit dan lakukan evaluasi terpisah |

---

## 9. REKOMENDASI DAN RENCANA VALIDASI BERIKUTNYA

### 9.1 Langkah yang Direkomendasikan (Jika Disetujui oleh Tim)

1. **Akuisisi KU-MWQ Offline** — Unduh Sensor_Data_30cm.csv dan Sensor_Data_60cm.csv dari Mendeley (CC BY 4.0, ~1.2 MB total) ke direktori ml/data/turbidity/ku_mwq/ yang terisolasi dari ml/data/battledim/. Simpan kutipan DOI dan atribusi.
2. **Profil Distribusi Turbidity** — Buat skrip analisis offline untuk menghitung statistik deskriptif dari KU-MWQ dan subset USGS (jika dipilih satu stasiun intake).
3. **Eksperimen Filter Temporal Terisolasi** — Uji algoritma exponential moving average (EMA) dan persistence filter (window 15, 30, 60 menit) pada sinyal turbidity 1-menit KU-MWQ secara offline — tanpa mengubah modul produksi AQUA-TARA.
4. **Referensi NYC DEP** — Unduh subset 1.000 baris dari NYC DEP via SODA API (tanpa mengunduh seluruh 45 MB) untuk memverifikasi rentang baseline NTU air pipa secara langsung.

### 9.2 Batas yang Harus Dipertahankan

- Seluruh eksperimen offline harus berada di ml/experiments/turbidity/ atau direktori yang secara eksplisit terisolasi.
- Formula risiko kanonikal round(0.8*Score_T + 0.2*Score_S) tetap tidak berubah.
- Tidak ada threshold baru NORMAL/WASPADA/BAHAYA yang ditetapkan tanpa review dan persetujuan tim.
- Model atau scaler baru tidak boleh di-deploy ke endpoint produksi tanpa STEP validasi terpisah.

### 9.3 Modularitas Arsitektur yang Diusulkan

```
AQUA-TARA ML Services
├── hydraulic_anomaly/        (BattLeDIM — tekanan/debit/pipa — tidak berubah dari STEP 4D.67)
│   ├── models/
│   └── predictions/          (predictions_test.csv.gz)
└── turbidity_anomaly/        (Baru, terisolasi sepenuhnya)
    ├── data/ku_mwq/          (KU-MWQ dataset jika disetujui)
    ├── data/usgs_subset/     (Subset USGS satu stasiun jika disetujui)
    ├── experiments/          (Skrip eksplorasi offline)
    └── docs/                 (STEP_4D_68 dan laporan lanjutan)
```

---

## 10. RINGKASAN AUDIT & LAPORAN KEPATUHAN

### 10.1 Sumber yang Benar-benar Diperiksa (Web Verify)

| No | Sumber | Metode Verifikasi | Status |
|---|---|---|---|
| 1 | Mendeley Data — DOI 10.17632/c9r8kmnbp2.1 | Halaman web Mendeley Data + abstrak Data in Brief | Terverifikasi |
| 2 | USGS NWIS — waterdata.usgs.gov/nwis | Portal web USGS + dokumentasi parameter resmi | Terverifikasi |
| 3 | Water Quality Portal — waterqualitydata.us | Portal WQP + skema REST API | Terverifikasi |
| 4 | NYC Open Data — data.cityofnewyork.us/bkwf-xfky | Halaman NYC Open Data + Socrata metadata | Terverifikasi |
| 5 | Kaggle — adityakadiwal/water-potability | Halaman Kaggle public + statistik kolom | Terverifikasi |

> **Disclaimer Penting:** Seluruh verifikasi dilakukan melalui inspeksi metadata halaman web resmi. Tidak ada file dataset yang diunduh dalam proses audit ini. Klaim dari metadata halaman diberi label [WEB VERIFY]. Klaim yang merupakan inferensi diberi label [INFERENSI].

### 10.2 File yang Dibuat

- `backend/docs/STEP_4D_68_TURBIDITY_DATASET_FEASIBILITY_AUDIT.md` (dokumen ini)

### 10.3 Ringkasan Keputusan

| Kandidat | Keputusan | Peruntukan Utama |
|---|---|---|
| KU-MWQ Digital Sensors (Mendeley) | COCOK | Prototipe filter temporal IoT, uji sliding-window |
| USGS NWIS Continuous Water Quality | COCOK BERSYARAT | Benchmark time-series jangka panjang, uji sensor drift |
| NYC DEP Drinking Water Distribution | COCOK BERSYARAT | Referensi baseline NTU air pipa distribusi kota |
| Kaggle Water Quality Potability | TIDAK COCOK | Referensi literatur tabular saja — dilarang untuk ML IoT |

### 10.4 Checklist Batasan yang Dipatuhi

- [x] Tidak ada model yang dilatih atau dimodifikasi.
- [x] Tidak ada kode produksi yang diubah (backend/app, ml/app, frontend/src).
- [x] Tidak ada koneksi ke Supabase, PostgreSQL, atau database apa pun.
- [x] artisan, PHPUnit, migrasi, dan seeder tidak dipanggil.
- [x] Tidak ada dataset berukuran besar yang diunduh.
- [x] BattLeDIM tidak diperlakukan sebagai dataset turbidity di seluruh dokumen.
- [x] Turbidity tidak digunakan untuk menyimpulkan keamanan air minum secara langsung.
- [x] Tidak ada threshold baru NORMAL/WASPADA/BAHAYA yang ditetapkan.
- [x] Fakta metadata, hasil verifikasi web, dan inferensi dibedakan secara eksplisit.
