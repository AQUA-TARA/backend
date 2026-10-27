# STEP 4D.87 — FAILURE-MODE GATE & ARCHITECTURAL SPECIFICATION DECISION

**Document ID:** `BACKEND-GATE-STEP-4D-87-001`  
**Phase:** STEP 4D.87 — Stale Sensor Contract & Failure-Mode Specification  
**Date:** 2026-10-19  
**Auditor:** AI Lead Data & ML Systems Architect  
**Scope:** Stale Sensor & Failure-Mode Specification Audit, Evidence Boundary, Verification Matrix, and Final Governance Gate Ruling  
**Gate Ruling:** **PASS FOR SPECIFICATION ONLY — NO CODE OR TEST SUITE MUTATION**  

---

## 1. PENETAPAN KEPUTUSAN EVALUASI GERBANG (GATE RULING)

Berdasarkan audit mendalam terhadap seluruh spektrum kegagalan sensor, peninjauan kode aktual Laravel dan Flask, serta perumusan dokumen spesifikasi formal [STEP_4D_87_STALE_SENSOR_CONTRACT.md](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/baseline_anomaly_detection/STEP_4D_87_STALE_SENSOR_CONTRACT.md) dan [STEP_4D_87_FAILURE_MODE_TEST_MATRIX.md](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/baseline_anomaly_detection/STEP_4D_87_FAILURE_MODE_TEST_MATRIX.md), diputuskan hasil evaluasi gerbang resmi:

$$\mathbf{PASS\ FOR\ SPECIFICATION\ ONLY}$$
$$\mathbf{ALL\ 5\ OPEN\ DECISIONS:\ REMAIN\ OPEN}$$
$$\mathbf{FIELD\ SENSOR\ INTEGRATION\ \&\ VALIDATION:\ HOLD\ —\ FIELD\ DATA\ REQUIRED}$$

---

## 2. KEPATUHAN BOUNDARY, FORMULA, DAN GOVERNANCE

Gerbang STEP 4D.87 menegaskan kepatuhan penuh terhadap seluruh batasan arsitektur proyek AQUA-TARA:

1. **Formula Produksi Tidak Berubah Mutlak:**
   $$\text{Risk\_Score} = \text{round}(0{,}8 \cdot Score_T + 0{,}2 \cdot Score_S)$$
   Ambang batas klasifikasi tetap tidak berubah:
   - $0$–$30$: Jernih / Risiko Rendah
   - $31$–$70$: Waspada / Risiko Sedang
   - $71$–$100$: Bahaya / Risiko Tinggi
2. **Definisi $Score_S$ Terkunci:** $Score_S = \min(100.0, \text{density} \times 15.0)$, di mana $\text{density}$ adalah jumlah aduan warga dalam klaster spasial.
3. **Pemisahan Sinyal Hidraulik:** Sinyal hidraulik ($Score_H$) terpisah secara ontologis dari $Score_S$. $Score_S$ tidak boleh digantikan oleh anomali tekanan atau debit SCADA.
4. **Larangan Salah Tafsir Fallback $Score_T = 30.0$:**
   Ditegaskan secara mengikat bahwa fallback numerik $Score_T = 30.0$ saat sensor tidak tersedia **BUKAN BUKTI BAHWA AIR SECARA FISIK AMAN ATAU JERNIH**. Fallback tersebut hanyalah mekanisme pertahanan komputasi untuk mencegah crash aplikasi.
5. **Pembedaan Sensor Kosong vs Stale:**
   Kondisi ketiadaan simpul/telemetri (`iot_data: []`) dipisahkan secara tegas dari kondisi data usang akibat keterlambatan transmisi (`measured_at < NOW() - \Delta t`).
6. **Status Kelima Keputusan Terbuka (Open Decisions):**

