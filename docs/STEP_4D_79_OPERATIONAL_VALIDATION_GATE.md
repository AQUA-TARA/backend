# STEP 4D.79 — OPERATIONAL VALIDATION & FIELD EXPERIMENTATION GATE

**Document ID:** `GATE-OPERATIONAL-STEP-4D-79-001`  
**Phase:** STEP 4D.79 — Clustering Robustness, Score_S Semantics & Operational Validation Gate  
**Date:** 2026-10-17  
**Auditor / Lead System Architect:** AI Lead Data & ML Systems Architect  
**Gate Decision Ruling:** **PASS FOR ROBUSTNESS DESIGN ONLY**  
*(Catatan Pembatasan: PASS eksklusif untuk spesifikasi desain ketahanan clustering, verifikasi reproduksibilitas metrik, dan audit semantik Score_S. DITAHAN MUTLAK (HOLD) untuk klaim validitas operasional lapangan atau penerapan sensor fisik sebelum tersedianya data telemetri PDAM Tarakan riil).*

---

## 1. PENETAPAN KEPUTUSAN EVALUASI GERBANG (GATE RULING)

Berdasarkan sintesis bukti eksperimental pada STEP 4D.79, ditetapkan keputusan resmi:

$$\mathbf{PASS\ FOR\ ROBUSTNESS\ DESIGN\ ONLY}$$

### Rasional Ilmiah & Bukti Penetapan:
1. **Reproduksibilitas Metrik Terbukti 100% Bit-Exact:**
   - Hasil evaluasi Silhouette Score ($0{,}9344$), Davies–Bouldin ($0{,}2601$), dan ukuran kluster `{0:15, 1:5, 2:16, 3:2, 4:3}` dari STEP 4D.78 terbukti 100% dapat direproduksi pada environment library `scikit-learn 1.6.1`, `numpy 2.2.6`, dan `pandas 2.2.3`. Tidak ditemukan discrepancy komputasi.
2. **Ketahanan Algoritma Terhadap Fluktuasi Lapangan (Robustness Verified):**
   - Algoritma terbukti sangat tahan terhadap noise GPS smartphone warga (pergeseran centroid hanya $3{,}19\text{ meter}$ pada jitter $10\text{ m}$).
   - Algoritma stabil terhadap variasi acak kehadiran pelapor (10% dropout mempertahankan Silhouette $0{,}9294$).
3. **Semantik $Score_S$ dan Proteksi Formula Risiko Terbukti Aman:**
   - Analisis membuktikan bahwa $Score_S$ murni merepresentasikan kepadatan aduan warga.
   - Sistem terlindungi dari manipulasi pelaporan palsu (*Sybil reporting immunity*): bobot $Score_S$ yang dibatasi maksimal 20 poin mencegah eskalasi status menjadi Bahaya jika sensor fisik kualitas air mencatat air jernih ($Score_T = 0 \implies \text{Risk Score} = 20$, tetap kategori Aman).
4. **Verifikasi Proposal Asli 100% Selaras:**
   - Kutipan autentik dari dokumen primer `docs/PROPOSAL.pdf` Halaman 10 mengonfirmasi bahwa *centroid bukan lokasi pasti kebocoran pipa, melainkan area prioritas inspeksi teknisi*.
5. **Alasan Tidak Diberikan "Full Operational Pass":**
   - Belum ada data laporan warga Kota Tarakan riil (masih menggunakan test oracle sintetis).
   - Sensor fisik lapangan (turbidity optik dan pressure SCADA) di jaringan pipa PDAM Tarakan belum terpasang fisik, sehingga metrik operasional lapangan belum dapat diukur secara empiris.

---

## 2. SPESIFIKASI METRIK OPERASIONAL LAPANGAN (FUTURE FIELD VALIDATION)

Metrik berikut dirancang secara formal untuk diukur ketika data lapangan teranonimkan PDAM Tarakan telah tersedia:

