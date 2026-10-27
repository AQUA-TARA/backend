# STEP 4D.69 - KU-MWQ DATASET ACQUISITION & INTEGRITY AUDIT

**Tanggal Audit:** 15 Oktober 2026
**Auditor:** Tim Teknis AQUA-TARA / AI Assistant
**Lingkup:** Akuisisi, verifikasi integritas, dan profiling dataset KU-MWQ
**Status Sistem Produksi:** Unchanged - Read-Only Audit (Zero Database / Zero Migration / Zero App Mutation)

---

## RINGKASAN EKSEKUTIF

Dataset KU-MWQ **telah ditemukan sudah tersimpan** di `ml/data/turbidity/ku_mwq/` dalam format `.xlsx` (Excel), bukan `.csv` seperti yang diklaim STEP 4D.68. Temuan utama:
1. DOI yang dicatat di STEP 4D.68 berbeda dari DOI aktual dataset
2. Format file adalah Excel `.xlsx`, bukan CSV
3. Tanggal pengumpulan data adalah Januari 2020, bukan Oktober 2019
4. Rentang turbidity jauh lebih tinggi (134-249 NTU vs 0 sampai 100+ NTU yang diklaim)
5. Turbidity disimpan sebagai integer (bukan float)
6. 100% dari 19.246 titik data berada di zona BAHAYA menurut threshold AQUA-TARA

Semua temuan didokumentasikan dengan label **[FILE CHECK]**, **[WEB VERIFY]**, atau **[INFERENSI]**.

---

## 1. RENCANA & SUMBER RESMI YANG DIGUNAKAN

> Ditampilkan sebelum eksekusi sesuai batasan wajib.

### 1.1 Rencana Audit

| Tahap | Aksi | Guardrail |
|---|---|---|
| 1 | Verifikasi sumber Mendeley Data via web (DOI, versi, lisensi, file) | Baca-saja |
| 2 | Periksa direktori ml/data/turbidity/ku_mwq/ untuk konflik nama | Baca-saja |
| 3 | Hitung SHA-256 dan ukuran file | Baca-saja |
| 4 | Profil struktur: encoding, sheet, kolom, baris, tipe data | Baca-saja |
| 5 | Profil kualitas: null, duplikat, gap waktu, nilai ekstrem | Baca-saja |
| 6 | Statistik deskriptif turbidity dan perbandingan antar file | Baca-saja |
| 7 | Dokumentasikan temuan dan perbedaan dari klaim STEP 4D.68 | Tulis laporan |

### 1.2 URL Sumber Resmi

- **URL klaim STEP 4D.68:** https://data.mendeley.com/datasets/c9r8kmnbp2/1
- **URL aktual terverifikasi:** https://data.mendeley.com/datasets/34rczh25kc/4

> **[WEB VERIFY]** DOI yang benar adalah `10.17632/34rczh25kc`, versi terbaru adalah 4.
> DOI `10.17632/c9r8kmnbp2.1` yang dikutip STEP 4D.68 tidak dapat diverifikasi sebagai DOI aktif.

---

## 2. VERIFIKASI SUMBER RESMI MENDELEY DATA

### 2.1 Perbandingan Klaim STEP 4D.68 vs Sumber Terverifikasi

| Atribut | Klaim STEP 4D.68 | Sumber Terverifikasi |
|---|---|---|
| **DOI** | `10.17632/c9r8kmnbp2.1` | `10.17632/34rczh25kc` [WEB VERIFY] |
| **URL Mendeley** | `.../c9r8kmnbp2/1` | `.../34rczh25kc/4` [WEB VERIFY] |
| **Versi** | 1 | 4 (terbaru) [WEB VERIFY] |
| **Judul** | KU-MWQ: A Dataset for Monitoring Water Quality Using Digital Sensors | Sama [WEB VERIFY] |
| **Penerbit** | Khulna University CSE / Md. Milon Islam et al. | Khulna University (Khan Jahan Ali Hall) [WEB VERIFY] |
| **Lisensi** | CC BY 4.0 | CC BY 4.0 [WEB VERIFY] TERKONFIRMASI |
| **Format File** | CSV (.csv) | Excel XLSX (.xlsx) [FILE CHECK] |
| **Periode Data** | Oktober 2019 | 15-22 Januari 2020 [FILE CHECK] |
| **Baris per file** | ~9.623 | 9.623 TERKONFIRMASI [FILE CHECK] |

