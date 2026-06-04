# kipApp API Integration — Kinetik Phase 5

## VERIFY AGAINST HARDCOPY SPEC

Before enabling this integration in production, the following **MUST** be
confirmed against the official kipApp API documentation (or a populated test
account):

- [ ] **(a) Official server auth mechanism.** Current implementation uses a
  per-user ~24h session JWT (header `x-auth: Bearer <token>`) captured from
  browser DevTools. The official server-to-server API credentials have been
  approved but not yet received. When available, either set `KIP_TOKEN` to the
  granted key or bind a new `KipAuthenticator` implementation (e.g. OAuth2
  client-credentials) in the container.

- [ ] **(b) Populated shape of rkpegawai / rktimkerja.** During the 2026-06-04
  live capture the `GET /v1/dashboard/rkpegawai?niplama=<niplama>` endpoint
  returned `{jumlahrk:0}`, so the per-RK row fields are still unconfirmed.
  `KipPlanData::fromApiRow` retains defensive candidate-key mapping until a
  populated response is captured.

- [x] **(c) Activity field names — RESOLVED (captured live 2026-06-04).**
  See confirmed field table below. `KipActivityData::fromApiRow` now uses the
  confirmed primary keys with minimal fallbacks.

- [x] **(d) Activity endpoint flow — RESOLVED (captured live 2026-06-04).**
  The fetch is a two-step process. See "Two-Step Activity Fetch" below.

---

## Architecture Overview

```
config/kinetik.php
    └── KIP_SOURCE (api|mock), KIP_BASE_URL, KIP_TOKEN, KIP_TIMEOUT

KinetikServiceProvider
    ├── KipAuthenticator → ConfigBearerAuthenticator   (swappable)
    └── KipActivitySource → ApiKipActivitySource       (or Mock when KIP_SOURCE=mock)

KipActivitySource (contract)
    ├── ApiKipActivitySource  — live HTTP calls via Laravel Http client
    └── MockKipActivitySource — hard-coded fixtures for offline dev/CI

DTOs
    ├── KipActivityData::fromApiRow(array $row): self
    └── KipPlanData::fromApiRow(array $row): self

SyncKipActivitiesAction::execute(KipActivitySource, Collection<Employee>): int
    └── updateOrCreate keyed on external_id

kinetik:sync-kip-activities [--niplama=*]
    └── scheduled: weeklyOn(Monday, 05:00)
```

## Two-Step Activity Fetch

`ApiKipActivitySource::fetchUnsentActivities(string $nipLama)` resolves unsent
activities in two HTTP calls:

**Step 1** — `GET /v1/dashboard/kegiatanpegawai/belumkirim?niplama=<niplama>`

Returns an array of period/SKP group objects. Each group has a `kegiatan` array;
collect every `skpid` where `jumlahkegiatan > 0`.

```json
[
  {
    "jumlahkegiatan": 1,
    "kegiatan": [
      {
        "skpid": "1284341", "periodeid": 8, "tahun": 2026,
        "periodepenilaianid": 2, "bulan": "II",
        "wilayahid": "7200_11", "kodewilayah": "7200",
        "wilayah": "Sulawesi Tengah", "unitkerjaid": "42",
        "kodeunitkerja": "92000", "unitkerja": "BPS Provinsi",
        "jumlahkegiatan": 1
      }
    ]
  }
]
```

**Step 2** — For each collected `skpid`:
`GET /v1/kegiatan?skpid=<skpid>`

Returns an array of per-day activity rows. Only rows where
`tanggalkirim === null` are unsent; those are the ones kept and persisted.

```json
[
  {
    "kegiatanperhariid": "14168350", "rkid": "13946149",
    "rencanakinerja": "Terlaksananya Kegiatan Press Release yang Baik dan Sesuai SOP",
    "kegiatan": "Monitoring Penyiapan Dukungan TI untuk Press Release Juni 2026",
    "butirid": null, "kodebutir": null, "butir": null, "output": null,
    "tanggal": "2026-06-02", "tanggalselesai": null,
    "jammulai": null, "jamselesai": null,
    "progres": 100,
    "capaian": "Press Release terlaksana dengan lancar",
    "datadukung": "https://www.youtube.com/watch?v=Cqhe-dMuoD8",
    "iscapaianskp": 1, "parentid": null,
    "tanggalkirim": null,
    "periodeid": 8, "tahun": 2026,
    "wilayahid": "7200_11", "namawilayah": "Sulawesi Tengah",
    "unitkerjaid": "42", "namaunitkerja": "BPS Provinsi"
  }
]
```

`fetchActivitiesBySkp(string $skpid): Collection` is also available as a public
method for direct single-SKP fetches (returns all rows without filtering).

