# STEP 4D.76 — HYDRAULIC INTEGRATION DECISION RECORD & ARCHITECTURE GOVERNANCE

**Document ID:** `DECISION-HYDRAULIC-STEP-4D-76-001`  
**Phase:** STEP 4D.76 — Discrepancy Resolution & Hydraulic Integration Decision Gate  
**Date:** 2026-10-17  
**Auditor / System Architect:** AI Lead Data & ML Systems Architect  
**Status:** **OPEN DESIGN DECISION GATE (NO PRODUCTION MUTATION)**  
**Target Scope:** Formalisasi Decision Record untuk keputusan arsitektur OD-SCORE-S-01 sampai OD-SCORE-S-04 sebelum integrasi hidraulik apa pun diizinkan menyentuh sistem produksi AQUA-TARA.

---

## 1. PRINSIP TATA KELOLA ARSITEKTUR & GUARDRAILS

Sebelum menetapkan catatan keputusan, ditegaskan batasan non-negosiasi sistem produksi:
1. **Integritas Formula Kanonikal:** Formula produksi risiko kualitas air:
   $$\text{Risk\_Score} = \text{round}(0{,}6 \cdot Score_T + 0{,}4 \cdot Score_S)$$
   serta ambang batas risiko:
   - $0$ – $30$: Jernih / Aman (Optimal)
   - $31$ – $70$: Waspada (Indikasi Keruh Ringan)
   - $71$ – $100$: Bahaya (Keruh Tinggi / Risiko Kritis)  
   **DILARANG DIUBAH ATAU DITIMPA SECARA SEPIHAK**.
2. **Citizen UI Protection:** Antarmuka warga (*Citizen UI*) berbasis mobile-first hanya menampilkan informasi kualitas air publik dan status laporan warga. Data aset fisik, topologi perpipaan SCADA, sensor tekanan, dan telemetry raw adalah rahasia infrastruktur (Objek Vital / Data Utilitas Terbatas) yang dilarang bocor ke publik.
3. **Status Implementasi:** Dokumen ini berstatus **DECISION RECORD KONSEPTUAL**. Tidak ada kode produksi Laravel, frontend Vue, skema database, migrasi, atau API endpoint yang dimodifikasi.

---

## 2. FORMAL DECISION RECORDS

### DECISION RECORD: OD-SCORE-S-01
#### Topik: Hubungan Sinyal Hidraulik Terhadap $Score_S$ dan Formula Risiko

- **Deskripsi Permasalahan:**  
  Dalam arsitektur awal AQUA-TARA, $Score_S$ merepresentasikan *Spatial Density Score* dari laporan warga berbasis kluster K-Means ($\min(100.0, \text{density} \times 15.0)$). Muncul usulan agar anomali hidraulik SCADA (tekanan & debit) diintegrasikan ke dalam penilaian risiko. Perlu diputuskan apakah sinyal hidraulik menjadi input ke $Score_S$, atau dipisah sebagai modul analitik hidraulik independen ($Score_H$).

- **Opsi yang Dievaluasi:**
  - **Opsi A (Direct Replacement):** Mengganti total perhitungan $Score_S$ dengan anomali hidraulik SCADA.
  - **Opsi B (Hybrid Blended $Score_S$):** Menggabungkan densitas warga dan anomali hidraulik ke dalam $Score_S$ dengan pembobotan internal, misalnya $Score_S = w_1 \cdot \text{Density} + w_2 \cdot \text{Hydraulic}$.
  - **Opsi C (Parallel Hydraulic Module $Score_H$ — Recommended):** Mempertahankan formula produksi $\text{round}(0{,}8 \cdot Score_T + 0{,}2 \cdot Score_S)$ secara utuh untuk warga, dan menciptakan modul pendukung paralel $Score_H \in [0, 100]$ khusus di portal Admin/Teknisi PDAM untuk penentuan investigasi fisik.

