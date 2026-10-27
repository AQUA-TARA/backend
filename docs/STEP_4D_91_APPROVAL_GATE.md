# STEP 4D.91 — APPROVAL GATE & LOCAL TEST PLANNING DECISION

**Document ID:** `BACKEND-GATE-STEP-4D-91-001`  
**Phase:** STEP 4D.91 — Pre-Implementation Approval & Local Remediation Test Planning  
**Date:** 2026-10-19  
**Auditor:** AI Lead Data & ML Systems Architect  
**Scope:** Pre-Implementation Review Audit, Local Remediation Test Planning, Boundary Governance, and Final Gate Ruling  
**Gate Ruling:** **PASS FOR PRE-IMPLEMENTATION REVIEW DOCUMENTATION ONLY — NO PRODUCTION OR TEST CODE MUTATION**  

---

## 1. PENETAPAN KEPUTUSAN EVALUASI GERBANG (GATE RULING)

Berdasarkan audit mendalam terhadap paket tinjauan pra-implementasi [STEP_4D_91_PREIMPLEMENTATION_REVIEW.md](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/baseline_anomaly_detection/STEP_4D_91_PREIMPLEMENTATION_REVIEW.md) dan cetak biru rencana pengujian terisolasi [STEP_4D_91_LOCAL_TEST_PLAN.md](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/experiments/baseline_anomaly_detection/STEP_4D_91_LOCAL_TEST_PLAN.md) untuk item remediasi perangkat lunak lokal REM-01, REM-04, REM-05, dan REM-06, ditetapkan keputusan gerbang arsitektural resmi:

$$\mathbf{PASS\ FOR\ PRE-IMPLEMENTATION\ REVIEW\ DOCUMENTATION\ ONLY}$$
$$\mathbf{ALL\ 4\ REMEDIATION\ ITEMS:\ NO-GO\ FOR\ IMPLEMENTATION\ (PENDING\ FORMAL\ SIGNOFF)}$$
$$\mathbf{ALL\ 5\ OPEN\ DECISIONS:\ REMAIN\ OPEN}$$
$$\mathbf{FIELD\ SENSOR\ INTEGRATION\ \&\ VALIDATION:\ HOLD\ —\ FIELD\ DATA\ REQUIRED}$$

---

## 2. KEPATUHAN BOUNDARY, FORMULA, DAN GOVERNANCE

Gerbang STEP 4D.91 menegaskan kepatuhan penuh terhadap seluruh batasan arsitektur sistem AQUA-TARA:

1. **Formula Produksi Tidak Berubah Mutlak:**
   $$\text{Risk\_Score} = \text{round}(0{,}8 \cdot Score_T + 0{,}2 \cdot Score_S)$$
   Ambang batas klasifikasi risiko tetap tidak berubah:
   - $0$–$30$: Jernih / Risiko Rendah
   - $31$–$70$: Waspada / Risiko Sedang
   - $71$–$100$: Bahaya / Risiko Tinggi
2. **Definisi $Score_S$ Terkunci:** $Score_S = \min(100.0, \text{density} \times 15.0)$, di mana $\text{density}$ adalah jumlah aduan warga dalam klaster geospasial K-Means.
3. **Pemisahan Ontologis Sinyal Hidraulik:** Sinyal anomali hidraulik SCADA ($Score_H$) terpisah mutlak dari $Score_S$. $Score_S$ mengukur keluhan persepsi warga, bukan estimasi fisik kebocoran pipa air.
4. **Larangan Salah Tafsir Fallback $Score_T = 30.0$:**
   Ditegaskan secara mengikat bahwa fallback numerik $Score_T = 30.0$ hanyalah *defensive numerical default* agar sistem perangkat lunak tidak melempar eksepsi saat array kosong atau bernilai null, dan **SAMA SEKALI BUKAN BUKTI KUALITAS AIR DI LAPANGAN SECARA FISIK BERSIH/AMAN**.
5. **Status Kelima Keputusan Terbuka (Open Decisions):**

| ID Keputusan | Masalah Pokok | Status Gate STEP 4D.91 | Justifikasi Faktual |
|:---:|:---|:---:|:---|
| **OD-SCORE-S-01** | Arsitektur modul sinyal hidraulik paralel $Score_H$ | **`OPEN`** | Sinyal SCADA tetap terisolasi; tidak ada percampuran ke $Score_S$. |
| **OD-SCORE-S-02** | Metode kalibrasi kuantil P95/P99 ke skala 0–100 | **`OPEN`** | Tetap menunggu dataset telemetri baseline 6 bulan PDAM Tarakan. |
| **OD-SCORE-S-03** | Protokol sensor SCADA offline & batas data stale | **`OPEN`** | Spesifikasi stale telah disiapkan; ambang 15m/60m tetap PROPOSED. |
| **OD-GOV-01** | Kebijakan deduplikasi aduan per warga (<50m, <60m) | **`OPEN`** | Tidak ada kode deduplikasi yang diimplementasikan di produksi. |
| **OD-GIS-01** | Batas wilayah resmi GeoJSON 5 kecamatan Tarakan | **`OPEN`** | Menunggu berkas Shapefile resmi Bappeda/BIG; library `shapely` tidak ditambahkan. |

---

## 3. STATUS PRE-IMPLEMENTATION GO / NO-GO PER ITEM REMEDIASI

Semua item remediasi lokal ditahan pada status **`NO-GO FOR IMPLEMENTATION`** sampai adanya penandatanganan memo persetujuan:
1. **REM-01 (Null Turbidity Schema):** Status **`NO-GO (SPECIFICATION READY)`** — Menunggu penandatanganan memo persetujuan oleh Lead Backend Architect dan Lead ML Engineer.
2. **REM-04 (Clock Skew Tolerance & Future Guard):** Status **`NO-GO (SPECIFICATION READY)`** — Menunggu penandatanganan memo persetujuan oleh Lead Firmware Engineer dan Lead Backend Architect.
3. **REM-05 (HTTP Client Retry Policy):** Status **`NO-GO (SPECIFICATION READY)`** — Menunggu penandatanganan memo persetujuan oleh Site Reliability Engineer (SRE).
4. **REM-06 (Metadata Telemetry Status Fallback):** Status **`NO-GO (SPECIFICATION READY)`** — Menunggu penandatanganan memo persetujuan dan verifikasi desain dashboard visual oleh Product Owner.

---

## 4. INTEGRITAS LINGKUNGAN & KETENTUAN PENGHENTIAN

- **Tidak Ada Mutasi Kode:** Tidak ada berkas di `backend/app/`, `ml/app/`, `frontend/src/`, `database/`, atau direktori tes yang dimodifikasi atau ditambahkan.
- **Tidak Ada Operasi Git / Deployment:** Tidak ada `git add`, `git commit`, `git push`, migrasi database, seeder, atau deployment yang dijalankan.
- **Penghentian Tahap:** Tahap STEP 4D.91 dinyatakan **SELESAI PENUH**. Sistem **BERHENTI DI SINI** dan **TIDAK MELANJUTKAN** ke STEP 4D.92 tanpa instruksi eksplisit berikutnya.
