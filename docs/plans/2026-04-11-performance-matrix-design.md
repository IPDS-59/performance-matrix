# Performance Matrix System — Design Document
**Project:** Sistem Matriks Kinerja Pegawai BPS Provinsi Sulawesi Tengah
**Date:** 2026-04-11
**Status:** Approved — ready for implementation

---

## 1. Overview

A full-stack internal web application replacing the current Google Forms workflow for tracking employee performance, project assignments, and monthly achievement reporting at BPS Provinsi Sulawesi Tengah.

---

## 2. Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 (PHP 8.3+) |
| Frontend | Vue 3 (`<script setup>` + Composition API + TypeScript) |
| Bridge | Inertia.js |
| UI Components | shadcn-vue + Tailwind CSS |
| State (client) | Pinia |
| Auth | Laravel Breeze (Inertia/Vue variant) |
| Authorization | spatie/laravel-permission |
| Database | MySQL 8.0 |
| Package Manager | pnpm |
| Testing | Pest (PHP) + Vitest (JS) |

---

## 3. Data Model

All identifiers use English naming. UI labels remain in Indonesian.

### Tables

```
teams
  id, name, code, description, is_active, timestamps

employees
  id, user_id (FK→users, nullable), team_id (FK→teams)
  name, full_name, employee_number (unique, nullable)
  position, degree_front, degree_back, is_active, timestamps

projects
  id, team_id (FK→teams), leader_id (FK→employees, nullable)
  name, description, objective, kpi
  status (active|completed|cancelled), year
  timestamps

project_members
  id, project_id (FK→projects), employee_id (FK→employees)
  role (leader|member), timestamps
  UNIQUE(project_id, employee_id)

work_items
  id, project_id (FK→projects), number (smallint)
  description, timestamps
  UNIQUE(project_id, number)

performance_reports
  id, work_item_id (FK→work_items), reported_by (FK→employees, nullable)
  period_month (tinyint), period_year (year)
  achievement_percentage (decimal 5,2), issues (text, nullable)
  solutions (text, nullable), action_plan (text, nullable)
  timestamps
  UNIQUE(work_item_id, period_year, period_month)

users (Breeze default + additions)
  + role enum(admin|head|staff)  ← used by Spatie as role name seed
```

### Data Hierarchy

```
Team (19)
└── Project (139, scoped by year)
    ├── leader_id → Employee
    ├── project_members (810 records, role: leader|member)
    └── WorkItem (sub-activities, numbered 1..n)
        └── PerformanceReport (monthly)
            ├── achievement_percentage (0–100)
            ├── issues
            ├── solutions
            └── action_plan
```

---

## 4. Roles & Permissions (Spatie)

### Roles
- `admin` — full system management
- `head` — read-only on all data + matrix + reports
- `staff` — enter performance reports for own assigned projects only

### Permission Matrix

| Permission | admin | head | staff |
|---|:---:|:---:|:---:|
| `manage-teams` | ✓ | | |
| `manage-employees` | ✓ | | |
| `manage-projects` | ✓ | | |
| `manage-work-items` | ✓ | | |
| `view-matrix` | ✓ | ✓ | |
| `view-reports` | ✓ | ✓ | |
| `enter-performance` | | | ✓ |

### Scoping Rule (staff)
A staff user linked to `employees.user_id` may only submit `PerformanceReport` records where their `employee_id` appears in `project_members` for that project's `work_item`.

### User ↔ Employee Link
`users.id` → `employees.user_id` (nullable). Staff must be linked to enter performance. Head/admin do not require a link.

---

## 5. Event / Listener Architecture

### Events → Listeners

| Event | Listeners |
|---|---|
| `PerformanceReportSaved` | `LogPerformanceActivity`, `RecalculateTeamProgress` |
| `PerformanceBatchSubmitted` | `LogPerformanceActivity`, `RecalculateTeamProgress` |
| `ProjectMembersUpdated` | `SyncProjectLeaderRole` |
| `EmployeeLinkedToUser` | `AssignStaffRole` |

### `RecalculateTeamProgress`
Maintains a denormalized `team_monthly_progress` table (or cache key) so the Dashboard doesn't run heavy aggregations per request. Fires after any batch save.

---

## 6. Backend Architecture

### Controller Pattern (Thin Controllers + Actions)
Controllers handle only HTTP concerns: validate → call Action → respond.

```
app/
├── Actions/
│   ├── Performance/
│   │   ├── SavePerformanceBatchAction.php
│   │   └── SavePerformanceReportAction.php
│   ├── Projects/
│   │   └── SyncProjectMembersAction.php
│   └── Employees/
│       └── LinkEmployeeToUserAction.php
├── Events/
│   ├── PerformanceReportSaved.php
│   ├── PerformanceBatchSubmitted.php
│   ├── ProjectMembersUpdated.php
│   └── EmployeeLinkedToUser.php
├── Listeners/
│   ├── LogPerformanceActivity.php
│   ├── RecalculateTeamProgress.php
│   ├── SyncProjectLeaderRole.php
│   └── AssignStaffRole.php
├── Http/Controllers/
│   ├── DashboardController.php
│   ├── TeamController.php
│   ├── EmployeeController.php
│   ├── ProjectController.php
│   ├── WorkItemController.php
│   └── PerformanceController.php
├── Models/
│   ├── Team.php
│   ├── Employee.php
│   ├── Project.php
│   ├── WorkItem.php
│   └── PerformanceReport.php
└── Policies/
    ├── TeamPolicy.php
    ├── EmployeePolicy.php
    ├── ProjectPolicy.php
    ├── WorkItemPolicy.php
    └── PerformancePolicy.php
```