- **Manfaat & Risiko:**
  - *Opsi A:* Risiko sangat tinggi; merusak validasi K-Means yang telah disahkan, menghilangkan partisipasi warga, dan membuat sistem lumpuh saat sensor SCADA offline.
  - *Opsi B:* Menimbulkan distorsi semantik; laporan warga (kekeruhan visual) dan tekanan pipa (hidraulik) memiliki skala waktu dan sifat fisika yang berbeda.
  - *Opsi C:* Nol risiko terhadap sistem produksi; formula warga tetap teruji, teknisi PDAM mendapatkan perspektif hidraulik tanpa mengacaukan skor warga.

- **Kebutuhan Bukti (Evidence Requirements):**
  - Data korelasi empiris multivariat antara penurunan tekanan hidraulik dengan insiden kekeruhan air di jaringan distribusi PDAM nyata (bukan simulasi).

- **Pihak yang Wajib Menyetujui (Approval Authority):**
  - Product Owner AQUA-TARA, Lead Backend Architect, Lead ML Engineer, Kepala Divisi Distribusi PDAM Mitra.

- **Status:** **OPEN DESIGN DECISION (ARCHITECTURAL RECOMMENDATION: OPTION C)**.

- **Konsekuensi Terhadap Formula Produksi:**  
  Dengan Opsi C, formula produksi $\text{round}(0{,}8 \cdot Score_T + 0{,}2 \cdot Score_S)$ **tetap 100% utuh tanpa perubahan**.

---

### DECISION RECORD: OD-SCORE-S-02
#### Topik: Metodologi Kalibrasi Anomaly Score ke Skala 0–100

- **Deskripsi Permasalahan:**  
  Model deteksi anomali hidraulik (seperti Isolation Forest) menghasilkan nilai kontinu *decision function* dalam rentang real terbatas (misal $[-0{,}5, +0{,}5]$) atau status biner $(0/1)$. Untuk dapat disajikan secara intuitif kepada operator atau disandingkan dengan skor risiko, skor anomali harus dinormalisasi ke rentang $[0, 100]$ tanpa memicu inflasi alarm palsu (*false positive inflation*) atau mengaburkan batas waspada/bahaya ($30$ dan $70$).

- **Opsi yang Dievaluasi:**
  - **Opsi A (Min-Max Scaling Historis):** Memetakan rentang skor historis minimum dan maksimum ke $0$ s.d. $100$.
  - **Opsi B (Sigmoid / Logistic Transformation dengan Parameter Ambang Terkalibrasi):** Menggunakan fungsi sigmoid berbasis deviasi kuantil $P_{95}$ dan $P_{99}$ dari periode kalibrasi baseline.
  - **Opsi C (Piecewise Linear Threshold Mapping):** 
    - Periode normal terverifikasi dipetakan ke $[0, 30]$;
    - Deviasi transient ringan dipetakan ke $(30, 70]$;
    - Persistent burst terkonfirmasi ($\ge 15$ menit berturut-turut) dipetakan ke $(70, 100]$.

- **Manfaat & Risiko:**
  - *Opsi A:* Sangat rentan terhadap outlier ekstrem historis; satu spike dapat memampatkan seluruh skor normal mendekati 0.
  - *Opsi B:* Memberikan gradien halus, namun sulit diinterpretasikan oleh teknisi lapangan (makna nilai 52 vs 63 menjadi abstrak).
  - *Opsi C:* Selaras dengan semantik operasional AQUA-TARA (Aman, Waspada, Bahaya), namun memerlukan kalibrasi empiris pada masing-masing DMA.

- **Kebutuhan Bukti (Evidence Requirements):**
  - Dataset baseline minimal 6 bulan dari jaringan PDAM nyata tanpa kebocoran besar untuk menghitung distribusi skor anomali latar belakang (*background noise*).
  - Validasi *false discovery rate* pada data uji independen.

- **Pihak yang Wajib Menyetujui (Approval Authority):**
  - Lead ML Engineer, Data Scientist, Konsultan Hidrolika PDAM.

- **Status:** **OPEN DESIGN DECISION (NO FINAL FORMULA WITHOUT CALIBRATION DATA)**.  
  *Catatan Kritis:* Dilarang keras menerapkan formula konversi linear sembarang (seperti $\text{score} \times 100$) ke produksi sebelum kalibrasi empiris tervalidasi.

- **Konsekuensi Terhadap Formula Produksi:**  
  Tidak ada perubahan formula produksi sebelum bukti kalibrasi tersedia dan ditandatangani.

