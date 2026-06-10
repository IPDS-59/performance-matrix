# Sub-Diagram: Fungsi Inti Integrasi kipApp

> Zoom-in ke bagian terpenting: **bagaimana data kipApp masuk ke Kinetik lalu
> menjadi rekap**. Lengkapi ERD utama (`erd-kinetik.dbml`).

---

## 1. Sequence Diagram — Sinkronisasi & Claim

```mermaid
sequenceDiagram
  actor ADM as Admin / Cron
  participant KIN as Kinetik
  participant CRED as kip_credentials
  participant KIP as kipApp API
  participant DB as kip_activities
  actor ANG as Anggota

  ADM->>KIN: Klik "Sinkronkan Semua"
  KIN->>CRED: ambil 1 token admin (x-auth)
  loop tiap pegawai (kunci: niplama)
    KIN->>KIP: GET belumkirim?niplama
    KIP-->>KIN: daftar SKP (skpid)
    loop tiap skpid
      KIN->>KIP: GET kegiatan?skpid
      KIP-->>KIN: uraian harian (ambil yg tanggalkirim = null)
    end
    KIN->>DB: upsert kip_activities (by external_id)
  end

  Note over ANG,DB: Menu Rekap Mingguan
  ANG->>KIN: Claim kegiatan ke RK + isi Target/Realisasi/Kendala/Solusi/RTL
  KIN->>KIN: Capaian = Realisasi / Target x 100
  KIN-->>ANG: Tersimpan (activity_claims, status = saved)
```

**Inti yang ditunjukkan:**
- Cukup **1 token admin** untuk menarik **semua** pegawai (kunci = `niplama`).
- Tarikan **2 langkah**: `belumkirim` → `kegiatan?skpid`.
- Data mentah masuk ke `kip_activities`; setelah di-claim jadi `activity_claims`.

---

## 2. Flowchart — Alur Tarik & Claim

```mermaid
flowchart TD
  A["Mulai sinkronisasi<br/>(token admin)"] --> B{"Pegawai punya<br/>NIP Lama?"}
  B -- tidak --> B
  B -- ya --> C["GET belumkirim?niplama"]
  C --> D["GET kegiatan?skpid<br/>(tiap SKP)"]
  D --> E{"tanggalkirim = null?<br/>(belum dikirim)"}
  E -- tidak --> D
  E -- ya --> F["upsert kip_activities<br/>(external_id unik)"]
  F --> G["Tampil di Rekap Mingguan"]
  G --> H["Anggota claim ke RK<br/>+ Target/Realisasi/Kendala/Solusi/RTL"]
  H --> I["Simpan activity_claims"]
  I --> J["Rekap Tim per projek<br/>+ bukti rapat (PJ)"]
```

---

## 3. Pemetaan Field: kipApp → Kinetik

| Dari kipApp (`kegiatan`) | Kolom Kinetik | Catatan |
|---|---|---|
| `kegiatanperhariid` | `kip_activities.external_id` | kunci idempotent |
| `kegiatan` | `description` | uraian |
| `tanggal` / `tanggalselesai` | `activity_date_start/end` | |
| `jammulai` / `jamselesai` | `time_start/end` | opsi jam |
| `datadukung` | `evidence_url` | link bukti |
| `rkid` / `rencanakinerja` | `rk_external_id` / `rk_name` | tautan RK |
| `progres` (0–100) | `progress` | |
| `capaian` (teks) | `achievement_note` | |
| `tanggalkirim` | `sent_at` | null = belum kirim |
| **Target / Realisasi** | `activity_claims.target/realization` | **tidak ada di kipApp → manual** |

> Struktur (Tim/Projek/Anggota Projek) juga bisa ditarik: `timkerja/anggota`,
> `proyek?timkerjaid`, `proyek/anggota?proyekid`.