> **PERBEDAAN KRITIS:** DOI, URL, versi, format file, dan periode data semua berbeda dari klaim STEP 4D.68.
> Hanya jumlah baris (9.623) dan lisensi (CC BY 4.0) yang terkonfirmasi sesuai.

> **[INFERENSI]** Mendeley Data mungkin telah memperbarui dataset dari versi 1 ke versi 4 dengan DOI baru,
> atau STEP 4D.68 mengutip DOI yang salah dari sumber sekunder.

### 2.2 Status Lisensi

| Atribut | Detail | Sumber |
|---|---|---|
| Lisensi | Creative Commons Attribution 4.0 International (CC BY 4.0) | [WEB VERIFY] |
| Penggunaan Offline | Diizinkan | [WEB VERIFY] |
| Penggunaan Riset | Diizinkan | [WEB VERIFY] |
| Syarat Atribusi | Wajib mencantumkan kredit penulis asli | [WEB VERIFY] |

**Kewajiban Atribusi (yang benar):**
Md. Milon Islam et al., "KU-MWQ: A Dataset for Monitoring Water Quality Using Digital Sensors,"
Mendeley Data, DOI: 10.17632/34rczh25kc (v4).

---

## 3. FILE YANG DITEMUKAN DI DIREKTORI

### 3.1 Keberadaan File

**Direktori:** `ml/data/turbidity/ku_mwq/`

> **[FILE CHECK]** File ditemukan sudah ada di direktori target sebelum audit ini dimulai.
> Tidak dilakukan pengunduhan ulang dalam STEP ini.

| Nama File | Format | Ada | Ukuran (bytes) |
|---|---|---|---|
| `Sensor_Data_30cm.xlsx` | Excel XLSX | YA | 285.192 |
| `Sensor_Data_60cm.xlsx` | Excel XLSX | YA | 228.702 |
| `Sensor_Data_30cm.csv` | CSV | TIDAK | - |
| `Sensor_Data_60cm.csv` | CSV | TIDAK | - |

> **TEMUAN:** File tersedia dalam format `.xlsx`, bukan `.csv`.
> Nama sheet di dalam workbook ("Sensor data for 30 cm", "Sensor data for 60 cm") sesuai metadata Mendeley v4.

### 3.2 Checksum & Integritas File

> **[FILE CHECK]** SHA-256 dihitung dengan `hashlib.sha256()`, block size 64KB.

| File | Ukuran (bytes) | SHA-256 | Tanggal Unduh |
|---|---|---|---|
| `Sensor_Data_30cm.xlsx` | 285.192 | `524f6cdecf15cb83e2e82d5db743233034e9e212b4e46c3e1b57cc0b305eb3e8` | Tidak diketahui |
| `Sensor_Data_60cm.xlsx` | 228.702 | `b4495c7860d614ff9487bb656514e70c53357bb05a94c980f9845ad8a620ce8e` | Tidak diketahui |

> **[INFERENSI]** File sudah ada sebelum audit. Tanggal unduh dan sumber asli tidak dapat dikonfirmasi.
> SHA-256 ini berlaku sebagai baseline checksum untuk referensi masa mendatang.

---

## 4. PROFIL STRUKTUR FILE

### 4.1 Format & Encoding

> **[FILE CHECK]** Diperiksa menggunakan `pandas.read_excel()` dengan engine `openpyxl`.