---

### DECISION RECORD: OD-SCORE-S-03
#### Topik: Protokol Perilaku Saat Sensor SCADA Offline, Data Stale, atau Telemetri Tidak Valid

- **Deskripsi Permasalahan:**  
  Jaringan telemetri IoT dan SCADA di lapangan sering mengalami gangguan: sensor offline karena kehabisan baterai/pemadaman listrik, paket data hilang, jitter jaringan seluler (*stale data*), atau transmisi nilai fisik yang mustahil (tekanan negatif atau lonjakan akibat *water hammer*). Sistem harus memiliki kontrak perilaku deterministik saat kegagalan terjadi, tanpa menciptakan fallback fiktif yang tidak ada di kode.

- **Opsi yang Dievaluasi:**
  - **Opsi A (Assume Normal / Zero Deviation):** Mengasumsikan nilai sensor normal jika offline.
  - **Opsi B (Assume Critical / Fail-Safe Alarm):** Memicu status alarm saat sensor hilang komunikasi $> 30$ menit.
  - **Opsi C (Explicit Status Segregation & Canonical Fallback — Recommended):**
    - Sinyal status hidraulik dipisahkan menjadi: `ONLINE_HEALTHY`, `TELEMETRY_DEGRADED`, `SENSOR_OFFLINE`, `QUALITY_SUSPECT`.
    - Jika sensor offline $\le 15$ menit (1–3 sampel): *Linear forward fill* dengan penanda status `DEGRADED`.
    - Jika sensor offline $> 15$ menit: Modul hidraulik membekukan inferensi anomali (`STATUS = SENSOR_OFFLINE`), menolak imputasi tebakan, dan mencatat degradasi di Audit Log.
    - Sistem penilaian risiko warga ($Risk\_Score$) secara deterministik mengabaikan modul hidraulik dan beroperasi 100% menggunakan formula kanonikal produksi yang ada saat ini (mengandalkan sensor turbidity dan laporan warga).

- **Manfaat & Risiko:**
  - *Opsi A:* Risiko kebocoran besar tidak terdeteksi saat sensor sengaja dirusak atau mati.
  - *Opsi B:* Membanjiri operator dengan alarm palsu teknis (masalah IT/jaringan seluler disangka pipa pecah).
  - *Opsi C:* Transparan, membedakan insiden hidraulik pipa dari insiden konektivitas IoT, dan menjaga operasi inti warga tetap stabil tanpa ketergantungan rapuh.

- **Kebutuhan Bukti (Evidence Requirements):**
  - Hasil stress test simulasi *network drop* pada ingress buffer.
  - Verifikasi bahwa ketiadaan data hidraulik tidak memicu eksepsi HTTP 500 pada ML service.

- **Pihak yang Wajib Menyetujui (Approval Authority):**
  - Site Reliability Engineer (SRE), Lead Backend Architect, SCADA Operations Lead.

- **Status:** **OPEN DESIGN DECISION (RECOMMENDED CONTRACT SPECIFIED; PENDING FIELD TEST HARNESS)**.