| ID Keputusan | Masalah Pokok | Status Gate STEP 4D.87 | Justifikasi Faktual |
|:---:|:---|:---:|:---|
| **OD-SCORE-S-01** | Arsitektur modul sinyal hidraulik paralel $Score_H$ | **`OPEN`** | Tidak ada modifikasi arsitektur; formula $Score_S$ warga tetap terisolasi dari SCADA. |
| **OD-SCORE-S-02** | Metode kalibrasi kuantil P95/P99 ke skala 0–100 | **`OPEN`** | Tetap menunggu dataset telemetri baseline DMA 6 bulan lokal PDAM Tarakan. |
| **OD-SCORE-S-03** | Protokol sensor SCADA offline & batas data stale | **`OPEN`** | Spesifikasi kontrak stale telah disusun, parameter ambang batas waktu (15m, 60m) berstatus PROPOSED. |
| **OD-GOV-01** | Kebijakan deduplikasi aduan per warga (<50m, <60m) | **`OPEN`** | Tidak ada deduplikasi sepihak yang dimasukkan ke basis kode produksi. |
| **OD-GIS-01** | Batas wilayah resmi GeoJSON 5 kecamatan Tarakan | **`OPEN`** | Menunggu data resmi Bappeda/BIG Tarakan; library `shapely` tidak ditambahkan. |

---

## 3. VERIFIKASI EKSEKUSI TES TERSEDIA

Pengujian yang tersedia dalam repositori telah dijalankan secara langsung dan terbukti 100% lulus tanpa kegagalan:

1. **Python / Pytest (`ml/`):**
   - File: `tests/test_production_algorithm.py` dan `tests/test_analyze.py`.
   - Hasil: **43 Passed, 0 Failed, 0 Skipped** (durasi 20.76s).
   - Membuktikan: Fallback numerik sensor kosong ($Score_T = 30.0$), normalisasi turbidity, penanganan `None`/`NaN` internal, clamping nilai negatif/ekstrem, pembatasan $K$-Means, dan pencegahan field terlarang.
2. **PHP / PHPUnit (`backend/`):**
   - File: `tests/Feature/MLAnalysisIntegrationTest.php`.
   - Hasil: **7 Tests, 18 Assertions — 100% Passed** (durasi 25.68s).
   - Membuktikan: Penanganan exception terkendali saat layanan Flask offline (timeout 10s), rollback integritas transaksi basis data, serta persistensi relasi atomik.
3. **Pembedaan Status Rancangan Skenario Uji Baru:**
   Skenario uji yang memerlukan pengubahan skema atau implementasi kode baru (seperti filter query stale data, guard clock skew masa depan, dan perizinan null pada HTTP schema) dicatat dengan status **`NOT RUN`** pada matriks pengujian. Tidak ada hasil tes yang dikarang.

---

## 4. KEPUTUSAN YANG MENUNGGU PERSETUJUAN (PENDING SIGNOFFS)

Seluruh parameter dan perubahan arsitektur yang dirumuskan dalam spesifikasi STEP 4D.87 berstatus **`PROPOSED`** dan memerlukan persetujuan formal:
1. **Ambang Batas Usia Data Stale:** Usulan jendela waktu 15 menit (*fresh*) dan 60 menit (*stale cutoff*) menunggu persetujuan SRE AQUA-TARA, Lead Backend Architect, dan Kepala Seksi SCADA PDAM Tarakan (`OD-SCORE-S-03`).
2. **Penyelarasan Skema HTTP Null Turbidity:** Usulan perizinan union type `float | None` pada field sensor di `ml/app/schemas/analyze.py` menunggu persetujuan Lead ML Engineer dan Lead Backend Architect.
3. **Pencegahan Clock Skew Masa Depan:** Usulan penolakan ingest data dengan timestamp $> now() + 60s$ menunggu persetujuan Lead Firmware Engineer.
4. **Retry & Logging Telemetri:** Usulan retry 2x dengan backoff 500ms pada client HTTP Laravel dan metric anomali instrumentasi menunggu persetujuan SRE.

---

## 5. INTEGRITAS REPOSITORI & KESELAMATAN LINGKUNGAN

- **Tidak Ada Mutasi Kode:** Tidak ada berkas di `backend/app/`, `ml/app/`, `frontend/src/`, `database/`, atau direktori tes yang dimodifikasi atau ditambahkan.
- **Tidak Ada Operasi Git / Deployment:** Tidak ada perintah `git add`, `git commit`, `git push`, migrasi basis data, atau deployment yang dijalankan.
- **Penghentian Tahap:** Tahap STEP 4D.87 dinyatakan **SELESAI PENUH**. Sistem berhenti dan tidak melanjutkan ke STEP 4D.88.
