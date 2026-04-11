# Performance Matrix System — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a full-stack employee performance tracking web app for BPS Provinsi Sulawesi Tengah using Laravel 13 + Vue 3 + Inertia.js + shadcn-vue, replacing the current Google Forms workflow.

**Architecture:** Thin controllers delegate to Action classes; side effects flow through Laravel Events → Listeners; Vue composables own all state using `toRefs`/`MaybeRefOrGetter`; shadcn-vue components are stateless presentational units.

**Tech Stack:** Laravel 13, PHP 8.3, Vue 3 (`<script setup>` + TypeScript), Inertia.js, shadcn-vue, Tailwind CSS, Pinia, spatie/laravel-permission, Pest, Vitest, pnpm, MySQL 8.0

---

## Pre-flight Checklist

Before starting, verify these are installed locally:
- PHP 8.3+ (`php -v`)
- Composer 2.x (`composer -V`)
- Node 20+ (`node -v`)
- pnpm 9+ (`pnpm -v`)
- MySQL 8.0 running locally
- `bd` CLI installed (`bd --version`)
- `gh` CLI authenticated (`gh auth status`)
- `git` with git-flow extension (`git flow version`)

---

## Phase 0: Project Bootstrap

### Task 0.1: Scaffold Laravel Project

**Files:**
- Create: `matriks-kinerja/` (new Laravel project, becomes the working directory)

**Step 1: Create the Laravel 13 project**
```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix
composer create-project laravel/laravel matriks-kinerja "^13.0"
cd matriks-kinerja
```

**Step 2: Install Laravel Breeze with Inertia/Vue**
```bash
composer require laravel/breeze --dev
php artisan breeze:install vue --typescript --ssr=false
```

**Step 3: Verify Breeze scaffolded correctly**
```bash
ls resources/js/Pages/Auth/
# Expected: Login.vue Register.vue ForgotPassword.vue etc.
```

**Step 4: Commit (bootstrap only — no AI files)**
```bash
git add .
git commit -m "chore: scaffold laravel 13 with breeze vue/inertia"
```

---

### Task 0.2: Install PHP Dependencies

**Step 1: Install Spatie Permission + Excel**
```bash
composer require spatie/laravel-permission maatwebsite/excel
```

**Step 2: Publish Spatie config**
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

**Step 3: Install Pest**
```bash
composer require pestphp/pest pestphp/pest-plugin-laravel --dev
php artisan pest:install
```

**Step 4: Commit**
```bash
git add composer.json composer.lock config/permission.php database/migrations/
git commit -m "chore: add spatie/permission, maatwebsite/excel, pest"
```

---

### Task 0.3: Install JS Dependencies & shadcn-vue

**Step 1: Install shadcn-vue**
```bash
pnpm dlx shadcn-vue@latest init
```
When prompted:
- Framework: Vite
- TypeScript: Yes
- Tailwind CSS config path: `tailwind.config.js`
- Components path: `@/components/ui`
- Utils path: `@/lib/utils`

**Step 2: Install required shadcn-vue components**
```bash
pnpm dlx shadcn-vue@latest add button card badge dialog sheet tabs progress select accordion table input label textarea separator avatar dropdown-menu tooltip
```

**Step 3: Install chart + virtual scroll + other utilities**
```bash
pnpm add chart.js vue-chartjs @vueuse/core pinia
pnpm add -D @types/node
```

**Step 4: Install Vitest**
```bash
pnpm add -D vitest @vue/test-utils jsdom @vitest/coverage-v8
```

**Step 5: Add Vitest config to `vite.config.ts`**
```ts
// vite.config.ts — add inside defineConfig
test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./resources/js/test/setup.ts'],
},
```

**Step 6: Create test setup file**
```ts
// resources/js/test/setup.ts
import { config } from '@vue/test-utils'
import { createPinia } from 'pinia'

config.global.plugins = [createPinia()]
```

**Step 7: Commit**
```bash
git add .
git commit -m "chore: add shadcn-vue, chart.js, pinia, vitest"
```

---

### Task 0.4: Configure Environment

**Files:**
- Modify: `.env`
- Modify: `config/app.php`

**Step 1: Set up `.env`**
```env
APP_NAME="Matriks Kinerja BPS Sulteng"
APP_URL=http://localhost:8000
APP_LOCALE=id
APP_TIMEZONE=Asia/Makassar

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=matriks_kinerja
DB_USERNAME=root
DB_PASSWORD=
```

**Step 2: Create the MySQL database**
```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS matriks_kinerja CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**Step 3: Set Indonesian locale in `config/app.php`**
```php
'locale' => env('APP_LOCALE', 'id'),
'faker_locale' => 'id_ID',
```

**Step 4: Commit**
```bash
git add config/app.php
git commit -m "chore: configure app locale and timezone for BPS Sulteng"
```
> Note: Never commit `.env` — it's already in `.gitignore`.

---

### Task 0.5: Initialize Git Flow + .gitignore

**Step 1: Initialize git flow**
```bash
git flow init -d
# Uses defaults: main=main, develop=develop, feature=feature/, etc.
```

**Step 2: Update `.gitignore` — add AI-related exclusions**
```
# AI / Claude
CLAUDE-LARAVEL-INERTIA.md
seeder_data.json
.claude/
*.claude.md
```

**Step 3: Verify excluded files aren't tracked**
```bash
git status
# CLAUDE-LARAVEL-INERTIA.md and seeder_data.json should NOT appear
```

**Step 4: Push initial branches to GitHub**
```bash
gh repo create matriks-kinerja-bps-sulteng --private --source=. --push
git push origin develop
```

**Step 5: Start feature branch for the full build**
```bash
git flow feature start initial-setup
```

---

### Task 0.6: Initialize Beads Issue Tracker

**Step 1: Initialize Beads in project root**
```bash
bd init
```

**Step 2: Create issues for each phase**
```bash
bd create --title "Phase 1: Database Foundation" --body "Migrations, models, seeders for teams/employees/projects/work_items/performance_reports/employee_educations"
bd create --title "Phase 2: Auth & Permissions" --body "Spatie roles (admin/head/staff), policies, user↔employee link"
bd create --title "Phase 3: Events & Listeners" --body "PerformanceReportSaved, PerformanceBatchSubmitted, ProjectMembersUpdated, EmployeeLinkedToUser"
bd create --title "Phase 4: Backend CRUD" --body "Actions, thin controllers, policies for Teams/Employees/Projects/WorkItems/Performance"
bd create --title "Phase 5: Dashboard Backend" --body "Role-aware DashboardController, matrix data endpoint"
bd create --title "Phase 6: Frontend Foundation" --body "AppLayout, Pinia stores, base composables (useFilters, useAsync)"
bd create --title "Phase 7: Admin CRUD Pages" --body "Teams, Employees, Projects Vue pages with shadcn-vue DataTable"
bd create --title "Phase 8: Performance Entry Page" --body "Accordion UI (Team→Project→WorkItem), usePerformanceForm composable"
bd create --title "Phase 9: Matrix View" --body "Assignment/Progress toggle grid with virtual scroll, useMatrix composable"
bd create --title "Phase 10: Dashboard Page" --body "Role-aware dashboard with charts, useDashboard composable"
bd create --title "Phase 11: CI/CD Setup" --body "GitHub Actions: ci.yml, release.yml, housekeeping.yml using banua-coder-workflow"
```

**Step 3: List issues to confirm**
```bash
bd list
```

---

### Task 0.7: Set Up GitHub Actions

**Files:**
- Create: `.github/workflows/ci.yml`
- Create: `.github/workflows/release.yml`
- Create: `.github/workflows/housekeeping.yml`

**Step 1: Create CI workflow**
```yaml
# .github/workflows/ci.yml
name: CI

on:
  push:
    branches: [develop, main]
  pull_request:
    branches: [develop, main]

jobs:
  ci:
    uses: banua-coder/banua-coder-workflow/.github/workflows/ci-laravel.yml@main
    with:
      php-version: '8.3'
      node-version: '20'
      pnpm-version: '9'
      database: mysql
      run-lint: true
      run-frontend-lint: true
      run-typecheck: true
      run-tests: true
      run-coverage: true
      build-assets: true
      coverage-driver: pcov
```

**Step 2: Create release workflow**
```yaml
# .github/workflows/release.yml
name: Release

on:
  push:
    tags: ['v*']

jobs:
  release:
    uses: banua-coder/banua-coder-workflow/.github/workflows/release.yml@main
    with:
      project-type: web-app
      main-branch: main
      develop-branch: develop
      changelog-format: conventional
      auto-merge-backport: true
      php-version: '8.3'
    secrets:
      GH_PAT: ${{ secrets.GH_PAT }}
```

**Step 3: Create housekeeping workflow**
```yaml
# .github/workflows/housekeeping.yml
name: Housekeeping

on:
  pull_request:
    types: [closed]