| Atribut | Sensor_Data_30cm.xlsx | Sensor_Data_60cm.xlsx |
|---|---|---|
| Format Kontainer | Office Open XML (.xlsx) | Office Open XML (.xlsx) |
| Nama Sheet | "Sensor data for 30 cm" | "Sensor data for 60 cm" |
| Jumlah Sheet | 1 | 1 |
| Encoding | UTF-8 (OOXML default) | UTF-8 (OOXML default) |
| Delimiter | Tidak berlaku (format biner) | Tidak berlaku |
| Header Row | Baris 1 (ada) | Baris 1 (ada) |

### 4.2 Kolom

> **[FILE CHECK]** CATATAN PENTING: Kolom Temperature mengandung **trailing space** di nama kolom.

**File 30cm - 4 Kolom:**

| Nama Kolom | Dtype | Catatan |
|---|---|---|
| `Date and Time` | object -> datetime64[ns] setelah parse | Timestamp |
| `Temperature (C) ` | float64 | **TRAILING SPACE** di nama kolom |
| `pH` | float64 | **HANYA ADA DI FILE 30CM** |
| `Turbidity (NTU)` | int64 | **INTEGER**, bukan float |

**File 60cm - 3 Kolom:**

| Nama Kolom | Dtype | Catatan |
|---|---|---|
| `Date and Time` | object -> datetime64[ns] setelah parse | Timestamp |
| `Temperature (C) ` | float64 | **TRAILING SPACE** di nama kolom |
| `Turbidity (NTU)` | int64 | **INTEGER**, bukan float |

> **TEMUAN:** File 60cm **TIDAK MEMILIKI KOLOM pH**.
> STEP 4D.68 tidak secara eksplisit mencatat bahwa pH hanya ada di kedalaman 30cm.

### 4.3 Baris & Shape

> **[FILE CHECK]**

| File | Baris Data | Kolom |
|---|---|---|
| `Sensor_Data_30cm.xlsx` | 9.623 | 4 |
| `Sensor_Data_60cm.xlsx` | 9.623 | 3 |

Jumlah baris sesuai klaim STEP 4D.68 (9.623 baris).

---

## 5. PROFIL TIMESTAMP & TEMPORAL

### 5.1 Format & Rentang Waktu

> **[FILE CHECK]** Timestamp di-parse menggunakan `pd.to_datetime()`.

| Atribut | Sensor_Data_30cm.xlsx | Sensor_Data_60cm.xlsx |
|---|---|---|
| Format | YYYY-MM-DD HH:MM:SS (24 jam) | YYYY-MM-DD HH:MM:SS (24 jam) |
| Contoh | 2020-01-15 16:00:35 | 2020-01-15 16:00:35 |
| Timestamp Awal | 2020-01-15 16:00:35 | 2020-01-15 16:00:35 |
| Timestamp Akhir | 2020-01-22 16:25:32 | 2020-01-22 16:25:32 |
| Total Durasi | 7 hari 0 jam 24 menit 57 detik | 7 hari 0 jam 24 menit 57 detik |
| Timezone | **TIDAK ADA (naive datetime)** | **TIDAK ADA (naive datetime)** |
| NaT (parse error) | 0 | 0 |
| Timestamp Duplikat | 0 | 0 |

> **[INFERENSI]** Tidak ada timezone dalam file. Berdasarkan lokasi (Khulna, Bangladesh),
> asumsi wajar adalah UTC+6 (Bangladesh Standard Time/BST), namun tidak dapat dikonfirmasi dari file.

> **PERBEDAAN DENGAN STEP 4D.68:** Periode data diklaim Oktober 2019, aktual Januari 2020.

### 5.2 Interval Sampling

> **[FILE CHECK]** Dihitung dari `df['Date and Time'].diff().dt.total_seconds()`.

| Metrik | Nilai |
|---|---|
| Median interval | 58.0 detik |
| Interval minimum | 1.0 detik |
| Interval maksimum | 3.650 detik (60.8 menit) |

**Distribusi interval (file 30cm):**

| Rentang | Jumlah | Persentase |
|---|---|---|
| = 1 detik | 1 | 0.01% |
| 58-60 detik | 558 | 5.80% |
| 57-61 detik | 944 | 9.82% |
| Di luar 57-61 detik | 8.678 | 90.18% |

