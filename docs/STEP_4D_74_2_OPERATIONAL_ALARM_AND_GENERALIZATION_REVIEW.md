# STEP 4D.74.2 — OPERATIONAL ALARM EPISODE AND GENERALIZATION REVIEW

**Tanggal Audit:** 15 Oktober 2026  
**Auditor:** Tim Teknis AQUA-TARA / AI Data & ML Auditor  
**Lingkup:** Tinjauan operasional episode alarm, audit batas generalisasi model, klarifikasi periode "normal" terhadap seluruh ground truth (fixed bursts vs background leaks), verifikasi aturan penggabungan jeda alarm (*alarm gap bridging*), audit keterbatasan sampel deteksi onset baru ($N=2$), serta rekonsiliasi komparatif terhadap laporan STEP 4D.74 dan STEP 4D.74.1.  
**Status Sistem Produksi:** Unchanged — Read-Only Audit (Zero Supabase, Zero Migration, Zero Production Code Mutation, Zero Git Commit).

---

## 1. RINGKASAN STATUS AUDIT & TEMUAN UTAMA

Status Keseluruhan: **PASS (VERIFIED & AUDITED)**  
Seluruh 6 butir peninjauan operasional dan batas generalisasi telah selesai diperiksa secara mendalam tanpa pelanggaran guardrail.

### Ringkasan Temuan Kritis:
1. **Definisi Episode Alarm Operasional & Penggabungan Jeda (*Gap Bridging*):**  
   - *Definisi Transisi Murni 0→1 (Strict):* Setiap transisi dari kondisi non-alarm ke alarm dihitung sebagai episode baru, menghasilkan **14,24 episode/hari** pada Isolation Forest raw.
   - *Definisi Konsolidasi Operasional Lapangan (Bridging $\le 15$ menit):* Alarm yang berkedip dengan jeda senyap $\le 15$ menit diperlakukan oleh operator PDAM sebagai satu insiden investigasi berlanjut. Aturan ini menurunkan frekuensi panggilan palsu IF raw dari **14,24 episode/hari** menjadi **3,49 episode/hari** (dan pada model IF + Persist 15m menjadi **3,00 episode/hari** atau ~21 panggilan/minggu).
2. **Klarifikasi Ground Truth "Periode Normal Murni" (Temuan Kritis Hidraulik):**  
   - Pada Test Set (35.136 baris), label "normal" ($y=0$ sebanyak 25.935 baris) **HANYA MERUJUK PADA KETIADAAN FIXED BURSTS**.
   - Pemeriksaan terhadap seluruh kolom kebocoran (`2018_Leakages.csv`) membuktikan bahwa **100% sampel pada Test Set (35.136 dari 35.136 baris) secara fisik tetap mengalami kebocoran background alami** (`p257`, `p427`, `p654`, `p810` aktif dengan laju 0,01–6,91 L/s).
   - *Status Aktual:* Tidak ada satu pun timestep pada Test Set yang berstatus nol kebocoran mutlak (*zero leakage*). Model pada benchmark ini sebenarnya dilatih dan diuji untuk mendeteksi *burst anomali di atas kebocoran background yang sudah ada*, bukan mendeteksi kebocoran pada jaringan perpipaan yang 100% kedap air.
3. **Audit Keterbatasan Sampel Deteksi Onset Baru ($N=2$):**  
   - Hanya terdapat **2 kejadian yang benar-benar memenuhi kriteria kejadian baru** (*New Burst Onset*) pada Test Set: `p158` (6–23 Okt) dan `p369` (26 Okt – 8 Nov).
   - Kejadian `p183` berstatus *pre-existing* (sudah berlangsung selama 25 hari sebelum 1 September 2018).
   - **Batas Generalisasi Tegas:** Keberhasilan mendeteksi 2 kejadian ini ($100\%$ deteksi dengan TTD 10 menit hingga 8,8 jam) **SAMA SEKALI TIDAK MEWAKILI SELURUH VARIASI JARINGAN PIPA PDAM REALITAS**. Ukuran sampel $N=2$ secara statistik tidak mencukupi untuk menjamin keandalan generalisasi sistem produksi.

