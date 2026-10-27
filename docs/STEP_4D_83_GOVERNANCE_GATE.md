# STEP 4D.83 — GOVERNANCE GATE (BACKEND & INTEGRATION PERSPECTIVE)

**Document ID:** `BACKEND-GOVERNANCE-GATE-STEP-4D-83-001`
**Phase:** STEP 4D.83 — Open Decisions Governance & Validation Sign-off Preparation
**Date:** 2026-10-17
**Auditor:** AI Lead Data & ML Systems Architect
**Scope:** Backend Laravel, API Contract, Database Schema, dan Infrastruktur Integrasi
**Status:** GOVERNANCE SPECIFICATION ONLY (READ-ONLY / NO CODE MUTATION)

**Production Guardrails yang Berlaku:**
- Formula Produksi: `Risk_Score = round(0.8 * Score_T + 0.2 * Score_S)` — TIDAK BERUBAH
- Ambang Batas: 0–30 Jernih/Aman, 31–70 Waspada, 71–100 Bahaya — TIDAK BERUBAH
- `Score_H` tetap terpisah dari `Score_S` — TETAP BERLAKU
- Integrasi sensor produksi: **HOLD — FIELD DATA REQUIRED**

---

## 1. TUJUAN DOKUMEN

Dokumen ini mendefinisikan gate tata kelola dari perspektif backend dan integrasi untuk proyek AQUA-TARA. Dokumen ini adalah referensi bagi Lead Backend Architect, Site Reliability Engineer (SRE), dan developer yang bekerja pada lapisan API, database, dan orkestrasi layanan. Dokumen ini tidak mengubah kode manapun.

---

## 2. PETA STATUS KODE PRODUKSI SAAT INI

Berikut adalah status faktual komponen backend yang relevan dengan kelima Open Decisions:

### 2.1 Komponen yang Sudah Stabil & Tidak Boleh Diubah

