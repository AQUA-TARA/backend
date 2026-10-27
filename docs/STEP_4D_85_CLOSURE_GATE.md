# STEP 4D.85 — EVIDENCE CLOSURE GATE (BACKEND & GOVERNANCE PERSPECTIVE)

**Document ID:** `BACKEND-CLOSURE-GATE-STEP-4D-85-001`  
**Phase:** STEP 4D.85 — Evidence Gap Closure Planning  
**Date:** 2026-10-18  
**Auditor:** AI Lead Data & ML Systems Architect  
**Scope:** Backend Architecture, Governance Milestone Alignment, and Final Gate Ruling  
**Gate Ruling:** **PASS FOR CLOSURE PLANNING SPECIFICATION ONLY — FIELD VALIDATION REMAINS ON HOLD**  

---

## 1. PENETAPAN KEPUTUSAN EVALUASI GERBANG (GATE RULING)

Berdasarkan audit kelayakan perencanaan penutupan kesenjangan bukti ([STEP_4D_85_EVIDENCE_CLOSURE_PLAN.md](file:///e:/TUGAS/pengkoding\anti\aqua-tara\ml\experiments\baseline_anomaly_detection\STEP_4D_85_EVIDENCE_CLOSURE_PLAN.md)) dan pemetaan dependensi tahapan ([STEP_4D_85_DEPENDENCY_MATRIX.md](file:///e:/TUGAS/pengkoding\anti\aqua-tara\ml\experiments\baseline_anomaly_detection\STEP_4D_85_DEPENDENCY_MATRIX.md)), ditetapkan keputusan resmi gerbang:

$$\mathbf{PASS\ FOR\ CLOSURE\ PLANNING\ SPECIFICATION\ ONLY}$$
$$\mathbf{ALL\ 5\ OPEN\ DECISIONS:\ REMAIN\ OPEN}$$
$$\mathbf{FIELD\ SENSOR\ INTEGRATION\ \&\ VALIDATION:\ HOLD\ —\ FIELD\ DATA\ REQUIRED}$$

---

## 2. PENGUNCIAN GUARDRAIL SISTEM PRODUKSI

Seluruh guardrail sistem produksi dikonfirmasi berada dalam kondisi utuh, terkunci, dan wajib ditaati oleh seluruh lapisan backend dan data science:

1. **Formula Risiko Kualitas Air Kanonikal:**  
   $$\text{Risk\_Score} = \text{round}(0{,}8 \cdot Score_T + 0{,}2 \cdot Score_S)$$  
   Terkunci pada [production_analysis.py L81-82](file:///e:/TUGAS/pengkoding\anti\aqua-tara\ml\app\services\production_analysis.py#L81-L82). Pembobotan 80% kekeruhan dan 20% partisipasi warga tidak boleh diubah.
2. **Kategori & Ambang Batas Risiko:**  
   - $0$–$30$: Jernih / Aman  
   - $31$–$70$: Waspada  
   - $71$–$100$: Bahaya  
   Tidak ada perubahan ambang batas numerik.
3. **Pemisahan Ontologis Modul Hidraulik:**  
   Sinyal tekanan/debit hidraulik SCADA ($Score_H$) tetap menjadi modul paralel terpisah bagi teknisi PDAM dan dilarang disuntikkan ke $Score_S$ warga.
4. **Mekanisme Fail-Safe Fallback Score_T:**  
   Mekanisme fallback kanonikal $Score_T = 30.0$ tetap aktif secara deterministik:
   - Jika daftar node IoT kosong: [production_analysis.py L75-76](file:///e:/TUGAS/pengkoding\anti\aqua-tara\ml\app\services\production_analysis.py#L75-L76).
   - Jika pembacaan sensor turbidity bernilai `None` atau `NaN`: [production_analysis.py L6-8](file:///e:/TUGAS/pengkoding\anti\aqua-tara\ml\app\services\production_analysis.py#L6-L8) via `normalize_turbidity`.
5. **Pembatas Privasi Warga:**  
   - [ReportController.php L90-96](file:///e:/TUGAS/pengkoding\anti\aqua-tara\backend\app\Http\Controllers\ReportController.php#L90-L96) hanya mengembalikan `ticket_id` dan `status`.
   - [LiveMap.vue L7-13](file:///e:/TUGAS/pengkoding\anti\aqua-tara\frontend\src\pages\user\LiveMap.vue#L7-L13) melarang keras ekspos koordinat logger, polyline pipa, dan centroid ML ke warga.

---

## 3. STATUS KEPUTUSAN TERBUKA PASCA-PERENCANAAN (OPEN DECISIONS STATUS)

Penyusunan rencana penutupan bukti ini **TIDAK MENUTUP KEPUTUSAN APAPUN**. Kelima Open Decisions tetap berstatus **OPEN**:

| ID Keputusan | Definisi Masalah | Otoritas Penandatangan Sah Sumber | Status Gate Saat Ini |
|:---:|:---|:---|:---:|
| **OD-SCORE-S-01** | Arsitektur modul sinyal hidraulik ($Score_H$ paralel vs penimpaan $Score_S$) | Product Owner AQUA-TARA, Lead Backend Architect, Direktur Teknik PDAM Tarakan | **OPEN — ARCHITECTURAL HOLD** |
| **OD-SCORE-S-02** | Metode kalibrasi kuantil empiris P95/P99 ke rentang $[0, 100]$ | Lead ML Engineer AQUA-TARA, Konsultan Hidrolika / Spesialis SCADA PDAM | **OPEN — NO FORMULA PERMITTED** |
| **OD-SCORE-S-03** | Protokol sensor SCADA offline, batas stale, dan graceful degradation | SRE AQUA-TARA, Lead Backend Architect, Kepala Seksi SCADA PDAM Tarakan | **OPEN — CONCEPT ONLY, NO HARNESS** |
| **OD-GOV-01** | Kebijakan deduplikasi aduan per warga (< 50m, < 60m) | Product Owner AQUA-TARA, Kepala Bagian Humas / Layanan PDAM Tarakan | **OPEN — POLICY PENDING APPROVAL** |
| **OD-GIS-01** | Pengesahan polygon GeoJSON batas 5 kecamatan Tarakan | Bagian Perencanaan Teknik & GIS PDAM Kota Tarakan, Bappeda / BIG | **OPEN — SOURCE DATA UNAVAILABLE** |

---

## 4. LARANGAN OPERASIONAL & BATASAN KODE

Selama fase perantara dan persiapan penutupan kesenjangan bukti, larangan berikut berlaku mengikat:
1. **Dilarang Mengubah Kode Produksi:** Tidak ada perubahan yang diizinkan pada backend Laravel, frontend Vue, Flask ML service, skema database, migration, seeder, atau konfigurasi deployment.
2. **Dilarang Menutup Open Decisions Secara Prematur:** Tidak ada OD yang boleh diubah statusnya menjadi `CLOSED` hanya karena dokumen perencanaan telah selesai dibuat. Penutupan hanya sah setelah sidang penutupan (Milestone M5) dengan bukti fisik dan tanda tangan lengkap.
3. **Dilarang Membuat/Mengarang Data Lapangan Palsu:** Dilarang membuat dataset telemetri tiruan yang diklaim sebagai data nyata PDAM Kota Tarakan, dilarang memalsukan sertifikat kalibrasi lab, dan dilarang mengarang koordinat galian SPK.
4. **Dilarang Melakukan Git Staging / Commit:** Seluruh dokumen perencanaan dibiarkan pada direktori lokal tanpa dilakukan `git add`, `git commit`, atau `git push`.
5. **Penghentian Tahap:** Pekerjaan dihentikan penuh pada akhir STEP 4D.85. **Dilarang melanjutkan otomatis ke STEP 4D.86** tanpa instruksi eksplisit pengguna.
