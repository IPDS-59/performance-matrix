# Kinetik Phase 2a: IKU and RK Masters CRUD Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build full CRUD for IKU (`performance_indicators`) and RK (`performance_plans`) masters with Inertia/Vue 3 pages, Policies, routes, and Pest tests.

**Architecture:** Two resource controllers mirroring `ProjectController` (admin vs. team-lead bifurcation for create/store), auto-discovered Policies registered via `Gate::policy()` in `AppServiceProvider`, six Inertia Vue pages, two new TS types, two nav entries, and two Pest feature test files.

**Tech Stack:** Laravel 11, Inertia.js, Vue 3 `<script setup lang="ts">`, Spatie Permission, Pest, SQLite (tests) / Postgres (prod), Tailwind, shadcn-vue (Button, Input, Label, Table, Popover/Command, Select).

---

## Key patterns to mirror

- **Controllers**: `app/Http/Controllers/ProjectController.php` — admin vs. lead bifurcation in `create()` and `store()`.
- **Policies**: `app/Policies/ProjectPolicy.php` — registered via `Gate::policy()` in `AppServiceProvider::boot()`.
- **Routes**: `routes/web.php` inside `Route::middleware(['auth', 'verified'])->group()`.
- **Vue Index with filters**: `resources/js/Pages/Projects/Index.vue` — `year` + `team_id` filter via `router.get`.
- **Vue Create/Edit**: `resources/js/Pages/Teams/Create.vue` and `Teams/Edit.vue` — `useForm`, Popover/Command combobox for foreign keys.
- **Tests**: `tests/Feature/Http/ProjectControllerTest.php` — guest redirect, admin CRUD, lead scoping, 403s.
- **Test helpers**: `tests/Pest.php` — `adminUser()`, `staffUser()`, `headUser()`, `seedRolesAndPermissions()`.
- **Types**: `resources/js/types/index.d.ts` — add interfaces for `PerformanceIndicator` and `PerformancePlan`.
- **Nav**: `resources/js/Layouts/AppLayout.vue` — inside `<!-- Admin section -->` `<template v-if="isAdmin">` block; Projects link uses `canViewProjects` from shared `can` prop.
- **`can` prop**: `app/Http/Middleware/HandleInertiaRequests.php` — already shares `can.view_projects`. We need `can.view_indicators` and `can.view_plans` for non-admin nav entries.

---

## Task 1: Add `PerformanceIndicator` and `PerformancePlan` TypeScript types

**Files:**
- Modify: `resources/js/types/index.d.ts`

**Step 1: Add interfaces**

Append after the `Project` interface (line ~44):

```typescript
export interface PerformanceIndicator {
    id: number;
    team_id: number;
    year: number;
    code?: string | null;
    name: string;
    target?: number | string | null;
    target_unit?: string | null;
    description?: string | null;
    team?: Team | null;
}

export interface PerformancePlan {
    id: number;
    project_id: number;
    code?: string | null;
    description: string;
    target?: number | string | null;
    target_unit?: string | null;
    period_type: 'year' | 'quarter';
    period?: number | null;
    pic_employee_id?: number | null;
    project?: Project | null;
    pic?: Employee | null;
}
```

**Step 2: Verify typecheck still passes**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && npm run typecheck
```

Expected: no new errors.

---

## Task 2: Create `PerformanceIndicatorPolicy`

**Files:**
- Create: `app/Policies/PerformanceIndicatorPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Step 1: Create the policy**

```php
<?php

namespace App\Policies;

use App\Models\PerformanceIndicator;
use App\Models\Team;
use App\Models\User;

class PerformanceIndicatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage-projects') || $user->hasRole(['head', 'staff']);
    }

    public function view(User $user, PerformanceIndicator $indicator): bool
    {
        return $user->hasPermissionTo('manage-projects') || $user->hasRole(['head', 'staff']);
    }

    public function create(User $user): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        return Team::where('leader_id', $employee->id)->exists();
    }

    public function update(User $user, PerformanceIndicator $indicator): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        return Team::where('id', $indicator->team_id)
            ->where('leader_id', $employee->id)
            ->exists();
    }

    public function delete(User $user, PerformanceIndicator $indicator): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        return Team::where('id', $indicator->team_id)
            ->where('leader_id', $employee->id)
            ->exists();
    }
}
```

**Step 2: Register in `AppServiceProvider::boot()`**

In `app/Providers/AppServiceProvider.php`, add these two lines after the existing `Gate::policy(PerformanceReport::class, PerformancePolicy::class);`:

```php
use App\Models\PerformanceIndicator;
use App\Models\PerformancePlan;
use App\Policies\PerformanceIndicatorPolicy;
use App\Policies\PerformancePlanPolicy;
// ...
Gate::policy(PerformanceIndicator::class, PerformanceIndicatorPolicy::class);
Gate::policy(PerformancePlan::class, PerformancePlanPolicy::class);
```

Note: Add `PerformancePlanPolicy` registration at the same time (next task creates the file).

---

## Task 3: Create `PerformancePlanPolicy`

**Files:**
- Create: `app/Policies/PerformancePlanPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\PerformancePlan;
use App\Models\Team;
use App\Models\User;

class PerformancePlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage-projects') || $user->hasRole(['head', 'staff']);
    }

    public function view(User $user, PerformancePlan $plan): bool
    {
        return $user->hasPermissionTo('manage-projects') || $user->hasRole(['head', 'staff']);
    }

    public function create(User $user): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        return Team::where('leader_id', $employee->id)->exists();
    }

    public function update(User $user, PerformancePlan $plan): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        $plan->loadMissing('project');

        return Team::where('id', $plan->project->team_id)
            ->where('leader_id', $employee->id)
            ->exists();
    }

    public function delete(User $user, PerformancePlan $plan): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        $plan->loadMissing('project');

        return Team::where('id', $plan->project->team_id)
            ->where('leader_id', $employee->id)
            ->exists();
    }
}
```

---

## Task 4: Create `PerformanceIndicatorController`

**Files:**
- Create: `app/Http/Controllers/PerformanceIndicatorController.php`

Mirror the admin-vs-lead pattern from `ProjectController`. The controller validates inline (no separate FormRequest needed — matches the existing project/team approach).