## Confirmed Activity Field Mapping

Fields confirmed from live capture (niplama 340054274, 2026-06-04).
Primary key listed first; fallback in parentheses where kept.

| DTO property | API key (primary) | Fallback |
|---|---|---|
| `externalId` | `kegiatanperhariid` | `kegiatan_id` |
| `description` | `kegiatan` | `uraian_kegiatan` |
| `dateStart` | `tanggal` | `tgl_mulai` |
| `dateEnd` | `tanggalselesai` | `tgl_selesai` |
| `timeStart` | `jammulai` | `jam_mulai` |
| `timeEnd` | `jamselesai` | `jam_selesai` |
| `evidenceUrl` | `datadukung` | `bukti_dukung` |
| `rkExternalId` | `rkid` | — |
| `rkName` | `rencanakinerja` | — |
| `progress` | `progres` (int) | — |
| `achievementNote` | `capaian` | — |
| `sentAt` | `tanggalkirim` (null = unsent) | — |
| `periodId` | `periodeid` | — |
| `sourceYear` | `tahun` (int) | — |
| `raw` | whole row | — |

## Configuration

`.env` / `.env.example` keys:

| Key | Default | Description |
|-----|---------|-------------|
| `KIP_SOURCE` | `api` | `api` = live kipApp; `mock` = offline fixture |
| `KIP_BASE_URL` | `https://kipapp.bps.go.id/api` | API base URL |
| `KIP_TOKEN` | *(empty)* | Bearer token for `x-auth` header. Paste the full value from DevTools. Official creds TBD. |
| `KIP_TIMEOUT` | `15` | HTTP timeout in seconds |

## Auth

The `KipAuthenticator` contract has a single method `apply(PendingRequest): PendingRequest`.
`ConfigBearerAuthenticator` (the default) reads `KIP_TOKEN` from config and
adds `x-auth: Bearer <token>` to every request. If the token is empty the
header is omitted (no-op, not an error).

To swap in a different auth mechanism (e.g. OAuth2 client-credentials once
official creds arrive):

```php
// In KinetikServiceProvider or AppServiceProvider
$this->app->bind(KipAuthenticator::class, MyOAuth2Authenticator::class);
```

## Database

Two migrations ship with this feature:

1. `2026_06_04_000001_add_nip_columns_to_employees` — adds `nip_lama` (9-digit
   legacy NIP) and `nip_baru` (18-digit new NIP) nullable columns to `employees`.
2. `2026_06_04_000002_create_kip_activities_table` — stores synced activities
   with `external_id` unique key (idempotent upserts), `employee_id` FK
   (nullable, null-on-delete), `raw_payload` JSON, new fields
   (`rk_external_id`, `rk_name`, `progress`, `achievement_note`, `period_id`,
   `source_year`, `sent_at`), and three reserved columns for future use.

Both migrations are compatible with SQLite (tests/CI) and PostgreSQL (production).

## Running the Sync

```bash
# Sync all active employees with a nip_lama
php artisan kinetik:sync-kip-activities

# Sync specific NIPs only
php artisan kinetik:sync-kip-activities --niplama=340054274 --niplama=340060925
```

The command is also scheduled weekly on Monday at 05:00 WIB via `routes/console.php`.

## Offline / Mock Mode

Set `KIP_SOURCE=mock` in `.env`. `MockKipActivitySource` returns 3 fixture
activities and 2 fixture plans per employee — sufficient for UI and sync
testing without network access. Mock fixtures use the confirmed field names
(`kegiatanperhariid`, `kegiatan`, `tanggal`, etc.).

## Source Files

| File | Purpose |
|------|---------|
| `config/kinetik.php` | All kipApp config keys |
| `app/Kinetik/Contracts/KipActivitySource.php` | Source contract |
| `app/Kinetik/Contracts/KipAuthenticator.php` | Auth contract |
| `app/Kinetik/Auth/ConfigBearerAuthenticator.php` | Default auth (config token) |
| `app/Kinetik/Data/KipActivityData.php` | Activity DTO |
| `app/Kinetik/Data/KipPlanData.php` | RK (plan) DTO |
| `app/Kinetik/Sources/ApiKipActivitySource.php` | Live HTTP source (2-step flow) |
| `app/Kinetik/Sources/MockKipActivitySource.php` | Fixture source |
| `app/Kinetik/Exceptions/KipApiException.php` | API error type |
| `app/Providers/KinetikServiceProvider.php` | Container bindings |
| `app/Actions/Kinetik/SyncKipActivitiesAction.php` | Upsert action |
| `app/Console/Commands/SyncKipActivitiesCommand.php` | Artisan command |
| `app/Models/KipActivity.php` | Eloquent model |
