# STEP 4D.88 — ACCEPTANCE CRITERIA SPECIFICATION GATE

**Document ID:** `BACKEND-GATE-STEP-4D-88-001`  
**Phase:** STEP 4D.88 — Stale Sensor Contract Review & Acceptance Criteria  
**Date:** 2026-10-19  
**Auditor:** AI Lead Data & ML Systems Architect  
**Scope:** Stale Sensor Contract Review, Multi-Domain Acceptance Criteria Governance, Evidence Boundary Verification, and Final Gate Ruling  
**Gate Ruling:** **PASS FOR ACCEPTANCE CRITERIA SPECIFICATION ONLY — NO PRODUCTION OR TEST CODE MUTATION**  

---

## 1. PENETAPAN KEPUTUSAN EVALUASI GERBANG (GATE RULING)

Berdasarkan audit mendalam terhadap kontrak kegagalan sensor pada [STEP_4D_88_CONTRACT_REVIEW.md](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/baseline_anomaly_detection/STEP_4D_88_CONTRACT_REVIEW.md) dan perumusan matriks kriteria penerimaan teruji pada [STEP_4D_88_ACCEPTANCE_CRITERIA.md](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/baseline_anomaly_detection/STEP_4D_88_ACCEPTANCE_CRITERIA.md), ditetapkan keputusan resmi gerbang arsitektural:

$$\mathbf{PASS\ FOR\ ACCEPTANCE\ CRITERIA\ SPECIFICATION\ ONLY}$$
$$\mathbf{ALL\ 5\ OPEN\ DECISIONS:\ REMAIN\ OPEN}$$
$$\mathbf{FIELD\ SENSOR\ INTEGRATION\ \&\ VALIDATION:\ HOLD\ —\ FIELD\ DATA\ REQUIRED}$$

---

## 2. KEPATUHAN BOUNDARY, FORMULA, DAN GOVERNANCE

Gerbang STEP 4D.88 menegaskan kepatuhan penuh terhadap seluruh batasan arsitektur sistem AQUA-TARA:

1. **Formula Produksi Tidak Berubah Mutlak:**
   $$\text{Risk\_Score} = \text{round}(0{,}8 \cdot Score_T + 0{,}2 \cdot Score_S)$$
   Ambang batas klasifikasi tetap tidak berubah:
   - $0$–$30$: Jernih / Risiko Rendah
   - $31$–$70$: Waspada / Risiko Sedang
   - $71$–$100$: Bahaya / Risiko Tinggi
2. **Definisi $Score_S$ Terkunci:** $Score_S = \min(100.0, \text{density} \times 15.0)$, di mana $\text{density}$ adalah jumlah aduan warga dalam klaster geospasial K-Means.
3. **Pemisahan Ontologis $Score_H$:** Sinyal anomali hidraulik SCADA ($Score_H$) terpisah mutlak dari $Score_S$. $Score_S$ mengukur keluhan persepsi warga, bukan besaran fisik kerusakan pipa air.
4. **Larangan Salah Tafsir Fallback $Score_T = 30.0$:**
   Ditegaskan kembali bahwa fallback $Score_T = 30.0$ hanyalah *defensive numerical default* agar komputasi tidak crash saat data sensor tidak tersedia, dan **BUKAN JAMINAN KUALITAS AIR DI LAPANGAN SECARA FISIK BERSIH/AMAN**.
5. **Pembedaan Sensor Kosong vs Sensor Stale:**
   Kondisi tidak adanya sensor (`iot_data: []`) dipisahkan secara tegas dari kondisi data usang (`measured_at` kedaluwarsa). Keduanya memiliki akar masalah operasional yang berbeda.
6. **Status Kelima Keputusan Terbuka (Open Decisions):**

| ID Keputusan | Masalah Pokok | Status Gate STEP 4D.88 | Justifikasi Faktual |
|:---:|:---|:---:|:---|
| **OD-SCORE-S-01** | Arsitektur modul sinyal hidraulik paralel $Score_H$ | **`OPEN`** | Sinyal SCADA tetap terisolasi; tidak ada percampuran ke $Score_S$. |
| **OD-SCORE-S-02** | Metode kalibrasi kuantil P95/P99 ke skala 0–100 | **`OPEN`** | Tetap menunggu dataset telemetri baseline 6 bulan PDAM Tarakan. |
| **OD-SCORE-S-03** | Protokol sensor SCADA offline & batas data stale | **`OPEN`** | Kriteria penerimaan telah disusun; parameter 15m/60m berstatus PROPOSED. |
| **OD-GOV-01** | Kebijakan deduplikasi aduan per warga (<50m, <60m) | **`OPEN`** | Tidak ada kode deduplikasi yang diimplementasikan di produksi. |
| **OD-GIS-01** | Batas wilayah resmi GeoJSON 5 kecamatan Tarakan | **`OPEN`** | Menunggu berkas resmi Bappeda/BIG; library `shapely` tidak ditambahkan. |

---

## 3. PEMISAHAN KUADRAN TATA KELOLA KRITERIA PENERIMAAN

Penetapan kriteria penerimaan pada STEP 4D.88 memisahkan wewenang secara objektif:
- **Kuadran A (Uji Software Lokal - `VERIFIED_PASS`):** Kriteria `AC-LOC-01` s.d. `AC-LOC-04` telah diverifikasi 100% lulus pada test suite lokal (Pytest 43 passed, PHPUnit 7 passed).
- **Kuadran B (Arsitektur & Product Owner - `PROPOSED_PENDING_SIGNOFF`):** Kriteria `AC-ARC-01` s.d. `AC-ARC-03` (anotasi metadata audit fallback, harmonisasi skema null, dan retry client) memerlukan persetujuan Product Owner dan Lead Architect.
- **Kuadran C (SCADA & Ahli PDAM - `PROPOSED_PENDING_SIGNOFF`):** Kriteria `AC-SCA-01` s.d. `AC-SCA-03` (ambang batas keusangan 15m/60m, deteksi transmit loss 45m, dan clock skew guard 60s) berstatus usulan dan menunggu telaah teknis Kepala Seksi SCADA PDAM.
- **Kuadran D (Validasi Lapangan - `BLOCKED_ON_FIELD_DATA`):** Kriteria `AC-FLD-01` dan `AC-FLD-02` (kalibrasi formazin laboratorium dan data telemetri DMA kontinu 6 bulan) diblokir oleh ketersediaan instrumen dan data fisik di lapangan Kota Tarakan.

---

## 4. INTEGRITAS LINGKUNGAN & KETENTUAN PENGHENTIAN

- **Tidak Ada Mutasi Kode:** Tidak ada berkas di `backend/app/`, `ml/app/`, `frontend/src/`, `database/`, atau direktori tes yang dimodifikasi atau ditambahkan.
- **Tidak Ada Operasi Git / Deployment:** Tidak ada `git add`, `git commit`, `git push`, migrasi database, atau deployment yang dijalankan.
- **Penghentian Tahap:** Tahap STEP 4D.88 dinyatakan **SELESAI PENUH**. Sistem **BERHENTI DI SINI** dan **TIDAK MELANJUTKAN** ke STEP 4D.89 tanpa persetujuan eksplisit berikutnya.
