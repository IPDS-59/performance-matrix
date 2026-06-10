# Kinetik — Delivery Plan (Template untuk Linear)

## Pemetaan ke Linear (penting)

| Konsep Linear | Dipakai untuk | Status kamu |
|---|---|---|
| **Project** | satu wadah besar = **`Kinetik`** | ✅ sudah dibuat |
| **Milestone** | fase **M1 / M2 / M3** | ✅ sudah dibuat |
| **Issue** | tiap task | belum |
| **Label** | area/"epic" (`integrasi`, `pilar-a`, `pilar-b`, `infra`, `spike`, `reserved`) | buat dulu |
| Assignee / Priority / Estimate | per issue | per issue |

> **E0–E4 itu BUKAN project.** Mereka cuma pengelompokan area → jadikan **Label**.
> Semua issue tinggal di **satu** project `Kinetik`, dibedakan lewat **Milestone**
> + **Label**.

### Langkah cepat
1. Di project `Kinetik`: **Add members** (Sukma, Jihan, Ical).
2. Buat **Labels**: `integrasi`, `pilar-a`, `pilar-b`, `infra`, `spike`, `reserved`,
   plus `backend`, `frontend`.
3. **Import issues** (sidebar kiri "Import issues") → CSV di bawah → target project
   **Kinetik** → map kolom (Title, Description, Priority, Estimate, Assignee, Labels).
4. Kalau importer tidak punya kolom **Milestone**: import dulu, lalu di Linear
   **filter by Label** → pilih semua → set Milestone sesuai tabel di bawah.

---

## Setup

- **Project:** Kinetik · **Members:** Sukma (lead), Jihan, Ical
- **Labels (area/epic):** `integrasi` · `pilar-a` · `pilar-b` · `infra` · `spike` · `reserved` · (+ `backend` / `frontend`)
- **Priority:** Urgent (P0) · High (P1) · Medium (P2) · Low (P3)
- **Estimate (points):** 1 · 2 · 3 · 5 · 8
- **Milestones:** **M1 (MVP)** · **M2** · **M3**

## Working Agreement (ringkas)

- **Git:** `feature/*` → `develop` → `main` (gitflow). Semua PR di-review **Sukma**.
- **Definition of Done:** kode + test hijau + PR merged + (jika UI) diverifikasi di browser.
- **Cadence:** planning mingguan + update harian (async).
- **Sumber kebenaran desain:** RFC (`rfc-001-kinetik.md`). Plan ini turunannya.

---

## Daftar Issue (per area)

Format: **Judul** — deskripsi · *Assignee · Priority · Estimate · Milestone · Labels*

