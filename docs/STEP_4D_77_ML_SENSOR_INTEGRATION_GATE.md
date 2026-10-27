# STEP 4D.77 — ML & SENSOR INTEGRATION DECISION GATE

**Document ID:** `GATE-ML-SENSOR-STEP-4D-77-001`  
**Phase:** STEP 4D.77 — Sensor Readiness & ML Validation Planning Gate  
**Date:** 2026-10-17  
**Auditor / System Architect:** AI Lead Data & ML Systems Architect  
**Gate Decision Ruling:** **HOLD — FIELD DATA REQUIRED**  
*(Sub-status Operasional: PASS FOR VALIDATION DESIGN ONLY untuk dokumen arsitektur dan matriks perencanaan, tetapi DITAHAN MUTLAK (HOLD) untuk integrasi fisik sensor lapangan, perubahan formula produksi, atau penerapan Score_H).*

---

## 1. PENETAPAN KEPUTUSAN GATE (DECISION GATE RULING)

Berdasarkan audit faktual terhadap kesiapan data pada 3 jalur analitik (K-Means Spasial, Sensor Turbidity, dan Sensor Tekanan SCADA), ditetapkan keputusan resmi:

$$\mathbf{HOLD\ —\ FIELD\ DATA\ REQUIRED}$$

### Rasional Ilmiah & Bukti Penetapan Status HOLD:
1. **Ketiadaan Sensor Lapangan Fisik (Zero Field Telemetry):**
   - Jalur Turbidity dan Jalur Tekanan Air di Kota Tarakan saat ini **belum memiliki perangkat keras sensor fisik yang terpasang dan mentransmisikan data riil**.
   - Data yang ada di repositori saat ini hanya berupa data oracle sintetis (`iot_measurements.csv`), dataset drainase air limbah luar negeri dengan 88,6% data kosong (Turbinator Gothenburg), dataset kolam ikan tropis (KU-MWQ), dan benchmark simulasi hidraulik sintetis luar negeri (BattLeDIM 2018 L-Town).
   - Mengizinkan integrasi produksi saat ini berarti menyambungkan sistem ke data fiktif atau data yang tidak merepresentasikan dinamika air minum perkotaan Tarakan.
2. **Keterbatasan Bukti Ilmiah ($N=2$ & Proximity Bias):**
   - Eksperimen deteksi kebocoran BattLeDIM hanya memiliki 2 insiden burst pada masa uji, yang letaknya tepat berdampingan dengan sensor tekanan. Bukti ini tidak cukup untuk mengklaim generalisasi ke jaringan perpipaan heterogen PDAM.
3. **Keputusan Desain Arsitektur Belum Ditandatangani:**
   - Keputusan OD-SCORE-S-01 (apakah sinyal hidraulik menjadi input $Score_S$ atau modul paralel $Score_H$) dan OD-SCORE-S-02 (metode kalibrasi skor anomali ke rentang 0–100) masih berstatus **OPEN DESIGN DECISION** tanpa nota kesepakatan PDAM.
4. **Proteksi Formula Produksi Warga:**
   - Formula produksi kanonikal:
     $$\text{Risk\_Score} = \text{round}(0{,}6 \cdot Score_T + 0{,}4 \cdot Score_S)$$
     dengan threshold: $0$–$30$ (Aman/Jernih), $31$–$70$ (Waspada), $71$–$100$ (Bahaya) sedang berjalan stabil dan terlindungi dari regresi.

---

## 2. STATUS TIGA JALUR ANALITIK PADA GATE STEP 4D.77

```
┌────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                   STATUS EVALUASI GERBANG PER JALUR                                    │
├──────────────────────────────┬───────────────────────────────┬─────────────────────────────────────────┤
│ Jalur Analitik               │ Status Evaluasi               │ Rincian Status Faktual                  │
├──────────────────────────────┼───────────────────────────────┼─────────────────────────────────────────┤
│ 1. K-Means Spasial           │ PASS FOR VALIDATION DESIGN    │ Implementasi runtime deterministik;     │
│                              │ (PENDING FIELD CROWD DATA)    │ lulus uji unit test; menunggu data      │
│                              │                               │ sebaran keluhan warga Tarakan aktual.   │
├──────────────────────────────┼───────────────────────────────┼─────────────────────────────────────────┤
│ 2. Sensor Kekeruhan          │ HOLD — FIELD DATA REQUIRED    │ Skema database & endpoint siap;         │
│    (Turbidity / NTU)         │                               │ belum ada sensor fisik lapangan;        │
│                              │                               │ beroperasi pada fallback kanonikal.     │
├──────────────────────────────┼───────────────────────────────┼─────────────────────────────────────────┤
│ 3. Sensor Tekanan Hidraulik  │ HOLD — FIELD DATA REQUIRED    │ Benchmark BattLeDIM diaudit; N=2 tidak  │
│    (Water Pressure / Flow)   │                               │ cukup untuk produksi; dilarang menimpa  │
│                              │                               │ Score_S; Score_H berstatus modul open.  │
└──────────────────────────────┴───────────────────────────────┴─────────────────────────────────────────┘
```

---

## 3. PRASYARAT PELEPASAN STATUS HOLD (EXIT CRITERIA)

Status **HOLD — FIELD DATA REQUIRED** hanya dapat diangkat dan ditinjau ulang apabila seluruh kriteria berikut telah dipenuhi secara faktual:

1. **Pemasangan & Penerimaan Telemetri Sensor Lapangan Nyata:**
   - Terpasangnya minimal 1 pilot project sensor optik kekeruhan (NTU) dan pressure logger di salah satu zona perpipaan PDAM Tarakan.
   - Tersedianya aliran telemetri stabil selama minimal 30 hari berturut-turut ke endpoint `POST /api/iot/ingest` dengan uptime data $\ge 95\%$.
2. **Uji Validasi Silang Spektrofotometer (Ground Truth Turbidity):**
   - Dilakukannya validasi silang antara bacaan sensor optik online dengan hasil uji laboratorium PDAM menggunakan larutan formazin standar (deviasi $< 10\%$).
3. **Penyelesaian Formal Open Design Decisions:**
   - Penandatanganan dokumen persetujuan arsitektur bersama Product Owner dan Pimpinan PDAM yang secara formal menetapkan:
     - OD-SCORE-S-01: Pengesahan modul hidraulik terpisah $Score_H$ (menolak penimpaan $Score_S$).
     - OD-SCORE-S-02: Formula kalibrasi empiris $Score_H$ berbasis distribusi kuantil tekanan pipa setempat.
     - OD-SCORE-S-03: Kontrak penanganan saat sensor offline (membekukan $Score_H$ dan kembali ke evaluasi kekeruhan murni).
4. **Verifikasi Keamanan Kontrak API Publik:**
   - Pengujian terautomasi (*automated integration guard*) yang membuktikan tidak ada data internal pipa atau telemetri SCADA yang bocor melalui rute API warga.

---

## 4. LARANGAN & BATASAN KETAT FASE BERIKUTNYA

- **DILARANG** melakukan git add, stage, commit, push, atau deployment.
- **DILARANG** mengubah formula produksi $\text{round}(0{,}8 \cdot Score_T + 0{,}2 \cdot Score_S)$ atau ambang batas $0$–$30$, $31$–$70$, $71$–$100$.
- **DILARANG** membuat generator data tiruan (*fake generator*) yang dilabeli seolah-olah data lapangan riil.
- **DILARANG** memulai implementasi kode produksi fitur hidraulik sebelum status gerbang resmi diangkat melalui revisi audit berikutnya.