> **TEMUAN PENTING:** Ini adalah **IRREGULAR TIME-SERIES**, bukan regular 60-second intervals.
> 90% interval berada di luar rentang 57-61 detik.
> Filter temporal berbasis window **WAJIB menggunakan time-based indexing** (`pd.Timedelta`),
> bukan row-based indexing.

### 5.3 Gap Waktu

> **[FILE CHECK]** Gap = interval > 5 menit.

| Kriteria | File 30cm | File 60cm |
|---|---|---|
| Gap > 5 menit (300s) | 10 | 10 |
| Gap > 10 menit (600s) | 2 | 2 |
| Gap > 60 menit (3600s) | 1 | 1 |

**5 Gap Terbesar (identik di kedua file):**

| Rank | Gap (detik) | Gap (menit) | Timestamp setelah gap |
|---|---|---|---|
| 1 | 3.650 | **60.8 menit** | 2020-01-17 09:08:26 |
| 2 | 872 | 14.5 menit | 2020-01-16 08:21:55 |
| 3 | 599 | 10.0 menit | 2020-01-16 07:59:37 |
| 4 | 515 | 8.6 menit | 2020-01-21 09:25:41 |
| 5 | 441 | 7.3 menit | 2020-01-16 05:46:23 |

> **TEMUAN:** Gap yang identik di kedua file membuktikan kedua sensor berhenti bersamaan.
> Kedua file **TIDAK INDEPENDEN** satu sama lain.

---

## 6. PROFIL KUALITAS DATA

### 6.1 Nilai Kosong (Null/NaN)

> **[FILE CHECK]** `df.isna().sum()`

| Kolom | File 30cm | File 60cm |
|---|---|---|
| Date and Time | 0 (0.0000%) | 0 (0.0000%) |
| Temperature | 0 (0.0000%) | 0 (0.0000%) |
| pH | 0 (0.0000%) | N/A |
| Turbidity (NTU) | **0 (0.0000%)** | **0 (0.0000%)** |

Klaim STEP 4D.68 (<0.1% missing) terkonfirmasi: missing values adalah **0.0000%** (nol mutlak).

### 6.2 Nilai Tak Hingga

> **[FILE CHECK]** `np.isinf()` -- Tidak ada nilai tak hingga di kolom numerik mana pun.

### 6.3 Nilai Negatif & Nol

> **[FILE CHECK]** Tidak ada turbidity negatif. Minimum aktual 194 NTU (30cm) dan 134 NTU (60cm).
> Tidak ada nilai nol untuk turbidity di kedua file.

---

## 7. STATISTIK DESKRIPTIF AKTUAL

> **[FILE CHECK]** Semua statistik dihitung langsung dari file menggunakan pandas.

### 7.1 Turbidity (NTU)

| Statistik | Sensor_Data_30cm.xlsx | Sensor_Data_60cm.xlsx |
|---|---|---|
| Count | 9.623 | 9.623 |
| **Min** | **194 NTU** | **134 NTU** |
| **Max** | **249 NTU** | **154 NTU** |
| Mean | 215.49 NTU | 141.85 NTU |
| Median | 212.00 NTU | 140.00 NTU |
| Std | 16.18 NTU | 6.48 NTU |
| Q1 (P25) | 202.00 NTU | 137.00 NTU |
| Q3 (P75) | 224.00 NTU | 147.00 NTU |
| P5 | 195.00 NTU | 134.00 NTU |
| P95 | 248.00 NTU | 153.00 NTU |
| P99 | 249.00 NTU | 154.00 NTU |
| Nilai Unik | 56 | 21 |
| Tipe Data | int64 (integer) | int64 (integer) |

> **TEMUAN KRITIS:** Rentang turbidity aktual **JAUH LEBIH TINGGI** dari klaim STEP 4D.68.
> Menurut threshold AQUA-TARA (>71 NTU = BAHAYA):
> **SELURUH 19.246 BARIS (100% DATA) BERADA DI ZONA BAHAYA.**
> Dataset tidak mencakup rentang Normal (<30 NTU) maupun Waspada (31-70 NTU).