### Routes (routes/web.php)
```php
// Admin only
Route::resource('teams', TeamController::class);
Route::resource('employees', EmployeeController::class);
Route::resource('projects', ProjectController::class);
Route::post('/projects/{project}/work-items', [WorkItemController::class, 'store']);
Route::put('/work-items/{workItem}', [WorkItemController::class, 'update']);
Route::delete('/work-items/{workItem}', [WorkItemController::class, 'destroy']);

// Head + Admin
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/matrix', [DashboardController::class, 'matrix'])->name('matrix');

// Staff
Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');
Route::post('/performance/batch', [PerformanceController::class, 'storeBatch'])->name('performance.batch');
```

---

## 7. Dashboard (Role-Aware)

| Role | View |
|---|---|
| `staff` | Own projects' status this month (cards + mini progress bars) |
| `head` | Team summary: avg achievement % per team, top/bottom teams |
| `admin` | Executive: org-wide achievement %, trend chart (12 months), per-team breakdown |

Charts: vue-chartjs (line chart for trend, bar chart for team breakdown).

---

## 8. Matrix View (Toggle)

- **Assignment view**: rows = employees, columns = projects, cell = ✓ if assigned
- **Progress view**: same grid, cell = achievement % for selected month (color-coded: red <50, yellow 50–79, green ≥80)
- Virtual scrolling for the grid (70×139 = ~9,700 cells)
- Filter: year, month, team

---

## 9. Frontend Architecture

### Structure
```
resources/js/
├── composables/
│   ├── useFilters.ts         # toRefs(reactive({year, month, teamId}))
│   ├── useMatrix.ts          # MaybeRefOrGetter<MatrixFilters>
│   ├── usePerformanceForm.ts # batch form state + submit
│   ├── useDashboard.ts       # role-aware data
│   └── useAsync.ts           # loading/error wrapper
├── stores/
│   ├── sidebar.ts            # Pinia: sidebar open/close
│   └── toast.ts              # Pinia: toast queue
├── layouts/
│   └── AppLayout.vue         # role-aware nav sidebar
├── components/
│   ├── ui/                   # shadcn-vue re-exports
│   ├── matrix/
│   │   ├── MatrixGrid.vue    # stateless, receives rows/cols/cells
│   │   └── MatrixCell.vue    # stateless cell renderer
│   ├── performance/
│   │   ├── TeamAccordion.vue
│   │   ├── ProjectAccordion.vue
│   │   └── WorkItemRow.vue
│   └── dashboard/
│       ├── StatCard.vue
│       ├── TrendChart.vue
│       └── TeamBreakdownChart.vue
└── pages/
    ├── Dashboard.vue
    ├── Matrix/Index.vue
    ├── Teams/{Index,Create,Edit}.vue
    ├── Employees/{Index,Create,Edit}.vue
    ├── Projects/{Index,Create,Edit}.vue
    └── Performance/Index.vue
```

### Composable Rules (enforced project-wide)
1. Always return `toRefs(state)` or individual `ref`s — never a raw `reactive` object
2. Inputs that may be refs use `MaybeRefOrGetter<T>` + `toValue()` inside watchers
3. Composables own state; components receive props and emit events only

### Performance
- Inertia partial reloads for filter changes (no full page reload)
- Virtual scrolling on matrix grid
- Lazy-loaded page chunks via Vite
- Denormalized team progress cache (server-side) to avoid expensive aggregations

---

## 10. Branding

- **Logo:** `public/images/bps-sulteng-logo.png` (BPS Provinsi Sulawesi Tengah)
- **Primary colour:** BPS blue (`#1B4B8A`)
- **Font:** System sans-serif stack (no custom font load overhead)
- **Locale:** Indonesian (`id`) for all UI labels, date/number formatting

---

## 11. CI/CD (banua-coder-workflow)

```
.github/workflows/
├── ci.yml           # calls banua-coder/banua-coder-workflow ci-laravel.yml
│                    # PHP 8.3, Node 20, pnpm, MySQL, Pest, coverage
├── release.yml      # calls release.yml (web-app, changelog, back-merge)
└── housekeeping.yml # calls housekeeping.yml (delete merged branches)
```

---

## 12. Git Workflow

- **Branches:** `main`, `develop`, `feature/*`, `release/*`, `hotfix/*`
- **PRs:** feature → develop (never directly to main)
- **AI files excluded from git:** `CLAUDE-LARAVEL-INERTIA.md`, `seeder_data.json`, `.claude/`
- **`.gitignore` additions:** standard Laravel + `*.claude.md`, `seeder_data.json`

---

## 13. Issue Tracking

- **Tool:** Beads (`bd`) — local only (Dolt, no remote push)
- **Workflow:** Create one Beads issue per implementation phase; write ADRs and progress notes in issue body/comments

---

## 14. Seeder Execution Order

1. `TeamSeeder`
2. `EmployeeSeeder`
3. `ProjectSeeder` (includes `project_members`)
4. `WorkItemSeeder` (from `seeder_data.json`)
5. `RolePermissionSeeder` (Spatie roles + permissions)
6. `UserSeeder` (demo admin/head/staff accounts)