```php
<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PerformanceIndicator;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceIndicatorController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PerformanceIndicator::class);

        $user = $request->user();
        $isAdmin = $user->hasPermissionTo('manage-projects');
        $year = $request->integer('year', now()->year);
        $teamId = $request->integer('team_id');

        if (! $isAdmin && ! $teamId) {
            $teamId = $user->employee?->team_id;
        }

        $indicators = PerformanceIndicator::with('team:id,name')
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->where('year', $year)
            ->orderBy('code')
            ->orderBy('name')
            ->get();

        $teams = $isAdmin
            ? Team::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : Team::where('id', $teamId)->get(['id', 'name']);

        $canCreate = $user->can('create', PerformanceIndicator::class);

        return Inertia::render('PerformanceIndicators/Index', compact('indicators', 'teams', 'year', 'teamId', 'canCreate'));
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PerformanceIndicator::class);

        $user = $request->user();
        $isAdmin = $user->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $teams = Team::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        } else {
            $employee = $user->employee;
            $teams = $employee
                ? Team::where('leader_id', $employee->id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect();
        }

        return Inertia::render('PerformanceIndicators/Create', compact('teams', 'isAdmin'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PerformanceIndicator::class);

        $isAdmin = $request->user()->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $validated = $request->validate([
                'team_id'     => ['required', 'exists:teams,id'],
                'year'        => ['required', 'integer', 'min:2020', 'max:2099'],
                'code'        => ['nullable', 'string', 'max:50'],
                'name'        => ['required', 'string', 'max:255'],
                'target'      => ['nullable', 'numeric'],
                'target_unit' => ['nullable', 'string', 'max:100'],
                'description' => ['nullable', 'string'],
            ]);
        } else {
            $employee = $request->user()->employee;
            $ledTeamIds = Team::where('leader_id', $employee?->id)->pluck('id');
            abort_if(! $employee || $ledTeamIds->isEmpty(), 403, 'Akun belum memimpin tim manapun.');

            $validated = $request->validate([
                'team_id'     => ['required', Rule::in($ledTeamIds->all())],
                'year'        => ['required', 'integer', 'min:2020', 'max:2099'],
                'code'        => ['nullable', 'string', 'max:50'],
                'name'        => ['required', 'string', 'max:255'],
                'target'      => ['nullable', 'numeric'],
                'target_unit' => ['nullable', 'string', 'max:100'],
                'description' => ['nullable', 'string'],
            ]);
        }

        PerformanceIndicator::create($validated);

        return redirect()->route('performance-indicators.index')
            ->with('success', 'IKU berhasil ditambahkan.');
    }

    public function edit(PerformanceIndicator $performanceIndicator, Request $request): Response
    {
        $this->authorize('update', $performanceIndicator);

        $user = $request->user();
        $isAdmin = $user->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $teams = Team::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        } else {
            $teams = Team::where('id', $performanceIndicator->team_id)->get(['id', 'name']);
        }

        return Inertia::render('PerformanceIndicators/Edit', compact('performanceIndicator', 'teams', 'isAdmin'));
    }

    public function update(Request $request, PerformanceIndicator $performanceIndicator): RedirectResponse
    {
        $this->authorize('update', $performanceIndicator);

        $isAdmin = $request->user()->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $validated = $request->validate([
                'team_id'     => ['required', 'exists:teams,id'],
                'year'        => ['required', 'integer', 'min:2020', 'max:2099'],
                'code'        => ['nullable', 'string', 'max:50'],
                'name'        => ['required', 'string', 'max:255'],
                'target'      => ['nullable', 'numeric'],
                'target_unit' => ['nullable', 'string', 'max:100'],
                'description' => ['nullable', 'string'],
            ]);
        } else {
            $validated = $request->validate([
                'year'        => ['required', 'integer', 'min:2020', 'max:2099'],
                'code'        => ['nullable', 'string', 'max:50'],
                'name'        => ['required', 'string', 'max:255'],
                'target'      => ['nullable', 'numeric'],
                'target_unit' => ['nullable', 'string', 'max:100'],
                'description' => ['nullable', 'string'],
            ]);
        }

        $performanceIndicator->update($validated);

        return redirect()->route('performance-indicators.index')
            ->with('success', 'IKU berhasil diperbarui.');
    }

    public function destroy(PerformanceIndicator $performanceIndicator): RedirectResponse
    {
        $this->authorize('delete', $performanceIndicator);

        $performanceIndicator->delete();

        return redirect()->route('performance-indicators.index')
            ->with('success', 'IKU berhasil dihapus.');
    }
}
```

---

## Task 5: Create `PerformancePlanController`