---

## 2. STATUS GIT REPOSITORY SEBELUM DAN SESUDAH AUDIT

### 2.1 Status Sebelum Audit
```text
?? .gitignore
?? backend/
?? frontend/
?? ml/
```
*(Branch master, No commits yet, seluruh direktori utama untracked).*

### 2.2 Status Sesudah Audit
```text
?? .gitignore
?? backend/
?? frontend/
?? ml/
```
*(Zero file tracked termodifikasi, zero staging area `git add`, zero commit Git, integritas file sumber 100% terjaga).*

---

## 3. AUDIT BUTIR 1: DEFINISI EPISODE ALARM OPERASIONAL LAPANGAN

Dalam praktik operasional utilitas air minum, alarm yang berulang dalam rentang waktu singkat tidak memicu dispatch tim lapangan baru, melainkan dianggap sebagai alarm berulang dari investigasi yang sama.

### 3.1 Tiga Aturan Metodologis Penghitungan Episode:
1. **Aturan A (Strict Transitions 0→1):** Setiap kali status alarm berubah dari 0 ke 1, dihitung 1 episode terpisah tanpa memedulikan jeda waktu sebelumnya.
2. **Aturan B (15-Minute Gap Bridging):** Jika jeda antara dua status alarm $\le 15$ menit (3 timestep), jeda tersebut dianggap sebagai kedipan sensor (*sensor flicker*) dan digabungkan menjadi 1 episode berkelanjutan.
3. **Aturan C (30-Minute Gap Bridging):** Jika jeda antara dua status alarm $\le 30$ menit (6 timestep), seluruh rangkaian alarm digabungkan menjadi 1 episode investigasi tunggal.

### 3.2 Perbandingan Jumlah Episode Alarm Palsu (Pada 25.935 Timestep Non-Burst)

| Model Evaluasi | Konfigurasi Sinyal | Total False Positive Timestep | Aturan A (Strict 0→1) | Aturan B (Bridging $\le 15$m) | Aturan C (Bridging $\le 30$m) | Rata-rata Durasi Episode (Aturan B) |
|---|---|---|---|---|---|---|
| **Isolation Forest** | **Raw (5m)** | 6.672 | **14,24 eps/hari** (1.737 eps) | **3,49 eps/hari** (426 eps) | **2,11 eps/hari** (258 eps) | **100,5 menit** |
| | **Persist 15m** | 4.042 | **4,80 eps/hari** (585 eps) | **3,00 eps/hari** (366 eps) | **1,93 eps/hari** (235 eps) | **64,2 menit** |
| **Rolling Z-Score** | **Raw (5m)** | 6.423 | **18,43 eps/hari** (2.248 eps) | **7,47 eps/hari** (911 eps) | **4,21 eps/hari** (513 eps) | **47,3 menit** |
| | **Persist 15m** | 3.369 | **3,50 eps/hari** (427 eps) | **2,72 eps/hari** (332 eps) | **2,30 eps/hari** (281 eps) | **55,0 menit** |
| **First Difference** | **Raw (5m)** | 6.885 | **20,18 eps/hari** (2.462 eps) | **8,11 eps/hari** (990 eps) | **4,30 eps/hari** (525 eps) | **47,4 menit** |
| | **Persist 15m** | 3.610 | **3,43 eps/hari** (419 eps) | **2,78 eps/hari** (339 eps) | **2,43 eps/hari** (296 eps) | **56,8 menit** |

### 3.3 Kesimpulan Analisis Episode:
- Penurunan dari 14,24 ke 3,49 episode/hari pada Isolation Forest menunjukkan bahwa sebagian besar false alarm mentah berbentuk "pola terfragmentasi" (alarm menyala-mati dalam rentang waktu singkat).
- Kombinasi **Filter Persistensi 15 Menit + Konsolidasi Lapangan 15 Menit** menurunkan beban tim inspeksi menjadi **~3 panggilan palsu per hari** (turun $78\%$ dibanding evaluasi naif).

