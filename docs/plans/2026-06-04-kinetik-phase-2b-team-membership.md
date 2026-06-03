# Kinetik Phase 2b: Team Membership Management Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a many-to-many team membership management UI so admins (and team leads for their own team) can add/remove employees from teams and set each member's role (member/leader) and is_primary flag.

**Architecture:** A dedicated `TeamMemberController` handles `editMembers` (GET) and `updateMembers` (PUT) on nested route `teams/{team}/members`. A `SyncTeamMembersAction` mirrors `SyncProjectMembersAction` but preserves `started_at` for existing pivot rows and sets it to `now()` for new ones. A dedicated `Teams/Members.vue` page reuses the searchable combobox + chip-list-with-role pattern from `Projects/Edit.vue`. `TeamPolicy` gains a `manageMembers` gate. `Teams/Index.vue` gets a "Kelola Anggota" button per row (visible to admin or the team's own lead).

**Tech Stack:** Laravel 11, Inertia.js v2, Vue 3 `<script setup lang="ts">`, Pest v3, Pint, vue-tsc.

---

## Context You Must Know

### Key files (already read — do NOT re-read unless you need a detail not here)

| File | Role |
|------|------|
| `app/Actions/Projects/SyncProjectMembersAction.php` | Pattern to mirror — but team pivot has `is_primary` + `started_at` extra columns |
| `app/Http/Controllers/TeamController.php` | Extend by adding `editMembers`/`updateMembers` — OR create separate `TeamMemberController` (plan uses separate controller) |
| `app/Policies/TeamPolicy.php` | Already exists; need to add `manageMembers` method |
| `app/Providers/AppServiceProvider.php` | Policy registration is done here via `Gate::policy()` — but `TeamPolicy` is already auto-discovered (Laravel convention); check if manual registration needed |
| `routes/web.php` | Add 2 new named routes inside the auth group |
| `resources/js/Pages/Teams/Index.vue` | Add "Kelola Anggota" button per row |
| `resources/js/Pages/Teams/Edit.vue` | No change needed |
| `resources/js/Pages/Projects/Edit.vue` | Reference for member picker + role toggle UX |
| `resources/js/types/index.d.ts` | Add `TeamMemberPivot`, extend `Team` with `members_count` |
| `tests/Feature/Http/TeamControllerTest.php` | Mirror this style for the new test file |
| `tests/Pest.php` | `adminUser()`, `staffUser()` helpers available globally |

### Pivot columns on `employee_team`
```
employee_id, team_id, role (member|leader), is_primary (bool),
started_at (date nullable), ended_at (date nullable), timestamps
```

### Authorization rules
- `manageMembers(User $user, Team $team)`:
  - `$user->hasPermissionTo('manage-teams')` → true
  - OR: user's employee is the team's `leader_id` → true
  - else false

### started_at preservation strategy
`sync()` detaches+reattaches all rows. We must use a custom merge:
1. Load existing pivot rows keyed by `employee_id` → `['started_at' => ...]`
2. For each entry in the new memberMap: if `employee_id` was already a member, carry over its `started_at`; else set `now()->toDateString()`
3. Call `$team->members()->sync($enrichedMap)`

### leader_id sync strategy (keep it simple)
After syncing the pivot, if exactly one member in the submitted list has `role === 'leader'`, update `teams.leader_id` to that employee's id. If zero or many leaders submitted, leave `teams.leader_id` unchanged. Document this in the action. This mirrors how `SyncProjectLeaderRole` listener works for projects.

---

## Task 1: Add `manageMembers` to `TeamPolicy`

**Files:**
- Modify: `app/Policies/TeamPolicy.php`

**Step 1: Add the method**

Open `app/Policies/TeamPolicy.php` and add after the `update` method:

```php
public function manageMembers(User $user, Team $team): bool
{
    if ($user->hasPermissionTo('manage-teams')) {
        return true;
    }

    $employee = $user->employee;
    if ($employee === null) {
        return false;
    }

    return $team->leader_id === $employee->id;
}
```

**Step 2: Verify no registration needed**

Laravel auto-discovers `TeamPolicy` via model convention (`Team` → `TeamPolicy`). No `Gate::policy()` call needed (unlike `PerformanceReport` which is manual). Confirm by grepping:

```bash
grep -r "TeamPolicy" /Users/ryanaidilp/Documents/Projects/Web/performance_matrix/app/Providers/
```

Expected: no results (auto-discovered).

**Step 3: Run pint on the file**

```bash
./vendor/bin/pint app/Policies/TeamPolicy.php
```

---

## Task 2: Create `SyncTeamMembersAction`

**Files:**
- Create: `app/Actions/Teams/SyncTeamMembersAction.php`

**Step 1: Write the failing test**

Create `tests/Feature/Actions/SyncTeamMembersActionTest.php`:

```php
<?php

use App\Actions\Teams\SyncTeamMembersAction;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

it('attaches new members with started_at set to today', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();

    $action = new SyncTeamMembersAction;
    $action->execute($team, [
        $emp->id => ['role' => 'member', 'is_primary' => false],
    ]);

    $pivot = DB::table('employee_team')
        ->where('team_id', $team->id)
        ->where('employee_id', $emp->id)
        ->first();

    expect($pivot)->not->toBeNull();
    expect($pivot->role)->toBe('member');
    expect($pivot->started_at)->toBe(now()->toDateString());
});

it('preserves started_at for existing members on re-sync', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();
    $originalDate = '2025-01-15';

    $team->members()->attach($emp->id, [
        'role' => 'member',
        'is_primary' => false,
        'started_at' => $originalDate,
    ]);

    $action = new SyncTeamMembersAction;
    $action->execute($team, [
        $emp->id => ['role' => 'leader', 'is_primary' => false],
    ]);

    $pivot = DB::table('employee_team')
        ->where('team_id', $team->id)
        ->where('employee_id', $emp->id)
        ->first();

    expect($pivot->started_at)->toBe($originalDate);
    expect($pivot->role)->toBe('leader');
});

it('removes members not in the new map', function () {
    $team = Team::factory()->create();
    $empA = Employee::factory()->create();
    $empB = Employee::factory()->create();

    $team->members()->attach($empA->id, ['role' => 'member', 'is_primary' => false]);
    $team->members()->attach($empB->id, ['role' => 'member', 'is_primary' => false]);

    $action = new SyncTeamMembersAction;
    $action->execute($team, [
        $empA->id => ['role' => 'member', 'is_primary' => false],
    ]);

    expect($team->members()->count())->toBe(1);
    expect($team->members()->where('employees.id', $empA->id)->exists())->toBeTrue();
    expect($team->members()->where('employees.id', $empB->id)->exists())->toBeFalse();
});

it('updates leader_id when exactly one leader submitted', function () {
    $team = Team::factory()->create(['leader_id' => null]);
    $emp = Employee::factory()->create();

    $action = new SyncTeamMembersAction;
    $action->execute($team, [
        $emp->id => ['role' => 'leader', 'is_primary' => false],
    ]);

    expect($team->fresh()->leader_id)->toBe($emp->id);
});

it('does not change leader_id when zero leaders submitted', function () {
    $team = Team::factory()->create();
    $leader = Employee::factory()->create();
    $team->update(['leader_id' => $leader->id]);

    $member = Employee::factory()->create();

    $action = new SyncTeamMembersAction;
    $action->execute($team, [
        $member->id => ['role' => 'member', 'is_primary' => false],
    ]);

    expect($team->fresh()->leader_id)->toBe($leader->id);
});
```

**Step 2: Run tests to confirm they fail**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && php artisan test tests/Feature/Actions/SyncTeamMembersActionTest.php 2>&1 | tail -20
```

Expected: errors about class not found.

**Step 3: Create the action**

Create `app/Actions/Teams/SyncTeamMembersAction.php`:

```php
<?php

namespace App\Actions\Teams;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

class SyncTeamMembersAction
{
    /**
     * Sync team members, preserving started_at for existing rows.
     *
     * leader_id update rule: if exactly one member in $memberMap has role='leader',
     * update teams.leader_id to that employee. Otherwise leave it unchanged.
     * This keeps the canonical leader_id consistent without silently clearing it.
     *
     * @param  array<int, array{role: string, is_primary: bool}>  $memberMap  keyed by employee_id
     */
    public function execute(Team $team, array $memberMap): void
    {
        // Load existing started_at values so we can preserve them.
        $existing = DB::table('employee_team')
            ->where('team_id', $team->id)
            ->whereIn('employee_id', array_keys($memberMap))
            ->pluck('started_at', 'employee_id');

        $today = now()->toDateString();

        $enriched = [];
        foreach ($memberMap as $employeeId => $pivotData) {
            $enriched[$employeeId] = array_merge($pivotData, [
                'started_at' => $existing[$employeeId] ?? $today,
            ]);
        }

        $team->members()->sync($enriched);
        $team->refresh();

        // Update leader_id if exactly one leader is present in the submitted set.
        $leaders = array_filter($memberMap, fn ($p) => ($p['role'] ?? '') === 'leader');
        if (count($leaders) === 1) {
            $team->update(['leader_id' => array_key_first($leaders)]);
        }
    }
}
```

**Step 4: Run tests**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && php artisan test tests/Feature/Actions/SyncTeamMembersActionTest.php 2>&1 | tail -20
```

Expected: all 5 pass.

**Step 5: Run pint**

```bash
./vendor/bin/pint app/Actions/Teams/SyncTeamMembersAction.php tests/Feature/Actions/SyncTeamMembersActionTest.php
```

---

## Task 3: Create `TeamMemberController`

**Files:**
- Create: `app/Http/Controllers/TeamMemberController.php`
- Create: `app/Http/Requests/UpdateTeamMembersRequest.php`

**Step 1: Create the FormRequest**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && php artisan make:request UpdateTeamMembersRequest
```

Edit `app/Http/Requests/UpdateTeamMembersRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\Team $team */
        $team = $this->route('team');

        return $this->user()->can('manageMembers', $team);
    }

    public function rules(): array
    {
        return [
            'members'              => ['array'],
            'members.*.employee_id' => ['required', 'exists:employees,id'],
            'members.*.role'        => ['required', 'in:member,leader'],
            'members.*.is_primary'  => ['boolean'],
        ];
    }
}
```

**Step 2: Create the controller**

Create `app/Http/Controllers/TeamMemberController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Actions\Teams\SyncTeamMembersAction;
use App\Http\Requests\UpdateTeamMembersRequest;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TeamMemberController extends Controller
{
    public function edit(Team $team): Response
    {
        $this->authorize('manageMembers', $team);

        $team->load('members:id,name,display_name');

        $employees = Employee::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'display_name']);

        return Inertia::render('Teams/Members', compact('team', 'employees'));
    }

    public function update(UpdateTeamMembersRequest $request, Team $team, SyncTeamMembersAction $syncMembers): RedirectResponse
    {
        $memberMap = collect($request->validated()['members'] ?? [])
            ->keyBy('employee_id')
            ->map(fn ($m) => [
                'role'       => $m['role'],
                'is_primary' => (bool) ($m['is_primary'] ?? false),
            ])
            ->all();

        $syncMembers->execute($team, $memberMap);

        return redirect()->route('teams.index')->with('success', 'Anggota tim berhasil diperbarui.');
    }
}
```

**Step 3: Register routes in `routes/web.php`**

Inside the `auth` middleware group, after the `teams` resource line, add:

```php
Route::get('/teams/{team}/members', [TeamMemberController::class, 'edit'])->name('teams.members.edit');
Route::put('/teams/{team}/members', [TeamMemberController::class, 'update'])->name('teams.members.update');
```

Also add the import at the top of `routes/web.php`:

```php
use App\Http\Controllers\TeamMemberController;
```

**Step 4: Run pint**

```bash
./vendor/bin/pint app/Http/Controllers/TeamMemberController.php app/Http/Requests/UpdateTeamMembersRequest.php routes/web.php
```

---

## Task 4: Write feature tests for `TeamMemberController`

**Files:**
- Create: `tests/Feature/Http/TeamMemberControllerTest.php`

**Step 1: Write the tests**

```php
<?php