| Kode Metrik | Nama Metrik Operasional | Definisi & Formula Pengukuran | Target Operasional yang Disarankan | Status Saat Ini |
|:---:|:---|:---|:---:|:---:|
| **OM-01** | **Centroid-to-Incident Proximity** | Jarak geospasial (meter) dari centroid kluster K-Means ke titik fisik perbaikan pipa aktual yang dicatat teknisi pada Surat Perintah Kerja (SPK). | $\le 300\text{ meter}$ dari titik galian pipa. | **PLANNED — PENDING FIELD DATA** |
| **OM-02** | **Report Batch Resolution Rate** | Persentase laporan warga dalam satu kluster yang terselesaikan sekaligus dalam satu kali kunjungan regu pemeliharaan lapangan. | $\ge 80\%$ aduan selesai per kunjungan. | **PLANNED — PENDING FIELD DATA** |
| **OM-03** | **False-Priority Dispatch Rate** | Rasio penugasan teknisi ke kluster prioritas tinggi yang ternyata tidak menemukan anomali fisik di lapangan. | $\le 10\%$ dari total penugasan. | **PLANNED — PENDING FIELD DATA** |
| **OM-04** | **Area Priority Stability** | Stabilitas urutan prioritas zona kelurahan selama rentang waktu 24 jam (mencegah teknisi berpindah-pindah rute akibat fluktuasi laporan sesaat). | Indeks stabilitas Spearman Rank $\ge 0{,}75$. | **PLANNED — PENDING FIELD DATA** |

*Peringatan Kepatuhan:* Seluruh metrik operasional di atas **BELUM PERNAH DIKLAIM TELAH TERUKUR** karena ketiadaan log teknisi lapangan PDAM saat ini.

---

## 3. PENEGASAN PEMISAHAN TIGA PILAR SISTEM

Sistem AQUA-TARA secara ketat memisahkan tiga pilar analitik tanpa saling tumpang tindih:

```
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                 ARSITEKTUR TIGA PILAR AQUA-TARA                                 │
├───────────────────────────────┬─────────────────────────────────┬───────────────────────────────┤
│ 1. K-Means Spasial            │ 2. Sensor Kekeruhan (Turbidity) │ 3. Modul Hidraulik (Score_H)  │
├───────────────────────────────┼─────────────────────────────────┼───────────────────────────────┤
│ • Input: [lat, lng] warga.    │ • Input: Sensor fisik NTU.      │ • Input: Tekanan (bar) & debit.│
│ • Output: Centroid & Score_S. │ • Output: Score_T (Bobot 80%).  │ • Output: Score_H (Mandiri).  │
│ • Peran: Klasterisasi aduan.  │ • Peran: Mutu sanitasi air.     │ • Peran: Deteksi pipa pecah.  │
│ • Status: Robust & Verified.  │ • Status: Fallback 30 NTU.      │ • Status: Modul Terpisah SCADA.│
│                               │                                 │ • DILARANG MENIMPA Score_S!   │
└───────────────────────────────┴─────────────────────────────────┴───────────────────────────────┘
```

---

## 4. KESIMPULAN & ARAHAN TATA KELOLA

1. **Kode Produksi Tetap Terkunci:** Dilarang memodifikasi file backend Laravel, Flask ML service, database schema, migration, seeder, frontend Vue, atau formula kanonikal $\text{round}(0{,}8 \cdot Score_T + 0{,}2 \cdot Score_S)$.
2. **Keputusan Terbuka (Open Decisions):** Keputusan OD-SCORE-S-01 s.d. OD-SCORE-S-03 tetap berstatus Open Design Decision sampai adanya perjanjian kerja sama formal bersama utilitas air PDAM.
3. **Penyelesaian Tahap STEP 4D.79:** Tahap audit ketahanan, semantik $Score_S$, dan perancangan validasi operasional telah diselesaikan secara tuntas. Tidak ada perubahan git staging, commit, push, atau deployment yang dilakukan. Pekerjaan dihentikan dan tidak melanjutkan ke STEP 4D.80.