jobs:
  housekeeping:
    uses: banua-coder/banua-coder-workflow/.github/workflows/housekeeping.yml@main
    with:
      protected-branches: 'main,develop'
```

**Step 4: Commit**
```bash
git add .github/
git commit -m "ci: add github actions using banua-coder-workflow"
```

---

## Phase 1: Database Foundation

> Update Beads: `bd update 1 --status in_progress`

### Task 1.1: Migrations

**Files:**
- Create: `database/migrations/YYYY_MM_DD_000001_create_teams_table.php`
- Create: `database/migrations/YYYY_MM_DD_000002_create_employees_table.php`
- Create: `database/migrations/YYYY_MM_DD_000003_create_employee_educations_table.php`
- Create: `database/migrations/YYYY_MM_DD_000004_create_projects_table.php`
- Create: `database/migrations/YYYY_MM_DD_000005_create_project_members_table.php`
- Create: `database/migrations/YYYY_MM_DD_000006_create_work_items_table.php`
- Create: `database/migrations/YYYY_MM_DD_000007_create_performance_reports_table.php`
- Modify: `database/migrations/YYYY_MM_DD_000000_add_role_to_users_table.php`

**Step 1: Generate migrations**
```bash
php artisan make:migration create_teams_table
php artisan make:migration create_employees_table
php artisan make:migration create_employee_educations_table
php artisan make:migration create_projects_table
php artisan make:migration create_project_members_table
php artisan make:migration create_work_items_table
php artisan make:migration create_performance_reports_table
php artisan make:migration add_role_to_users_table
```

**Step 2: Implement `create_teams_table`**
```php
Schema::create('teams', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('code', 20)->nullable();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Step 3: Implement `create_employees_table`**
```php
Schema::create('employees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('team_id')->constrained();
    $table->string('name');
    $table->string('display_name')->nullable(); // write-through cache for degree-formatted name
    $table->string('employee_number', 20)->nullable()->unique();
    $table->string('position')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->index(['team_id', 'is_active']);
});
```

**Step 4: Implement `create_employee_educations_table`**
```php
Schema::create('employee_educations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->enum('type', ['prefix', 'suffix']);
    $table->string('abbreviation');           // required — e.g. "Ir.", "SST", "M.Si"
    $table->string('degree_level')->nullable(); // e.g. "S1", "S2", "D3"
    $table->string('field_of_study')->nullable();
    $table->string('institution')->nullable();
    $table->year('graduation_year')->nullable();
    $table->unsignedTinyInteger('sort_order')->default(0);
    $table->timestamps();
    $table->index(['employee_id', 'type', 'sort_order']);
});
```

**Step 5: Implement `create_projects_table`**
```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained();
    $table->foreignId('leader_id')->nullable()->constrained('employees')->nullOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->text('objective')->nullable();
    $table->text('kpi')->nullable();
    $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
    $table->year('year')->default(2026);
    $table->timestamps();
    $table->index(['team_id', 'year', 'status']);
});
```

**Step 6: Implement `create_project_members_table`**
```php
Schema::create('project_members', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->enum('role', ['leader', 'member'])->default('member');
    $table->timestamps();
    $table->unique(['project_id', 'employee_id']);
    $table->index('employee_id');
});
```

**Step 7: Implement `create_work_items_table`**
```php
Schema::create('work_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->unsignedSmallInteger('number');
    $table->text('description');
    $table->timestamps();
    $table->unique(['project_id', 'number']);
});
```

**Step 8: Implement `create_performance_reports_table`**
```php
Schema::create('performance_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('work_item_id')->constrained()->cascadeOnDelete();
    $table->foreignId('reported_by')->nullable()->constrained('employees')->nullOnDelete();
    $table->unsignedTinyInteger('period_month');
    $table->year('period_year');
    $table->decimal('achievement_percentage', 5, 2)->default(0);
    $table->text('issues')->nullable();
    $table->text('solutions')->nullable();
    $table->text('action_plan')->nullable();
    $table->timestamps();
    $table->unique(['work_item_id', 'period_year', 'period_month']);
    $table->index(['period_year', 'period_month']);
});
```

**Step 9: Implement `add_role_to_users_table`**
```php
Schema::table('users', function (Blueprint $table) {
    $table->enum('role', ['admin', 'head', 'staff'])->default('staff')->after('email');
});
```

**Step 10: Run migrations**
```bash
php artisan migrate
# Expected: all tables created without errors
```

**Step 11: Commit**
```bash
git add database/migrations/
git commit -m "feat(db): add all migrations for performance matrix schema"
```

---

### Task 1.2: Models

**Files:**
- Create: `app/Models/Team.php`
- Create: `app/Models/Employee.php`
- Create: `app/Models/EmployeeEducation.php`
- Create: `app/Models/Project.php`
- Create: `app/Models/WorkItem.php`
- Create: `app/Models/PerformanceReport.php`
- Modify: `app/Models/User.php`

**Step 1: Write failing model test**
```php
// tests/Unit/Models/EmployeeTest.php
it('computes display name from educations when display_name column is null', function () {
    $employee = Employee::factory()->create(['name' => 'John Doe', 'display_name' => null]);
    EmployeeEducation::factory()->create([
        'employee_id' => $employee->id,
        'type' => 'prefix',
        'abbreviation' => 'Dr.',
        'sort_order' => 0,
    ]);
    EmployeeEducation::factory()->create([
        'employee_id' => $employee->id,
        'type' => 'suffix',
        'abbreviation' => 'M.Si',
        'sort_order' => 0,
    ]);

    expect($employee->fresh()->display_name)->toBe('Dr. John Doe, M.Si');
});
```

**Step 2: Run test to see it fail**
```bash
php artisan test tests/Unit/Models/EmployeeTest.php
# Expected: FAIL — Employee class not found
```

**Step 3: Implement `Team` model**
```php
// app/Models/Team.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = ['name', 'code', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

**Step 4: Implement `EmployeeEducation` model**
```php
// app/Models/EmployeeEducation.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEducation extends Model
{
    protected $fillable = [
        'employee_id', 'type', 'abbreviation',
        'degree_level', 'field_of_study', 'institution',
        'graduation_year', 'sort_order',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopePrefixes($query)
    {
        return $query->where('type', 'prefix')->orderBy('sort_order');
    }

    public function scopeSuffixes($query)
    {
        return $query->where('type', 'suffix')->orderBy('sort_order');
    }
}
```

**Step 5: Implement `Employee` model**
```php
// app/Models/Employee.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

class Employee extends Model
{
    protected $fillable = [
        'user_id', 'team_id', 'name', 'display_name',
        'employee_number', 'position', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class)->orderBy('sort_order');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function ledProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'leader_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Compute and persist display_name from educations.
     * Called by the UpdateEmployeeDisplayName listener.
     */
    public function recomputeDisplayName(): void
    {
        $prefixes = $this->educations()->prefixes()->pluck('abbreviation')->implode(' ');
        $suffixes = $this->educations()->suffixes()->pluck('abbreviation')->implode(', ');

        $display = trim($prefixes . ' ' . $this->name);
        if ($suffixes) {
            $display .= ', ' . $suffixes;
        }

        $this->update(['display_name' => $display]);
    }

    /**
     * Fallback accessor — returns stored display_name or bare name.
     */
    public function getDisplayNameAttribute(?string $value): string
    {
        return $value ?? $this->name;
    }
}
```

**Step 6: Implement `Project` model**
```php
// app/Models/Project.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

class Project extends Model
{
    protected $fillable = [
        'team_id', 'leader_id', 'name', 'description',
        'objective', 'kpi', 'status', 'year',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'leader_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class)->orderBy('number');
    }
}
```

**Step 7: Implement `WorkItem` model**
```php
// app/Models/WorkItem.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class WorkItem extends Model
{
    protected $fillable = ['project_id', 'number', 'description'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function performanceReports(): HasMany
    {
        return $this->hasMany(PerformanceReport::class)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month');
    }
}
```

**Step 8: Implement `PerformanceReport` model**
```php
// app/Models/PerformanceReport.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReport extends Model
{
    protected $table = 'performance_reports';

    protected $fillable = [
        'work_item_id', 'reported_by', 'period_month', 'period_year',
        'achievement_percentage', 'issues', 'solutions', 'action_plan',
    ];

    protected $casts = ['achievement_percentage' => 'decimal:2'];

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reported_by');
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }
}
```

**Step 9: Update `User` model**
```php
// In User.php, add to $fillable: 'role'
// Add relationship:
public function employee(): HasOne
{
    return $this->hasOne(Employee::class);
}
```

**Step 10: Create model factories**
```bash
php artisan make:factory TeamFactory --model=Team
php artisan make:factory EmployeeFactory --model=Employee
php artisan make:factory EmployeeEducationFactory --model=EmployeeEducation
php artisan make:factory ProjectFactory --model=Project
php artisan make:factory WorkItemFactory --model=WorkItem
php artisan make:factory PerformanceReportFactory --model=PerformanceReport
```

Implement each factory using `fake()` — see `database/factories/UserFactory.php` as reference pattern.

**Step 11: Run model test**
```bash
php artisan test tests/Unit/Models/EmployeeTest.php
# Expected: PASS
```

**Step 12: Commit**
```bash
git add app/Models/ database/factories/
git commit -m "feat(models): add Team, Employee, EmployeeEducation, Project, WorkItem, PerformanceReport"
```

---

### Task 1.3: Seeders

**Files:**
- Create: `database/seeders/TeamSeeder.php`
- Create: `database/seeders/EmployeeSeeder.php`
- Create: `database/seeders/ProjectSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

> Data source: `seeder_data.json` (in the project root, NOT committed to git). Read it at runtime using `json_decode(file_get_contents(base_path('../seeder_data.json')), true)`.

**Step 1: Implement `TeamSeeder`**
```php
// database/seeders/TeamSeeder.php
<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(base_path('../seeder_data.json')), true);

        foreach ($data['teams'] as $team) {
            Team::create([
                'id'   => $team['id'],
                'name' => $team['name'],
                'code' => $team['code'],
            ]);
        }
    }
}
```

**Step 2: Implement `EmployeeSeeder`**

Key: migrate `degree_front`/`degree_back` strings into `employee_educations` records and compute `display_name`.

```php
// database/seeders/EmployeeSeeder.php
<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeEducation;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(base_path('../seeder_data.json')), true);

        foreach ($data['employees'] as $emp) {
            $employee = Employee::create([
                'id'              => $emp['id'],
                'team_id'         => $emp['team_id'],
                'name'            => $emp['name'],
                'employee_number' => $emp['nip'] ?? null,
            ]);

            // Migrate degree_front → prefix education record
            if (!empty($emp['degree_front'])) {
                EmployeeEducation::create([
                    'employee_id'  => $employee->id,
                    'type'         => 'prefix',
                    'abbreviation' => trim($emp['degree_front']),
                    'sort_order'   => 0,
                ]);
            }

            // Migrate degree_back → one suffix record per comma-separated part
            if (!empty($emp['degree_back'])) {
                foreach (array_values(array_filter(array_map('trim', explode(',', $emp['degree_back'])))) as $index => $abbr) {
                    EmployeeEducation::create([
                        'employee_id'  => $employee->id,
                        'type'         => 'suffix',
                        'abbreviation' => $abbr,
                        'sort_order'   => $index,
                    ]);
                }
            }

            // Compute and persist display_name
            $employee->recomputeDisplayName();
        }
    }
}
```

**Step 3: Implement `ProjectSeeder`**
```php
// database/seeders/ProjectSeeder.php
<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(base_path('../seeder_data.json')), true);

        foreach ($data['projects'] as $p) {
            Project::create([
                'id'          => $p['id'],
                'team_id'     => $p['team_id'],
                'leader_id'   => $p['ketua_id'],
                'name'        => $p['name'],
                'description' => $p['description'] ?? null,
                'objective'   => $p['sasaran'] ?? null,
                'kpi'         => $p['iku'] ?? null,
                'year'        => $p['year'],
            ]);
        }

        foreach ($data['project_members'] as $m) {
            DB::table('project_members')->insert([
                'project_id'  => $m['project_id'],
                'employee_id' => $m['employee_id'],
                'role'        => $m['role'] === 'ketua' ? 'leader' : 'member',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
}
```

**Step 4: Wire `DatabaseSeeder`**
```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call([
        TeamSeeder::class,
        EmployeeSeeder::class,
        ProjectSeeder::class,
        RolePermissionSeeder::class, // created in Phase 2
        UserSeeder::class,           // created in Phase 2
    ]);
}
```

**Step 5: Run seeders (skip Role/User seeders — not yet created)**
```bash
php artisan db:seed --class=TeamSeeder
php artisan db:seed --class=EmployeeSeeder
php artisan db:seed --class=ProjectSeeder
# Expected: no errors; verify with:
php artisan tinker --execute="echo \App\Models\Team::count() . ' teams, ' . \App\Models\Employee::count() . ' employees, ' . \App\Models\Project::count() . ' projects';"
# Expected: 19 teams, 70 employees, 139 projects
```

**Step 6: Commit**
```bash
git add database/seeders/
git commit -m "feat(seed): add team, employee, project seeders with degree migration"
```

> Update Beads: `bd update 1 --status completed`

---

## Phase 2: Auth & Permissions

> `bd update 2 --status in_progress`

### Task 2.1: Spatie Roles & Permissions Configuration

**Files:**
- Create: `database/seeders/RolePermissionSeeder.php`
- Modify: `config/permission.php`

**Step 1: Write failing test**
```php
// tests/Feature/Auth/RolePermissionTest.php
it('admin has all management permissions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    expect($admin->can('manage-teams'))->toBeTrue()
        ->and($admin->can('view-matrix'))->toBeTrue()
        ->and($admin->can('enter-performance'))->toBeFalse();
});

it('staff can only enter performance', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $staff->assignRole('staff');

    expect($staff->can('enter-performance'))->toBeTrue()
        ->and($staff->can('manage-teams'))->toBeFalse()
        ->and($staff->can('view-matrix'))->toBeFalse();
});
```

**Step 2: Run to see it fail**
```bash
php artisan test tests/Feature/Auth/RolePermissionTest.php
# Expected: FAIL
```

**Step 3: Implement `RolePermissionSeeder`**
```php
// database/seeders/RolePermissionSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage-teams',
            'manage-employees',
            'manage-projects',
            'manage-work-items',
            'view-matrix',
            'view-reports',
            'enter-performance',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        $head = Role::firstOrCreate(['name' => 'head']);
        $head->syncPermissions(['view-matrix', 'view-reports']);

        $staff = Role::firstOrCreate(['name' => 'staff']);
        $staff->syncPermissions(['enter-performance']);
    }
}
```

**Step 4: Run seeder**
```bash
php artisan db:seed --class=RolePermissionSeeder
```

**Step 5: Run tests**
```bash
php artisan test tests/Feature/Auth/RolePermissionTest.php
# Expected: PASS
```

**Step 6: Commit**
```bash
git add database/seeders/RolePermissionSeeder.php tests/Feature/Auth/
git commit -m "feat(auth): add spatie roles and permissions seeder"
```

---

### Task 2.2: User Seeder & Employee Link

**Files:**
- Create: `database/seeders/UserSeeder.php`

**Step 1: Implement `UserSeeder`**
```php
// database/seeders/UserSeeder.php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Admin BPS', 'email' => 'admin@bps-sulteng.go.id', 'role' => 'admin'],
            ['name' => 'Kepala BPS', 'email' => 'kepala@bps-sulteng.go.id', 'role' => 'head'],
            ['name' => 'Staff Demo', 'email' => 'staff@bps-sulteng.go.id', 'role' => 'staff'],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => Hash::make('password'), 'role' => $u['role']]
            );
            $user->assignRole($u['role']);
        }
    }
}
```

**Step 2: Implement `AssignStaffRole` listener (used in Phase 3 — scaffold now)**
```bash
php artisan make:listener AssignStaffRole
```
```php
// app/Listeners/AssignStaffRole.php
public function handle(EmployeeLinkedToUser $event): void
{
    $event->user->assignRole('staff');
}
```

**Step 3: Run full seeder stack**
```bash
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=UserSeeder
```

**Step 4: Commit**
```bash
git add database/seeders/UserSeeder.php app/Listeners/AssignStaffRole.php
git commit -m "feat(auth): add user seeder with demo accounts and employee link listener"
```

> `bd update 2 --status completed`

---

## Phase 3: Events & Listeners

> `bd update 3 --status in_progress`

### Task 3.1: Create Events

```bash
php artisan make:event PerformanceReportSaved
php artisan make:event PerformanceBatchSubmitted
php artisan make:event ProjectMembersUpdated
php artisan make:event EmployeeLinkedToUser
```

**Implement each event with its payload:**

```php
// app/Events/PerformanceReportSaved.php
public function __construct(public readonly PerformanceReport $report) {}

// app/Events/PerformanceBatchSubmitted.php
public function __construct(public readonly array $reportIds, public readonly int $teamId) {}

// app/Events/ProjectMembersUpdated.php
public function __construct(public readonly Project $project) {}

// app/Events/EmployeeLinkedToUser.php
public function __construct(public readonly Employee $employee, public readonly User $user) {}
```

---

### Task 3.2: Create Listeners

```bash
php artisan make:listener LogPerformanceActivity
php artisan make:listener RecalculateTeamProgress
php artisan make:listener SyncProjectLeaderRole
```

**Implement `RecalculateTeamProgress`:**
```php
// app/Listeners/RecalculateTeamProgress.php
public function handle(PerformanceBatchSubmitted|PerformanceReportSaved $event): void
{
    $teamId = $event instanceof PerformanceBatchSubmitted
        ? $event->teamId
        : $event->report->workItem->project->team_id;

    Cache::forget("team_progress_{$teamId}");
    // The cache will be re-populated on next Dashboard request
}
```

**Implement `SyncProjectLeaderRole`:**
```php
// app/Listeners/SyncProjectLeaderRole.php
public function handle(ProjectMembersUpdated $event): void
{
    $project = $event->project;

    // Ensure leader also exists in project_members as 'leader' role
    if ($project->leader_id) {
        $project->members()->syncWithoutDetaching([
            $project->leader_id => ['role' => 'leader'],
        ]);
    }
}
```

**Implement `LogPerformanceActivity`:**
```php
// app/Listeners/LogPerformanceActivity.php
// Uses Laravel's built-in activity_log or a simple DB log table — log: who, what, when
public function handle(PerformanceBatchSubmitted|PerformanceReportSaved $event): void
{
    \Log::channel('performance')->info('Performance submitted', [
        'user_id' => auth()->id(),
        'event'   => get_class($event),
    ]);
}
```

---

### Task 3.3: Register in EventServiceProvider

**Modify: `app/Providers/EventServiceProvider.php`**
```php
protected $listen = [
    PerformanceReportSaved::class => [
        LogPerformanceActivity::class,
        RecalculateTeamProgress::class,
    ],
    PerformanceBatchSubmitted::class => [
        LogPerformanceActivity::class,
        RecalculateTeamProgress::class,
    ],
    ProjectMembersUpdated::class => [
        SyncProjectLeaderRole::class,
    ],
    EmployeeLinkedToUser::class => [
        AssignStaffRole::class,
    ],
];
```

**Step: Write + run event test**
```php
// tests/Unit/Events/PerformanceBatchSubmittedTest.php
it('dispatches RecalculateTeamProgress on batch submit', function () {
    Event::fake();
    $team = Team::factory()->create();

    event(new PerformanceBatchSubmitted(reportIds: [1, 2], teamId: $team->id));

    Event::assertDispatched(PerformanceBatchSubmitted::class);
});
```

**Commit:**
```bash
git add app/Events/ app/Listeners/ app/Providers/EventServiceProvider.php tests/Unit/Events/
git commit -m "feat(events): add performance, project, employee events and listeners"
```

> `bd update 3 --status completed`

---

## Phase 4: Backend CRUD

> `bd update 4 --status in_progress`

### Task 4.1: Policies

```bash
php artisan make:policy TeamPolicy --model=Team
php artisan make:policy EmployeePolicy --model=Employee
php artisan make:policy ProjectPolicy --model=Project
php artisan make:policy WorkItemPolicy --model=WorkItem
php artisan make:policy PerformancePolicy --model=PerformanceReport
```

**Pattern for all admin-only policies (Team, WorkItem):**
```php
public function viewAny(User $user): bool { return $user->can('manage-teams'); }
public function create(User $user): bool { return $user->can('manage-teams'); }
public function update(User $user, Team $team): bool { return $user->can('manage-teams'); }
public function delete(User $user, Team $team): bool { return $user->can('manage-teams'); }
```

**`PerformancePolicy` — staff scoped to own projects:**
```php
public function create(User $user): bool
{
    return $user->can('enter-performance');
}

public function storeForWorkItem(User $user, WorkItem $workItem): bool
{
    if (!$user->can('enter-performance')) return false;

    $employee = $user->employee;
    if (!$employee) return false;

    return $workItem->project->members()->where('employee_id', $employee->id)->exists();
}
```

---

### Task 4.2: Actions

```bash
php artisan make:class Actions/Teams/UpsertTeamAction
php artisan make:class Actions/Employees/UpsertEmployeeAction
php artisan make:class Actions/Employees/LinkEmployeeToUserAction
php artisan make:class Actions/Projects/UpsertProjectAction
php artisan make:class Actions/Projects/SyncProjectMembersAction
php artisan make:class Actions/Performance/SavePerformanceBatchAction
```

**`SavePerformanceBatchAction` — the core action:**
```php
// app/Actions/Performance/SavePerformanceBatchAction.php
<?php

namespace App\Actions\Performance;

use App\Events\PerformanceBatchSubmitted;
use App\Models\PerformanceReport;

class SavePerformanceBatchAction
{
    public function execute(array $items, ?int $reportedBy): array
    {
        $savedIds = [];

        foreach ($items as $item) {
            $report = PerformanceReport::updateOrCreate(
                [
                    'work_item_id' => $item['work_item_id'],
                    'period_year'  => $item['period_year'],
                    'period_month' => $item['period_month'],
                ],
                [
                    'achievement_percentage' => $item['achievement_percentage'] ?? 0,
                    'issues'                 => $item['issues'] ?? null,
                    'solutions'              => $item['solutions'] ?? null,
                    'action_plan'            => $item['action_plan'] ?? null,
                    'reported_by'            => $reportedBy,
                ]
            );

            $savedIds[] = $report->id;
        }

        return $savedIds;
    }
}
```

**`LinkEmployeeToUserAction`:**
```php
// app/Actions/Employees/LinkEmployeeToUserAction.php
public function execute(Employee $employee, User $user): void
{
    $employee->update(['user_id' => $user->id]);
    event(new EmployeeLinkedToUser($employee, $user));
}
```

**`SyncProjectMembersAction`:**
```php
// app/Actions/Projects/SyncProjectMembersAction.php
public function execute(Project $project, array $memberIds, int $leaderId): void
{
    $sync = collect($memberIds)->mapWithKeys(fn ($id) => [
        $id => ['role' => $id === $leaderId ? 'leader' : 'member'],
    ])->toArray();

    $project->members()->sync($sync);
    $project->update(['leader_id' => $leaderId]);

    event(new ProjectMembersUpdated($project));
}
```

---

### Task 4.3: Controllers

```bash
php artisan make:controller TeamController --resource
php artisan make:controller EmployeeController --resource
php artisan make:controller ProjectController --resource
php artisan make:controller WorkItemController
php artisan make:controller PerformanceController
```

**`PerformanceController` (key controller):**
```php
// app/Http/Controllers/PerformanceController.php
<?php

namespace App\Http\Controllers;

use App\Actions\Performance\SavePerformanceBatchAction;
use App\Events\PerformanceBatchSubmitted;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PerformanceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('enter-performance');

        $year   = $request->integer('year', now()->year);
        $month  = $request->integer('month', now()->month);
        $teamId = $request->input('team_id');

        $employee = auth()->user()->employee;

        $query = Team::active()
            ->with(['projects' => function ($q) use ($year, $month, $employee) {
                $q->where('year', $year)
                    ->whereHas('members', fn ($m) => $m->where('employee_id', $employee->id))
                    ->with(['leader', 'workItems.performanceReports' => function ($pr) use ($year, $month) {
                        $pr->forPeriod($year, $month);
                    }]);
            }]);

        if ($teamId) {
            $query->where('id', $teamId);
        }

        return Inertia::render('Performance/Index', [
            'teams'    => $query->orderBy('name')->get(),
            'filters'  => ['year' => $year, 'month' => $month, 'team_id' => $teamId],
            'allTeams' => Team::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeBatch(Request $request, SavePerformanceBatchAction $action)
    {
        $validated = $request->validate([
            'items'                         => 'required|array',
            'items.*.work_item_id'          => 'required|exists:work_items,id',
            'items.*.period_year'           => 'required|integer|min:2020|max:2099',
            'items.*.period_month'          => 'required|integer|min:1|max:12',
            'items.*.achievement_percentage'=> 'nullable|numeric|min:0|max:100',
            'items.*.issues'                => 'nullable|string|max:2000',
            'items.*.solutions'             => 'nullable|string|max:2000',
            'items.*.action_plan'           => 'nullable|string|max:2000',
        ]);

        $employee = auth()->user()->employee;

        // Authorize each work item belongs to employee's projects
        foreach ($validated['items'] as $item) {
            $workItem = \App\Models\WorkItem::findOrFail($item['work_item_id']);
            $this->authorize('storeForWorkItem', [PerformanceReport::class, $workItem]);
        }

        $savedIds = $action->execute($validated['items'], $employee?->id);

        $teamId = \App\Models\WorkItem::find($validated['items'][0]['work_item_id'])
            ?->project?->team_id;

        event(new PerformanceBatchSubmitted($savedIds, $teamId));

        return back()->with('success', 'Capaian kinerja berhasil disimpan.');
    }
}
```

**Write + run feature test:**
```php
// tests/Feature/Controllers/PerformanceControllerTest.php
it('staff can only submit for their own project work items', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $staff->assignRole('staff');
    $employee = Employee::factory()->create(['user_id' => $staff->id]);
    $project = Project::factory()->create();
    $project->members()->attach($employee->id, ['role' => 'member']);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($staff)->post('/performance/batch', [
        'items' => [[
            'work_item_id'           => $workItem->id,
            'period_year'            => 2026,
            'period_month'           => 4,
            'achievement_percentage' => 75,
        ]],
    ]);

    $response->assertRedirect();
    expect(PerformanceReport::count())->toBe(1);
});

it('staff cannot submit for unassigned project work items', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $staff->assignRole('staff');
    Employee::factory()->create(['user_id' => $staff->id]);
    $workItem = WorkItem::factory()->create(); // no member relationship

    $this->actingAs($staff)->post('/performance/batch', [
        'items' => [['work_item_id' => $workItem->id, 'period_year' => 2026, 'period_month' => 4]],
    ])->assertForbidden();
});
```

```bash
php artisan test tests/Feature/Controllers/PerformanceControllerTest.php
# Expected: PASS
```

**Commit:**
```bash
git add app/Http/Controllers/ app/Actions/ app/Policies/ tests/Feature/Controllers/
git commit -m "feat(backend): add controllers, actions, and policies for all resources"
```

> `bd update 4 --status completed`

---

## Phase 5: Dashboard Backend

> `bd update 5 --status in_progress`

### Task 5.1: DashboardController

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`

```php
// app/Http/Controllers/DashboardController.php
<?php

namespace App\Http\Controllers;

use App\Models\{Team, Employee, Project, PerformanceReport};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user  = auth()->user();
        $year  = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        $data = match (true) {
            $user->hasRole('admin') => $this->adminData($year, $month),
            $user->hasRole('head')  => $this->headData($year, $month),
            default                 => $this->staffData($user, $year, $month),
        };

        return Inertia::render('Dashboard', array_merge($data, [
            'filters' => ['year' => $year, 'month' => $month],
        ]));
    }

    public function matrix(Request $request)
    {
        $this->authorize('view-matrix');

        $year   = $request->integer('year', now()->year);
        $month  = $request->integer('month', now()->month);
        $teamId = $request->input('team_id');
        $mode   = $request->input('mode', 'assignment'); // assignment | progress

        $employees = Employee::active()->with('team')->orderBy('name')->get(['id', 'name', 'display_name', 'team_id']);
        $projectQuery = Project::where('year', $year)->with('members');

        if ($teamId) {
            $projectQuery->where('team_id', $teamId);
        }

        $projects = $projectQuery->orderBy('name')->get(['id', 'name', 'team_id']);

        // Build cell data
        if ($mode === 'assignment') {
            $cells = $this->buildAssignmentCells($employees, $projects);
        } else {
            $cells = $this->buildProgressCells($employees, $projects, $year, $month);
        }

        return Inertia::render('Matrix/Index', [
            'employees' => $employees,
            'projects'  => $projects,
            'cells'     => $cells,
            'filters'   => ['year' => $year, 'month' => $month, 'team_id' => $teamId, 'mode' => $mode],
            'allTeams'  => Team::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function adminData(int $year, int $month): array
    {
        $cacheKey = "admin_dashboard_{$year}_{$month}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($year, $month) {
            $reports = PerformanceReport::forPeriod($year, $month)
                ->with('workItem.project.team')
                ->get();

            $overall = $reports->avg('achievement_percentage') ?? 0;

            $byTeam = $reports->groupBy(fn ($r) => $r->workItem->project->team->name)
                ->map(fn ($g) => round($g->avg('achievement_percentage'), 2));

            // 12-month trend
            $trend = collect(range(1, 12))->map(function ($m) use ($year) {
                $avg = PerformanceReport::forPeriod($year, $m)->avg('achievement_percentage') ?? 0;
                return ['month' => $m, 'average' => round($avg, 2)];
            });

            return [
                'overall'      => round($overall, 2),
                'byTeam'       => $byTeam,
                'trend'        => $trend,
                'totalProjects'=> Project::where('year', $year)->count(),
                'totalEmployees'=> Employee::active()->count(),
            ];
        });
    }

    private function headData(int $year, int $month): array
    {
        return $this->adminData($year, $month); // same view, same data
    }

    private function staffData($user, int $year, int $month): array
    {
        $employee = $user->employee;

        if (!$employee) {
            return ['projects' => [], 'overall' => 0];
        }

        $projects = $employee->projects()
            ->where('year', $year)
            ->with(['workItems.performanceReports' => fn ($q) => $q->forPeriod($year, $month)])
            ->get();

        $overall = $projects->flatMap->workItems
            ->flatMap->performanceReports
            ->avg('achievement_percentage') ?? 0;

        return ['projects' => $projects, 'overall' => round($overall, 2)];
    }

    private function buildAssignmentCells($employees, $projects): array
    {
        $cells = [];
        $memberIndex = [];

        foreach ($projects as $project) {
            foreach ($project->members as $member) {
                $memberIndex[$project->id][$member->id] = true;
            }
        }

        foreach ($employees as $emp) {
            foreach ($projects as $project) {
                $cells["{$emp->id}_{$project->id}"] = isset($memberIndex[$project->id][$emp->id]);
            }
        }

        return $cells;
    }

    private function buildProgressCells($employees, $projects, int $year, int $month): array
    {
        $projectsWithReports = $projects->load([
            'workItems.performanceReports' => fn ($q) => $q->forPeriod($year, $month),
        ]);

        $cells = [];

        foreach ($employees as $emp) {
            foreach ($projectsWithReports as $project) {
                $isMember = $project->members->contains($emp->id);

                if (!$isMember) {
                    $cells["{$emp->id}_{$project->id}"] = null;
                    continue;
                }

                $avg = $project->workItems
                    ->flatMap->performanceReports
                    ->avg('achievement_percentage');

                $cells["{$emp->id}_{$project->id}"] = $avg !== null ? round($avg, 1) : null;
            }
        }

        return $cells;
    }
}
```

**Update routes:**
```php
// routes/web.php — add all routes per design doc
```

**Write test:**
```php
// tests/Feature/Controllers/DashboardControllerTest.php
it('admin sees overall performance data', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/dashboard')->assertInertia(
        fn ($page) => $page->component('Dashboard')
            ->has('overall')
            ->has('byTeam')
            ->has('trend')
    );
});
```

**Commit:**
```bash
git add app/Http/Controllers/DashboardController.php routes/web.php tests/Feature/Controllers/DashboardControllerTest.php
git commit -m "feat(dashboard): add role-aware dashboard and matrix controller"
```

> `bd update 5 --status completed`

---

## Phase 6: Frontend Foundation

> `bd update 6 --status in_progress`

### Task 6.1: Pinia Stores

**Files:**
- Create: `resources/js/stores/sidebar.ts`
- Create: `resources/js/stores/toast.ts`

**Step 1: Write Vitest test**
```ts
// resources/js/test/stores/sidebar.test.ts
import { setActivePinia, createPinia } from 'pinia'
import { useSidebarStore } from '@/stores/sidebar'