use App\Models\Employee;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

// ── GET /teams/{team}/members ─────────────────────────────────────────────

it('admin can access member management page', function () {
    $team = Team::factory()->create();

    $this->actingAs(adminUser())
        ->get(route('teams.members.edit', $team))
        ->assertInertia(fn ($page) => $page
            ->component('Teams/Members')
            ->has('team')
            ->has('employees')
        );
});

it('team lead can access their own team member management page', function () {
    $user = staffUser();
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    $team->update(['leader_id' => $employee->id]);

    $this->actingAs($user)
        ->get(route('teams.members.edit', $team))
        ->assertInertia(fn ($page) => $page->component('Teams/Members'));
});

it('team lead cannot access another team member management page', function () {
    $user = staffUser();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    $teamA->update(['leader_id' => $employee->id]);

    $this->actingAs($user)
        ->get(route('teams.members.edit', $teamB))
        ->assertForbidden();
});

it('plain staff member cannot access member management page', function () {
    $team = Team::factory()->create();

    $this->actingAs(staffUser())
        ->get(route('teams.members.edit', $team))
        ->assertForbidden();
});

// ── PUT /teams/{team}/members ─────────────────────────────────────────────

it('admin can add and set roles for team members', function () {
    $team = Team::factory()->create();
    $empA = Employee::factory()->create();
    $empB = Employee::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $empA->id, 'role' => 'leader', 'is_primary' => false],
                ['employee_id' => $empB->id, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertRedirect(route('teams.index'));

    expect($team->members()->count())->toBe(2);
    expect($team->members()->where('employees.id', $empA->id)->wherePivot('role', 'leader')->exists())->toBeTrue();
    expect($team->members()->where('employees.id', $empB->id)->wherePivot('role', 'member')->exists())->toBeTrue();
});

