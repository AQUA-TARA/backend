# STEP 4D.75 — SCORE_S EXTERNAL HYDRAULIC INTEGRATION DESIGN

**Document ID:** `DESIGN-SCORE-S-STEP-4D-75-001`  
**Phase:** STEP 4D.75 — Experimental Synthesis & Score_S Integration Design  
**Date:** 2026-10-16  
**Auditor / Author:** AI Lead Data & ML Systems Architect  
**Status:** **PROPOSED CONCEPTUAL DESIGN (READ-ONLY ARCHITECTURE SPECIFICATION)**  
**Target Audience:** Engineering Backend Lead, ML Engineer, SCADA Integration Specialist  
**Production Guardrails:** Zero Production Mutation, Zero Database Mutation, Zero Schema Change, Canonical Risk Formula Unchanged (`round(0.6 * Score_T + 0.4 * Score_S)`), Risk Level Thresholds Unchanged (0–30 Jernih/Aman, 31–70 Waspada, 71–100 Bahaya).

---

## 1. PENDAHULUAN & PRINSIP DASAR ARSITEKTUR

### 1.1 Latar Belakang Sistem Produksi Saat Ini
Sistem produksi AQUA-TARA menghitung skor risiko gabungan pada level laporan warga (`RiskResult`) menggunakan formula kanonikal:
$$\text{final\_score} = \text{round}(0.6 \cdot Score_T + 0.4 \cdot Score_S)$$
Di mana dalam implementasi saat ini ([production_analysis.py](file:///e:/TUGAS/pengkoding/anti/aqua-tara/ml/app/services/production_analysis.py#L71-L87)):
- $Score_T \in [0, 100]$: *Turbidity Score* yang dinormalisasi dari node IoT sensor kualitas air terdekat (atau fallback 30 jika tidak ada node).
- $Score_S \in [0, 100]$: *Spatial Density Score* yang dihitung dari jumlah laporan warga di dalam kluster spasial K-Means ($\min(100.0, \text{density} \times 15.0)$).

Ambang batas kategori operasional diatur secara kanonikal:
- **0 – 30:** Jernih / Aman (Optimal)
- **31 – 70:** Waspada (Indikasi Keruh Ringan)
- **71 – 100:** Bahaya (Keruh Tinggi / Risiko Kritis)

### 1.2 Prinsip Pemisahan Independen (Decoupling Principle)
Anomali hidraulik SCADA (tekanan dan debit pipa PDAM) **TIDAK OTOMATIS SAMA DENGAN SCORE_S**.
Oleh karena itu, rancangan integrasi eksternal ini menetapkan prinsip arsitektur mutlak:
1. **Zero Impact on Citizen Mobile-First Core:** Pengguna warga tidak boleh dibebani kerumitan telemetri SCADA atau data infrastruktur privat PDAM.
2. **Modular External Hydraulic Service:** Pemrosesan telemetri hidraulik beroperasi pada worker terpisah di luar siklus permohonan HTTP warga.
3. **No Premature Mapping:** Sebelum ada kesepakatan formal bersama utilitas air mitra, tidak boleh ada pemetaan skor sepihak yang menimpa definisi $Score_S$ produksi.

---

## 2. ARSITEKTUR KONSEPTUAL DAN ALIRAN DATA

Rancangan konseptual mengusulkan pemisahan subsistem telemetri hidraulik eksternal:

```mermaid
flowchart TD
    subgraph ExternalProvider["PDAM / External SCADA Provider"]
        SCADA_Sensors["Pressure Transducers & Flow Meters"]
        SCADA_Gateway["SCADA Historian / MQTT Broker"]
    end

    subgraph HydraulicIngestSubsystem["Proposed External Hydraulic Subsystem (Isolated)"]
        IngestBuffer["Secure Ingest Adapter (Webhook / MQTT Subscriber)"]
        ValidationEngine["Quality, Boundary & Deduplication Filter"]
        HydraulicCache[("Time-Series Telemetry Store (InfluxDB / Timescale)")]
        AnomalyInference["Hydraulic Anomaly Pipeline (Isolation Forest + Persist 15m)"]
    end

    subgraph ProductionCore["Current AQUA-TARA Production Core (Unchanged)"]
        MLService["ML Analysis Worker (production_analysis.py)"]
        BackendLaravel["Laravel Core (Reports, Triage, RiskResults)"]
        FrontendVue["Vue Admin Dashboard & Citizen Mobile Map"]
    end

    SCADA_Sensors --> SCADA_Gateway
    SCADA_Gateway -->|TLS Authenticated Push/Pull| IngestBuffer
    IngestBuffer --> ValidationEngine
    ValidationEngine --> HydraulicCache
    HydraulicCache --> AnomalyInference

    AnomalyInference -.->|Proposed Scored Metric (Under Review)| MLService
    MLService --> BackendLaravel
    BackendLaravel --> FrontendVue
```

---

## 3. DATA CONTRACT & SCHEMA PROPOSAL (CONCEPTUAL ONLY)

Kontrak data berikut bersifat **USULAN RANCANGAN** dan belum diimplementasikan pada database produksi:

### 3.1 Skema Telemetri Masuk (Ingress Telemetry Payload)
```json
{
  "$schema": "https://aqua-tara.id/schemas/v1/hydraulic-telemetry.json",
  "utility_id": "PDAM-TIRTA-01",
  "dma_zone_id": "DMA-CENTRAL-04",
  "batch_id": "bfa892a0-4a88-4e89-8d82-c21ad22f0091",
  "timestamp_utc": "2026-10-16T02:00:00Z",
  "telemetry": [
    {
      "sensor_id": "PT-n31",
      "metric_type": "pressure",
      "value": 3.42,
      "unit": "bar",
      "quality_flag": "GOOD"
    },
    {
      "sensor_id": "FM-p227",
      "metric_type": "flow_rate",
      "value": 78.4,
      "unit": "m3_per_hour",
      "quality_flag": "GOOD"
    }
  ]
}
```

### 3.2 Kamus Data dan Standarisasi Satuan
- **Timestamp:** Wajib ISO-8601 dengan zona waktu eksplisit (disarankan UTC / `Z` untuk penyimpanan internal). Format lokal (WIB / `+07:00`) wajib dikonversi di ingress layer.
- **Satuan Tekanan:** Standarisasi ke **bar** ($1\text{ bar} = 100\text{ kPa} \approx 10,197\text{ m head}$).
- **Satuan Debit:** Standarisasi ke **$\text{m}^3/\text{h}$** atau **L/s** ($1\text{ m}^3/\text{h} \approx 0,2778\text{ L/s}$).
- **Sensor Identifiers:** Wajib terdaftar dalam katalog aset hidraulik PDAM yang dipetakan ke zona DMA (*District Metered Area*).

---

## 4. PENANGANAN KUALITAS DATA & KEGAGALAN (ROBUSTNESS)

Modul hidraulik eksternal harus tahan terhadap kegagalan telemetri nyata:

1. **Deduplikasi & Jaringan Terlambat (*Out-of-Order Data*):**  
   - Setiap batch telemetri divalidasi berdasarkan pasangan `(sensor_id, timestamp_utc)`. Data duplikat ditolak secara idempoten.
   - Data terlambat (*late arriving data*) dengan jeda $> 60$ menit diproses ke arsip historis tanpa memicu alarm instan ke operator.
2. **Sensor Offline & Imputasi Hilang (*Missing Data Imputation*):**  
   - Jika sensor offline $\le 15$ menit (1–3 timestep 5 menit): Imputasi linier (*forward fill* dibatasi maksimal 3 langkah berturut-turut).
   - Jika sensor offline $> 15$ menit: Tandai sensor sebagai `SENSOR_OFFLINE`. Model multivariat tidak boleh mengasumsikan nilai 0, melainkan beralih ke sub-model parsial atau memicu status degradasi *telemetry degraded*.
3. **Outlier Fisik Mustahil (*Spike Filtering*):**  
   - Pengecekan ambang batas fisik pipa: Tekanan negatif ($< 0$ bar) atau tekanan melebihi kapasitas pompa ($> 16$ bar) otomatis ditandai `QUALITY_SUSPECT` dan tidak diikutkan dalam inferensi Isolation Forest.

---

## 5. VERSIONING, PROVENANCE & CALIBRATION

1. **Model Versioning:** Setiap prediksi hidraulik wajib menyertakan identitas model (`model_name`, `model_version`, `weights_digest`, `trained_on_dataset_id`).
2. **Data Provenance:** Audit log wajib mencatat asal telemetri (ID utilitas penyedia, waktu penerimaan, dan status kalibrasi sensor).
3. **Periodik Re-kalibrasi:** Koefisien kekasaran pipa dan dinamika beban musiman PDAM membutuhkan re-kalibrasi berkala (misal: tiap 3 bulan) guna mencegah *concept drift*.

---

## 6. OBSERVABILITY, LOGGING & HUMAN REVIEW

1. **Metrik Operasional:**
   - Frekuensi episode alarm per zona DMA per minggu (target: $\le 3,0$ episode/hari).
   - Latensi pipa ingest-to-inference ($< 30$ detik dari penerimaan telemetri).
   - Tingkat ketersediaan sensor SCADA (% uptime bulanan).
2. **Protokol Human-in-the-Loop:**
   - Setiap anomali hidraulik yang berpotensi memicu eskalasi operasional wajib ditinjau oleh operator SCADA melalui antarmuka *Triage Review* sebelum kru lapangan diberangkatkan.
   - Peringatan sistem wajib menggunakan terminologi yang sesuai aturan: **"Area Prioritas Pemeriksaan"** atau **"Indikasi Deviasi Hidraulik"**, dan **DILARANG menyatakan lokasi centroid sebagai titik kebocoran fisik pasti**.

---

## 7. OPEN DESIGN DECISIONS (KEPUTUSAN DESAIN TERBUKA)

Berikut adalah daftar keputusan arsitektur yang **MASIH TERBUKA** dan membutuhkan konsensus antar tim pemangku kepentingan:

| Kode Keputusan | Topik Keputusan | Deskripsi Isu & Opsi yang Dipertimbangkan | Rekomendasi Awal | Status |
|:---:|:---|:---|:---|:---:|
| **OD-SCORE-S-01** | **Semantik Pemetaan $Score_S$** | Apakah anomali hidraulik SCADA akan: <br>*(Opsi A)* Menggantikan sepenuhnya densitas laporan warga pada $Score_S$, <br>*(Opsi B)* Digabung sebagai multi-faktor $(\alpha \cdot \text{Density} + \beta \cdot \text{Hydraulic})$, atau <br>*(Opsi C)* Tetap terpisah sebagai parameter independen $Score_H$? | **Opsi C (Pertahankan formula produksi, jadikan $Score_H$ modul analitik paralel bagi operator)** | **OPEN DESIGN DECISION** |
| **OD-SCORE-S-02** | **Fungsi Normalisasi Skor Kontinu** | Bagaimana memetakan output diskrit (0/1) atau continuous anomaly score Isolation Forest ($[-0.5, 0.5]$) ke rentang $[0, 100]$ tanpa mendistorsi ambang risiko 30 dan 70? | Butuh kalibrasi empiris berbasis data tekanan nyata utilitas mitra. | **OPEN DESIGN DECISION** |
| **OD-SCORE-S-03** | **Fallback Saat Sensor SCADA Offline** | Jika utilitas air mengalami kegagalan telemetri SCADA massal, apa nilai default yang digunakan agar tidak memicu alarm palsu atau menutupi bahaya kekeruhan air? | Fallback ke skema produksi saat ini (menggunakan densitas laporan warga). | **OPEN DESIGN DECISION** |
| **OD-SCORE-S-04** | **Batas Akses Data Privat Infrastruktur** | Data tekanan dan peta pipa adalah aset rahasia utilitas (obvitnas). Bagaimana memastikan frontend warga tidak pernah menerima koordinat sensor atau nilai tekanan pipa? | Sanitasi ketat pada backend gateway; hanya data agregat publik yang dikirim ke Citizen UI. | **OPEN DESIGN DECISION** |

---

## 8. INTEGRITY VERIFICATION & SUMMARY

Rancangan ini sepenuhnya bersifat konseptual dan arsitektural. Tidak ada tabel baru, migrasi baru, controller baru, atau perubahan rute yang diterapkan pada repositori. Sistem produksi tetap berada dalam integritas aman dan terverifikasi.