**Files:**
- Create: `app/Http/Controllers/PerformancePlanController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PerformancePlanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PerformancePlan::class);

        $user = $request->user();
        $isAdmin = $user->hasPermissionTo('manage-projects');
        $projectId = $request->integer('project_id');

        $plans = PerformancePlan::with('project.team:id,name', 'pic:id,name,display_name')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when(! $isAdmin, function ($q) use ($user) {
                $employee = $user->employee;
                if ($employee) {
                    $ledTeamIds = Team::where('leader_id', $employee->id)->pluck('id');
                    $q->whereHas('project', fn ($pq) => $pq->whereIn('team_id', $ledTeamIds));
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->orderBy('code')
            ->orderBy('description')
            ->get();

        if ($isAdmin) {
            $projects = Project::with('team:id,name')->orderBy('name')->get(['id', 'name', 'team_id', 'year']);
        } else {
            $employee = $user->employee;
            $ledTeamIds = $employee
                ? Team::where('leader_id', $employee->id)->pluck('id')
                : collect();
            $projects = $ledTeamIds->isNotEmpty()
                ? Project::with('team:id,name')->whereIn('team_id', $ledTeamIds)->orderBy('name')->get(['id', 'name', 'team_id', 'year'])
                : collect();
        }

        $canCreate = $user->can('create', PerformancePlan::class);

        return Inertia::render('PerformancePlans/Index', compact('plans', 'projects', 'projectId', 'canCreate'));
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PerformancePlan::class);

        $user = $request->user();
        $isAdmin = $user->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $projects = Project::with('team:id,name')->orderBy('name')->get(['id', 'name', 'team_id']);
            $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'display_name', 'team_id']);
        } else {
            $employee = $user->employee;
            $ledTeamIds = $employee
                ? Team::where('leader_id', $employee->id)->pluck('id')
                : collect();

            $projects = $ledTeamIds->isNotEmpty()
                ? Project::with('team:id,name')->whereIn('team_id', $ledTeamIds)->orderBy('name')->get(['id', 'name', 'team_id'])
                : collect();

            $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'display_name', 'team_id']);
        }

        return Inertia::render('PerformancePlans/Create', compact('projects', 'employees', 'isAdmin'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PerformancePlan::class);

        $isAdmin = $request->user()->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $validated = $request->validate([
                'project_id'      => ['required', 'exists:projects,id'],
                'code'            => ['nullable', 'string', 'max:50'],
                'description'     => ['required', 'string'],
                'target'          => ['nullable', 'numeric'],
                'target_unit'     => ['nullable', 'string', 'max:100'],
                'period_type'     => ['required', Rule::in(['year', 'quarter'])],
                'period'          => ['nullable', 'integer', 'min:1', 'max:4'],
                'pic_employee_id' => ['nullable', 'exists:employees,id'],
            ]);
        } else {
            $employee = $request->user()->employee;
            $ledTeamIds = Team::where('leader_id', $employee?->id)->pluck('id');
            abort_if(! $employee || $ledTeamIds->isEmpty(), 403, 'Akun belum memimpin tim manapun.');

            $allowedProjectIds = Project::whereIn('team_id', $ledTeamIds)->pluck('id');

            $validated = $request->validate([
                'project_id'      => ['required', Rule::in($allowedProjectIds->all())],
                'code'            => ['nullable', 'string', 'max:50'],
                'description'     => ['required', 'string'],
                'target'          => ['nullable', 'numeric'],
                'target_unit'     => ['nullable', 'string', 'max:100'],
                'period_type'     => ['required', Rule::in(['year', 'quarter'])],
                'period'          => ['nullable', 'integer', 'min:1', 'max:4'],
                'pic_employee_id' => ['nullable', 'exists:employees,id'],
            ]);
        }

        PerformancePlan::create($validated);

        return redirect()->route('performance-plans.index')
            ->with('success', 'Rencana Kinerja berhasil ditambahkan.');
    }

    public function edit(PerformancePlan $performancePlan, Request $request): Response
    {
        $this->authorize('update', $performancePlan);

        $user = $request->user();
        $isAdmin = $user->hasPermissionTo('manage-projects');

        $performancePlan->load('project.team:id,name', 'pic:id,name,display_name');

        if ($isAdmin) {
            $projects = Project::with('team:id,name')->orderBy('name')->get(['id', 'name', 'team_id']);
            $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'display_name', 'team_id']);
        } else {
            $employee = $user->employee;
            $ledTeamIds = $employee
                ? Team::where('leader_id', $employee->id)->pluck('id')
                : collect();
            $projects = $ledTeamIds->isNotEmpty()
                ? Project::with('team:id,name')->whereIn('team_id', $ledTeamIds)->orderBy('name')->get(['id', 'name', 'team_id'])
                : collect();
            $teamId = $performancePlan->project->team_id ?? null;
            $employees = $teamId
                ? Employee::where('team_id', $teamId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'display_name', 'team_id'])
                : collect();
        }

        return Inertia::render('PerformancePlans/Edit', compact('performancePlan', 'projects', 'employees', 'isAdmin'));
    }

    public function update(Request $request, PerformancePlan $performancePlan): RedirectResponse
    {
        $this->authorize('update', $performancePlan);

        $isAdmin = $request->user()->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $validated = $request->validate([
                'project_id'      => ['required', 'exists:projects,id'],
                'code'            => ['nullable', 'string', 'max:50'],
                'description'     => ['required', 'string'],
                'target'          => ['nullable', 'numeric'],
                'target_unit'     => ['nullable', 'string', 'max:100'],
                'period_type'     => ['required', Rule::in(['year', 'quarter'])],
                'period'          => ['nullable', 'integer', 'min:1', 'max:4'],
                'pic_employee_id' => ['nullable', 'exists:employees,id'],
            ]);
        } else {
            $validated = $request->validate([
                'code'            => ['nullable', 'string', 'max:50'],
                'description'     => ['required', 'string'],
                'target'          => ['nullable', 'numeric'],
                'target_unit'     => ['nullable', 'string', 'max:100'],
                'period_type'     => ['required', Rule::in(['year', 'quarter'])],
                'period'          => ['nullable', 'integer', 'min:1', 'max:4'],
                'pic_employee_id' => ['nullable', 'exists:employees,id'],
            ]);
        }

        $performancePlan->update($validated);

        return redirect()->route('performance-plans.index')
            ->with('success', 'Rencana Kinerja berhasil diperbarui.');
    }

    public function destroy(PerformancePlan $performancePlan): RedirectResponse
    {
        $this->authorize('delete', $performancePlan);

        $performancePlan->delete();

        return redirect()->route('performance-plans.index')
            ->with('success', 'Rencana Kinerja berhasil dihapus.');
    }
}
```

---

## Task 6: Add routes to `routes/web.php`

**Files:**
- Modify: `routes/web.php`

Add two use statements at the top (with existing ones):

```php
use App\Http\Controllers\PerformanceIndicatorController;
use App\Http\Controllers\PerformancePlanController;
```

Add two resource routes inside the `Route::middleware(['auth', 'verified'])->group()` after the existing project route:

```php
Route::resource('performance-indicators', PerformanceIndicatorController::class)->except(['show']);
Route::resource('performance-plans', PerformancePlanController::class)->except(['show']);
```

**Verify routes list correctly:**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && php artisan route:list --name=performance-indicators,performance-plans
```

---

## Task 7: Extend `HandleInertiaRequests` middleware to share `can` flags for new resources

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

The nav links for IKU and RK will be shown to admins (inside the `isAdmin` block) and also to team leads via `canViewProjects` (which the `ProjectPolicy::viewAny` already returns true for `head`/`staff` roles). 

Looking at AppLayout.vue more carefully:
- Teams/Employees nav → `v-if="isAdmin"` block  
- Projects nav → `v-if="canViewProjects"` (separate, outside admin block)

The IKU and RK links should be visible to the same users who can see Projects (`manage-projects` OR `head`/`staff` role). Since `PerformanceIndicatorPolicy::viewAny` returns the same condition as `ProjectPolicy::viewAny`, we can reuse `canViewProjects` for these links OR add explicit `can.view_indicators` and `can.view_plans` flags.

**Decision: add two new `can` flags** to keep nav logic explicit and allow future independent policy changes.

In `HandleInertiaRequests::share()`, extend the `'can'` closure:

```php
'can' => fn () => [
    'view_projects'   => $request->user() ? rescue(fn () => $request->user()->can('viewAny', Project::class), false) : false,
    'view_indicators' => $request->user() ? rescue(fn () => $request->user()->can('viewAny', PerformanceIndicator::class), false) : false,
    'view_plans'      => $request->user() ? rescue(fn () => $request->user()->can('viewAny', PerformancePlan::class), false) : false,
],
```

Add imports at top:
```php
use App\Models\PerformanceIndicator;
use App\Models\PerformancePlan;
```

---

## Task 8: Add nav links to `AppLayout.vue`

**Files:**
- Modify: `resources/js/Layouts/AppLayout.vue`

**Step 1: Add computed flags from shared `can` prop**

After the existing `canViewProjects` computed (line ~18):

```typescript
const canViewIndicators = computed(() => (page.props.can as Record<string, boolean>)?.view_indicators ?? false);
const canViewPlans = computed(() => (page.props.can as Record<string, boolean>)?.view_plans ?? false);
```

**Step 2: Add nav links in the template**

After the existing Projects link (`v-if="canViewProjects"`), add:

```html
<!-- IKU (admin + team leads) -->
<Link
    v-if="canViewIndicators"
    :href="route('performance-indicators.index')"
    :class="route().current('performance-indicators.*') ? 'bg-white/20' : 'hover:bg-white/10'"
    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
>
    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
    </svg>
    <span v-if="sidebar.isOpen">IKU</span>
</Link>