it('admin can remove a team member', function () {
    $team = Team::factory()->create();
    $empA = Employee::factory()->create();
    $empB = Employee::factory()->create();

    $team->members()->attach($empA->id, ['role' => 'member', 'is_primary' => false]);
    $team->members()->attach($empB->id, ['role' => 'member', 'is_primary' => false]);

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $empA->id, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertRedirect(route('teams.index'));

    expect($team->members()->count())->toBe(1);
    expect($team->members()->where('employees.id', $empB->id)->exists())->toBeFalse();
});

it('pivot reflects is_primary correctly', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $emp->id, 'role' => 'member', 'is_primary' => true],
            ],
        ])
        ->assertRedirect();

    $pivot = DB::table('employee_team')
        ->where('team_id', $team->id)
        ->where('employee_id', $emp->id)
        ->first();

    expect((bool) $pivot->is_primary)->toBeTrue();
});

it('team lead can manage members of their own team', function () {
    $user = staffUser();
    $team = Team::factory()->create();
    $leader = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    $team->update(['leader_id' => $leader->id]);

    $member = Employee::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $leader->id, 'role' => 'leader', 'is_primary' => false],
                ['employee_id' => $member->id, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertRedirect(route('teams.index'));

    expect($team->members()->count())->toBe(2);
});