### 7.2 Distribusi Nilai Turbidity

**File 30cm:**

| Rentang NTU | Jumlah | Persen | Kategori AQUA-TARA |
|---|---|---|---|
| 0-30 NTU | 0 | 0.0% | Normal |
| 31-70 NTU | 0 | 0.0% | Waspada |
| 71-193 NTU | 0 | 0.0% | Bahaya |
| 194-199 NTU | 1.835 | 19.1% | Bahaya (Ekstrem) |
| 200-249 NTU | 7.788 | 80.9% | Bahaya (Ekstrem) |

**File 60cm:**

| Rentang NTU | Jumlah | Persen | Kategori AQUA-TARA |
|---|---|---|---|
| 0-30 NTU | 0 | 0.0% | Normal |
| 31-70 NTU | 0 | 0.0% | Waspada |
| 71-133 NTU | 0 | 0.0% | Bahaya |
| 134-149 NTU | 7.581 | 78.8% | Bahaya (Ekstrem) |
| 150-154 NTU | 2.042 | 21.2% | Bahaya (Ekstrem) |

### 7.3 Profil Harian Turbidity (30cm)

> **[FILE CHECK]** Dikelompokkan per hari kalender.

| Tanggal | Mean (NTU) | Min | Max | Count |
|---|---|---|---|---|
| 2020-01-15 | 196.10 | 195 | 197 | 472 |
| 2020-01-16 | 203.30 | 194 | 215 | 1.336 |
| 2020-01-17 | 214.39 | 210 | 218 | 1.439 |
| 2020-01-18 | 207.67 | 199 | 217 | 1.541 |
| 2020-01-19 | 203.55 | 196 | 226 | 1.440 |
| 2020-01-20 | 239.31 | 226 | 249 | 1.282 |
| 2020-01-21 | 238.46 | 219 | 249 | 1.247 |
| 2020-01-22 | 212.06 | 209 | 219 | 866 |

> **[INFERENSI]** Lonjakan signifikan pada 20-21 Januari (239-238 NTU vs 196-214 NTU sebelumnya).
> Tidak ada ground-truth label penyebab yang tersedia.

### 7.4 Temperature

| Statistik | File 30cm | File 60cm |
|---|---|---|
| Min | 15.60 C | 18.08 C |
| Max | 23.81 C | 24.21 C |
| Mean | 20.00 C | 21.51 C |
| Std | 2.05 C | 1.58 C |

### 7.5 pH (hanya file 30cm)

| Statistik | Nilai |
|---|---|
| Min | 6.98 |
| Max | 8.43 |
| Mean | 7.76 |
| Std | 0.44 |

---

## 8. PERBANDINGAN KEDUA FILE (30cm vs 60cm)

> **[FILE CHECK]**

| Aspek | Temuan |
|---|---|
| Timestamp | Identik - 9.623 timestamps sama persis di kedua file |
| Gap waktu | Identik - 10 gap >5 menit, lokasi sama |
| Turbidity mean | 30cm: 215.49 NTU vs 60cm: 141.85 NTU |
| Korelasi Pearson (turbidity) | **r = 0.6188** - korelasi positif sedang |
| Selisih rata-rata (30cm - 60cm) | +73.63 NTU |
| Std selisih | 13.19 NTU |
| Selisih min / max | 56 NTU / 101 NTU |
| Kolom berbeda | 30cm punya pH; 60cm tidak |

> **KESIMPULAN:** Kedua file **TIDAK INDEPENDEN** - berasal dari sesi pengumpulan yang sama.
> Menggunakan keduanya sebagai training set independen akan membentuk **DATA LEAKAGE TEMPORAL**.

---

## 9. VERIFIKASI SATUAN TURBIDITY & METODOLOGI KALIBRASI

> **[WEB VERIFY]** Berdasarkan metadata Mendeley Data dan abstrak Data in Brief (Elsevier):