describe('sidebar store', () => {
    beforeEach(() => setActivePinia(createPinia()))

    it('toggles open state', () => {
        const store = useSidebarStore()
        expect(store.isOpen).toBe(true)
        store.toggle()
        expect(store.isOpen).toBe(false)
    })
})
```

**Step 2: Run to fail**
```bash
pnpm vitest run resources/js/test/stores/sidebar.test.ts
# Expected: FAIL
```

**Step 3: Implement stores**
```ts
// resources/js/stores/sidebar.ts
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useSidebarStore = defineStore('sidebar', () => {
    const isOpen = ref(true)
    const toggle = () => { isOpen.value = !isOpen.value }
    const close = () => { isOpen.value = false }
    return { isOpen, toggle, close }
})
```

```ts
// resources/js/stores/toast.ts
import { defineStore } from 'pinia'
import { ref } from 'vue'

export type Toast = { id: string; message: string; type: 'success' | 'error' | 'info' }

export const useToastStore = defineStore('toast', () => {
    const toasts = ref<Toast[]>([])

    function push(message: string, type: Toast['type'] = 'success') {
        const id = crypto.randomUUID()
        toasts.value.push({ id, message, type })
        setTimeout(() => dismiss(id), 4000)
    }

    function dismiss(id: string) {
        toasts.value = toasts.value.filter(t => t.id !== id)
    }

    return { ...toRefs({ toasts: toasts }), push, dismiss }
})
```

**Step 4: Run test**
```bash
pnpm vitest run resources/js/test/stores/
# Expected: PASS
```

---

### Task 6.2: Base Composables

**Files:**
- Create: `resources/js/composables/useFilters.ts`
- Create: `resources/js/composables/useAsync.ts`

**Step 1: Write test**
```ts
// resources/js/test/composables/useFilters.test.ts
import { useFilters } from '@/composables/useFilters'
import { isRef } from 'vue'

