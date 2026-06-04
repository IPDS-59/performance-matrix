# Kinetik Phase 1 — Data Model

## Hierarchy

```
Team (unit kerja)
 └─ PerformanceIndicator  (IKU — Indikator Kinerja Utama)
     └─ Project           (Program Kerja)
         └─ PerformancePlan  (RK — Rencana Kinerja)
             └─ WorkItem     (Butir Kerja)
```

Many-to-many team membership is tracked in the `employee_team` pivot table alongside the existing `employees.team_id` (home team) and `teams.leader_id` (canonical lead), which are preserved unchanged.

## Data Dictionary

### performance_indicators (IKU)

| Column | Indonesian domain term | Notes |
|---|---|---|
| `id` | ID | Primary key |
| `team_id` | Unit Kerja | FK → teams |
| `year` | Tahun Anggaran | Integer, e.g. 2026 |
| `code` | Kode IKU | Nullable, e.g. "IKU-01" |
| `name` | Nama IKU | |
| `target` | Target IKU | Decimal(12,2), nullable |
| `target_unit` | Satuan Target | e.g. "Dokumen", "%" |
| `description` | Deskripsi | Text, nullable |

### projects (Program Kerja)

New column added in this phase:

| Column | Indonesian domain term | Notes |
|---|---|---|
| `performance_indicator_id` | IKU Terkait | Nullable FK → performance_indicators |

### performance_plans (RK / Rencana Kinerja)

| Column | Indonesian domain term | Notes |
|---|---|---|
| `id` | ID | Primary key |
| `project_id` | Program Kerja | FK → projects, cascade delete |
| `code` | Kode RK | Nullable, e.g. "RK-01" |
| `description` | Uraian Rencana Kinerja | Text |
| `target` | Target RK | Decimal(12,2), nullable |
| `target_unit` | Satuan Target | e.g. "Kegiatan" |
| `period_type` | Jenis Periode | Default "year"; future values: "quarter", "month" |
| `period` | Periode | Nullable unsigned smallint, e.g. quarter 1–4 |
| `pic_employee_id` | PIC / Penanggung Jawab | Nullable FK → employees, null on delete |

### work_items (Butir Kerja)

New column added in this phase:

| Column | Indonesian domain term | Notes |
|---|---|---|
| `performance_plan_id` | RK Terkait | Nullable FK → performance_plans |

### employee_team (Keanggotaan Tim)

Pivot table for the many-to-many relationship between employees and teams.

| Column | Indonesian domain term | Notes |
|---|---|---|
| `id` | ID | Primary key |
| `employee_id` | Pegawai | FK → employees, cascade delete |
| `team_id` | Tim | FK → teams, cascade delete |
| `role` | Peran | `member` = Anggota Tim, `leader` = Ketua Tim |
| `is_primary` | Tim Utama | Boolean; true when this team matches `employees.team_id` |
| `started_at` | Tanggal Bergabung | Date, nullable |
| `ended_at` | Tanggal Berakhir | Date, nullable |

Unique constraint on `(employee_id, team_id)`. Index on `(team_id, role)`.

## Backwards Compatibility

- `employees.team_id` (home team): preserved, still used for single-team lookups and seeding `is_primary = true`.
- `teams.leader_id` (canonical lead): preserved, still used for badge display; seeding migration writes a `role = 'leader'` pivot row for each non-null `leader_id`.

## Seeding Migration

`2026_06_05_000600_seed_employee_team_memberships` backfills pivot rows from the existing denormalised columns:

1. For every `employee` with `team_id != null` → insert pivot row with `role = 'member'`, `is_primary = true` (if absent).
2. For every `team` with `leader_id != null` → upsert pivot row with `role = 'leader'` (update if row exists, insert otherwise).

The migration is idempotent and its `down()` is a no-op to avoid destroying hand-edited pivot data.
