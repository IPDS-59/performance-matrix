# Matriks Kinerja BPS Sulteng

Aplikasi web untuk pencatatan dan pemantauan kinerja pegawai BPS Provinsi Sulawesi Tengah. Dibangun dengan Laravel 13, Vue 3, dan Inertia.js.

## Fitur Utama

- **Manajemen proyek & rincian kegiatan** — Admin dan ketua tim dapat membuat, mengedit, dan mengelola proyek beserta rincian kegiatan (work items) beserta target per anggota.
- **Pelaporan kinerja** — Pegawai melaporkan realisasi bulanan per rincian kegiatan, dilengkapi bukti dukung (file atau tautan).
- **Alur persetujuan** — Ketua tim meninjau, menyetujui, atau menolak laporan. Penolakan dapat diajukan ulang (resubmit) dengan notifikasi otomatis ke ketua.
- **Lampiran inline** — Pratinjau gambar, PDF, dan URL langsung di aplikasi tanpa unduh paksa.
- **Notifikasi real-time** — Bell notifications dengan polling latar belakang; halaman notifikasi lengkap dengan pagination.
- **Dashboard** — Statistik personal, matriks tim, dan grafik peringkat karyawan per peran (admin, kepala, staf).
- **Matriks kinerja** — Tampilan lintas tim dan pegawai dengan filter tahun/bulan.

## Tumpukan Teknologi

| Layer | Teknologi |
|---|---|
| Backend | PHP 8.4, Laravel 13, Spatie Permission |
| Frontend | Vue 3 (Composition API), Inertia.js, TypeScript |
| UI | Tailwind CSS v3, shadcn-vue (reka-ui), tailwindcss-animate |
| Build | Vite 6, pnpm |
| Database | MySQL / SQLite (testing) |
| Testing | Pest, PHPUnit |
| CI | GitHub Actions (banua-coder/banua-coder-workflow) |

## Peran & Izin

| Peran | Hak Akses |
|---|---|
| `admin` | Kelola tim, pegawai, proyek, rincian kegiatan; lihat semua laporan & matriks |
| `head` | Lihat matriks & laporan; masuk kinerja |
| `staff` | Masuk kinerja; ketua tim dapat membuat proyek untuk timnya |

Ketua tim (staf yang menjadi `leader_id` pada proyek) dapat menambah/mengedit rincian kegiatan dan menyetujui laporan anggota.

## Instalasi

### Prasyarat

- PHP >= 8.4
- Composer
- Node.js >= 20
- pnpm
- MySQL 8+ atau SQLite

### Langkah Setup

```bash
# 1. Clone dan masuk ke direktori
git clone <repo-url>
cd performance-matrix

# 2. Instal dependensi PHP
composer install

# 3. Instal dependensi JavaScript
pnpm install

# 4. Salin dan konfigurasi environment
cp .env.example .env
php artisan key:generate

# 5. Konfigurasi database di .env, lalu jalankan migrasi dan seeder
php artisan migrate --seed

# 6. Build aset frontend
pnpm run build

# 7. (Development) Jalankan dev server
php artisan serve
pnpm run dev
```

### Akun Default (setelah seeding)

| Email | Password | Peran |
|---|---|---|
| `admin@bpssulteng.id` | `password` | admin |
| `andi@bpssulteng.id` | `password` | head |
| `bagas@bpssulteng.id` | `password` | staff (ketua tim) |
| `citra@bpssulteng.id` | `password` | staff |

## Pengujian

```bash
# Jalankan semua tes
php artisan test

# Dengan coverage
php artisan test --coverage
```

## Struktur Proyek (Ringkas)

```
app/
  Actions/          # Business logic (SavePerformanceReport, SyncProjectMembers, …)
  Events/           # PerformanceBatchSubmitted, dll.
  Http/Controllers/ # Thin controllers
  Listeners/        # NotifyTeamLeadOnReportSubmitted, dll.
  Models/           # Project, WorkItem, WorkItemAssignment, PerformanceReport, …
  Policies/         # ProjectPolicy, PerformancePolicy, …
resources/js/
  Components/       # Reusable components (PerformanceTimeline, BuktiDukungPicker, …)
  Layouts/          # AppLayout
  Pages/            # Inertia pages (Dashboard, Projects, Performance, …)
```

## Lisensi

Hak Cipta © 2025 BPS Provinsi Sulawesi Tengah. Seluruh hak dilindungi.