---

## 4. AUDIT BUTIR 2: VERIFIKASI GROUND TRUTH "PERIODE NORMAL MURNI"

Audit memeriksa seluruh matriks 14 kolom kebocoran pada berkas `ml/data/battledim/2018_Leakages.csv` selama periode pengujian (1 September – 31 Desember 2018):

### 4.1 Realitas Fisik Hidraulik Test Set (35.136 Timestep):
- **Jumlah baris dengan fixed burst aktif (`p183`, `p158`, `p369`):** **9.201 baris (26,19%)**.
- **Jumlah baris dengan background leak aktif (`p257`, `p427`, `p654`, `p810`):** **35.136 baris (100,00%)**.
- **Jumlah baris bebas kebocoran mutlak (*Zero Leakage*):** **0 baris (0,00%)**.

### 4.2 Implikasi Metodologis:
1. **Status "Normal" Adalah Relatif:** Sinyal yang diberi label negatif ($y=0$) pada set pengujian sebenarnya mencakup debit bocor laten sebesar $0,01$ hingga $6,91$ L/s yang mengalir terus-menerus di 4 titik pipa.
2. **Karakteristik Deteksi:** Model pada hakikatnya tidak mendeteksi peralihan dari "pipa sempurna" ke "pipa bocor", melainkan mendeteksi **lonjakan hidraulik tambahan (*burst step change*) di atas kebocoran laten yang sudah ada**.
3. **Ketidakpastian Label:** Pada jaringan pipa riil perkotaan, kebocoran mikro (sambungan rembes, korosi pipa tua) selalu ada. Fakta bahwa BattLeDIM mempertahankan background leak sepanjang periode uji membuktikan bahwa benchmark ini realistis, namun mengharuskan auditor menegaskan bahwa performa deteksi ini berlaku pada kondisi baseline yang terdegradasi.

---

## 5. AUDIT BUTIR 3: DETEKSI ONSET BARU & BATAS GENERALISASI

### 5.1 Jumlah Kejadian yang Memenuhi Definisi "Onset Baru"
Dari total 3 kejadian fixed burst pada Test Set, hanya **2 kejadian yang benar-benar bermula di masa pengujian**:
1. **`p158`:** Onset 2018-10-06 02:35 (Durasi 17,5 hari, debit puncak 5,03 L/s).
2. **`p369`:** Onset 2018-10-26 02:05 (Durasi 13,8 hari, debit puncak 3,97 L/s).

*(Kejadian `p183` dikeluarkan dari evaluasi onset karena sudah aktif 25 hari sebelumnya).*

### 5.2 Rangkuman Time-to-Detection (TTD) pada 2 Kejadian Baru:
- **Heuristic First Difference:** TTD $p158 = 3,83$ jam; TTD $p369 = 0,17$ jam (10 menit).
- **Heuristic Rolling Z-Score:** TTD $p158 = 3,83$ jam; TTD $p369 = 3,67$ jam.
- **Isolation Forest:** TTD $p158 = 8,33$ jam; TTD $p369 = 8,83$ jam.

### 5.3 Batasan Generalisasi Tegas:
1. **Ukuran Sampel Kejadian Sangat Kecil ($N=2$):** Keberhasilan mendeteksi 2 kebocoran pipa ini tidak dapat dijadikan jaminan bahwa model akan mampu mendeteksi kebocoran di pipa lain dengan diameter berbeda, lokasi topologi yang lebih jauh dari sensor, atau kebocoran dengan kenaikan debit sangat lambat (*slow incipient leak*).
2. **Ketergantungan Kedekatan Sensor:** Pipa `p369` dan `p158` terletak dekat dengan sensor tekanan `n31` dan `n215`. Jika kebocoran terjadi di zona buta (*blind spot*) jaringan tanpa sensor tekanan terdekat, waktu deteksi dipastikan akan jauh lebih lambat atau bahkan tidak terdeteksi sama sekali.