- **Konsekuensi Terhadap Formula Produksi:**  
  Formula produksi tetap menggunakan penanganan bawaan ([production_analysis.py](file:///e:/TUGAS/pengkoding\anti\aqua-tara\ml\app\services\production_analysis.py#L48-L62)): jika node IoT tidak ditemukan, fallback turbidity = 30 NTU dan density laporan tetap dihitung.

---

### DECISION RECORD: OD-SCORE-S-04
#### Topik: Pemisahan Informasi Aset SCADA dan Topologi Jaringan dari Akses Publik (Citizen UI)

- **Deskripsi Permasalahan:**  
  Data posisi pipa bawah tanah, diameter, katup kontrol (*valves*), pompa transmisi, dan sensor tekanan SCADA merupakan aset sensitif infrastruktur air minum publik (berisiko sabotase atau kebocoran informasi keamanan utilitas). Citizen UI adalah aplikasi publik berbasis peramban/seluler. Harus ada dinding pemisah mutlak (*air-tight separation*) antara telemetri internal PDAM dan tampilan publik.

- **Opsi yang Dievaluasi:**
  - **Opsi A (Frontend Filtering):** Seluruh data sensor dikirim ke frontend, lalu disaring (*hide*) oleh komponen Vue.
  - **Opsi B (Backend Architectural Boundary & Obfuscation — Mandatory Standard):**
    - API publik warga (`/api/v1/citizen/...`) **sama sekali tidak memiliki relasi atau endpoint** ke tabel telemetri SCADA, log tekanan, atau geometri pipa PDAM.
    - Portal Admin (`/api/v1/admin/...`) membatasi data SCADA melalui otentikasi peran ketat (*Role-Based Access Control* / RBAC: `SUPERADMIN`, `PDAM_OPERATOR`, `FIELD_ENGINEER`).
    - Peta Citizen UI hanya menampilkan visualisasi status air per wilayah kelurahan/lingkungan secara agregat, tanpa pernah menyajikan koordinat sensor SCADA, ID pipa fisik, atau nilai bar/psi.
    - Sesuai kaidah ML AQUA-TARA: Centroid kluster analitik dilarang direpresentasikan sebagai titik fisik pipa bocor kepada publik; istilah yang digunakan wajib berupa **"Area Waspada Kualitas Air"** bagi warga, dan **"Area Prioritas Pemeriksaan"** bagi tim teknis internal.

- **Manfaat & Risiko:**
  - *Opsi A:* Pelanggaran fatal keamanan informasi; siapapun dapat membongkar jaringan pipa PDAM via inspect element / devtools network tab.
  - *Opsi B:* Menjamin kepatuhan regulasi keamanan infrastruktur publik dan menjaga kepercayaan utilitas air mitra.

- **Kebutuhan Bukti (Evidence Requirements):**
  - Audit kontrak API dan skema respon JSON endpoint warga (memastikan tidak ada atribut `scada_*`, `pipe_id`, atau `pressure_bar` yang lolos ke serialisasi DTO warga).
  - Automated penetration test / schema validation guard pada route testing Laravel.

- **Pihak yang Wajib Menyetujui (Approval Authority):**
  - Cyber Security Officer, Legal / Compliance PDAM, Lead Backend Architect.

- **Status:** **VERIFIED ARCHITECTURAL BOUNDARY (MANDATORY POLICY FOR FUTURE IMPLEMENTATION)**.

- **Konsekuensi Terhadap Formula Produksi:**  
  Tidak ada dampak negatif; justru melindungi sistem produksi dari kebocoran data privat.

---

## 3. MATRIKS RINGKASAN STATUS KEPUTUSAN DESAIN

| Kode Keputusan | Domain | Opsi Terpilih / Direkomendasikan | Status Gate | Otoritas Persetujuan | Prasyarat Implementasi Produksi |
|:---:|:---|:---|:---:|:---:|:---|
| **OD-SCORE-S-01** | Arsitektur Skor | Opsi C: Modul Paralel $Score_H$, pertahankan formula produksi $Score_T / Score_S$ | **OPEN DESIGN DECISION** | PO, Backend Lead, PDAM | Nota kesepakatan integrasi PDAM |
| **OD-SCORE-S-02** | Normalisasi ML | Opsi C: Piecewise threshold mapping berbasis kalibrasi kuantil | **OPEN DESIGN DECISION** | ML Lead, Hidrolika | Dataset baseline riil 6 bulan |
| **OD-SCORE-S-03** | Penanganan Sensor Mati | Opsi C: Status segregasi eksplisit, canonical fallback otomatis | **OPEN DESIGN DECISION** | SRE, Backend Lead | Uji ketahanan ingress adapter |
| **OD-SCORE-S-04** | Keamanan Data | Opsi B: Isolasi total endpoint backend, zero SCADA di Citizen UI | **VERIFIED BOUNDARY** | Security, Backend Lead | Penegakan API Contract Guard |

---

## 4. KESIMPULAN

Seluruh keputusan di atas menegaskan bahwa **tidak ada izin untuk mengubah sistem produksi pada fase saat ini**. Dokumen ini menjadi dasar tata kelola jika kelak ada fase integrasi SCADA bersama mitra utilitas air fisik.