- **Satuan:** NTU (Nephelometric Turbidity Unit) - dikonfirmasi dari nama kolom `Turbidity (NTU)`.
- **Sensor:** DF-Robot SEN0189 (fototransistor inframerah) terhubung ke Arduino Mega 2560.
- **Kalibrasi:** Nilai ADC dikalibrasi ke NTU menggunakan kurva regresi tegangan-NTU
  yang didokumentasikan dalam paper Data in Brief.
- **Metode:** Scattered light pada sudut 90 derajat - setara dengan prinsip nephelometric.

> **[INFERENSI]** Nilai NTU adalah **HASIL KALIBRASI SENSOR**, bukan pengukuran lab bersertifikat
> (seperti EPA Method 180.1). Ketidakpastian kalibrasi tidak dikuantifikasi dalam dataset.

---

## 10. RINGKASAN MASALAH DATA

| # | Masalah | Severitas | Implikasi |
|---|---|---|---|
| M1 | **DOI berbeda:** `c9r8kmnbp2.1` vs `34rczh25kc` | KRITIS | Kutipan akademis STEP 4D.68 tidak dapat diverifikasi |
| M2 | **Format file berbeda:** CSV vs XLSX | TINGGI | Pipeline CSV perlu penyesuaian |
| M3 | **Periode data berbeda:** Oktober 2019 vs Januari 2020 | TINGGI | Referensi silang temporal tidak valid |
| M4 | **Turbidity range jauh lebih tinggi:** 0-100+ NTU vs 134-249 NTU aktual | TINGGI | 100% data di zona BAHAYA; tidak mencakup Normal/Waspada |
| M5 | **Turbidity integer, bukan float** | SEDANG | Resolusi terbatas (56 nilai unik 30cm; 21 untuk 60cm) |
| M6 | **Trailing space di nama kolom Temperature** | RENDAH | Bug potensial saat akses literal string |
| M7 | **Tidak ada timezone di timestamp** | SEDANG | Analisis lintas-zona berisiko |
| M8 | **Interval sampling irregular** (median 58s, range 1-3650s) | SEDANG | Wajib gunakan time-based indexing |
| M9 | **Gap 60.8 menit** pada 2020-01-17 pagi | RENDAH | Penanganan eksplisit diperlukan untuk sliding window |
| M10 | **pH hanya di file 30cm** | RENDAH | Feature pH tidak seragam antar kedalaman |
| M11 | **Kedua file tidak independen** (timestamp identik) | SEDANG | Shared temporal context harus dideklarasikan |
| M12 | **Tanggal unduh tidak diketahui** | RENDAH | Provenance audit tidak lengkap |

---

## 11. KETERBATASAN DATASET

| Keterbatasan | Detail |
|---|---|
| Domain mismatch | Kolam ikan air tawar (134-249 NTU) vs pipa distribusi air minum (<5 NTU). Model yang dikalibrasi di sini tidak dapat langsung diterapkan ke AQUA-TARA. |
| Durasi pendek | 7 hari tidak mencakup variasi musiman |
| Tidak ada label anomali | Variasi algoritmik tidak dapat diklaim sebagai anomali nyata |
| Kalibrasi non-absolut | Kurva regresi Arduino-DF-Robot, bukan standar lab bersertifikat |
| Resolusi integer | Turbidity int membatasi presisi numerik |
| Satu lokasi | Generalisasi ke ekosistem lain memerlukan justifikasi |

---

## 12. KEPATUHAN GUARDRAILS