it('team lead gets 403 when managing another team', function () {
    $user = staffUser();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $leader = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    $teamA->update(['leader_id' => $leader->id]);

    $member = Employee::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->put(route('teams.members.update', $teamB), [
            'members' => [
                ['employee_id' => $member->id, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertForbidden();
});

it('plain staff member is forbidden from updating team members', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();

    $this->actingAs(staffUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $emp->id, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertForbidden();
});

it('rejects invalid role value', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $emp->id, 'role' => 'superstar', 'is_primary' => false],
            ],
        ])
        ->assertSessionHasErrors(['members.0.role']);
});

it('rejects non-existent employee_id', function () {
    $team = Team::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => 999999, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertSessionHasErrors(['members.0.employee_id']);
});

it('idempotent sync does not duplicate rows', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();

    $team->members()->attach($emp->id, [
        'role' => 'member',
        'is_primary' => false,
        'started_at' => '2025-03-01',
    ]);

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $emp->id, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertRedirect();

    expect($team->members()->count())->toBe(1);
});

it('preserves started_at on idempotent re-sync', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();
    $originalDate = '2025-03-01';

    $team->members()->attach($emp->id, [
        'role' => 'member',
        'is_primary' => false,
        'started_at' => $originalDate,
    ]);

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $emp->id, 'role' => 'leader', 'is_primary' => false],
            ],
        ])
        ->assertRedirect();

    $pivot = DB::table('employee_team')
        ->where('team_id', $team->id)
        ->where('employee_id', $emp->id)
        ->first();

    expect($pivot->started_at)->toBe($originalDate);
});
```

**Step 2: Run the tests (expect failures — controller/routes not wired yet from previous task)**

After completing Task 3, run:

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && php artisan test tests/Feature/Http/TeamMemberControllerTest.php 2>&1 | tail -30
```