<!-- Rencana Kinerja (admin + team leads) -->
<Link
    v-if="canViewPlans"
    :href="route('performance-plans.index')"
    :class="route().current('performance-plans.*') ? 'bg-white/20' : 'hover:bg-white/10'"
    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
>
    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
    </svg>
    <span v-if="sidebar.isOpen">Rencana Kinerja (RK)</span>
</Link>
```

---

## Task 9: Create Vue pages for IKU

### 9a: `resources/js/Pages/PerformanceIndicators/Index.vue`

Mirror `Projects/Index.vue` filter pattern but simpler (no grouping).

```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { PerformanceIndicator, Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ref } from 'vue';

const props = defineProps<{
    indicators: PerformanceIndicator[];
    teams: Team[];
    year: number;
    teamId: number;
    canCreate: boolean;
}>();

const year = ref(String(props.year));
const teamId = ref(props.teamId ? String(props.teamId) : '');

function applyFilters() {
    router.get(route('performance-indicators.index'), { year: year.value, team_id: teamId.value || '' }, { preserveState: true });
}

const confirmOpen = ref(false);
const pendingId = ref<number | null>(null);
const pendingName = ref('');

function confirmDelete(id: number, name: string) {
    pendingId.value = id;
    pendingName.value = name;
    confirmOpen.value = true;
}

function executeDelete() {
    if (pendingId.value !== null) {
        router.delete(route('performance-indicators.destroy', pendingId.value));
    }
}
</script>