| No | Guardrail | Status | Bukti |
|---|---|---|---|
| 1 | Tidak mengakses/mengubah Supabase atau database | PATUH | Tidak ada koneksi DB |
| 2 | Tidak menjalankan Laravel, PHPUnit, migration, seeder | PATUH | artisan tidak dipanggil |
| 3 | Tidak mengubah kode produksi, formula risk score, model, scaler, threshold | PATUH | Hanya file audit dibuat |
| 4 | Tidak mengunggah dataset ke layanan eksternal | PATUH | File hanya dibaca lokal |
| 5 | Tidak commit Git tanpa persetujuan | PATUH | Tidak ada git commit |
| 6 | Tidak menghapus/menimpa file yang ada; cek konflik nama | PATUH | Konflik diperiksa; file xlsx tidak dimodifikasi |
| 7 | Tidak training, fit scaler, optimasi threshold | PATUH | Hanya statistik deskriptif |
| 8 | Tidak membuat label anomali sintetis | PATUH | Tidak ada labeling |
| 9 | Tidak menyatakan anomali nyata tanpa ground truth | PATUH | Turbidity tinggi = kondisi baseline kolam |
| 10 | Tidak mengunduh dataset lain | PATUH | Hanya KU-MWQ diperiksa |
| 11 | Tidak mengasumsikan NTU dari nama kolom saja | PATUH | Unit diverifikasi dari Mendeley metadata |
| 12 | Jika berbeda dari STEP 4D.68, stop dan laporkan | PATUH | Perbedaan dilaporkan lengkap |

---

## 13. REKOMENDASI LANGKAH BERIKUTNYA

### 13.1 Tindakan Wajib Sebelum Penggunaan

1. **Koreksi DOI:** DOI yang benar adalah `10.17632/34rczh25kc` (v4). Perbarui referensi di STEP 4D.68.
2. **Konfirmasi provenance:** Verifikasi file XLSX berasal dari Mendeley Data v4, bukan sumber lain.
3. **Deklarasikan timezone:** Tentukan UTC+6 (BST) atau UTC sebelum operasi time-based join.

### 13.2 Panduan Penggunaan Dataset

| Kasus Penggunaan | Layak? | Catatan |
|---|---|---|
| Uji algoritma filter temporal (EMA, persistence window, median filter) | **YA** | Gunakan time-based indexing |
| Benchmark respons transien sensor IoT terhadap spike | **YA** | Variasi diurnal dan event-based tersedia |
| Validasi sliding window terhadap gap waktu | **YA** | Gap realistis hingga 60.8 menit tersedia |
| Kalibrasi threshold Normal/Waspada/Bahaya AQUA-TARA | **TIDAK** | 100% data di zona Bahaya ekstrem; tidak representatif |
| Klaim validasi model untuk air pipa distribusi | **TIDAK** | Domain mismatch sangat besar (kolam ikan vs air minum) |
| Analisis multi-kedalaman dengan asumsi independen | **TIDAK** | Kedua file berbagi timestamp - tidak independen |

### 13.3 Catatan Teknis Pipeline

```python
# Load file XLSX
import pandas as pd
df = pd.read_excel('Sensor_Data_30cm.xlsx', engine='openpyxl')

# Normalkan nama kolom (strip trailing spaces)
df.columns = df.columns.str.strip()

# Parse timestamp dan set timezone eksplisit
df['Date and Time'] = pd.to_datetime(df['Date and Time'])
df = df.set_index('Date and Time')

# Turbidity adalah int64 -- dokumentasikan dalam komentar kode
# Gunakan resample() atau rolling() berbasis time-index, bukan rolling(n_rows)
# Contoh: df['Turbidity (NTU)'].rolling('15min').mean()
```

---

## 14. RINGKASAN AUDIT

### 14.1 File yang Diaudit

| File | Ditemukan | Ukuran | SHA-256 |
|---|---|---|---|
| `ml/data/turbidity/ku_mwq/Sensor_Data_30cm.xlsx` | YA | 285.192 bytes | `524f6cdecf15cb83e2e82d5db743233034e9e212b4e46c3e1b57cc0b305eb3e8` |
| `ml/data/turbidity/ku_mwq/Sensor_Data_60cm.xlsx` | YA | 228.702 bytes | `b4495c7860d614ff9487bb656514e70c53357bb05a94c980f9845ad8a620ce8e` |

### 14.2 Pemeriksaan yang Dilakukan