it('returns individual refs so destructuring preserves reactivity', () => {
    const { year, month, teamId } = useFilters({ year: 2026, month: 4, teamId: null })

    expect(isRef(year)).toBe(true)
    expect(isRef(month)).toBe(true)
    expect(isRef(teamId)).toBe(true)
    expect(year.value).toBe(2026)
})

it('reset restores initial values', () => {
    const { year, reset } = useFilters({ year: 2026, month: 4, teamId: null })
    year.value = 2025
    reset()
    expect(year.value).toBe(2026)
})
```

**Step 2: Implement `useFilters`**
```ts
// resources/js/composables/useFilters.ts
import { reactive, toRefs } from 'vue'

export interface FilterState {
    year: number
    month: number
    teamId: number | null
}

export function useFilters(initial: FilterState) {
    const state = reactive<FilterState>({ ...initial })

    function reset() {
        Object.assign(state, initial)
    }

    function toQuery(): Record<string, string> {
        return {
            year: String(state.year),
            month: String(state.month),
            ...(state.teamId ? { team_id: String(state.teamId) } : {}),
        }
    }

    return {
        ...toRefs(state),
        reset,
        toQuery,
    }
}
```

**Implement `useAsync`:**
```ts
// resources/js/composables/useAsync.ts
import { ref, readonly } from 'vue'