<template>
    <Head title="IKU" />
    <AppLayout>
        <template #title>Indikator Kinerja Utama (IKU)</template>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tahun</label>
                <Select v-model="year" @update:model-value="applyFilters">
                    <SelectTrigger class="w-28">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="y in [2023, 2024, 2025, 2026, 2027]" :key="y" :value="String(y)">{{ y }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div v-if="teams.length > 1">
                <label class="block text-xs font-medium text-gray-600 mb-1">Tim Kerja</label>
                <Select v-model="teamId" @update:model-value="applyFilters">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Semua Tim" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Semua Tim</SelectItem>
                        <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="ml-auto" v-if="canCreate">
                <Button as-child>
                    <Link :href="route('performance-indicators.create')">Tambah IKU</Link>
                </Button>
            </div>
        </div>

        <div class="rounded-md border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Kode</TableHead>
                        <TableHead>Nama IKU</TableHead>
                        <TableHead>Tim</TableHead>
                        <TableHead>Tahun</TableHead>
                        <TableHead>Target</TableHead>
                        <TableHead class="w-28 text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!indicators.length">
                        <TableCell colspan="6" class="text-center text-gray-400 py-8">Belum ada data.</TableCell>
                    </TableRow>
                    <TableRow v-for="indicator in indicators" :key="indicator.id">
                        <TableCell class="font-mono text-sm">{{ indicator.code ?? '—' }}</TableCell>
                        <TableCell>{{ indicator.name }}</TableCell>
                        <TableCell>{{ indicator.team?.name ?? '—' }}</TableCell>
                        <TableCell>{{ indicator.year }}</TableCell>
                        <TableCell>
                            <template v-if="indicator.target != null">
                                {{ indicator.target }} {{ indicator.target_unit }}
                            </template>
                            <template v-else>—</template>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="inline-flex gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="route('performance-indicators.edit', indicator.id)">Edit</Link>
                                </Button>
                                <Button variant="destructive" size="sm" @click="confirmDelete(indicator.id, indicator.name)">
                                    Hapus
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Hapus IKU"
            :description="`IKU &quot;${pendingName}&quot; akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.`"
            confirm-label="Hapus IKU"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>
```

### 9b: `resources/js/Pages/PerformanceIndicators/Create.vue`

```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import InputError from '@/Components/InputError.vue';

const props = defineProps<{ teams: Team[]; isAdmin: boolean }>();

const form = useForm({
    team_id: null as number | null,
    year: new Date().getFullYear(),
    code: '',
    name: '',
    target: '' as string | number,
    target_unit: '',
    description: '',
});

function submit() {
    form.post(route('performance-indicators.store'));
}
</script>

<template>
    <Head title="Tambah IKU" />
    <AppLayout>
        <template #title>Tambah IKU</template>

        <div class="max-w-lg bg-white rounded-md border p-6">
            <form @submit.prevent="submit" class="space-y-4">
                <div v-if="isAdmin || teams.length > 1">
                    <Label for="team_id">Tim Kerja</Label>
                    <Select v-model="form.team_id" @update:model-value="(v) => form.team_id = Number(v)">
                        <SelectTrigger class="mt-1 w-full">
                            <SelectValue placeholder="Pilih tim..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.team_id" />
                </div>
                <div>
                    <Label for="year">Tahun</Label>
                    <Input id="year" type="number" v-model="form.year" class="mt-1" min="2020" max="2099" />
                    <InputError :message="form.errors.year" />
                </div>
                <div>
                    <Label for="code">Kode IKU</Label>
                    <Input id="code" v-model="form.code" class="mt-1" placeholder="Opsional" />
                    <InputError :message="form.errors.code" />
                </div>
                <div>
                    <Label for="name">Nama IKU</Label>
                    <Input id="name" v-model="form.name" class="mt-1" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label for="target">Target</Label>
                        <Input id="target" type="number" step="any" v-model="form.target" class="mt-1" placeholder="Opsional" />
                        <InputError :message="form.errors.target" />
                    </div>
                    <div>
                        <Label for="target_unit">Satuan</Label>
                        <Input id="target_unit" v-model="form.target_unit" class="mt-1" placeholder="Kegiatan, %, dll." />
                        <InputError :message="form.errors.target_unit" />
                    </div>
                </div>
                <div>
                    <Label for="description">Deskripsi</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" as-child>
                        <a :href="route('performance-indicators.index')">Batal</a>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Simpan</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
```

### 9c: `resources/js/Pages/PerformanceIndicators/Edit.vue`

```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { PerformanceIndicator, Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import InputError from '@/Components/InputError.vue';

const props = defineProps<{ performanceIndicator: PerformanceIndicator; teams: Team[]; isAdmin: boolean }>();

const form = useForm({
    team_id: props.performanceIndicator.team_id,
    year: props.performanceIndicator.year,
    code: props.performanceIndicator.code ?? '',
    name: props.performanceIndicator.name,
    target: props.performanceIndicator.target ?? '' as string | number,
    target_unit: props.performanceIndicator.target_unit ?? '',
    description: props.performanceIndicator.description ?? '',
});

function submit() {
    form.put(route('performance-indicators.update', props.performanceIndicator.id));
}
</script>

<template>
    <Head title="Edit IKU" />
    <AppLayout>
        <template #title>Edit IKU</template>

        <div class="max-w-lg bg-white rounded-md border p-6">
            <form @submit.prevent="submit" class="space-y-4">
                <div v-if="isAdmin || teams.length > 1">
                    <Label for="team_id">Tim Kerja</Label>
                    <Select :model-value="String(form.team_id)" @update:model-value="(v) => form.team_id = Number(v)">
                        <SelectTrigger class="mt-1 w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.team_id" />
                </div>
                <div>
                    <Label for="year">Tahun</Label>
                    <Input id="year" type="number" v-model="form.year" class="mt-1" min="2020" max="2099" />
                    <InputError :message="form.errors.year" />
                </div>
                <div>
                    <Label for="code">Kode IKU</Label>
                    <Input id="code" v-model="form.code" class="mt-1" />
                    <InputError :message="form.errors.code" />
                </div>
                <div>
                    <Label for="name">Nama IKU</Label>
                    <Input id="name" v-model="form.name" class="mt-1" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label for="target">Target</Label>
                        <Input id="target" type="number" step="any" v-model="form.target" class="mt-1" />
                        <InputError :message="form.errors.target" />
                    </div>
                    <div>
                        <Label for="target_unit">Satuan</Label>
                        <Input id="target_unit" v-model="form.target_unit" class="mt-1" />
                        <InputError :message="form.errors.target_unit" />
                    </div>
                </div>
                <div>
                    <Label for="description">Deskripsi</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" as-child>
                        <a :href="route('performance-indicators.index')">Batal</a>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Perbarui</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
```

---

## Task 10: Create Vue pages for RK

### 10a: `resources/js/Pages/PerformancePlans/Index.vue`

```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { PerformancePlan, Project } from '@/types';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ref } from 'vue';

interface ProjectOption {
    id: number;
    name: string;
    year: number;
    team?: { id: number; name: string } | null;
}

const props = defineProps<{
    plans: PerformancePlan[];
    projects: ProjectOption[];
    projectId: number;
    canCreate: boolean;
}>();

const projectId = ref(props.projectId ? String(props.projectId) : '');

function applyFilters() {
    router.get(route('performance-plans.index'), { project_id: projectId.value || '' }, { preserveState: true });
}

const confirmOpen = ref(false);
const pendingId = ref<number | null>(null);
const pendingName = ref('');

function confirmDelete(id: number, name: string) {
    pendingId.value = id;
    pendingName.value = name;
    confirmOpen.value = true;
}

function executeDelete() {
    if (pendingId.value !== null) {
        router.delete(route('performance-plans.destroy', pendingId.value));
    }
}

const periodTypeLabel: Record<string, string> = {
    year: 'Tahunan',
    quarter: 'Triwulan',
};
</script>

<template>
    <Head title="Rencana Kinerja" />
    <AppLayout>
        <template #title>Rencana Kinerja (RK)</template>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Proyek</label>
                <Select v-model="projectId" @update:model-value="applyFilters">
                    <SelectTrigger class="w-64">
                        <SelectValue placeholder="Semua Proyek" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Semua Proyek</SelectItem>
                        <SelectItem v-for="p in projects" :key="p.id" :value="String(p.id)">
                            {{ p.name }} ({{ p.year }})
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="ml-auto" v-if="canCreate">
                <Button as-child>
                    <Link :href="route('performance-plans.create')">Tambah RK</Link>
                </Button>
            </div>
        </div>

        <div class="rounded-md border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Kode</TableHead>
                        <TableHead>Deskripsi</TableHead>
                        <TableHead>Proyek</TableHead>
                        <TableHead>Periode</TableHead>
                        <TableHead>Target</TableHead>
                        <TableHead>PIC</TableHead>
                        <TableHead class="w-28 text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!plans.length">
                        <TableCell colspan="7" class="text-center text-gray-400 py-8">Belum ada data.</TableCell>
                    </TableRow>
                    <TableRow v-for="plan in plans" :key="plan.id">
                        <TableCell class="font-mono text-sm">{{ plan.code ?? '—' }}</TableCell>
                        <TableCell>{{ plan.description }}</TableCell>
                        <TableCell>{{ plan.project?.name ?? '—' }}</TableCell>
                        <TableCell>
                            {{ periodTypeLabel[plan.period_type] ?? plan.period_type }}
                            <template v-if="plan.period"> TW{{ plan.period }}</template>
                        </TableCell>
                        <TableCell>
                            <template v-if="plan.target != null">{{ plan.target }} {{ plan.target_unit }}</template>
                            <template v-else>—</template>
                        </TableCell>
                        <TableCell>{{ plan.pic?.display_name || plan.pic?.name || '—' }}</TableCell>
                        <TableCell class="text-right">
                            <div class="inline-flex gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="route('performance-plans.edit', plan.id)">Edit</Link>
                                </Button>
                                <Button variant="destructive" size="sm" @click="confirmDelete(plan.id, plan.description)">
                                    Hapus
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Hapus Rencana Kinerja"
            :description="`RK &quot;${pendingName}&quot; akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.`"
            confirm-label="Hapus RK"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>
```

### 10b: `resources/js/Pages/PerformancePlans/Create.vue`

```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { Employee } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { computed, ref } from 'vue';

interface ProjectOption {
    id: number;
    name: string;
    year: number;
    team_id: number;
    team?: { id: number; name: string } | null;
}

const props = defineProps<{
    projects: ProjectOption[];
    employees: Employee[];
    isAdmin: boolean;
}>();

const form = useForm({
    project_id: null as number | null,
    code: '',
    description: '',
    target: '' as string | number,
    target_unit: '',
    period_type: 'year' as 'year' | 'quarter',
    period: null as number | null,
    pic_employee_id: null as number | null,
});

const picOpen = ref(false);

const selectedPicLabel = computed(() => {
    if (form.pic_employee_id === null) return '— Tidak ada —';
    const emp = props.employees.find(e => e.id === form.pic_employee_id);
    return emp ? (emp.display_name || emp.name) : '— Tidak ada —';
});

// Employees for the selected project's team
const teamEmployees = computed(() => {
    if (!form.project_id) return props.employees;
    const project = props.projects.find(p => p.id === form.project_id);
    if (!project) return props.employees;
    return props.employees.filter(e => e.team_id === project.team_id);
});

function submit() {
    form.post(route('performance-plans.store'));
}
</script>

<template>
    <Head title="Tambah Rencana Kinerja" />
    <AppLayout>
        <template #title>Tambah Rencana Kinerja (RK)</template>

        <div class="max-w-lg bg-white rounded-md border p-6">
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <Label for="project_id">Proyek</Label>
                    <Select :model-value="form.project_id ? String(form.project_id) : ''" @update:model-value="(v) => { form.project_id = v ? Number(v) : null; form.pic_employee_id = null; }">
                        <SelectTrigger class="mt-1 w-full">
                            <SelectValue placeholder="Pilih proyek..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="p in projects" :key="p.id" :value="String(p.id)">
                                {{ p.name }} ({{ p.year }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.project_id" />
                </div>
                <div>
                    <Label for="code">Kode RK</Label>
                    <Input id="code" v-model="form.code" class="mt-1" placeholder="Opsional" />
                    <InputError :message="form.errors.code" />
                </div>
                <div>
                    <Label for="description">Deskripsi</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label for="target">Target</Label>
                        <Input id="target" type="number" step="any" v-model="form.target" class="mt-1" placeholder="Opsional" />
                        <InputError :message="form.errors.target" />
                    </div>
                    <div>
                        <Label for="target_unit">Satuan</Label>
                        <Input id="target_unit" v-model="form.target_unit" class="mt-1" placeholder="Kegiatan, %, dll." />
                        <InputError :message="form.errors.target_unit" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label>Tipe Periode</Label>
                        <Select v-model="form.period_type">
                            <SelectTrigger class="mt-1 w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="year">Tahunan</SelectItem>
                                <SelectItem value="quarter">Triwulan</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.period_type" />
                    </div>
                    <div v-if="form.period_type === 'quarter'">
                        <Label for="period">Triwulan</Label>
                        <Select :model-value="form.period ? String(form.period) : ''" @update:model-value="(v) => form.period = v ? Number(v) : null">
                            <SelectTrigger class="mt-1 w-full">
                                <SelectValue placeholder="Pilih..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">TW 1</SelectItem>
                                <SelectItem value="2">TW 2</SelectItem>
                                <SelectItem value="3">TW 3</SelectItem>
                                <SelectItem value="4">TW 4</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.period" />
                    </div>
                </div>
                <div>
                    <Label>PIC</Label>
                    <Popover v-model:open="picOpen">
                        <PopoverTrigger as-child>
                            <Button variant="outline" role="combobox" class="mt-1 w-full justify-between font-normal">
                                {{ selectedPicLabel }}
                                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-[--radix-popover-trigger-width] p-0">
                            <Command>
                                <CommandInput placeholder="Cari pegawai..." />
                                <CommandList>
                                    <CommandEmpty>Tidak ada hasil.</CommandEmpty>
                                    <CommandGroup>
                                        <CommandItem value="__none__" @select="() => { form.pic_employee_id = null; picOpen = false }">
                                            — Tidak ada —
                                            <Check v-if="form.pic_employee_id === null" class="ml-auto h-4 w-4" />
                                        </CommandItem>
                                        <CommandItem
                                            v-for="emp in teamEmployees"
                                            :key="emp.id"
                                            :value="emp.display_name || emp.name"
                                            @select="() => { form.pic_employee_id = emp.id; picOpen = false }"
                                        >
                                            {{ emp.display_name || emp.name }}
                                            <Check v-if="form.pic_employee_id === emp.id" class="ml-auto h-4 w-4" />
                                        </CommandItem>
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                    <InputError :message="form.errors.pic_employee_id" />
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" as-child>
                        <a :href="route('performance-plans.index')">Batal</a>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Simpan</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
```

### 10c: `resources/js/Pages/PerformancePlans/Edit.vue`

Mirror Create.vue with existing data pre-populated:

```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { Employee, PerformancePlan } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { computed, ref } from 'vue';

interface ProjectOption {
    id: number;
    name: string;
    year: number;
    team_id: number;
    team?: { id: number; name: string } | null;
}

const props = defineProps<{
    performancePlan: PerformancePlan;
    projects: ProjectOption[];
    employees: Employee[];
    isAdmin: boolean;
}>();

const form = useForm({
    project_id: props.performancePlan.project_id,
    code: props.performancePlan.code ?? '',
    description: props.performancePlan.description,
    target: props.performancePlan.target ?? '' as string | number,
    target_unit: props.performancePlan.target_unit ?? '',
    period_type: props.performancePlan.period_type,
    period: props.performancePlan.period ?? null,
    pic_employee_id: props.performancePlan.pic_employee_id ?? null,
});

const picOpen = ref(false);

const selectedPicLabel = computed(() => {
    if (form.pic_employee_id === null) return '— Tidak ada —';
    const emp = props.employees.find(e => e.id === form.pic_employee_id);
    return emp ? (emp.display_name || emp.name) : '— Tidak ada —';
});

const teamEmployees = computed(() => {
    const project = props.projects.find(p => p.id === form.project_id);
    if (!project) return props.employees;
    return props.employees.filter(e => e.team_id === project.team_id);
});

function submit() {
    form.put(route('performance-plans.update', props.performancePlan.id));
}
</script>

<template>
    <Head title="Edit Rencana Kinerja" />
    <AppLayout>
        <template #title>Edit Rencana Kinerja (RK)</template>

        <div class="max-w-lg bg-white rounded-md border p-6">
            <form @submit.prevent="submit" class="space-y-4">
                <div v-if="isAdmin">
                    <Label>Proyek</Label>
                    <Select :model-value="String(form.project_id)" @update:model-value="(v) => { form.project_id = Number(v); form.pic_employee_id = null; }">
                        <SelectTrigger class="mt-1 w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="p in projects" :key="p.id" :value="String(p.id)">
                                {{ p.name }} ({{ p.year }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.project_id" />
                </div>
                <div v-else class="text-sm text-gray-500">
                    Proyek: <span class="font-medium text-gray-800">{{ performancePlan.project?.name ?? '—' }}</span>
                </div>
                <div>
                    <Label for="code">Kode RK</Label>
                    <Input id="code" v-model="form.code" class="mt-1" />
                    <InputError :message="form.errors.code" />
                </div>
                <div>
                    <Label for="description">Deskripsi</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label for="target">Target</Label>
                        <Input id="target" type="number" step="any" v-model="form.target" class="mt-1" />
                        <InputError :message="form.errors.target" />
                    </div>
                    <div>
                        <Label for="target_unit">Satuan</Label>
                        <Input id="target_unit" v-model="form.target_unit" class="mt-1" />
                        <InputError :message="form.errors.target_unit" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label>Tipe Periode</Label>
                        <Select v-model="form.period_type">
                            <SelectTrigger class="mt-1 w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="year">Tahunan</SelectItem>
                                <SelectItem value="quarter">Triwulan</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.period_type" />
                    </div>
                    <div v-if="form.period_type === 'quarter'">
                        <Label>Triwulan</Label>
                        <Select :model-value="form.period ? String(form.period) : ''" @update:model-value="(v) => form.period = v ? Number(v) : null">
                            <SelectTrigger class="mt-1 w-full">
                                <SelectValue placeholder="Pilih..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">TW 1</SelectItem>
                                <SelectItem value="2">TW 2</SelectItem>
                                <SelectItem value="3">TW 3</SelectItem>
                                <SelectItem value="4">TW 4</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.period" />
                    </div>
                </div>
                <div>
                    <Label>PIC</Label>
                    <Popover v-model:open="picOpen">
                        <PopoverTrigger as-child>
                            <Button variant="outline" role="combobox" class="mt-1 w-full justify-between font-normal">
                                {{ selectedPicLabel }}
                                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-[--radix-popover-trigger-width] p-0">
                            <Command>
                                <CommandInput placeholder="Cari pegawai..." />
                                <CommandList>
                                    <CommandEmpty>Tidak ada hasil.</CommandEmpty>
                                    <CommandGroup>
                                        <CommandItem value="__none__" @select="() => { form.pic_employee_id = null; picOpen = false }">
                                            — Tidak ada —
                                            <Check v-if="form.pic_employee_id === null" class="ml-auto h-4 w-4" />
                                        </CommandItem>
                                        <CommandItem
                                            v-for="emp in teamEmployees"
                                            :key="emp.id"
                                            :value="emp.display_name || emp.name"
                                            @select="() => { form.pic_employee_id = emp.id; picOpen = false }"
                                        >
                                            {{ emp.display_name || emp.name }}
                                            <Check v-if="form.pic_employee_id === emp.id" class="ml-auto h-4 w-4" />
                                        </CommandItem>
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                    <InputError :message="form.errors.pic_employee_id" />
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" as-child>
                        <a :href="route('performance-plans.index')">Batal</a>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Perbarui</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
```

---

## Task 11: Write IKU controller tests

**Files:**
- Create: `tests/Feature/Http/PerformanceIndicatorControllerTest.php`

```php
<?php

use App\Models\Employee;
use App\Models\PerformanceIndicator;
use App\Models\Team;

it('redirects guests to login for iku index', function () {
    $this->get(route('performance-indicators.index'))->assertRedirect(route('login'));
});

it('renders iku index for admin', function () {
    $this->actingAs(adminUser())
        ->get(route('performance-indicators.index'))
        ->assertInertia(fn ($page) => $page->component('PerformanceIndicators/Index')->has('indicators')->has('teams'));
});

it('renders iku index for staff', function () {
    $this->actingAs(staffUser())
        ->get(route('performance-indicators.index'))
        ->assertInertia(fn ($page) => $page->component('PerformanceIndicators/Index'));
});

it('filters iku by year and team', function () {
    $team = Team::factory()->create();
    PerformanceIndicator::factory()->create(['team_id' => $team->id, 'year' => 2025]);
    PerformanceIndicator::factory()->create(['team_id' => $team->id, 'year' => 2026]);

    $this->actingAs(adminUser())
        ->get(route('performance-indicators.index', ['year' => 2025, 'team_id' => $team->id]))
        ->assertInertia(fn ($page) => $page->component('PerformanceIndicators/Index')->has('indicators', 1));
});

it('renders iku create form for admin', function () {
    $this->actingAs(adminUser())
        ->get(route('performance-indicators.create'))
        ->assertInertia(fn ($page) => $page->component('PerformanceIndicators/Create')->has('teams'));
});

it('denies iku create for staff with no led team', function () {
    $user = staffUser();
    $team = Team::factory()->create();
    Employee::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $this->actingAs($user)
        ->get(route('performance-indicators.create'))
        ->assertForbidden();
});

it('admin can store iku', function () {
    $team = Team::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('performance-indicators.store'), [
            'team_id' => $team->id,
            'year' => 2026,
            'code' => 'IKU-01',
            'name' => 'Jumlah Publikasi',
            'target' => 5,
            'target_unit' => 'Dokumen',
        ])
        ->assertRedirect(route('performance-indicators.index'));

    expect(PerformanceIndicator::where('name', 'Jumlah Publikasi')->exists())->toBeTrue();
});

it('validates required fields on iku store', function () {
    $this->actingAs(adminUser())
        ->post(route('performance-indicators.store'), [])
        ->assertSessionHasErrors(['team_id', 'year', 'name']);
});

it('team lead can store iku for a led team', function () {
    $user = staffUser();
    $teamA = Team::factory()->create(['is_active' => true]);
    $teamB = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id]);
    $teamB->update(['leader_id' => $employee->id]);

    $this->actingAs($user)
        ->post(route('performance-indicators.store'), [
            'team_id' => $teamB->id,
            'year' => 2026,
            'name' => 'IKU Tim B',
        ])
        ->assertRedirect(route('performance-indicators.index'));

    expect(PerformanceIndicator::where('name', 'IKU Tim B')->exists())->toBeTrue();
});

it('team lead cannot store iku for a team they do not lead', function () {
    $user = staffUser();
    $teamA = Team::factory()->create(['is_active' => true]);
    $teamB = Team::factory()->create(['is_active' => true]);
    $teamC = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id]);
    $teamB->update(['leader_id' => $employee->id]);

    $this->actingAs($user)
        ->post(route('performance-indicators.store'), [
            'team_id' => $teamC->id,
            'year' => 2026,
            'name' => 'IKU Tidak Diizinkan',
        ])
        ->assertSessionHasErrors(['team_id']);
});