### Area: Foundation & Ops `(label: infra)`
- **Add members + labels di Linear** — siapkan board — *Sukma · High · 1 · M1 · infra*
- **Merge & release pekerjaan tertunda** (PR #76 shadcn) `develop → main` — *Sukma · High · 1 · M1 · infra*
- **Koordinasi ke Pak Hespri** — akses/dok API resmi + solusi token + letak Target/Realisasi — *Sukma · High · 2 · M1 · integrasi, spike*
- **Spike: keputusan SSO realm `pegawai-bps`** — *Sukma · Medium · 2 · M2 · spike, integrasi*

### Area: kipApp Sync Hardening `(label: integrasi)`
- **Sync struktur kipApp** → Tim/Projek/Anggota Tim/Anggota Projek (otomatis) — *Sukma · Urgent · 5 · M1 · integrasi, backend*
- **Daily cron sinkronisasi** (sekarang mingguan) — *Sukma · Urgent · 3 · M1 · integrasi, backend*
- **Token lifecycle** — deteksi kadaluarsa + alert + flow refresh/re-paste — *Sukma · High · 3 · M1 · integrasi, backend*
- **Robustness sync** — error handling, retry, logging, monitor gagal — *Jihan · High · 3 · M2 · integrasi, backend*
- **Target/Realisasi per kegiatan** — sesuaikan form claim; cek ketersediaan di kipApp — *Sukma · High · 2 · M1 · spike, integrasi*

### Area: Pilar A — Pelaporan `(label: pilar-a)`
- **Migrasi + model `recap_documents`** — *Sukma · Urgent · 3 · M1 · pilar-a, backend*
- **FinalizeRecapAction** (snapshot saat finalize) + buka kembali — *Sukma · Urgent · 5 · M1 · pilar-a, backend*
- **UI Finalize/Buka + badge status** di rekap mingguan — *Ical · Urgent · 3 · M1 · pilar-a, frontend*
- **PromoteRecapAction** (mingguan → bulanan) — *Sukma · High · 3 · M2 · pilar-a, backend*
- **UI Ringkasan Bulanan** (parafrase) untuk Rapat Bulanan — *Ical · High · 5 · M2 · pilar-a, frontend*
- **Bukti rapat** — batasi upload ke PJ + aturan wajib sebelum finalize — *Ical · Medium · 2 · M2 · pilar-a*
- **Triwulanan (FRA)** promote + UI + alur persetujuan — *Ical · Medium · 5 · M3 · pilar-a*
- **Tests Pilar A** — *Ical · High · 3 · M2 · pilar-a, backend*

### Area: Pilar B — Pemantauan `(label: pilar-b)`
- **WorkloadAnalyticsService** — metrik coverage/gap/volume/jam/capaian/recency — *Jihan · Urgent · 5 · M1 · pilar-b, backend*
- **Dashboard Tim** — ranking anggota, overload/underload, status aktif/tidak `(MVP)` — *Jihan · Urgent · 5 · M1 · pilar-b, frontend*
- **Tabel cache `workload_snapshots`** + job refresh — *Jihan · High · 3 · M2 · pilar-b, backend*
- **Dashboard Kantor** — antar tim, pemerataan, tren — *Jihan · High · 5 · M2 · pilar-b, frontend*
- **Profil Anggota** — kalender gap, jam, capaian, tren — *Jihan · Medium · 5 · M3 · pilar-b, frontend*
- **`complexity_weight` di RK** + konfigurasi bobot — *Sukma · Medium · 3 · M3 · pilar-b, backend*
- **Konfigurasi ambang** status aktif + hari kerja (libur/cuti) — *Jihan · Medium · 3 · M3 · pilar-b, backend*
- **Tests Pilar B** — *Jihan · High · 3 · M2 · pilar-b, backend*

### Area: Reserved (Backlog) `(label: reserved)`
- **RF-1 Penilaian kinerja** — *— · Low · 8 · — · reserved*
- **RF-2 Penilaian Kepala Kab/Kota** — *— · Low · — · — · reserved*
- **RF-4 Best Employee (AI)** — *— · Low · — · — · reserved*
- **RF-5 Angka Kredit dari SKP** — *— · Low · — · — · reserved*
- **RF-6 Estimasi Naik Pangkat** — *— · Low · — · — · reserved*

---

## Ringkasan Milestone

| Milestone | Isi utama |
|---|---|
| **M1 (MVP)** | daily sync + struktur; Pilar A weekly finalize+snapshot; Pilar B Dashboard Tim |
| **M2** | Pilar A promote→bulanan + UI bulanan; Pilar B Dashboard Kantor; tests |
| **M3** | Triwulanan FRA + persetujuan; bobot kompleksitas; Profil Anggota; ambang |

---

## CSV untuk Import ke Linear

> Simpan sebagai `kinetik-issues.csv`. Linear → **Import issues** → CSV →
> **target project = Kinetik** → map kolom. Kolom **Milestone** mungkin tidak
> langsung ke-map; kalau begitu, import dulu lalu set Milestone manual (filter by
> Label → pilih semua → set Milestone).

```csv
Title,Description,Priority,Estimate,Assignee,Labels,Milestone
Add members + labels di Linear,Siapkan board Kinetik,High,1,Sukma,infra,M1
Merge & release pekerjaan tertunda (PR #76),Merge shadcn tables develop->main,High,1,Sukma,infra,M1
Koordinasi API resmi + token + Target ke Hespri,Minta akses/dok API resmi & solusi token & letak target,High,2,Sukma,"integrasi,spike",M1
Spike keputusan SSO realm pegawai-bps,Evaluasi integrasi SSO Probis item 4,Medium,2,Sukma,"spike,integrasi",M2
Sync struktur kipApp (tim/projek/anggota/anggota projek),Tarik struktur otomatis dari kipApp,Urgent,5,Sukma,"integrasi,backend",M1
Daily cron sinkronisasi,Jadwalkan sync harian,Urgent,3,Sukma,"integrasi,backend",M1
Token lifecycle (expire/alert/refresh),Deteksi token kadaluarsa + flow refresh,High,3,Sukma,"integrasi,backend",M1
Robustness sync (retry/logging/monitor),Error handling & monitoring sync gagal,High,3,Jihan,"integrasi,backend",M2
Target/Realisasi per kegiatan,Sesuaikan form claim & cek ketersediaan di kipApp,High,2,Sukma,"spike,integrasi",M1
Migrasi + model recap_documents,Tabel dokumen rekap (snapshot/promote),Urgent,3,Sukma,"pilar-a,backend",M1
FinalizeRecapAction + buka kembali,Snapshot rekap saat finalize,Urgent,5,Sukma,"pilar-a,backend",M1
UI Finalize/Buka + badge status,Tombol finalize & status di rekap mingguan,Urgent,3,Ical,"pilar-a,frontend",M1
PromoteRecapAction (mingguan->bulanan),Salin snapshot mingguan ke bulanan,High,3,Sukma,"pilar-a,backend",M2
UI Ringkasan Bulanan (parafrase),Tampilan bulanan untuk Rapat Bulanan,High,5,Ical,"pilar-a,frontend",M2
Bukti rapat PJ-only + wajib sebelum finalize,Batasi upload bukti ke PJ,Medium,2,Ical,pilar-a,M2
Triwulanan FRA + persetujuan,Promote triwulanan + alur persetujuan,Medium,5,Ical,pilar-a,M3
Tests Pilar A,Test finalize & promote,High,3,Ical,"pilar-a,backend",M2
WorkloadAnalyticsService,Hitung metrik akuntabilitas,Urgent,5,Jihan,"pilar-b,backend",M1
Dashboard Tim,Ranking anggota + status aktif/tidak,Urgent,5,Jihan,"pilar-b,frontend",M1
Tabel cache workload_snapshots + job,Cache metrik untuk performa & tren,High,3,Jihan,"pilar-b,backend",M2
Dashboard Kantor,Antar tim + pemerataan + tren,High,5,Jihan,"pilar-b,frontend",M2
Profil Anggota,Kalender gap + jam + capaian + tren,Medium,5,Jihan,"pilar-b,frontend",M3
complexity_weight di RK + konfigurasi bobot,Bobot kompleksitas indeks beban,Medium,3,Sukma,"pilar-b,backend",M3
Konfigurasi ambang + hari kerja (libur/cuti),Atur ambang status & kalender kerja,Medium,3,Jihan,"pilar-b,backend",M3
Tests Pilar B,Test metrik & indeks beban,High,3,Jihan,"pilar-b,backend",M2
RF-1 Penilaian kinerja,Penilaian ketua->anggota & pimpinan->ketua,Low,8,,reserved,
RF-2 Penilaian Kepala Kab/Kota,Penilaian kepala satker,Low,,,reserved,
RF-4 Best Employee (AI),Ranking via AI,Low,,,reserved,
RF-5 Angka Kredit dari SKP,Konversi angka kredit,Low,,,reserved,
RF-6 Estimasi Naik Pangkat,Estimasi kepangkatan,Low,,,reserved,
```