export function useAsync<T>() {
    const loading = ref(false)
    const error = ref<string | null>(null)

    async function execute(fn: () => Promise<T>): Promise<T | null> {
        loading.value = true
        error.value = null
        try {
            return await fn()
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Terjadi kesalahan'
            return null
        } finally {
            loading.value = false
        }
    }

    return { loading: readonly(loading), error: readonly(error), execute }
}
```

**Step 3: Run tests**
```bash
pnpm vitest run resources/js/test/composables/
# Expected: PASS
```

**Step 4: Commit**
```bash
git add resources/js/stores/ resources/js/composables/ resources/js/test/
git commit -m "feat(frontend): add pinia stores and base composables with toRefs"
```

---

### Task 6.3: App Layout

**Files:**
- Create: `resources/js/layouts/AppLayout.vue`
- Modify: `resources/js/pages/Dashboard.vue` (stub, full impl in Phase 10)

```vue
<!-- resources/js/layouts/AppLayout.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { useSidebarStore } from '@/stores/sidebar'
import { storeToRefs } from 'pinia'

const page = usePage()
const user = computed(() => page.props.auth?.user)
const role = computed(() => user.value?.role)

const sidebar = useSidebarStore()
const { isOpen } = storeToRefs(sidebar)

const navItems = computed(() => {
    const items = []

    if (role.value === 'admin') {
        items.push(
            { label: 'Beranda', href: '/dashboard', icon: 'LayoutDashboard' },
            { label: 'Tim Kerja', href: '/teams', icon: 'Users' },
            { label: 'Pegawai', href: '/employees', icon: 'UserCircle' },
            { label: 'Proyek', href: '/projects', icon: 'Briefcase' },
        )
    }

    if (role.value === 'admin' || role.value === 'head') {
        items.push(
            { label: 'Beranda', href: '/dashboard', icon: 'LayoutDashboard' },
            { label: 'Matriks', href: '/matrix', icon: 'Grid' },
        )
    }

    if (role.value === 'staff') {
        items.push(
            { label: 'Beranda', href: '/dashboard', icon: 'LayoutDashboard' },
            { label: 'Capaian Kinerja', href: '/performance', icon: 'ClipboardCheck' },
        )
    }

    return items
})
</script>