it('admin can update iku', function () {
    $indicator = PerformanceIndicator::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('performance-indicators.update', $indicator), [
            'team_id' => $indicator->team_id,
            'year' => $indicator->year,
            'name' => 'Updated IKU Name',
        ])
        ->assertRedirect(route('performance-indicators.index'));

    expect($indicator->fresh()->name)->toBe('Updated IKU Name');
});

it('team lead can update iku for their led team', function () {
    $user = staffUser();
    $team = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team->update(['leader_id' => $employee->id]);
    $indicator = PerformanceIndicator::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->put(route('performance-indicators.update', $indicator), [
            'team_id' => $team->id,
            'year' => $indicator->year,
            'name' => 'Updated By Lead',
        ])
        ->assertRedirect(route('performance-indicators.index'));

    expect($indicator->fresh()->name)->toBe('Updated By Lead');
});

it('team lead gets 403 when updating iku for another team', function () {
    $user = staffUser();
    $teamA = Team::factory()->create(['is_active' => true]);
    $teamB = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $teamA->update(['leader_id' => $employee->id]);
    $indicator = PerformanceIndicator::factory()->create(['team_id' => $teamB->id]);

    $this->actingAs($user)
        ->put(route('performance-indicators.update', $indicator), [
            'year' => $indicator->year,
            'name' => 'Unauthorized Update',
        ])
        ->assertForbidden();
});

