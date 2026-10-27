# STEP 4D.97 — APPROVAL READINESS GATE DECISION

**Document ID:** `BACKEND-GATE-STEP-4D-97-001`  
**Phase:** STEP 4D.97 — Pre-Submission Approval Readiness Review  
**Date:** 2026-10-19  
**Auditor:** AI Lead Data & ML Systems Architect  
**Scope:** Pre-Submission Review Governance, Decision Questions Verification, Boundary Enforcement, and Final Gate Ruling  
**Gate Ruling:** **PASS FOR PRE-SUBMISSION DOCUMENTATION REVIEW ONLY — NO PRODUCTION OR TEST CODE MUTATION**  

---

## 1. PENETAPAN KEPUTUSAN EVALUASI GERBANG (GATE RULING)

Berdasarkan hasil penelaahan kritis pra-pengajuan pada [STEP_4D_97_PRE_SUBMISSION_REVIEW.md](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/baseline_anomaly_detection/STEP_4D_97_PRE_SUBMISSION_REVIEW.md) dan perumusan daftar pertanyaan keputusan eksplisit pada [STEP_4D_97_DECISION_QUESTIONS.md](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/baseline_anomaly_detection/STEP_4D_97_DECISION_QUESTIONS.md), ditetapkan keputusan gerbang arsitektural resmi:

$$\mathbf{PASS\ FOR\ PRE-SUBMISSION\ DOCUMENTATION\ REVIEW\ ONLY}$$
$$\mathbf{ALL\ 4\ LOCAL\ REMEDIATION\ ITEMS:\ REMAIN\ NO-GO\ (PENDING\ DECISION\ OWNER\ RESPONSE)}$$
$$\mathbf{ALL\ 5\ OPEN\ DECISIONS:\ REMAIN\ OPEN}$$
$$\mathbf{FIELD\ SENSOR\ INTEGRATION\ \&\ VALIDATION:\ HOLD\ —\ FIELD\ DATA\ REQUIRED}$$

---

## 2. KEPATUHAN BOUNDARY, FORMULA, DAN GOVERNANCE

Gerbang STEP 4D.97 menegaskan kepatuhan 100% terhadap seluruh batasan arsitektur sistem AQUA-TARA:

1. **Formula Produksi Tidak Berubah Mutlak:**
   $$\text{Risk\_Score} = \text{round}(0{,}8 \cdot Score_T + 0{,}2 \cdot Score_S)$$
   Ambang batas klasifikasi risiko tetap tidak berubah:
   - $0$–$30$: Jernih / Risiko Rendah
   - $31$–$70$: Waspada / Risiko Sedang
   - $71$–$100$: Bahaya / Risiko Tinggi
2. **Definisi $Score_S$ Terkanonisasi:**
   $$Score_S = \min(100.0, N_{cluster} \times 15.0)$$
   di mana $N_{cluster}$ adalah jumlah aduan warga dalam klaster geospasial K-Means (`density = cluster_counts[label]` pada `ml/app/services/production_analysis.py:72`). Istilah `density` di kode Python terbukti sebagai alias penamaan variabel lokal untuk jumlah laporan, dan tidak ada perbedaan matematis atau perubahan semantik fisik dalam basis kode.
3. **Pemisahan Ontologis Sinyal Hidraulik:** Sinyal anomali hidraulik SCADA ($Score_H$) terpisah mutlak dari $Score_S$. $Score_S$ mengukur keluhan persepsi warga, bukan estimasi fisik kebocoran pipa air.
4. **Larangan Salah Tafsir Fallback $Score_T = 30.0$:**
   Ditegaskan secara mengikat bahwa fallback numerik $Score_T = 30.0$ hanyalah *defensive numerical default* agar komputasi tidak melempar eksepsi saat data sensor tidak tersedia, dan **SAMA SEKALI BUKAN BUKTI KUALITAS AIR DI LAPANGAN SECARA FISIK BERSIH/AMAN**. Pengujian sintetis perangkat lunak **BUKAN VALIDASI SENSOR LAPANGAN**.
5. **Status Kelima Keputusan Terbuka (Open Decisions):**

| ID Keputusan | Masalah Pokok | Status Gate STEP 4D.97 | Justifikasi Faktual |
|:---:|:---|:---:|:---|
| **OD-SCORE-S-01** | Arsitektur modul sinyal hidraulik paralel $Score_H$ | **`OPEN`** | Sinyal SCADA tetap terisolasi; tidak ada percampuran ke $Score_S$. |
| **OD-SCORE-S-02** | Metode kalibrasi kuantil P95/P99 ke skala 0–100 | **`OPEN`** | Tetap menunggu dataset telemetri baseline 6 bulan PDAM Tarakan. |
| **OD-SCORE-S-03** | Protokol sensor SCADA offline & batas data stale | **`OPEN`** | Parameter kesegaran waktu 15m/60m tetap berstatus PROPOSED. |
| **OD-GOV-01** | Kebijakan deduplikasi aduan per warga (<50m, <60m) | **`OPEN`** | Tidak ada kode deduplikasi yang diimplementasikan di produksi. |
| **OD-GIS-01** | Batas wilayah resmi GeoJSON 5 kecamatan Tarakan | **`OPEN`** | Menunggu berkas Shapefile resmi Bappeda/BIG; library `shapely` tidak ditambahkan. |

---

## 3. STATUS PRE-SUBMISSION GO / NO-GO DAN TANGGUNG JAWAB PENGAMBIL KEPUTUSAN

Semua item remediasi lokal ditahan pada status **`NO-GO FOR IMPLEMENTATION`**:
1. **REM-01 (Null Turbidity Schema):** Status **`NO-GO`** — Menunggu jawaban kuesioner keputusan dan memo resmi dari Lead Backend Architect dan Lead ML Engineer.
2. **REM-04 (Clock Skew Ingress Guard):** Status **`NO-GO`** — Menunggu jawaban kuesioner keputusan dan spesifikasi RTC dari Lead Firmware Engineer dan Lead Backend Architect.
3. **REM-05 (HTTP Client Retry Policy):** Status **`NO-GO`** — Menunggu jawaban kuesioner keputusan dan rekomendasi load test dari Site Reliability Engineer (SRE) dan Lead Backend Architect.
4. **REM-06 (Metadata Telemetry Status Fallback):** Status **`NO-GO`** — Menunggu jawaban kuesioner keputusan dan nota kebijakan produk dari Product Owner AQUA-TARA dan Lead Backend Architect.

---

## 4. INTEGRITAS LINGKUNGAN & KETENTUAN PENGHENTIAN

- **Tidak Ada Mutasi Kode:** Tidak ada berkas di `backend/app/`, `ml/app/`, `frontend/src/`, `database/`, atau direktori tes yang dimodifikasi atau ditambahkan.
- **Tidak Ada Operasi Git / Deployment:** Tidak ada `git add`, `git commit`, `git push`, migrasi database, seeder, atau deployment yang dijalankan.
- **Penghentian Tahap:** Tahap STEP 4D.97 dinyatakan **SELESAI PENUH**. Sistem **BERHENTI DI SINI** dan **TIDAK MELANJUTKAN** ke STEP 4D.98 tanpa instruksi eksplisit berikutnya.