| Pemeriksaan | Status | Metode |
|---|---|---|
| Verifikasi DOI & sumber Mendeley | SELESAI | Web search + read_url_content |
| Verifikasi lisensi | SELESAI | Web search |
| Checksum SHA-256 | SELESAI | Python hashlib |
| Struktur file (format, sheet, kolom, tipe) | SELESAI | pandas + openpyxl |
| Timestamp (format, rentang, timezone, duplikat) | SELESAI | pandas |
| Interval sampling & distribusinya | SELESAI | pandas diff() |
| Gap waktu > 5 menit | SELESAI | pandas |
| Nilai null per kolom | SELESAI | pandas isna() |
| Nilai negatif & nol | SELESAI | pandas |
| Nilai tak hingga | SELESAI | numpy isinf() |
| Statistik deskriptif turbidity | SELESAI | pandas describe() + manual |
| Distribusi per bucket NTU | SELESAI | pandas conditional count |
| Profil harian turbidity | SELESAI | pandas groupby |
| Perbandingan 30cm vs 60cm | SELESAI | pandas corr() |
| Verifikasi satuan & kalibrasi | SELESAI | Web verify Mendeley metadata |
| Klaim STEP 4D.68 vs aktual | SELESAI | Cross-reference manual |

### 14.3 Langkah yang Tidak Dapat Diselesaikan (BLOCKED)

| Langkah | Alasan |
|---|---|
| Konfirmasi SHA-256 resmi Mendeley per-file | Mendeley tidak menampilkan checksum publik; halaman heavy JavaScript |
| Verifikasi tanggal unduh file | File sudah ada sebelum audit; metadata filesystem tidak membuktikan sumber |
| Akses paper Data in Brief lengkap | Elsevier memerlukan akses institusional |

### 14.4 Label yang Digunakan

| Label | Definisi |
|---|---|
| **[FILE CHECK]** | Hasil pemeriksaan langsung terhadap file di direktori proyek |
| **[WEB VERIFY]** | Informasi dari halaman web resmi yang dapat diakses publik |
| **[INFERENSI]** | Kesimpulan berdasarkan kombinasi fakta; belum dikonfirmasi dari sumber primer |

### 14.5 File Laporan yang Dibuat

- `backend/docs/STEP_4D_69_KU_MWQ_DATASET_INTEGRITY_AUDIT.md` - dokumen ini

---

## 15. TABEL PERBEDAAN LENGKAP: KLAIM STEP 4D.68 vs AKTUAL

| Atribut | Klaim STEP 4D.68 | Aktual Terverifikasi | Label |
|---|---|---|---|
| DOI | `10.17632/c9r8kmnbp2.1` | `10.17632/34rczh25kc` (v4) | [WEB VERIFY] |
| URL Mendeley | `.../c9r8kmnbp2/1` | `.../34rczh25kc/4` | [WEB VERIFY] |
| Versi | 1 | 4 | [WEB VERIFY] |
| Format file | `.csv` | `.xlsx` (Excel) | [FILE CHECK] |
| Nama file di direktori | `Sensor_Data_30cm.csv` | `Sensor_Data_30cm.xlsx` | [FILE CHECK] |
| Nama sheet | - | "Sensor data for 30 cm" | [FILE CHECK] |
| Periode data | Oktober 2019 | 15-22 Januari 2020 | [FILE CHECK] |
| Rentang turbidity | 0 hingga ~100+ NTU | 30cm: 194-249 NTU; 60cm: 134-154 NTU | [FILE CHECK] |
| Jumlah baris per file | 9.623 | 9.623 TERKONFIRMASI | [FILE CHECK] |
| Missing values | <0.1% | 0.0000% (nol mutlak) | [FILE CHECK] |
| Kolom 60cm | Temperature, pH, Turbidity | Temperature, Turbidity (TANPA pH) | [FILE CHECK] |
| Tipe data turbidity | - (tidak dispesifikasi) | int64 (integer) | [FILE CHECK] |
| Interval sampling | 1 menit | Median 58 detik; IRREGULAR (1-3650s) | [FILE CHECK] |
| Timezone | - (tidak disebutkan) | TIDAK ADA (naive datetime) | [FILE CHECK] |
| Lisensi | CC BY 4.0 | CC BY 4.0 TERKONFIRMASI | [WEB VERIFY] |