it('admin can delete iku', function () {
    $indicator = PerformanceIndicator::factory()->create();

    $this->actingAs(adminUser())
        ->delete(route('performance-indicators.destroy', $indicator))
        ->assertRedirect(route('performance-indicators.index'));

    expect(PerformanceIndicator::find($indicator->id))->toBeNull();
});

it('team lead can delete iku for their led team', function () {
    $user = staffUser();
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team->update(['leader_id' => $employee->id]);
    $indicator = PerformanceIndicator::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->delete(route('performance-indicators.destroy', $indicator))
        ->assertRedirect(route('performance-indicators.index'));

    expect(PerformanceIndicator::find($indicator->id))->toBeNull();
});

it('team lead gets 403 when deleting iku for another team', function () {
    $user = staffUser();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $teamA->update(['leader_id' => $employee->id]);
    $indicator = PerformanceIndicator::factory()->create(['team_id' => $teamB->id]);

    $this->actingAs($user)
        ->delete(route('performance-indicators.destroy', $indicator))
        ->assertForbidden();
});
```

**Step 2: Run the tests**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && ./vendor/bin/pest tests/Feature/Http/PerformanceIndicatorControllerTest.php
```

Expected: all pass.

---

## Task 12: Write RK controller tests

**Files:**
- Create: `tests/Feature/Http/PerformancePlanControllerTest.php`

