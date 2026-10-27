# STEP 4D.84 — READINESS GATE (BACKEND & GOVERNANCE PERSPECTIVE)

**Document ID:** `BACKEND-READINESS-GATE-STEP-4D-84-001`  
**Phase:** STEP 4D.84 — Evidence Register & Final Readiness Gate  
**Date:** 2026-10-18  
**Auditor:** AI Lead Data & ML Systems Architect  
**Scope:** Backend Architecture, API Contracts, Database Schema, and Readiness Gate Ruling  
**Gate Ruling:** **PASS FOR DESIGN & DOCUMENTATION READINESS ONLY — FIELD VALIDATION ON HOLD**  

---

## 1. PENETAPAN KEPUTUSAN EVALUASI GERBANG (GATE RULING)

Berdasarkan audit menyeluruh terhadap register bukti ([STEP_4D_84_EVIDENCE_REGISTER.md](file:///e:/TUGAS/pengkoding\anti\aqua-tara\ml\experiments\baseline_anomaly_detection\STEP_4D_84_EVIDENCE_REGISTER.md)), penilaian kesiapan multi-domain ([STEP_4D_84_READINESS_ASSESSMENT.md](file:///e:/TUGAS/pengkoding\anti\aqua-tara\ml\experiments\baseline_anomaly_detection\STEP_4D_84_READINESS_ASSESSMENT.md)), serta ketiadaan data fisik dari PDAM Kota Tarakan, ditetapkan keputusan resmi:

$$\mathbf{PASS\ FOR\ DESIGN\ \&\ DOCUMENTATION\ READINESS\ ONLY}$$
$$\mathbf{FIELD\ VALIDATION\ \&\ SENSOR\ INTEGRATION:\ HOLD}$$

---

## 2. KONDISI PEMENUHAN GUARDRAIL PRODUKSI

Seluruh guardrail sistem produksi dikonfirmasi berada dalam kondisi utuh dan terkunci:

1. **Formula Risiko Kualitas Air:**  
   $$\text{Risk\_Score} = \text{round}(0{,}8 \cdot Score_T + 0{,}2 \cdot Score_S)$$  
   Terkunci pada [production_analysis.py L81-82](file:///e:/TUGAS/pengkoding\anti\aqua-tara\ml\app\services\production_analysis.py#L81-L82). Bobot 80% kekeruhan dan 20% densitas laporan warga tidak berubah.
2. **Kategori & Ambang Batas Risiko:**  
   - $0$–$30$: Jernih / Aman  
   - $31$–$70$: Waspada  
   - $71$–$100$: Bahaya  
   Tidak berubah dan dipatuhi oleh seluruh controller serta DTO.
3. **Pemisahan Ontologis $Score_H$ vs $Score_S$:**  
   Sinyal tekanan/debit hidraulik SCADA ($Score_H$) tetap berstatus modul paralel eksklusif teknisi, dilarang disuntikkan ke $Score_S$ atau memodifikasi formula risiko warga tanpa persetujuan formal (OD-SCORE-S-01).
4. **Pembatas Privasi Warga:**  
   - Endpoint warga [ReportController.php L90-96](file:///e:/TUGAS/pengkoding\anti\aqua-tara\backend\app\Http\Controllers\ReportController.php#L90-L96) hanya mengembalikan `ticket_id` dan `status`.
   - UI Warga [LiveMap.vue L7-13](file:///e:/TUGAS/pengkoding\anti\aqua-tara\frontend\src\pages\user\LiveMap.vue#L7-L13) melarang ekspos pipa GeoJSON, raw IoT data, dan kluster internal ML.
5. **Mekanisme Fallback $Score_T$:**  
   Fallback kanonikal $Score_T = 30.0$ beroperasi dengan dua trigger terpisah yang jelas:
   - Daftar `iot_nodes` kosong ([L75-76](file:///e:/TUGAS/pengkoding\anti\aqua-tara\ml\app\services\production_analysis.py#L75-L76)).
   - Nilai pembacaan turbidity bernilai `None` atau `NaN` ([L6-8](file:///e:/TUGAS/pengkoding\anti\aqua-tara\ml\app\services\production_analysis.py#L6-L8) via `normalize_turbidity`).

---

## 3. STATUS KEPUTUSAN TERBUKA (OPEN DECISIONS SUMMARY)

| ID Keputusan | Definisi Singkat | Otoritas Penandatangan Sah | Status Gate Saat Ini |
|:---:|:---|:---|:---:|
| **OD-SCORE-S-01** | Arsitektur modul sinyal hidraulik ($Score_H$) | PO AQUA-TARA, Lead Backend Architect, Direktur Teknik PDAM Tarakan | **OPEN — ARCHITECTURAL HOLD** |
| **OD-SCORE-S-02** | Kalibrasi $Score_H$ kontinu ke $[0, 100]$ via kuantil P95/P99 | Lead ML Engineer AQUA-TARA, Konsultan Hidrolika PDAM Tarakan | **OPEN — NO FORMULA PERMITTED** |
| **OD-SCORE-S-03** | Protokol sensor SCADA offline & data stale | SRE AQUA-TARA, Lead Backend Architect, Kepala Seksi SCADA PDAM | **OPEN — CONCEPT ONLY, NO HARNESS** |
| **OD-GOV-01** | Kebijakan deduplikasi aduan per warga (< 50m, < 60m) | Product Owner AQUA-TARA, Kabag Humas/Layanan PDAM Tarakan | **OPEN — POLICY PENDING APPROVAL** |
| **OD-GIS-01** | Pengesahan polygon GeoJSON batas 5 kecamatan Tarakan | Bagian Perencanaan Teknik & GIS PDAM, Bappeda / BIG | **OPEN — SOURCE DATA UNAVAILABLE** |

---

## 4. LARANGAN OPERASIONAL & BATASAN KODE

Selama status gerbang ini berlaku, aturan berikut wajib ditaati oleh seluruh pengembang:
1. **Dilarang Mengubah Kode Produksi:** Tidak diperkenankan melakukan perubahan pada backend Laravel, frontend Vue, Flask ML service, skema database, migration, seeder, atau konfigurasi deployment.
2. **Dilarang Menutup Keputusan Terbuka Secara Sepihak:** Keputusan OD-SCORE-S-01 s.d. OD-GIS-01 dilarang diberi status *CLOSED* sebelum ada berkas fisik/digital bertanda tangan dari pejabat berwenang yang tercatat di atas.
3. **Dilarang Mengaktifkan Ingesti Sensor Produksi:** Pipa transmisi/distribusi PDAM belum dilengkapi sensor terkalibrasi; dilarang membuka endpoint sensor ke lingkungan live.
4. **Dilarang Melakukan Git Staging / Commit:** Seluruh perubahan dokumen tata kelola dibiarkan di working tree lokal tanpa dilakukan `git add`, `git commit`, atau `git push`.
5. **Penghentian Tahap:** Pekerjaan dihentikan penuh pada akhir STEP 4D.84. Tidak diperbolehkan melanjutkan otomatis ke STEP 4D.85 tanpa instruksi eksplisit pengguna.