<template>
  <div class="flex h-screen bg-gray-50">
    <!-- Sidebar -->
    <aside
      :class="['transition-all duration-200', isOpen ? 'w-64' : 'w-16']"
      class="bg-white border-r border-gray-200 flex flex-col"
    >
      <!-- Logo -->
      <div class="flex items-center gap-3 p-4 border-b border-gray-100">
        <img src="/images/bps-sulteng-logo.png" alt="BPS Sulteng" class="h-8 w-8 object-contain" />
        <span v-if="isOpen" class="font-semibold text-sm text-gray-800 leading-tight">
          BPS Prov. Sulawesi Tengah
        </span>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 p-3 space-y-1">
        <Link
          v-for="item in navItems"
          :key="item.href"
          :href="item.href"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-700 transition-colors"
        >
          <span class="w-5 h-5 shrink-0"><!-- icon slot --></span>
          <span v-if="isOpen">{{ item.label }}</span>
        </Link>
      </nav>

      <!-- User -->
      <div class="p-3 border-t border-gray-100">
        <p v-if="isOpen" class="text-xs text-gray-500 truncate">{{ user?.name }}</p>
      </div>
    </aside>

    <!-- Main -->
    <main class="flex-1 flex flex-col overflow-hidden">
      <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-3">
        <button @click="sidebar.toggle()" class="p-1 rounded hover:bg-gray-100">
          <span class="sr-only">Toggle sidebar</span>
          ☰
        </button>
        <slot name="header" />
      </header>

      <div class="flex-1 overflow-auto p-6">
        <slot />
      </div>
    </main>
  </div>
</template>
```

**Commit:**
```bash
git add resources/js/layouts/
git commit -m "feat(layout): add role-aware AppLayout with BPS Sulteng branding"
```

> `bd update 6 --status completed`

---

## Phase 7: Admin CRUD Pages

> `bd update 7 --status in_progress`

### Task 7.1: Teams Pages

**Files:**
- Create: `resources/js/pages/Teams/Index.vue`
- Create: `resources/js/pages/Teams/Create.vue`
- Create: `resources/js/pages/Teams/Edit.vue`

**Pattern (repeat for Employees, Projects):**

```vue
<!-- resources/js/pages/Teams/Index.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { useToastStore } from '@/stores/toast'
// shadcn-vue components
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow
} from '@/components/ui/table'

interface Team {
    id: number
    name: string
    code: string | null
    is_active: boolean
    employees_count: number
    projects_count: number
}

const props = defineProps<{ teams: Team[] }>()
const toast = useToastStore()

function destroy(id: number) {
    if (!confirm('Hapus tim ini?')) return
    router.delete(`/teams/${id}`, {
        onSuccess: () => toast.push('Tim berhasil dihapus'),
        onError: () => toast.push('Gagal menghapus tim', 'error'),
    })
}
</script>

<template>
  <AppLayout>
    <template #header>
      <h1 class="font-semibold text-gray-800">Tim Kerja</h1>
    </template>

    <div class="space-y-4">
      <div class="flex justify-between items-center">
        <p class="text-sm text-gray-500">{{ teams.length }} tim terdaftar</p>
        <Button as-child>
          <Link href="/teams/create">Tambah Tim</Link>
        </Button>
      </div>

      <div class="bg-white rounded-xl border border-gray-200">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Nama Tim</TableHead>
              <TableHead>Kode</TableHead>
              <TableHead>Status</TableHead>
              <TableHead class="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="team in teams" :key="team.id">
              <TableCell class="font-medium">{{ team.name }}</TableCell>
              <TableCell>
                <Badge variant="outline">{{ team.code }}</Badge>
              </TableCell>
              <TableCell>
                <Badge :variant="team.is_active ? 'default' : 'secondary'">
                  {{ team.is_active ? 'Aktif' : 'Nonaktif' }}
                </Badge>
              </TableCell>
              <TableCell class="text-right space-x-2">
                <Button variant="outline" size="sm" as-child>
                  <Link :href="`/teams/${team.id}/edit`">Edit</Link>
                </Button>
                <Button variant="destructive" size="sm" @click="destroy(team.id)">Hapus</Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </AppLayout>
</template>
```

Apply the same pattern for `Employees/Index.vue` and `Projects/Index.vue`, adapting columns and fields.

**Commit after all 3 CRUD sets:**
```bash
git add resources/js/pages/Teams/ resources/js/pages/Employees/ resources/js/pages/Projects/
git commit -m "feat(pages): add admin CRUD pages for teams, employees, projects"
```

> `bd update 7 --status completed`

---

## Phase 8: Performance Entry Page

> `bd update 8 --status in_progress`

### Task 8.1: `usePerformanceForm` Composable

**Files:**
- Create: `resources/js/composables/usePerformanceForm.ts`

**Step 1: Write test**
```ts
// resources/js/test/composables/usePerformanceForm.test.ts
import { usePerformanceForm } from '@/composables/usePerformanceForm'
import { isRef } from 'vue'

it('initializes empty items map', () => {
    const { items } = usePerformanceForm()
    expect(isRef(items)).toBe(true)
    expect(items.value).toEqual({})
})

it('upserts item by work_item_id', () => {
    const { items, upsert } = usePerformanceForm()
    upsert({ work_item_id: 1, period_year: 2026, period_month: 4, achievement_percentage: 80 })
    expect(items.value[1].achievement_percentage).toBe(80)
})