Expected: all pass after Task 3 is complete.

**Step 3: Run pint**

```bash
./vendor/bin/pint tests/Feature/Http/TeamMemberControllerTest.php
```

---

## Task 5: Create `Teams/Members.vue`

**Files:**
- Create: `resources/js/Pages/Teams/Members.vue`
- Modify: `resources/js/types/index.d.ts` (add `TeamMemberWithPivot`)

**Step 1: Add type to `resources/js/types/index.d.ts`**

Add after the `TeamMember` interface (around line 135):

```typescript
export interface TeamMemberPivot {
    role: 'member' | 'leader';
    is_primary: boolean;
    started_at?: string | null;
    ended_at?: string | null;
}

export interface TeamMemberWithPivot extends Employee {
    pivot: TeamMemberPivot;
}
```

Also extend `Team` with an optional `members` array — update the existing `Team` interface:

```typescript
export interface Team {
    id: number;
    name: string;
    code: string;
    description?: string | null;
    is_active: boolean;
    leader_id?: number | null;
    members?: TeamMemberWithPivot[];
}
```

**Step 2: Create the Vue page**

Create `resources/js/Pages/Teams/Members.vue`:

```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { Employee, Team, TeamMemberWithPivot } from '@/types';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Check, ChevronsUpDown, X } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { computed, ref } from 'vue';

type MemberRow = { employee_id: number; role: 'member' | 'leader'; is_primary: boolean };

const props = defineProps<{
    team: Team & { members?: TeamMemberWithPivot[] };
    employees: Employee[];
}>();

const initialMembers = computed<MemberRow[]>(() =>
    (props.team.members ?? []).map((m) => ({
        employee_id: m.id,
        role: (m.pivot?.role ?? 'member') as 'member' | 'leader',
        is_primary: Boolean(m.pivot?.is_primary),
    }))
);

const form = useForm<{ members: MemberRow[] }>({
    members: initialMembers.value,
});

// ── Employee picker ───────────────────────────────────────────────────────

const pickerOpen = ref(false);

const selectedIds = computed(() => new Set(form.members.map((m) => m.employee_id)));

const availableEmployees = computed(() =>
    props.employees.filter((e) => !selectedIds.value.has(e.id))
);

function addMember(employee: Employee) {
    form.members.push({ employee_id: employee.id, role: 'member', is_primary: false });
    pickerOpen.value = false;
}

function removeMember(index: number) {
    form.members.splice(index, 1);
}

function employeeName(id: number): string {
    const emp = props.employees.find((e) => e.id === id);
    return emp ? (emp.display_name || emp.name) : `#${id}`;
}