| Komponen | File | Fungsi | Status |
|:---|:---|:---|:---:|
| Formula Risiko Produksi | [production_analysis.py L81-82](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/app/services/production_analysis.py#L81-L82) | `Risk_Score = round(0.8 * Score_T + 0.2 * Score_S)` | LOCKED — JANGAN DIUBAH |
| Fallback Score_T | [production_analysis.py L75-76](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/app/services/production_analysis.py#L75-L76) & [L6-8](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/app/services/production_analysis.py#L6-L8) | `Score_T = 30.0` jika daftar `iot_nodes` kosong (L75-76), atau jika nilai sensor turbidity `None`/`NaN` (L6-8 via `normalize_turbidity`) | LOCKED — JANGAN DIUBAH |
| K-Means Entry Point | [production_analysis.py L23](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/app/services/production_analysis.py#L23) | `run_production_analysis(data)` — deterministik, random_state=42 | STABLE — JANGAN DIUBAH |
| Score_S Formula | [production_analysis.py L73](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/app/services/production_analysis.py#L73) | `score_s = min(100.0, density * 15.0)` | LOCKED — JANGAN DIUBAH |
| Privacy Boundary Citizen | [LiveMap.vue L7-13](file:///e:/TUGAS/pengkoding/anti/aqua-tara/frontend/src/pages/user/LiveMap.vue#L7-L13) | Zero ekspos pipe GeoJSON, raw IoT, centroid/cluster ML | LOCKED — JANGAN DIUBAH |
| Privacy Boundary Backend | [ReportController.php L90-96](file:///e:/TUGAS/pengkoding/anti/aqua-tara/backend/app/Http/Controllers/ReportController.php#L90-L96) | Hanya ticket_id dan status yang dikembalikan | LOCKED — JANGAN DIUBAH |

### 2.2 Komponen yang Masih Menggunakan Mock / Belum Diimplementasikan

| Komponen | File | Keterangan | OD Terkait |
|:---|:---|:---|:---|
| Public Map API | [mockServer.js handler app.get('/api/map/public') L173-221](file:///e:/TUGAS/pengkoding/anti/aqua-tara/frontend/mockServer.js#L173-L221) | Endpoint `/api/map/public` belum diimplementasikan di Laravel api.php — masih mock | OD-SCORE-S-01 |
| Spatial Ingress Filter | Belum ada | Guard filter batas wilayah Tarakan di API ingress laporan warga belum diimplementasikan | OD-GIS-01 |
| Deduplication Logic | Belum ada | Logika deduplikasi citizen_id + radius + jeda belum ada di kode produksi | OD-GOV-01 |
| Score_H Module | Belum ada | Modul deteksi anomali hidraulik Score_H belum diimplementasikan | OD-SCORE-S-01/02/03 |
| SCADA Ingestion Endpoint | Belum ada | Endpoint untuk telemetri tekanan DMA belum ada di api.php | OD-SCORE-S-01/02/03 |

---

## 3. LARANGAN IMPLEMENTASI YANG BERLAKU SEKARANG

Berdasarkan status gate HOLD yang ditetapkan di STEP 4D.76 sampai 4D.83, larangan berikut berlaku aktif sampai ada pencabutan resmi dari Product Owner dengan tanda tangan formal:

### 3.1 Larangan Mutlak terhadap Formula & Kode Produksi

1. **DILARANG** mengubah formula `Risk_Score = round(0.8 * Score_T + 0.2 * Score_S)`.
2. **DILARANG** mengubah threshold risiko (0–30, 31–70, 71–100).
3. **DILARANG** mengganti atau menambahkan variabel baru ke dalam vektor fitur K-Means tanpa melalui gate OD-SCORE-S-01.
4. **DILARANG** memetakan `Score_H` ke dalam `Score_S` atau menggabungkan keduanya dalam formula apapun.
5. **DILARANG** membuat formula normalisasi `Score_H` ke rentang [0, 100] tanpa data kalibrasi kuantil P95/P99 lokal (OD-SCORE-S-02).

### 3.2 Larangan terhadap Integrasi Sensor

1. **DILARANG** mengaktifkan endpoint ingesti sensor tekanan SCADA di lingkungan produksi.
2. **DILARANG** menggunakan data BattLeDIM sebagai pengganti data logger PDAM Tarakan dalam produksi.
3. **DILARANG** mengklaim sistem telah "tervalidasi" hanya berdasarkan eksperimen sintetis.

### 3.3 Larangan terhadap Perubahan Database & Migration

1. **DILARANG** menambahkan kolom `score_h` atau `hydraulic_anomaly` ke tabel `risk_results` sebelum OD-SCORE-S-01 ditandatangani.
2. **DILARANG** membuat tabel baru untuk telemetri tekanan PDAM di database produksi sebelum OD-SCORE-S-01 dan OD-SCORE-S-03 ditandatangani.
3. **DILARANG** mengubah tabel `clusters` untuk menyimpan centroid Score_H.

### 3.4 Larangan terhadap API Contract

1. **DILARANG** mengekspos koordinat sensor IoT, centroid ML internal, atau data pipa SCADA melalui endpoint `GET /api/map/public` atau endpoint warga manapun.
2. **DILARANG** membuat endpoint baru `POST /api/iot/pressure` di produksi sebelum OD-SCORE-S-01/03 diselesaikan.

---

## 4. SPESIFIKASI TEKNIS YANG DIUSULKAN (BELUM DIIMPLEMENTASIKAN)

Bagian ini mendokumentasikan arsitektur yang diusulkan untuk referensi masa depan. **Semua item di bawah ini adalah USULAN, bukan spesifikasi final.** Implementasi memerlukan persetujuan formal dari seluruh penandatangan OD terkait.

### 4.1 Usulan Arsitektur Score_H (jika OD-SCORE-S-01 disetujui — Opsi C)

```
[USULAN — BELUM DIIMPLEMENTASIKAN]

Alur Score_H yang diusulkan:
  POST /api/iot/pressure (endpoint baru, admin-only)
      |
      v
  pressure_readings table (tabel baru, migration belum dibuat)
      |
      v
  IsolationForest Model (modul Flask terpisah, belum dibuat)
      |
      v
  Score_H = f(anomaly_score, kuantil P95/P99 DMA lokal) [OD-SCORE-S-02]
      |
      v
  Dashboard Teknisi PDAM (role: admin-technician)
      |
      TIDAK TERHUBUNG ke Score_S atau Risk_Score warga manapun
```

### 4.2 Usulan Implementasi Deduplikasi (jika OD-GOV-01 disetujui — Opsi B)

```
[USULAN — BELUM DIIMPLEMENTASIKAN]

Lokasi kode yang diusulkan:
  backend/app/Services/MLAnalysisService.php
  Tambahkan metode: buildDatasetWithDeduplication()

  Logika yang diusulkan:
  SELECT r.report_id, r.latitude, r.longitude
  FROM reports r
  INNER JOIN tickets t ON t.report_id = r.id
  WHERE t.status != 'CLOSED'
    AND NOT EXISTS (
      SELECT 1 FROM reports r2
      INNER JOIN tickets t2 ON t2.report_id = r2.id
      WHERE t2.citizen_id = t.citizen_id        -- pengguna yang sama
        AND r2.created_at BETWEEN r.created_at - INTERVAL '60 minutes' AND r.created_at
        AND ST_Distance(r.coords, r2.coords) < 50  -- radius < 50 m
        AND r2.id < r.id                           -- bukan laporan pertama
    )

  Catatan:
  - Parameter 60 menit dan 50 m adalah USULAN BELUM DISETUJUI.
  - Perlu instalasi PostGIS untuk ST_Distance; atau implementasi Haversine manual.
  - Memerlukan persetujuan tertulis OD-GOV-01 sebelum diimplementasikan.
```

### 4.3 Usulan Implementasi Filter Spasial Ingress (jika OD-GIS-01 disetujui)

```
[USULAN — BELUM DIIMPLEMENTASIKAN]

Lokasi kode yang diusulkan:
  backend/app/Http/Requests/StoreReportRequest.php
  Tambahkan aturan validasi: 'latitude' => ['required', 'numeric', new WithinTarakanBounds]
  Buat custom Rule: backend/app/Rules/WithinTarakanBounds.php

  Logika yang diusulkan:
  - Jika shapefile GeoJSON resmi tersedia (OD-GIS-01 CLOSED):
      gunakan library spatial untuk point-in-polygon check
  - Jika hanya bounding box sementara (Opsi A):
      latitude: antara 3.00 dan 3.60; longitude: antara 117.40 dan 117.80
      Catatan: koordinat bounding box ini adalah USULAN TEKNIS, bukan batas hukum resmi.

  Memerlukan pengesahan shapefile GeoJSON resmi dari Bappeda/BIG sebelum diimplementasikan.
```

---

## 5. PROTOKOL REVIEW & ESKALASI

Jika seorang developer menemukan kebutuhan untuk mengubah komponen yang termasuk dalam kategori LOCKED atau komponen yang terkait dengan kelima Open Decisions di atas, protokol berikut harus diikuti:

1. **Jangan mengimplementasikan perubahan** sebelum proses review selesai.
2. **Ajukan RFC (Request for Change)** secara tertulis ke Product Owner dan Lead Backend Architect.
3. **Identifikasi OD yang terdampak** — apakah perubahan yang diusulkan memerlukan penutupan salah satu OD terlebih dahulu.
4. **Dapatkan tanda tangan** dari seluruh pihak yang wajib menyetujui OD terkait.
5. **Catat perubahan** dalam DECISIONS.md dengan nomor keputusan baru.

---

## 6. GATE AKHIR — PERSPEKTIF BACKEND

```
+======================================================================+
|    BACKEND GOVERNANCE GATE — STEP 4D.83                              |
|======================================================================|
|  Kode produksi                 : TIDAK BERUBAH — TERKUNCI            |
|  Formula Risk_Score            : TIDAK BERUBAH — TERKUNCI            |
|  Score_H implementasi          : BELUM ADA — MENUNGGU OD-SCORE-S-01  |
|  Filter spasial ingress        : BELUM ADA — MENUNGGU OD-GIS-01      |
|  Deduplikasi laporan           : BELUM ADA — MENUNGGU OD-GOV-01      |
|  Endpoint tekanan SCADA        : BELUM ADA — MENUNGGU OD-SCORE-S-01  |
|  Migration tabel pressure      : BELUM ADA — MENUNGGU OD-SCORE-S-01  |
|  Automated privacy guard test  : BELUM ADA — DIPERLUKAN SEBELUM PROD |
|                                                                       |
|  GATE: PASS  --> Dokumentasi governance backend (dokumen ini)        |
|  GATE: HOLD  --> Semua implementasi fitur baru terkait 5 OD          |
|  GATE: HOLD  --> Integrasi sensor produksi                           |
|  GATE: HOLD  --> Field validation tanpa sign-off formal              |
|                                                                       |
|  STATUS 5 OPEN DECISIONS: 0/5 CLOSED                                 |
+======================================================================+
```

---

## 7. RIWAYAT KEPUTUSAN TERKAIT (REFERENSI SILANG)

| STEP | Keputusan | Hasil |
|:---|:---|:---|
| STEP 4D.76 | Discrepancy Resolution & Hydraulic Integration Decision Gate | HOLD — ADDITIONAL EVIDENCE REQUIRED |
| STEP 4D.77 | Sensor Readiness & ML Validation Planning Gate | HOLD — FIELD DATA REQUIRED |
| STEP 4D.78 | Spatial Clustering Validation & Proposal Alignment Gate | PASS FOR SPATIAL VALIDATION DESIGN ONLY |
| STEP 4D.79 | Clustering Robustness, Score_S Semantics & Operational Validation Gate | PASS FOR ROBUSTNESS DESIGN ONLY |
| STEP 4D.80 | Spatial Validation Hardening & Score_S Definition Review | PASS FOR VALIDATION DESIGN ONLY |
| STEP 4D.81 | Spatial Clustering Decision Validation & Score_S Governance | PASS FOR VALIDATION DESIGN ONLY |
| STEP 4D.82 | Field Validation Readiness & Evidence-Based Acceptance Criteria | PASS FOR READINESS SPECIFICATION ONLY |
| **STEP 4D.83** | **Open Decisions Governance & Validation Sign-off Preparation** | **PASS FOR GOVERNANCE DOCUMENTATION ONLY / HOLD FOR PRODUCTION** |

Dokumen lengkap checklist tersedia di:
- `ml/experiments/baseline_anomaly_detection/STEP_4D_83_OPEN_DECISIONS_GOVERNANCE.md`
- `ml/experiments/baseline_anomaly_detection/STEP_4D_83_SIGNOFF_CHECKLIST.md`