```php
<?php

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;

it('redirects guests to login for rk index', function () {
    $this->get(route('performance-plans.index'))->assertRedirect(route('login'));
});

it('renders rk index for admin', function () {
    $this->actingAs(adminUser())
        ->get(route('performance-plans.index'))
        ->assertInertia(fn ($page) => $page->component('PerformancePlans/Index')->has('plans')->has('projects'));
});

it('filters rk by project_id', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    PerformancePlan::factory()->create(['project_id' => $projectA->id]);
    PerformancePlan::factory()->create(['project_id' => $projectB->id]);

    $this->actingAs(adminUser())
        ->get(route('performance-plans.index', ['project_id' => $projectA->id]))
        ->assertInertia(fn ($page) => $page->component('PerformancePlans/Index')->has('plans', 1));
});

it('renders rk create form for admin', function () {
    $this->actingAs(adminUser())
        ->get(route('performance-plans.create'))
        ->assertInertia(fn ($page) => $page->component('PerformancePlans/Create')->has('projects')->has('employees'));
});

it('admin can store rk', function () {
    $project = Project::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('performance-plans.store'), [
            'project_id' => $project->id,
            'description' => 'Penyusunan Publikasi',
            'period_type' => 'year',
        ])
        ->assertRedirect(route('performance-plans.index'));

    expect(PerformancePlan::where('description', 'Penyusunan Publikasi')->exists())->toBeTrue();
});

it('validates required fields on rk store', function () {
    $this->actingAs(adminUser())
        ->post(route('performance-plans.store'), [])
        ->assertSessionHasErrors(['project_id', 'description', 'period_type']);
});

it('team lead can store rk for a project in their led team', function () {
    $user = staffUser();
    $team = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team->update(['leader_id' => $employee->id]);
    $project = Project::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->post(route('performance-plans.store'), [
            'project_id' => $project->id,
            'description' => 'RK Tim Lead',
            'period_type' => 'year',
        ])
        ->assertRedirect(route('performance-plans.index'));

    expect(PerformancePlan::where('description', 'RK Tim Lead')->exists())->toBeTrue();
});

it('team lead cannot store rk for a project outside their led teams', function () {
    $user = staffUser();
    $teamA = Team::factory()->create(['is_active' => true]);
    $teamB = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $teamA->update(['leader_id' => $employee->id]);
    $projectB = Project::factory()->create(['team_id' => $teamB->id]);

    $this->actingAs($user)
        ->post(route('performance-plans.store'), [
            'project_id' => $projectB->id,
            'description' => 'RK Tidak Diizinkan',
            'period_type' => 'year',
        ])
        ->assertSessionHasErrors(['project_id']);
});

it('admin can update rk', function () {
    $plan = PerformancePlan::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('performance-plans.update', $plan), [
            'project_id' => $plan->project_id,
            'description' => 'Updated RK',
            'period_type' => 'year',
        ])
        ->assertRedirect(route('performance-plans.index'));

    expect($plan->fresh()->description)->toBe('Updated RK');
});

it('team lead can update rk for their led teams project', function () {
    $user = staffUser();
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team->update(['leader_id' => $employee->id]);
    $project = Project::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->put(route('performance-plans.update', $plan), [
            'description' => 'Updated By Lead',
            'period_type' => 'year',
        ])
        ->assertRedirect(route('performance-plans.index'));

    expect($plan->fresh()->description)->toBe('Updated By Lead');
});

it('team lead gets 403 when updating rk for another teams project', function () {
    $user = staffUser();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $teamA->update(['leader_id' => $employee->id]);
    $projectB = Project::factory()->create(['team_id' => $teamB->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $projectB->id]);

    $this->actingAs($user)
        ->put(route('performance-plans.update', $plan), [
            'description' => 'Unauthorized',
            'period_type' => 'year',
        ])
        ->assertForbidden();
});

it('admin can delete rk', function () {
    $plan = PerformancePlan::factory()->create();

    $this->actingAs(adminUser())
        ->delete(route('performance-plans.destroy', $plan))
        ->assertRedirect(route('performance-plans.index'));

    expect(PerformancePlan::find($plan->id))->toBeNull();
});

it('team lead can delete rk for their led teams project', function () {
    $user = staffUser();
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team->update(['leader_id' => $employee->id]);
    $project = Project::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->delete(route('performance-plans.destroy', $plan))
        ->assertRedirect(route('performance-plans.index'));

    expect(PerformancePlan::find($plan->id))->toBeNull();
});

it('team lead gets 403 when deleting rk for another teams project', function () {
    $user = staffUser();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $teamA->update(['leader_id' => $employee->id]);
    $projectB = Project::factory()->create(['team_id' => $teamB->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $projectB->id]);

    $this->actingAs($user)
        ->delete(route('performance-plans.destroy', $plan))
        ->assertForbidden();
});
```

**Step 2: Run the tests**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && ./vendor/bin/pest tests/Feature/Http/PerformancePlanControllerTest.php
```

Expected: all pass.

---

## Task 13: Run full test suite and linters

**Step 1: Full test suite**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && php artisan test
```

Expected: all tests pass (was 203 on develop; new tests add ~26 more).

**Step 2: Pint**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && ./vendor/bin/pint --dirty
```

Expected: no changes needed (all files already formatted) or auto-fixed with no remaining issues.

**Step 3: TypeScript check**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && npm run typecheck
```

Expected: no new errors.

---

## Execution order notes

1. Tasks 1–3 (types + policies) have no dependencies on each other — do in parallel.
2. Task 4 (IKU controller) depends on Task 3 (policy file must exist before `Gate::policy` is valid at runtime, but the controller file itself can be written before).
3. Task 5 (RK controller) depends on Task 3.
4. Task 6 (routes) depends on Tasks 4 and 5.
5. Task 7 (middleware `can` flags) can be done anytime after Task 3.
6. Task 8 (nav) depends on Task 7.
7. Tasks 9–10 (Vue pages) depend on Task 1 (types) and Task 6 (routes for `route()` calls).
8. Tasks 11–12 (tests) depend on Tasks 4–6.
9. Task 13 (verify) must be last.

**Key integration point**: `AppServiceProvider::boot()` in Task 2 must register both policies; write the two `Gate::policy()` calls together after both policy files exist (Tasks 2+3).