it('serializes items to array for submission', () => {
    const { upsert, toPayload } = usePerformanceForm()
    upsert({ work_item_id: 1, period_year: 2026, period_month: 4, achievement_percentage: 75 })
    expect(toPayload()).toHaveLength(1)
})
```

**Step 2: Run to fail**
```bash
pnpm vitest run resources/js/test/composables/usePerformanceForm.test.ts
```

**Step 3: Implement**
```ts
// resources/js/composables/usePerformanceForm.ts
import { ref, readonly } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToastStore } from '@/stores/toast'

export interface PerformanceItem {
    work_item_id: number
    period_year: number
    period_month: number
    achievement_percentage: number | null
    issues?: string | null
    solutions?: string | null
    action_plan?: string | null
}

export function usePerformanceForm() {
    const items = ref<Record<number, PerformanceItem>>({})
    const submitting = ref(false)
    const toast = useToastStore()

    function upsert(item: PerformanceItem) {
        items.value[item.work_item_id] = { ...items.value[item.work_item_id], ...item }
    }

    function toPayload(): PerformanceItem[] {
        return Object.values(items.value)
    }

    function submit() {
        if (toPayload().length === 0) return

        submitting.value = true

        router.post('/performance/batch', { items: toPayload() }, {
            onSuccess: () => toast.push('Capaian kinerja berhasil disimpan'),
            onError: () => toast.push('Gagal menyimpan capaian', 'error'),
            onFinish: () => { submitting.value = false },
        })
    }

    return {
        items: readonly(items),
        submitting: readonly(submitting),
        upsert,
        toPayload,
        submit,
    }
}
```

**Step 4: Run test**
```bash
pnpm vitest run resources/js/test/composables/usePerformanceForm.test.ts
# Expected: PASS
```

---

### Task 8.2: Accordion Components

**Files:**
- Create: `resources/js/components/performance/WorkItemRow.vue`
- Create: `resources/js/components/performance/ProjectAccordion.vue`
- Create: `resources/js/components/performance/TeamAccordion.vue`

```vue
<!-- resources/js/components/performance/WorkItemRow.vue -->
<!-- Stateless: receives workItem + current value; emits update -->
<script setup lang="ts">
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Progress } from '@/components/ui/progress'
import type { PerformanceItem } from '@/composables/usePerformanceForm'

defineProps<{
    workItemId: number
    number: number
    description: string
    value: Partial<PerformanceItem>
}>()

const emit = defineEmits<{
    update: [item: Partial<PerformanceItem>]
}>()
</script>

<template>
  <div class="border rounded-lg p-4 space-y-3 bg-white">
    <div class="flex items-start gap-3">
      <span class="text-xs font-mono text-gray-400 mt-1">{{ number }}</span>
      <p class="text-sm text-gray-700 flex-1">{{ description }}</p>
    </div>

    <div class="grid grid-cols-12 gap-3">
      <div class="col-span-3">
        <label class="text-xs text-gray-500 mb-1 block">Capaian (%)</label>
        <Input
          type="number"
          min="0"
          max="100"
          :value="value.achievement_percentage ?? ''"
          @input="emit('update', { work_item_id: workItemId, achievement_percentage: Number(($event.target as HTMLInputElement).value) })"
          class="text-center font-semibold"
        />
      </div>
      <div class="col-span-9 flex items-end pb-1">
        <Progress :value="value.achievement_percentage ?? 0" class="h-2 flex-1" />
      </div>
    </div>

    <div class="grid grid-cols-3 gap-3">
      <div>
        <label class="text-xs text-gray-500 mb-1 block">Permasalahan</label>
        <Textarea
          :value="value.issues ?? ''"
          @input="emit('update', { work_item_id: workItemId, issues: ($event.target as HTMLTextAreaElement).value })"
          rows="2"
          class="text-sm"
        />
      </div>
      <div>
        <label class="text-xs text-gray-500 mb-1 block">Solusi</label>
        <Textarea
          :value="value.solutions ?? ''"
          @input="emit('update', { work_item_id: workItemId, solutions: ($event.target as HTMLTextAreaElement).value })"
          rows="2"
          class="text-sm"
        />
      </div>
      <div>
        <label class="text-xs text-gray-500 mb-1 block">Rencana Tindak Lanjut</label>
        <Textarea
          :value="value.action_plan ?? ''"
          @input="emit('update', { work_item_id: workItemId, action_plan: ($event.target as HTMLTextAreaElement).value })"
          rows="2"
          class="text-sm"
        />
      </div>
    </div>
  </div>
</template>
```

---

### Task 8.3: Performance/Index.vue Page

```vue
<!-- resources/js/pages/Performance/Index.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import TeamAccordion from '@/components/performance/TeamAccordion.vue'
import { Button } from '@/components/ui/button'
import { useFilters } from '@/composables/useFilters'
import { usePerformanceForm } from '@/composables/usePerformanceForm'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
    teams: any[]
    filters: { year: number; month: number; team_id: number | null }
    allTeams: { id: number; name: string }[]
}>()

const { year, month, teamId, toQuery } = useFilters({
    year: props.filters.year,
    month: props.filters.month,
    teamId: props.filters.team_id,
})

const { items, submitting, upsert, submit } = usePerformanceForm()

function applyFilter() {
    router.get('/performance', toQuery(), { preserveState: true })
}

const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']
</script>

<template>
  <AppLayout>
    <template #header>
      <h1 class="font-semibold text-gray-800">Capaian Kinerja</h1>
    </template>

    <div class="space-y-6">
      <!-- Filters -->
      <div class="flex gap-3 items-end">
        <!-- year/month/team selects using shadcn Select -->
        <Button @click="applyFilter" variant="outline">Terapkan Filter</Button>
        <Button
          @click="submit"
          :disabled="submitting"
          class="ml-auto"
        >
          {{ submitting ? 'Menyimpan...' : 'Simpan Semua Capaian' }}
        </Button>
      </div>

      <!-- Accordion: Team → Project → WorkItem -->
      <TeamAccordion
        v-for="team in teams"
        :key="team.id"
        :team="team"
        :form-items="items"
        :period="{ year, month }"
        @update="upsert"
      />
    </div>
  </AppLayout>
</template>
```

**Commit:**
```bash
git add resources/js/composables/usePerformanceForm.ts resources/js/components/performance/ resources/js/pages/Performance/ resources/js/test/composables/usePerformanceForm.test.ts
git commit -m "feat(performance): add performance entry page with accordion and form composable"
```

> `bd update 8 --status completed`

---

## Phase 9: Matrix View

> `bd update 9 --status in_progress`

### Task 9.1: `useMatrix` Composable

**Files:**
- Create: `resources/js/composables/useMatrix.ts`

```ts
// resources/js/composables/useMatrix.ts
import { ref, computed, readonly, toRefs, reactive, watchEffect, toValue } from 'vue'
import type { MaybeRefOrGetter } from 'vue'
import { router } from '@inertiajs/vue3'

export interface MatrixFilters {
    year: number
    month: number
    teamId: number | null
    mode: 'assignment' | 'progress'
}

export function useMatrix(initialFilters: MaybeRefOrGetter<MatrixFilters>) {
    const state = reactive<MatrixFilters>({ ...toValue(initialFilters) })
    const loading = ref(false)

    // Re-fetch when filters change
    watchEffect(() => {
        const f = toValue(initialFilters)
        Object.assign(state, f)
    })

    function applyFilters() {
        loading.value = true
        router.get('/matrix', {
            year: state.year,
            month: state.month,
            ...(state.teamId ? { team_id: state.teamId } : {}),
            mode: state.mode,
        }, {
            preserveState: true,
            onFinish: () => { loading.value = false },
        })
    }

    function toggleMode() {
        state.mode = state.mode === 'assignment' ? 'progress' : 'assignment'
        applyFilters()
    }

    return {
        ...toRefs(state),
        loading: readonly(loading),
        applyFilters,
        toggleMode,
    }
}
```

---

### Task 9.2: MatrixGrid & MatrixCell Components

```vue
<!-- resources/js/components/matrix/MatrixCell.vue -->
<!-- Purely stateless — renders based on props -->
<script setup lang="ts">
defineProps<{
    mode: 'assignment' | 'progress'
    value: boolean | number | null
}>()

function progressColor(v: number): string {
    if (v >= 80) return 'bg-green-100 text-green-700'
    if (v >= 50) return 'bg-yellow-100 text-yellow-700'
    return 'bg-red-100 text-red-700'
}
</script>

<template>
  <td class="border border-gray-100 text-center text-xs" style="min-width: 48px; height: 32px;">
    <template v-if="mode === 'assignment'">
      <span v-if="value" class="text-green-500 font-bold">✓</span>
      <span v-else class="text-gray-200">·</span>
    </template>
    <template v-else>
      <span
        v-if="value !== null"
        :class="['inline-block px-1 rounded font-medium', progressColor(value as number)]"
      >
        {{ value }}%
      </span>
      <span v-else class="text-gray-200">·</span>
    </template>
  </td>
