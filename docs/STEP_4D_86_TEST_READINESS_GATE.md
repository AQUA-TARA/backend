# STEP 4D.86 — TEST READINESS GATE (INTERNAL HARNESS & SYNTHETIC AUDIT)

**Document ID:** `BACKEND-GATE-STEP-4D-86-001`  
**Phase:** STEP 4D.86 — Internal Test Harness & Synthetic Scenario Design  
**Date:** 2026-10-18  
**Auditor:** AI Lead Data & ML Systems Architect  
**Scope:** Backend Ingress, Test Harness Infrastructure, Behavioral Verification, and Final Gate Ruling  
**Gate Ruling:** **PASS FOR HARNESS & SCENARIO DESIGN SPECIFICATION ONLY — NO CODE MUTATION / PRODUCTION INTEGRATION REMAINS ON HOLD**  

---

## 1. PENETAPAN KEPUTUSAN EVALUASI GERBANG (GATE RULING)

Berdasarkan audit kemampuan pengujian internal ([STEP_4D_86_INTERNAL_TEST_HARNESS_DESIGN.md](file:///e:/TUGAS/pengkoding\anti\aqua-tara\ml\experiments\baseline_anomaly_detection\STEP_4D_86_INTERNAL_TEST_HARNESS_DESIGN.md)) dan penyusunan matriks skenario pengujian sintetis ([STEP_4D_86_SYNTHETIC_SCENARIO_MATRIX.md](file:///e:/TUGAS/pengkoding\anti\aqua-tara\ml\experiments\baseline_anomaly_detection\STEP_4D_86_SYNTHETIC_SCENARIO_MATRIX.md)), ditetapkan keputusan resmi gerbang:

$$\mathbf{PASS\ FOR\ HARNESS\ \&\ SCENARIO\ DESIGN\ SPECIFICATION\ ONLY}$$
$$\mathbf{ALL\ 5\ OPEN\ DECISIONS:\ REMAIN\ OPEN}$$
$$\mathbf{FIELD\ SENSOR\ INTEGRATION\ \&\ VALIDATION:\ HOLD\ —\ FIELD\ DATA\ REQUIRED}$$

---

## 2. AUDIT STATUS LIMA OPEN DECISIONS

Pekerjaan STEP 4D.86 berfokus pada desain uji internal dan audit infrastruktur pengujian tanpa mengubah status hukum kelima Open Decisions:

| ID Keputusan | Deskripsi Masalah Pokok | Status Sebelum 4D.86 | Status Pasca 4D.86 | Justifikasi Tata Kelola |
|:---:|:---|:---:|:---:|:---|
| **OD-SCORE-S-01** | Arsitektur sinyal hidraulik paralel $Score_H$ vs $Score_S$ | `OPEN` | **`OPEN`** | Menunggu data telemetri DMA PDAM $\ge 6$ bulan dan tanda tangan Direktur Teknik PDAM. |
| **OD-SCORE-S-02** | Kalibrasi empiris kuantil P95/P99 ke rentang 0–100 | `OPEN` | **`OPEN`** | Menunggu dataset riil lokal dari OD-SCORE-S-01 untuk menghitung sebaran empiris. |
| **OD-SCORE-S-03** | Protokol sensor SCADA offline, batas stale, fail-safe | `OPEN` | **`OPEN`** | Matriks skenario SC-SEN-06/07 telah dirancang, namun harness staging formal & sign-off SRE belum dieksekusi. |
| **OD-GOV-01** | Kebijakan deduplikasi aduan per warga (<50m, <60m) | `OPEN` | **`OPEN`** | Parameter radius 50m dan jeda 60m masih berstatus usulan; belum ada persetujuan Product Owner dan Humas PDAM. |
| **OD-GIS-01** | Pengesahan polygon GeoJSON batas 5 kecamatan Tarakan | `OPEN` | **`OPEN`** | Batas resmi Bappeda/BIG belum tersedia di repositori; fixture sintetis diberi label `SYNTHETIC_MOCK_POLYGON`. |

---

## 3. VERIFIKASI PERILAKU KODE (ACTUAL VS EXPECTED)

Hasil inspeksi kode sumber backend dan ML mengonfirmasi status pembuktian perilaku sistem:

1. **Penanganan Sensor Kosong & Nilai Abnormal:**
   - `production_analysis.py L75-76` dan `L6-8`: Fallback $Score_T = 30.0$ saat `iot_data` kosong, `None`, atau `NaN` telah **TERVERIFIKASI AKTUAL PADA KODE (`VERIFIED_CODE`)** dan lolos pengujian unit yang ada.
   - Nilai kekeruhan negatif di-clamp ke $0.0$ NTU (`L11`) dan ekstrem tinggi di-clamp ke $100.0$ (`L18`).
2. **Kesenjangan Data Sensor Stale (Discrepancy Gap):**
   - Kode backend `MLAnalysisService.php L111-124` saat ini **BELUM MEMERIKSA** batas kadaluarsa timestamp `measured_at`. Data sensor lama yang tertinggal di database tetap dianggap pembacaan aktif. Ini merupakan temuan kesenjangan arsitektural yang dicatat sebagai pendukung perlunya penyelesaian `OD-SCORE-S-03`.
3. **Ketiadaan Filter Ingress Spasial (Discrepancy Gap):**
   - API `ReportController.php` saat ini menerima titik koordinat manapun selama berformat numerik desimal valid. Titik di luar Pulau Tarakan (misal koordinat Bandung) tidak ditolak pada layer ingress karena ketiadaan berkas polygon resmi `OD-GIS-01`.
4. **Ketiadaan Deduplikasi di Lapisan Produksi:**
   - Laporan berulang dari warga yang sama tetap disimpan sebagai tiket terpisah dan dihitung dalam bobot K-Means. Hal ini sesuai dengan aturan bahwa **DEDUPLIKASI BELUM BOLEH DIIMPLEMENTASIKAN KE PRODUKSI** sebelum `OD-GOV-01` disahkan.

---

## 4. TEMUAN KESIAPAN INFRASTRUKTUR PENGUJIAN

1. **Toolchain Tersedia:**
   - Python 3.13.3 + pytest 9.1.1 + Flask 3.1.3 + scikit-learn 1.6.1 + NumPy 2.2.6 tersedia di lingkungan pengembang lokal.
   - PHP 8.3 + PHPUnit 12.5.12 tersedia di lingkungan backend.
2. **Prasyarat yang Hilang:**
   - Library `shapely` / `geopandas` belum terpasang di environment Python. Pengujian geometri topologi GeoJSON tingkat lanjut (deteksi self-intersection dan multi-polygon point-in-polygon) berstatus `BLOCKED_NO_SHAPELY`.
   - Mengikuti aturan ketat STEP 4D.86, dependensi baru **TIDAK DITAMBAHKAN** ke sistem pada fase audit ini.
3. **Pemisahan Tegas Pembuktian:**
   - Pengujian sintetis dan simulasi unit test **TIDAK DAPAT DAN TIDAK BOLEH DIKLAIM SEBAGAI VALIDASI SENSOR LAPANGAN ATAU DETEKSI KEBOCORAN FISIK**.
   - Validasi lapangan hanya sah jika didukung data perbandingan laboratorium 30 hari dan log operasional galian teknisi PDAM.

---

## 5. KEPATUHAN BOUNDARY PRODUKSI & PERNYATAAN INTEGRITAS

1. **Tidak Ada Mutasi Kode:** Tidak ada perubahan pada file produksi, API, frontend, database migration, seeder, maupun skema.
2. **Tidak Ada Perubahan Formula:** Formula $\text{Risk\_Score} = \text{round}(0{,}8 \cdot Score_T + 0{,}2 \cdot Score_S)$ dan ambang batas (0–30, 31–70, 71–100) tetap 100% utuh.
3. **Tidak Ada Staging / Commit:** Direktori repositori tetap bersih dari staging `git add` atau commit Git.
4. **Penghentian Tahap:** Tahap STEP 4D.86 dinyatakan **SELESAI PENUH**. Sistem berhenti dan tidak melanjutkan secara otomatis ke STEP 4D.87.