function submit() {
    form.put(route('teams.members.update', props.team.id));
}
</script>

<template>
    <Head :title="`Anggota Tim — ${team.name}`" />
    <AppLayout>
        <template #title>Kelola Anggota: {{ team.name }}</template>

        <div class="max-w-2xl space-y-6">
            <div class="rounded-md border bg-white p-6">
                <form @submit.prevent="submit" class="space-y-4">

                    <!-- Member picker -->
                    <div>
                        <Label>Tambah Anggota</Label>
                        <Popover v-model:open="pickerOpen">
                            <PopoverTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    role="combobox"
                                    class="mt-1 w-full justify-between font-normal"
                                >
                                    Pilih pegawai...
                                    <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-[--radix-popover-trigger-width] p-0">
                                <Command>
                                    <CommandInput placeholder="Cari pegawai..." />
                                    <CommandList>
                                        <CommandEmpty>Tidak ada pegawai tersedia.</CommandEmpty>
                                        <CommandGroup>
                                            <CommandItem
                                                v-for="emp in availableEmployees"
                                                :key="emp.id"
                                                :value="emp.display_name || emp.name"
                                                @select="() => addMember(emp)"
                                            >
                                                {{ emp.display_name || emp.name }}
                                                <Check
                                                    v-if="selectedIds.has(emp.id)"
                                                    class="ml-auto h-4 w-4"
                                                />
                                            </CommandItem>
                                        </CommandGroup>
                                    </CommandList>
                                </Command>
                            </PopoverContent>
                        </Popover>
                    </div>

                    <!-- Member list -->
                    <div v-if="form.members.length" class="space-y-2">
                        <Label>Daftar Anggota</Label>
                        <div
                            v-for="(member, index) in form.members"
                            :key="member.employee_id"
                            class="flex items-center gap-3 rounded-md border bg-gray-50 px-3 py-2"
                        >
                            <!-- Avatar initial -->
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                {{ employeeName(member.employee_id).charAt(0).toUpperCase() }}
                            </div>

                            <!-- Name -->
                            <span class="min-w-0 flex-1 truncate text-sm">
                                {{ employeeName(member.employee_id) }}
                            </span>

                            <!-- Role select -->
                            <Select v-model="member.role">
                                <SelectTrigger class="h-7 w-32 text-xs">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="member">Anggota</SelectItem>
                                    <SelectItem value="leader">Ketua Tim</SelectItem>
                                </SelectContent>
                            </Select>

                            <!-- Remove -->
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7 shrink-0 text-red-500 hover:bg-red-50 hover:text-red-600"
                                @click="removeMember(index)"
                            >
                                <X class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                        <InputError :message="form.errors.members" />
                    </div>

                    <p v-else class="text-sm text-gray-400">Belum ada anggota. Pilih pegawai di atas untuk menambahkan.</p>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="outline" as-child>
                            <a :href="route('teams.index')">Batal</a>
                        </Button>
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
```

**Step 3: Run typecheck**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && npm run typecheck 2>&1 | tail -30
```

Expected: no new errors.

---

## Task 6: Add "Kelola Anggota" to `Teams/Index.vue`

**Files:**
- Modify: `resources/js/Pages/Teams/Index.vue`

**Step 1: Update props**

The `Index` page currently only receives `teams: Team[]`. We need to also know if the current user can manage members of each team. Pass `canManage` as a boolean flag for admin, plus `authEmployeeId` for the lead check, OR compute `manageable` as a set of team IDs on the backend and pass it.

Simplest approach: pass `manageableTeamIds: number[]` from the controller — IDs the current user can manage members for.

Update `TeamController::index()` to compute `manageableTeamIds`:

```php
public function index(Request $request): Response
{
    $this->authorize('viewAny', Team::class);

    $user = $request->user();
    $isAdmin = $user->hasPermissionTo('manage-teams');

    $teams = Team::orderBy('name')->get(['id', 'name', 'code', 'is_active', 'leader_id']);

    $manageableTeamIds = $isAdmin
        ? $teams->pluck('id')->all()
        : ($user->employee
            ? $teams->where('leader_id', $user->employee->id)->pluck('id')->all()
            : []);

    return Inertia::render('Teams/Index', compact('teams', 'manageableTeamIds'));
}
```

**Step 2: Update `Teams/Index.vue`**

Update `defineProps` to include `manageableTeamIds: number[]`.

In the table, add a "Kelola Anggota" button next to Edit for teams in `manageableTeamIds`:

```vue
<script setup lang="ts">
// ... existing imports, add Link if not present
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ref } from 'vue';

const props = defineProps<{
    teams: Team[];
    manageableTeamIds: number[];
}>();

const manageableSet = new Set(props.manageableTeamIds);

// ... rest unchanged
```

In the template action cell, add button before Edit:

```vue
<TableCell class="text-right">
    <div class="inline-flex gap-2">
        <Button v-if="manageableSet.has(team.id)" variant="outline" size="sm" as-child>
            <Link :href="route('teams.members.edit', team.id)">Kelola Anggota</Link>
        </Button>
        <Button variant="outline" size="sm" as-child>
            <Link :href="route('teams.edit', team.id)">Edit</Link>
        </Button>
        <Button variant="destructive" size="sm" @click="confirmDelete(team.id, team.name)">
            Hapus
        </Button>
    </div>
</TableCell>
```

**Step 3: Update existing `TeamControllerTest.php`** to account for the new `manageableTeamIds` prop:

```php
it('renders index for admin', function () {
    $this->actingAs(adminUser())
        ->get(route('teams.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Teams/Index')
            ->has('teams')
            ->has('manageableTeamIds')
        );
});
```

**Step 4: Run typecheck**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && npm run typecheck 2>&1 | tail -30
```

Expected: no new errors.

**Step 5: Run pint**

```bash
./vendor/bin/pint app/Http/Controllers/TeamController.php resources/js/Pages/Teams/Index.vue 2>/dev/null || ./vendor/bin/pint app/Http/Controllers/TeamController.php
```

Note: pint only processes PHP files; run it only on PHP files.

---

## Task 7: Full test suite + lint pass

**Step 1: Run full test suite**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && php artisan test 2>&1 | tail -30
```

Expected: all pass (was 233 + Phase-1 tests; new tests add ~17 more).

**Step 2: Run pint on all dirty files**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && ./vendor/bin/pint --dirty
```

Expected: no style issues.

**Step 3: Run TypeScript typecheck**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && npm run typecheck 2>&1 | tail -30
```

Expected: no new errors.

**Step 4: Sync codegraph index**

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix && codegraph sync
```

---

## Summary of Files Changed

| Action | File |
|--------|------|
| Modify | `app/Policies/TeamPolicy.php` |
| Create | `app/Actions/Teams/SyncTeamMembersAction.php` |
| Create | `app/Http/Controllers/TeamMemberController.php` |
| Create | `app/Http/Requests/UpdateTeamMembersRequest.php` |
| Modify | `app/Http/Controllers/TeamController.php` (index method only) |
| Modify | `routes/web.php` |
| Create | `resources/js/Pages/Teams/Members.vue` |
| Modify | `resources/js/Pages/Teams/Index.vue` |
| Modify | `resources/js/types/index.d.ts` |
| Create | `tests/Feature/Actions/SyncTeamMembersActionTest.php` |
| Create | `tests/Feature/Http/TeamMemberControllerTest.php` |
| Modify | `tests/Feature/Http/TeamControllerTest.php` (add manageableTeamIds assertion) |

## Routes Added

| Method | URI | Name | Auth |
|--------|-----|------|------|
| GET | `/teams/{team}/members` | `teams.members.edit` | `manageMembers` policy |
| PUT | `/teams/{team}/members` | `teams.members.update` | `manageMembers` policy |