</template>
```

```vue
<!-- resources/js/components/matrix/MatrixGrid.vue -->
<!-- Virtual-scroll via CSS overflow; stateless: rows/cols/cells via props -->
<script setup lang="ts">
import MatrixCell from './MatrixCell.vue'

defineProps<{
    employees: { id: number; display_name: string }[]
    projects: { id: number; name: string }[]
    cells: Record<string, boolean | number | null>
    mode: 'assignment' | 'progress'
}>()
</script>

<template>
  <div class="overflow-auto max-h-[70vh] rounded-xl border border-gray-200">
    <table class="text-xs border-collapse w-max">
      <thead class="sticky top-0 z-10 bg-white">
        <tr>
          <th class="sticky left-0 z-20 bg-white border border-gray-200 px-3 py-2 text-left font-medium text-gray-600 min-w-48">
            Pegawai
          </th>
          <th
            v-for="project in projects"
            :key="project.id"
            class="border border-gray-100 px-2 py-2 font-medium text-gray-500 max-w-24 truncate"
            :title="project.name"
          >
            {{ project.name.substring(0, 20) }}…
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="employee in employees" :key="employee.id" class="hover:bg-gray-50">
          <td class="sticky left-0 bg-white border border-gray-200 px-3 py-1 font-medium text-gray-700 min-w-48">
            {{ employee.display_name }}
          </td>
          <MatrixCell
            v-for="project in projects"
            :key="`${employee.id}_${project.id}`"
            :mode="mode"
            :value="cells[`${employee.id}_${project.id}`] ?? null"
          />
        </tr>
      </tbody>
    </table>
  </div>
</template>
```

---

### Task 9.3: Matrix/Index.vue Page

```vue
<!-- resources/js/pages/Matrix/Index.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import MatrixGrid from '@/components/matrix/MatrixGrid.vue'
import { Button } from '@/components/ui/button'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useMatrix } from '@/composables/useMatrix'

const props = defineProps<{
    employees: any[]
    projects: any[]
    cells: Record<string, any>
    filters: { year: number; month: number; team_id: number | null; mode: string }
    allTeams: { id: number; name: string }[]
}>()

const { mode, year, month, teamId, loading, toggleMode, applyFilters } = useMatrix(() => ({
    year: props.filters.year,
    month: props.filters.month,
    teamId: props.filters.team_id,
    mode: props.filters.mode as 'assignment' | 'progress',
}))
</script>

<template>
  <AppLayout>
    <template #header>
      <h1 class="font-semibold text-gray-800">Matriks Kinerja</h1>
    </template>

    <div class="space-y-4">
      <!-- Controls -->
      <div class="flex items-center gap-4">
        <Tabs :model-value="mode" @update:model-value="toggleMode">
          <TabsList>
            <TabsTrigger value="assignment">Penugasan</TabsTrigger>
            <TabsTrigger value="progress">Capaian</TabsTrigger>
          </TabsList>
        </Tabs>
        <!-- year/month/team filters -->
        <Button @click="applyFilters" variant="outline" :disabled="loading">
          {{ loading ? 'Memuat...' : 'Terapkan' }}
        </Button>
      </div>

      <!-- Grid -->
      <MatrixGrid
        :employees="employees"
        :projects="projects"
        :cells="cells"
        :mode="mode"
      />
    </div>
  </AppLayout>
</template>
```

**Commit:**
```bash
git add resources/js/composables/useMatrix.ts resources/js/components/matrix/ resources/js/pages/Matrix/
git commit -m "feat(matrix): add matrix view with assignment/progress toggle and virtual scroll grid"
```

> `bd update 9 --status completed`

---

## Phase 10: Dashboard Page

> `bd update 10 --status in_progress`

### Task 10.1: `useDashboard` Composable

```ts
// resources/js/composables/useDashboard.ts
import { computed, toRefs, reactive, watchEffect, toValue } from 'vue'
import type { MaybeRefOrGetter } from 'vue'

export interface DashboardFilters { year: number; month: number }

export function useDashboard(rawFilters: MaybeRefOrGetter<DashboardFilters>) {
    const state = reactive<DashboardFilters>({ ...toValue(rawFilters) })

    watchEffect(() => {
        Object.assign(state, toValue(rawFilters))
    })

    return { ...toRefs(state) }
}
```

### Task 10.2: Chart Components

```vue
<!-- resources/js/components/dashboard/TrendChart.vue -->
<!-- Stateless: receives chartData prop -->
<script setup lang="ts">
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend } from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend)

const props = defineProps<{
    trend: { month: number; average: number }[]
}>()

const monthLabels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des']

const chartData = computed(() => ({
    labels: props.trend.map(t => monthLabels[t.month - 1]),
    datasets: [{
        label: 'Rata-rata Capaian (%)',
        data: props.trend.map(t => t.average),
        borderColor: '#1B4B8A',
        backgroundColor: 'rgba(27, 75, 138, 0.1)',
        tension: 0.3,
        fill: true,
    }],
}))

const options = { responsive: true, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 100 } } }
</script>

<template>
  <Line :data="chartData" :options="options" />
</template>
```

### Task 10.3: Dashboard.vue Page

Full role-aware implementation using the data shapes from `DashboardController`:

```vue
<!-- resources/js/pages/Dashboard.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import StatCard from '@/components/dashboard/StatCard.vue'
import TrendChart from '@/components/dashboard/TrendChart.vue'
import TeamBreakdownChart from '@/components/dashboard/TeamBreakdownChart.vue'
import { useDashboard } from '@/composables/useDashboard'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

const props = defineProps<{
    filters: { year: number; month: number }
    overall?: number
    byTeam?: Record<string, number>
    trend?: { month: number; average: number }[]
    totalProjects?: number
    totalEmployees?: number
    projects?: any[]     // staff view
}>()

const page = usePage()
const role = computed(() => page.props.auth?.user?.role)
const { year, month } = useDashboard(() => props.filters)
</script>

<template>
  <AppLayout>
    <template #header>
      <h1 class="font-semibold text-gray-800">Beranda</h1>
    </template>

    <!-- Admin / Head view -->
    <div v-if="role === 'admin' || role === 'head'" class="space-y-6">
      <div class="grid grid-cols-3 gap-4">
        <StatCard title="Rata-rata Capaian" :value="`${overall}%`" />
        <StatCard title="Total Proyek" :value="String(totalProjects)" />
        <StatCard title="Total Pegawai" :value="String(totalEmployees)" />
      </div>

      <div class="grid grid-cols-2 gap-6">
        <Card>
          <CardHeader><CardTitle class="text-sm">Tren Capaian {{ year }}</CardTitle></CardHeader>
          <CardContent><TrendChart :trend="trend ?? []" /></CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle class="text-sm">Capaian Per Tim</CardTitle></CardHeader>
          <CardContent><TeamBreakdownChart :by-team="byTeam ?? {}" /></CardContent>
        </Card>
      </div>
    </div>

    <!-- Staff view -->
    <div v-else class="space-y-4">
      <StatCard title="Rata-rata Capaian Saya" :value="`${overall}%`" />
      <!-- list of own projects with mini progress bars -->
    </div>
  </AppLayout>
</template>
```

**Commit:**
```bash
git add resources/js/composables/useDashboard.ts resources/js/components/dashboard/ resources/js/pages/Dashboard.vue
git commit -m "feat(dashboard): add role-aware dashboard page with trend and team breakdown charts"
```

> `bd update 10 --status completed`

---

## Phase 11: Final Wiring & Cleanup

> `bd update 11 --status in_progress`

### Task 11.1: TypeScript Type Safety Check

```bash
pnpm vue-tsc --noEmit
# Fix any type errors before continuing
```

### Task 11.2: Full Test Suite

```bash
# PHP tests
php artisan test --coverage --min=70
# JS tests
pnpm vitest run --coverage
```

### Task 11.3: Build Assets

```bash
pnpm build
# Expected: no errors, dist/ generated
```

### Task 11.4: Commit CI config and finalize feature branch

```bash
git add .
git commit -m "feat: complete performance matrix system implementation"
git flow feature finish initial-setup
# This merges feature/initial-setup → develop
```

### Task 11.5: Open PR to develop

```bash
git push origin develop
gh pr create --base develop --title "feat: initial performance matrix system" --body "Complete implementation per design doc: docs/plans/2026-04-11-performance-matrix-design.md"
```

> `bd update 11 --status completed`

---

## Appendix: Naming Reference

| Indonesian (UI label) | English (code) |
|---|---|
| Capaian Kinerja | PerformanceReport / performance_reports |
| Uraian | WorkItem / work_items |
| Ketua | leader |
| Anggota | member |
| Kepala | head |
| Permasalahan | issues |
| Solusi | solutions |
| Rencana Tindak Lanjut | action_plan |
| NIP | employee_number |
| Sasaran | objective |
| IKU | kpi |
| Capaian Persen | achievement_percentage |