---

## 6. AUDIT BUTIR 4: ANALISIS SENSITIVITAS FILTER PERSISTENSI

Audit menegaskan kembali status metodologis filter persistensi:
- **Tujuan Pengujian:** Pengujian window 15, 30, dan 60 menit pada Test Set **murni merupakan analisis sensitivitas trade-off operasional (Operational Sensitivity Analysis)**.
- **Pernyataan Auditor:** Test Set **TIDAK DIGUNAKAN UNTUK MEMILIH KONFIGURASI FINAL PRODUKSI**.
- **Hasil Sensitivitas:**
  - Peningkatan filter persistensi dari 5m ke 60m menurunkan recall kumulatif Isolation Forest dari $43,98\%$ menjadi $15,17\%$, namun menaikkan presisi dari $37,76\%$ menjadi $46,86\%$ dan memangkas episode false alarm mingguan dari 99,7 ke 6,3 panggilan/minggu.
  - Pilihan konfigurasi operasional di masa depan harus ditentukan oleh toleransi biaya utilitas (biaya air hilang vs biaya mobilisasi kru lapangan), bukan oleh optimasi kurva uji semata.

---

## 7. AUDIT BUTIR 5: REKONSILIASI LINTAS LAPORAN 4D.74, 4D.74.1, & 4D.74.2

| Parameter Audit | STEP 4D.74 | STEP 4D.74.1 | STEP 4D.74.2 (Terkoreksi / Diperjelas) | Status Rekonsiliasi |
|---|---|---|---|---|
| **Definisi Normal Test Set** | 25.935 timestep normal | 25.935 timestep non-fixed burst | Diklarifikasi: **100% tetap memiliki background leaks** | **PASS (Diperjelas)** |
| **Inklusi Timestep `p183`** | Termasuk dalam 9.201 burst steps | Termasuk 207 steps di matriks konfusi | Terverifikasi 100% utuh dalam kalkulasi klasifikasi | **PASS (Konsisten)** |
| **Jumlah Onset Baru** | Dievaluasi per kejadian | 2 kejadian baru + 1 pre-existing | Ditegaskan $N=2$ kejadian baru valid | **PASS (Konsisten)** |
| **Frekuensi False Alarm IF** | 25,73% FAR | 14,24 episode/hari (Strict 0→1) | Ditambahkan: **3,49 episode/hari (Bridging 15m)** | **PASS (Diperkaya)** |
| **Nilai Ambang Model** | $\Delta P=0,10$, $Z=1,0$, IF=$0,5970$ | Terverifikasi dari Validation | Terverifikasi bebas kebocoran test set | **PASS (Konsisten)** |

---

## 8. ARTEFAK HASIL AUDIT STEP 4D.74.2

Direktori Keluaran: `ml/experiments/baseline_anomaly_detection/step_4d_74_2/`

### Berkas yang Dihasilkan:
1. `run_review_4d_74_2.py`: Skrip mandiri read-only untuk audit ground truth total leakages dan evaluasi multi-definisi episode alarm (gap bridging).
2. `operational_alarm_generalization_review.json`: Ringkasan numerik detail hasil verifikasi background leakage, tabel komparasi episode alarm per definisi bridging (strict, 15m, 30m), evaluasi onset baru, dan rekonsiliasi lintas laporan.

---

## 9. PERNYATAAN KEPATUHAN SISTEM PRODUKSI

1. **Kode produksi backend Laravel, frontend Vue, dan modul `ml/app/` tidak diubah.**
2. **Formula skor risiko produksi `round(0.8 * Score_T + 0.2 * Score_S)` dan ambang batas klasifikasi tetap utuh.**
3. **Database Supabase dan database lokal tidak pernah diakses atau dimutasi.**
4. **Dataset asli dan artefak sebelumnya tidak ditimpa atau dihapus.**
5. **Tidak ada commit Git yang dibuat.**
6. **Model BattLeDIM tidak dinyatakan siap produksi dan pekerjaan dihentikan untuk peninjauan teknis.**
