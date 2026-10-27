# STEP 4D.80 — SPATIAL VALIDATION & PROPOSAL HARDENING GATE

**Document ID:** `GATE-VALIDATION-STEP-4D-80-001`  
**Phase:** STEP 4D.80 — Spatial Validation Hardening & Score_S Definition Review  
**Date:** 2026-10-17  
**Auditor / System Architect:** AI Lead Data & ML Systems Architect  
**Gate Decision Ruling:** **PASS FOR VALIDATION DESIGN ONLY**  
*(Catatan Pembatasan: PASS eksklusif untuk spesifikasi desain ketahanan pengelompokan spasial, algoritma Hungarian 1-ke-1, audit semantik Score_S, dan pembuktian proposal asli. DITAHAN MUTLAK (HOLD) untuk integrasi data sensor fisik atau pengubahan kode produksi sebelum tersedianya data telemetri lapangan PDAM Tarakan riil).*

---

## 1. PENETAPAN KEPUTUSAN EVALUASI GERBANG (GATE RULING)

Berdasarkan sintesis bukti eksperimental pada STEP 4D.80, ditetapkan keputusan resmi:

$$\mathbf{PASS\ FOR\ VALIDATION\ DESIGN\ ONLY}$$

### Rasional Ilmiah & Bukti Penetapan:
1. **Kestabilan Centroid Terbukti Secara Matematis (Hungarian Algorithm 1-to-1):**
   - Pencocokan optimal Hungarian membuktikan bahwa pergeseran centroid akibat noise GPS wajar ($\pm 10\text{ m}$) hanya sebesar **$3{,}19\text{ meter}$** (maksimum $5{,}94\text{ m}$) dengan nilai $\text{Adjusted Rand Index (ARI)} = 1{,}0000$ dan $\text{NMI} = 1{,}0000$. Tidak ada tabrakan (*collision*) atau pembalikan pasangan kluster.
   - Pada skenario data dropout 10%, partisi keanggotaan tetap stabil sempurna ($\text{ARI} = 1{,}0000$) dengan pergeseran rerata $4{,}24\text{ meter}$.
2. **Definisi Faktual $Score_S$ Terverifikasi Secara Tuntas:**
   - Variabel `density` pada kode produksi terbukti secara harfiah merepresentasikan **jumlah absolut laporan warga di dalam kluster ($N_{\text{cluster}}$)**, bukan fungsi radius fisik atau densitas per $\text{km}^2$.
   - Sistem terbukti memiliki kekebalan matematis (*Sybil immunity*): bobot $Score_S$ sebesar $20\%$ mencegah eskalasi status menjadi Bahaya sendirian jika sensor kekeruhan normal ($Score_T = 0 \implies \text{Risk} \le 20$, kategori Aman).
3. **Kesesuaian Teks Proposal Asli Terbukti Secara Harfiah:**
   - Kutipan terverifikasi dari `docs/PROPOSAL.pdf` Halaman 10 membuktikan bahwa prinsip dasar ML sistem:
     > *"Hasil clustering digunakan untuk menentukan kelompok atau tingkat prioritas wilayah, bukan untuk menyatakan bahwa centroid cluster merupakan titik pasti kerusakan pipa."*
     adalah amanat resmi dari dokumen perancangan awal.
4. **Alasan Tidak Diberikan "Full Operational Pass":**
   - Pengujian masih mengandalkan dataset test oracle sintetis ($N=41$). Data laporan warga riil Kota Tarakan belum tersedia di repositori guna melindungi privasi warga.
   - Sensor fisik kekeruhan dan logger tekanan SCADA di pipa PDAM Kota Tarakan belum terpasang fisik, sehingga validasi operasional lapangan riil tetap berstatus **HOLD — FIELD DATA REQUIRED** (sebagaimana diputuskan pada STEP 4D.77).

---

## 2. KUTIPAN TERVERIFIKASI DOKUMEN PROPOSAL ASLI (`docs/PROPOSAL.pdf`)

Untuk membedakan kutipan faktual dari interpretasi subjektif, berikut adalah tabel kutipan yang diverifikasi langsung dari dekompresi CMap ToUnicode stream PDF primer:

| No. Halaman Sumber | Topik Pembahasan | Kutipan Asli Terverifikasi (Verbatim) | Status Verifikasi |
|:---:|:---|:---|:---:|
| **Halaman 8** | Tujuan K-Means | *"Menerapkan algoritma K-Means Clustering untuk mengelompokkan wilayah atau titik jaringan berdasarkan karakteristik dan tingkat kerawanan gangguan distribusi air sehingga dapat membantu menentukan lokasi prioritas untuk pemantauan dan inspeksi teknisi."* | **VERIFIED FACTUAL** |
| **Halaman 9** | Dukungan Informasi Teknisi | *"Informasi mengenai lokasi pengaduan, koordinat, hasil pemantauan sensor, serta kelompok wilayah berdasarkan algoritma K-Means dapat digunakan sebagai informasi pendukung bagi teknisi dalam menentukan lokasi yang perlu diperiksa terlebih dahulu."* | **VERIFIED FACTUAL** |
| **Halaman 10** | **Batasan Centroid (Non-Lokasi Fisik)** | *"Metode Machine Learning yang digunakan pada tahap awal dibatasi pada algoritma K-Means Clustering untuk mengelompokkan titik atau wilayah berdasarkan karakteristik data yang tersedia. **Hasil clustering digunakan untuk menentukan kelompok atau tingkat prioritas wilayah, bukan untuk menyatakan bahwa centroid cluster merupakan titik pasti kerusakan pipa.**"* | **VERIFIED FACTUAL (CORE RULE)** |
| **Halaman 11** | Pemantauan Dashboard PDAM | *"Pihak PDAM Kota Tarakan melakukan dashboard monitoring pada informasi yang bersumber dari server cerdas AQUA-TARA, memantau tingkat kekeruhan air secara real-time melalui sensor IoT, memantau laporan visual dari masyarakat, serta memantau pergerakan Risk Score hasil clustering algoritma K-Means."* | **VERIFIED FACTUAL** |
| **Halaman 15** | Framework ML Engine | *"Machine Learning Engine Python dengan framework Flask digunakan secara spesifik untuk memproses data hybrid dan mengeksekusi algoritma clustering K-Means."* | **VERIFIED FACTUAL** |

---

## 3. PRASYARAT TAHAP LAPANGAN MASA DEPAN (FIELD EXIT CRITERIA)

Status gerbang **PASS FOR VALIDATION DESIGN ONLY** ini dapat ditingkatkan menjadi implementasi lapangan penuh bila kriteria berikut terpenuhi:

1. **Penyediaan Dataset Laporan Teranonimkan:**
   - Snapshot data laporan warga Kota Tarakan dengan pembersihan total data pribadi (PII) yang disimpan di folder terisolasi `.gitignore`.
2. **Pengesahan Kebijakan Deduplikasi oleh Product Owner:**
   - Persetujuan resmi mengenai opsi deduplikasi laporan berulang per warga (misal: radius $< 50\text{ m}$, jendela $< 60\text{ menit}$).
3. **Penyediaan Batas Polygon Resmi (GIS PDAM Tarakan):**
   - Integrasi file shapefile/GeoJSON batas administrasi resmi Kota Tarakan untuk mengaktifkan spatial bounding box filter pada API ingress.
4. **Pemasangan Sensor Fisik Lapangan:**
   - Memenuhi exit criteria STEP 4D.77 untuk sensor kekeruhan optik dan logger tekanan hidraulik SCADA.

---

## 4. ARAHAN TATA KELOLA KODE & REPOSITORI

- **DILARANG** melakukan `git add`, `git commit`, `git push`, atau deployment.
- **DILARANG** mengubah formula produksi kanonikal $\text{round}(0{,}8 \cdot Score_T + 0{,}2 \cdot Score_S)$ atau ambang batas risiko ($0$–$30$, $31$–$70$, $71$–$100$).
- **DILARANG** memetakan sinyal hidraulik $Score_H$ ke dalam $Score_S$.
- **PEKERJAAN DIHENTIKAN** pada akhir STEP 4D.80. Tidak ada kelanjutan otomatis ke STEP 4D.81.
