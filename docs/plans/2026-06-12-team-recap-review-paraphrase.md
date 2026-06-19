# Team Recap: PJ Review + Paraphrase Screen

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transform the three team-recap views (weekly/monthly/quarterly) into a PJ review + paraphrase screen — a dense sortable table of read-only numbers, per-row confirmation toggle, and an expandable panel for the PJ to paraphrase Kendala/Solusi/RTL per RK row.

**Architecture:** New migration adds `week_start`, `confirmed_at`, `confirmed_by` to `recap_overrides`. `RecapAggregator` exposes `pj_obstacle/pj_solution/pj_follow_up_plan`, `is_confirmed`, `confirmed_by` per row. `TeamRecapController` gains a `confirmOverride` method + route. All three Vue pages are rewritten with a shadcn Table, sortable Capaian, Konfirmasi checkbox, and expand-row paraphrase panel.

**Tech Stack:** Laravel 11 (PHP), Inertia.js + Vue 3 (Composition API + `<script setup>`), shadcn-vue Table/Button/Textarea/Select/Checkbox, TypeScript, Pest tests.

---

## Task 1: Migration — add week_start + confirm fields to recap_overrides

**Files:**
- Create: `database/migrations/2026_06_12_000100_add_confirm_fields_to_recap_overrides.php`

### Step 1: Write the migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recap_overrides', function (Blueprint $table) {
            $table->date('week_start')->nullable()->after('period_month');
            $table->timestamp('confirmed_at')->nullable()->after('created_by');
            $table->foreignId('confirmed_by')
                ->nullable()
                ->after('confirmed_at')
                ->constrained('employees')
                ->nullOnDelete();

            $table->index(['team_id', 'period_type', 'period_year', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::table('recap_overrides', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by']);
            $table->dropIndex(['team_id', 'period_type', 'period_year', 'week_start']);
            $table->dropColumn(['week_start', 'confirmed_at', 'confirmed_by']);
        });
    }
};
```

### Step 2: Run the migration

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix
php artisan migrate
```

Expected: "Migrating: 2026_06_12_000100_add_confirm_fields_to_recap_overrides … Migrated"

---

## Task 2: Update RecapOverride model

**Files:**
- Modify: `app/Models/RecapOverride.php`

### Step 1: Add new fillable + casts + confirmedBy relation

Add `week_start`, `confirmed_at`, `confirmed_by` to `$fillable`. Add to `$casts`:
- `'week_start' => 'date'`
- `'confirmed_at' => 'datetime'`

Add relation:

```php
public function confirmedBy(): BelongsTo
{
    return $this->belongsTo(Employee::class, 'confirmed_by');
}
```

The full updated model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecapOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'performance_plan_id',
        'period_type',
        'period_year',
        'period_quarter',
        'period_month',
        'week_start',
        'obstacle',
        'solution',
        'follow_up_plan',
        'follow_up_evidence_url',
        'follow_up_pic_employee_id',
        'follow_up_deadline',
        'created_by',
        'confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_quarter' => 'integer',
        'period_month' => 'integer',
        'week_start' => 'date',
        'confirmed_at' => 'datetime',
        'follow_up_deadline' => 'date',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function performancePlan(): BelongsTo
    {
        return $this->belongsTo(PerformancePlan::class);
    }

    public function followUpPic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'follow_up_pic_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'confirmed_by');
    }
}
```

### Step 2: Verify no LSP errors

```bash
./vendor/bin/pint app/Models/RecapOverride.php && echo OK
```

---

## Task 3: Update RecapAggregator

**Files:**
- Modify: `app/Services/Kinetik/RecapAggregator.php`

Three changes:

### Step 1: Update `overrides()` — add week path + eager-load confirmedBy

The current signature: `private function overrides(Team $team, string $periodType, int $year, ?int $month = null, ?int $quarter = null): Collection`

Add a `?string $weekStart = null` parameter and a `when` clause for the week path. Also add `confirmedBy` to eager loads.

```php
private function overrides(
    Team $team,
    string $periodType,
    int $year,
    ?int $month = null,
    ?int $quarter = null,
    ?string $weekStart = null,
): Collection {
    return RecapOverride::query()
        ->with(['followUpPic', 'confirmedBy'])
        ->where('team_id', $team->id)
        ->where('period_type', $periodType)
        ->where('period_year', $year)
        ->when($month !== null, fn (Builder $q) => $q->where('period_month', $month))
        ->when($quarter !== null, fn (Builder $q) => $q->where('period_quarter', $quarter))
        ->when($weekStart !== null, fn (Builder $q) => $q->whereDate('week_start', $weekStart))
        ->get()
        ->keyBy('performance_plan_id');
}
```

### Step 2: Update `weekly()` — fetch weekly overrides and pass them

```php
public function weekly(Team $team, string $weekStart): array
{
    $claims = $this->claimsQuery($team)
        ->whereDate('week_start', $weekStart)
        ->get();

    $year = Carbon::parse($weekStart)->year;
    $overrides = $this->overrides($team, 'week', $year, weekStart: $weekStart);

    return $this->segment($claims, $overrides, withFollowUp: false);
}
```

Import `Carbon\Carbon` at the top if not already imported (check the existing imports — it is already used for `defaultWeekStart`).

### Step 3: Update `aggregateRk()` — add pj_* fields and is_confirmed

After the existing `'is_overridden' => $override !== null,` line, add:

```php
'pj_obstacle' => $override?->obstacle,
'pj_solution' => $override?->solution,
'pj_follow_up_plan' => $override?->follow_up_plan,
'is_confirmed' => $override?->confirmed_at !== null,
'confirmed_by' => $override?->confirmedBy?->display_name ?? $override?->confirmedBy?->name,
```

Note: the existing `'obstacle'` key (which returns `$override?->obstacle ?? $obstacleAgg`) is kept as-is for backwards compatibility.

### Step 4: Run pint

```bash
./vendor/bin/pint app/Services/Kinetik/RecapAggregator.php && echo OK
```

---

## Task 4: Update TeamRecapController — storeOverride + add confirmOverride

**Files:**
- Modify: `app/Http/Controllers/TeamRecapController.php`

### Step 1: Extend `storeOverride` validation to include week

Change `'period_type' => ['required', 'in:month,quarter'],` → `'in:week,month,quarter'`.

Add to the validation rules:
```php
'week_start' => ['nullable', 'date', 'required_if:period_type,week'],
```

In the `updateOrCreate` match-key array, add:
```php
'week_start' => $validated['week_start'] ?? null,
```

Also: when `period_type === 'week'` and `period_year` is not supplied, derive it from `week_start`:
```php
if (($validated['period_type'] ?? '') === 'week' && empty($validated['period_year'])) {
    $validated['period_year'] = Carbon::parse($validated['week_start'])->year;
}
```

Place this derivation after the `$validated` assignment, before `authorizePj`.

### Step 2: Add `confirmOverride` method

```php
public function confirmOverride(Request $request): RedirectResponse
{
    $employee = $request->user()->employee;
    abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

    $validated = $request->validate([
        'team_id' => ['required', 'integer', 'exists:teams,id'],
        'performance_plan_id' => ['required', 'integer', 'exists:performance_plans,id'],
        'period_type' => ['required', 'in:week,month,quarter'],
        'period_year' => ['required', 'integer'],
        'period_month' => ['nullable', 'integer', 'between:1,12'],
        'period_quarter' => ['nullable', 'integer', 'between:1,4'],
        'week_start' => ['nullable', 'date', 'required_if:period_type,week'],
        'confirmed' => ['required', 'boolean'],
    ]);

    $this->authorizePj($employee, (int) $validated['team_id']);

    $key = [
        'team_id' => $validated['team_id'],
        'performance_plan_id' => $validated['performance_plan_id'],
        'period_type' => $validated['period_type'],
        'period_year' => $validated['period_year'],
        'period_month' => $validated['period_month'] ?? null,
        'period_quarter' => $validated['period_quarter'] ?? null,
        'week_start' => $validated['week_start'] ?? null,
    ];

    RecapOverride::updateOrCreate($key, [
        'confirmed_at' => $validated['confirmed'] ? now() : null,
        'confirmed_by' => $validated['confirmed'] ? $employee->id : null,
    ]);

    return back()->with('success', $validated['confirmed'] ? 'RK dikonfirmasi.' : 'Konfirmasi dibatalkan.');
}
```

### Step 3: Run pint

```bash
./vendor/bin/pint app/Http/Controllers/TeamRecapController.php && echo OK
```

---

## Task 5: Register the new route

**Files:**
- Modify: `routes/web.php`

After line:
```php
Route::post('/rekap-tim/override', [TeamRecapController::class, 'storeOverride'])->name('team-recap.override.store');
```

Add:
```php
Route::post('/rekap-tim/override/confirm', [TeamRecapController::class, 'confirmOverride'])->name('team-recap.override.confirm');
```

---

## Task 6: Update TypeScript types

**Files:**
- Modify: `resources/js/types/index.d.ts`

### Step 1: Update `RecapRow` interface

Add the new fields to the existing `RecapRow` interface (currently at line ~262):

```typescript
export interface RecapRow {
    performance_plan_id: number;
    rk_code?: string | null;
    rk_description: string;
    target: number;
    realization: number;
    achievement: number | null;
    target_unit?: string | null;
    obstacle: string | null;          // merged (override ?? aggregated) — kept for read views
    solution: string | null;
    follow_up_plan: string | null;
    obstacle_aggregated: string | null;
    solution_aggregated: string | null;
    follow_up_aggregated: string | null;
    // PJ paraphrase — raw override values (empty until PJ writes)
    pj_obstacle: string | null;
    pj_solution: string | null;
    pj_follow_up_plan: string | null;
    is_overridden: boolean;
    // Confirmation
    is_confirmed: boolean;
    confirmed_by: string | null;
    contributors: string[];
    // Quarterly (FRA) only
    follow_up_evidence_url?: string | null;
    follow_up_pic?: string | null;
    follow_up_pic_employee_id?: number | null;
    follow_up_deadline?: string | null;
}
```

---

## Task 7: Rewrite TeamWeeklyRecap.vue

**Files:**
- Modify: `resources/js/Pages/Kinetik/TeamWeeklyRecap.vue`

The new design: dense shadcn Table per segment with sortable Capaian, Konfirmasi column, and expand-row paraphrase panel. Evidence section stays at the bottom unchanged.

Key behavioral points:
- `sortDir` ref: `'asc' | 'desc'`, default `'asc'`.
- Sorted rows = `[...seg.rows].sort((a,b) => sortDir === 'asc' ? (a.achievement??0) - (b.achievement??0) : (b.achievement??0) - (a.achievement??0))`.
- Per-segment sort: use a `ref<Record<string,'asc'|'desc'>>` keyed by `seg.project_id ?? 'none'`.
- Expand state: `expandedRows` as `ref<Set<number>>`.
- Konfirmasi: `router.post(route('team-recap.override.confirm'), {...}, {preserveScroll:true, preserveState:true})`.
- Paraphrase save: `router.post(route('team-recap.override.store'), {...}, {preserveScroll:true, preserveState:true})`.
- No `useForm` needed — use `router.post` with inline reactive objects.
- Remove member Solusi/RTL columns entirely.
- Keep evidence section intact.

```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import type { RecapSegment, RecapRow, TeamOption, TeamRecapEvidence } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';
import { ChevronLeft, ChevronRight, ChevronDown, ChevronUp, ExternalLink, Trash2, Check, ChevronsUpDown } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { useDateFormat } from '@/composables/useDateFormat';

const { formatWeekRange } = useDateFormat();

const props = defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    segments: RecapSegment[];
    evidences: TeamRecapEvidence[];
    weekStart: string;
    weekEnd: string;
    prevWeek: string;
    nextWeek: string;
    canManage: boolean;
}>();

// ── Navigation ─────────────────────────────────────────────────────────────

function navigate(params: Record<string, string | number>) {
    router.get(route('team-recap.weekly'), {
        team: props.selectedTeamId ?? undefined,
        week: props.weekStart,
        ...params,
    }, { preserveState: false });
}

// ── Achievement color ──────────────────────────────────────────────────────

function achievementColor(val: number | null): string {
    const n = Number(val ?? 0);
    if (n >= 80) return 'text-green-600 font-semibold';
    if (n >= 50) return 'text-yellow-600 font-semibold';
    return 'text-red-600 font-semibold';
}

// ── Per-segment sort ───────────────────────────────────────────────────────

const sortDirs = ref<Record<string, 'asc' | 'desc'>>({});

function sortDir(segKey: string): 'asc' | 'desc' {
    return sortDirs.value[segKey] ?? 'asc';
}

function toggleSort(segKey: string) {
    sortDirs.value[segKey] = sortDir(segKey) === 'asc' ? 'desc' : 'asc';
}

function sortedRows(seg: RecapSegment): RecapRow[] {
    const key = String(seg.project_id ?? 'none');
    const dir = sortDir(key);
    return [...seg.rows].sort((a, b) =>
        dir === 'asc'
            ? (a.achievement ?? 0) - (b.achievement ?? 0)
            : (b.achievement ?? 0) - (a.achievement ?? 0)
    );
}

// ── Expand state ───────────────────────────────────────────────────────────

const expandedRows = ref<Set<number>>(new Set());

function toggleExpand(planId: number) {
    if (expandedRows.value.has(planId)) {
        expandedRows.value.delete(planId);
    } else {
        expandedRows.value.add(planId);
    }
}

// ── Confirmation ───────────────────────────────────────────────────────────

function toggleConfirm(row: RecapRow) {
    router.post(route('team-recap.override.confirm'), {
        team_id: props.selectedTeamId,
        performance_plan_id: row.performance_plan_id,
        period_type: 'week',
        period_year: new Date(props.weekStart + 'T00:00:00').getFullYear(),
        week_start: props.weekStart,
        confirmed: !row.is_confirmed,
    }, { preserveScroll: true, preserveState: true });
}

// ── Paraphrase forms (per planId) ──────────────────────────────────────────

type ParaForm = { obstacle: string; solution: string; follow_up_plan: string; saving: boolean };
const paraForms = ref<Record<number, ParaForm>>({});

function getParaForm(row: RecapRow): ParaForm {
    if (!paraForms.value[row.performance_plan_id]) {
        paraForms.value[row.performance_plan_id] = {
            obstacle: row.pj_obstacle ?? '',
            solution: row.pj_solution ?? '',
            follow_up_plan: row.pj_follow_up_plan ?? '',
            saving: false,
        };
    }
    return paraForms.value[row.performance_plan_id];
}

function saveParaphrase(row: RecapRow) {
    const f = getParaForm(row);
    f.saving = true;
    router.post(route('team-recap.override.store'), {
        team_id: props.selectedTeamId,
        performance_plan_id: row.performance_plan_id,
        period_type: 'week',
        period_year: new Date(props.weekStart + 'T00:00:00').getFullYear(),
        week_start: props.weekStart,
        obstacle: f.obstacle,
        solution: f.solution,
        follow_up_plan: f.follow_up_plan,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { f.saving = false; },
    });
}

// ── Evidence ───────────────────────────────────────────────────────────────

const evidenceTypeLabel: Record<string, string> = {
    notula: 'Notula',
    photo: 'Foto',
    attendance: 'Daftar Hadir',
};

const showEvidenceForm = ref(false);
const evidenceForm = ref({
    team_id: props.selectedTeamId,
    project_id: null as number | null,
    week_start: props.weekStart,
    type: 'notula',
    title: '',
    url: '',
    errors: {} as Record<string, string>,
    processing: false,
});

function submitEvidence() {
    evidenceForm.value.processing = true;
    router.post(route('team-recap.evidence.store'), {
        team_id: props.selectedTeamId,
        project_id: evidenceForm.value.project_id,
        week_start: props.weekStart,
        type: evidenceForm.value.type,
        title: evidenceForm.value.title,
        url: evidenceForm.value.url,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            evidenceForm.value.title = '';
            evidenceForm.value.url = '';
            showEvidenceForm.value = false;
        },
        onError: (errors) => { evidenceForm.value.errors = errors; },
        onFinish: () => { evidenceForm.value.processing = false; },
    });
}

function deleteEvidence(id: number) {
    router.delete(route('team-recap.evidence.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Rekap Tim" />
    <AppLayout>
        <template #title>Rekap Tim (Mingguan)</template>

        <div v-if="!teams.length" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Anda belum tergabung dalam tim mana pun.
        </div>

        <template v-else>
            <!-- Controls -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-md border bg-white px-4 py-3">
                <Select
                    :model-value="selectedTeamId != null ? String(selectedTeamId) : undefined"
                    @update:model-value="(v) => navigate({ team: Number(v) })"
                >
                    <SelectTrigger class="w-auto min-w-[16rem]">
                        <SelectValue placeholder="Pilih tim" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                    </SelectContent>
                </Select>

                <div class="flex items-center gap-3">
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Minggu sebelumnya" @click="navigate({ week: prevWeek })">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="text-sm font-medium text-gray-700">{{ formatWeekRange(weekStart, weekEnd) }}</span>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Minggu berikutnya" @click="navigate({ week: nextWeek })">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Segments by project -->
            <div v-if="!segments.length" class="mb-6 rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                Belum ada rekap tersimpan untuk tim ini pada minggu ini.
            </div>

            <div v-else class="mb-8 space-y-6">
                <div v-for="seg in segments" :key="seg.project_id ?? 'none'" class="overflow-hidden rounded-md border bg-white">
                    <div class="border-b bg-gray-50 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-800">{{ seg.project_name }}</h3>
                    </div>

                    <Table class="w-full text-sm">
                        <TableHeader>
                            <TableRow class="border-b bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                                <TableHead class="text-left">Rencana Kinerja</TableHead>
                                <TableHead class="text-left text-xs">Kontributor</TableHead>
                                <TableHead class="text-right">Target</TableHead>
                                <TableHead class="text-right">Realisasi</TableHead>
                                <TableHead class="cursor-pointer select-none text-right" @click="toggleSort(String(seg.project_id ?? 'none'))">
                                    <span class="inline-flex items-center gap-1">
                                        Capaian
                                        <ChevronsUpDown v-if="sortDir(String(seg.project_id ?? 'none')) === 'asc'" class="h-3 w-3" />
                                        <ChevronsUpDown v-else class="h-3 w-3 rotate-180" />
                                    </span>
                                </TableHead>
                                <TableHead class="text-center">Konfirmasi</TableHead>
                                <TableHead class="w-8" />
                            </TableRow>
                        </TableHeader>
                        <TableBody class="divide-y divide-gray-100">
                            <template v-for="row in sortedRows(seg)" :key="row.performance_plan_id">
                                <TableRow class="hover:bg-gray-50">
                                    <TableCell class="align-top">
                                        <p class="font-medium text-gray-800">{{ row.rk_description }}</p>
                                        <p v-if="row.rk_code" class="text-xs text-gray-500">{{ row.rk_code }}</p>
                                        <p v-if="row.is_overridden" class="mt-0.5 text-xs italic text-blue-500">Telah diparafrase</p>
                                    </TableCell>
                                    <TableCell class="align-top text-xs text-gray-600">{{ row.contributors.join(', ') || '—' }}</TableCell>
                                    <TableCell class="text-right align-top text-gray-700">{{ row.target }} {{ row.target_unit ?? '' }}</TableCell>
                                    <TableCell class="text-right align-top text-gray-700">{{ row.realization }}</TableCell>
                                    <TableCell class="text-right align-top">
                                        <span v-if="row.achievement != null" :class="achievementColor(row.achievement)">{{ row.achievement.toFixed(2) }}%</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </TableCell>
                                    <TableCell class="text-center align-top">
                                        <template v-if="canManage">
                                            <button
                                                type="button"
                                                :class="['inline-flex h-5 w-5 items-center justify-center rounded border-2 transition-colors', row.is_confirmed ? 'border-green-500 bg-green-500 text-white' : 'border-gray-300 hover:border-green-400']"
                                                :title="row.is_confirmed ? `Dikonfirmasi oleh ${row.confirmed_by ?? 'PJ'}` : 'Klik untuk konfirmasi'"
                                                @click="toggleConfirm(row)"
                                            >
                                                <Check v-if="row.is_confirmed" class="h-3 w-3" />
                                            </button>
                                            <p v-if="row.is_confirmed && row.confirmed_by" class="mt-0.5 text-xs text-green-600">{{ row.confirmed_by }}</p>
                                        </template>
                                        <template v-else>
                                            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', row.is_confirmed ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                                                {{ row.is_confirmed ? 'Terkonfirmasi' : 'Belum' }}
                                            </span>
                                        </template>
                                    </TableCell>
                                    <TableCell class="text-center align-top">
                                        <button
                                            type="button"
                                            class="flex h-6 w-6 items-center justify-center rounded hover:bg-gray-100"
                                            :title="expandedRows.has(row.performance_plan_id) ? 'Tutup panel' : 'Buka panel parafrase'"
                                            @click="toggleExpand(row.performance_plan_id)"
                                        >
                                            <ChevronDown v-if="!expandedRows.has(row.performance_plan_id)" class="h-4 w-4 text-gray-500" />
                                            <ChevronUp v-else class="h-4 w-4 text-gray-500" />
                                        </button>
                                    </TableCell>
                                </TableRow>

                                <!-- Expand panel -->
                                <TableRow v-if="expandedRows.has(row.performance_plan_id)" :key="`${row.performance_plan_id}-panel`" class="bg-gray-50">
                                    <TableCell colspan="8" class="px-6 py-4">
                                        <div class="space-y-4">
                                            <!-- Member kendala (read-only) -->
                                            <div>
                                                <p class="mb-1 text-xs font-medium text-gray-500">Kendala (anggota)</p>
                                                <p class="rounded bg-white px-3 py-2 text-sm text-gray-700 ring-1 ring-gray-200">{{ row.obstacle_aggregated || '—' }}</p>
                                            </div>

                                            <!-- PJ paraphrase inputs -->
                                            <template v-if="canManage">
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <Label class="text-xs">Kendala (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).obstacle" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Solusi (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).solution" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">RTL (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).follow_up_plan" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                </div>
                                                <div class="flex justify-end">
                                                    <Button size="sm" :disabled="getParaForm(row).saving" @click="saveParaphrase(row)">
                                                        Simpan parafrase
                                                    </Button>
                                                </div>
                                            </template>

                                            <!-- Read-only PJ paraphrase (non-PJ) -->
                                            <template v-else>
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">Kendala (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_obstacle || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">Solusi (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_solution || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">RTL (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_follow_up_plan || '—' }}</p>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- Evidence (notula / foto / DH) — unchanged -->
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">Bukti Dukung Rapat</h2>
                    <Button v-if="canManage" size="sm" variant="outline" @click="showEvidenceForm = !showEvidenceForm">
                        {{ showEvidenceForm ? 'Tutup' : 'Tambah Bukti' }}
                    </Button>
                    <span v-else class="text-xs text-gray-400">Hanya PJ yang dapat menambah bukti</span>
                </div>

                <form v-if="canManage && showEvidenceForm" class="mb-4 space-y-3 rounded-md border bg-white p-4" @submit.prevent="submitEvidence">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label for="ev-type">Jenis</Label>
                            <Select v-model="evidenceForm.type">
                                <SelectTrigger id="ev-type" class="mt-1 w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="notula">Notula</SelectItem>
                                    <SelectItem value="photo">Foto</SelectItem>
                                    <SelectItem value="attendance">Daftar Hadir</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label for="ev-title">Judul</Label>
                            <Input id="ev-title" v-model="evidenceForm.title" class="mt-1" placeholder="Rapat koordinasi..." />
                        </div>
                    </div>
                    <div>
                        <Label for="ev-url">URL Bukti Dukung <span class="text-red-500">*</span></Label>
                        <Input id="ev-url" v-model="evidenceForm.url" type="url" class="mt-1" placeholder="https://..." />
                        <InputError :message="evidenceForm.errors.url" />
                    </div>
                    <div class="flex justify-end">
                        <Button type="submit" size="sm" :disabled="evidenceForm.processing">Simpan Bukti</Button>
                    </div>
                </form>

                <div v-if="!evidences.length" class="rounded-md border border-dashed border-gray-200 bg-gray-50 py-8 text-center text-sm text-gray-400">
                    Belum ada bukti dukung untuk minggu ini.
                </div>
                <ul v-else class="divide-y divide-gray-100 overflow-hidden rounded-md border bg-white">
                    <li v-for="ev in evidences" :key="ev.id" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ evidenceTypeLabel[ev.type] ?? ev.type }}</span>
                            <span class="ml-2 text-sm text-gray-800">{{ ev.title || '—' }}</span>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <a :href="ev.url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                                Buka <ExternalLink class="h-3 w-3" />
                            </a>
                            <button v-if="canManage" type="button" class="text-gray-400 hover:text-red-600" title="Hapus" @click="deleteEvidence(ev.id)">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </template>
    </AppLayout>
</template>
```

---

## Task 8: Rewrite MonthlyRecap.vue

**Files:**
- Modify: `resources/js/Pages/Kinetik/MonthlyRecap.vue`

Same pattern as weekly. Period payload: `{ period_type: 'month', period_year: props.year, period_month: props.month }` (no `week_start`).

```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { RecapSegment, RecapRow, TeamOption } from '@/types';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';
import { ChevronLeft, ChevronRight, ChevronDown, ChevronUp, Check, ChevronsUpDown } from 'lucide-vue-next';

const props = defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    segments: RecapSegment[];
    year: number;
    month: number;
    canManage: boolean;
}>();

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const monthLabel = computed(() => `${MONTHS[props.month - 1]} ${props.year}`);

function navigate(params: Record<string, string | number>) {
    router.get(route('team-recap.monthly'), {
        team: props.selectedTeamId ?? undefined,
        year: props.year,
        month: props.month,
        ...params,
    }, { preserveState: false });
}

function shiftMonth(delta: number) {
    let m = props.month + delta;
    let y = props.year;
    if (m < 1) { m = 12; y -= 1; }
    if (m > 12) { m = 1; y += 1; }
    navigate({ year: y, month: m });
}

function achievementColor(val: number | null): string {
    const n = Number(val ?? 0);
    if (n >= 80) return 'text-green-600 font-semibold';
    if (n >= 50) return 'text-yellow-600 font-semibold';
    return 'text-red-600 font-semibold';
}

// ── Per-segment sort ───────────────────────────────────────────────────────

const sortDirs = ref<Record<string, 'asc' | 'desc'>>({});

function sortDir(segKey: string): 'asc' | 'desc' {
    return sortDirs.value[segKey] ?? 'asc';
}

function toggleSort(segKey: string) {
    sortDirs.value[segKey] = sortDir(segKey) === 'asc' ? 'desc' : 'asc';
}

function sortedRows(seg: RecapSegment): RecapRow[] {
    const key = String(seg.project_id ?? 'none');
    const dir = sortDir(key);
    return [...seg.rows].sort((a, b) =>
        dir === 'asc'
            ? (a.achievement ?? 0) - (b.achievement ?? 0)
            : (b.achievement ?? 0) - (a.achievement ?? 0)
    );
}

// ── Expand state ───────────────────────────────────────────────────────────

const expandedRows = ref<Set<number>>(new Set());

function toggleExpand(planId: number) {
    if (expandedRows.value.has(planId)) {
        expandedRows.value.delete(planId);
    } else {
        expandedRows.value.add(planId);
    }
}

// ── Confirmation ───────────────────────────────────────────────────────────

function toggleConfirm(row: RecapRow) {
    router.post(route('team-recap.override.confirm'), {
        team_id: props.selectedTeamId,
        performance_plan_id: row.performance_plan_id,
        period_type: 'month',
        period_year: props.year,
        period_month: props.month,
        confirmed: !row.is_confirmed,
    }, { preserveScroll: true, preserveState: true });
}

// ── Paraphrase forms ───────────────────────────────────────────────────────

type ParaForm = { obstacle: string; solution: string; follow_up_plan: string; saving: boolean };
const paraForms = ref<Record<number, ParaForm>>({});

function getParaForm(row: RecapRow): ParaForm {
    if (!paraForms.value[row.performance_plan_id]) {
        paraForms.value[row.performance_plan_id] = {
            obstacle: row.pj_obstacle ?? '',
            solution: row.pj_solution ?? '',
            follow_up_plan: row.pj_follow_up_plan ?? '',
            saving: false,
        };
    }
    return paraForms.value[row.performance_plan_id];
}

function saveParaphrase(row: RecapRow) {
    const f = getParaForm(row);
    f.saving = true;
    router.post(route('team-recap.override.store'), {
        team_id: props.selectedTeamId,
        performance_plan_id: row.performance_plan_id,
        period_type: 'month',
        period_year: props.year,
        period_month: props.month,
        obstacle: f.obstacle,
        solution: f.solution,
        follow_up_plan: f.follow_up_plan,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { f.saving = false; },
    });
}
</script>

<template>
    <Head title="Rekap Bulanan" />
    <AppLayout>
        <template #title>Rekap Bulanan</template>

        <div v-if="!teams.length" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Anda belum tergabung dalam tim mana pun.
        </div>

        <template v-else>
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-md border bg-white px-4 py-3">
                <Select
                    :model-value="selectedTeamId != null ? String(selectedTeamId) : undefined"
                    @update:model-value="(v) => navigate({ team: Number(v) })"
                >
                    <SelectTrigger class="w-auto min-w-[16rem]">
                        <SelectValue placeholder="Pilih tim" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                    </SelectContent>
                </Select>

                <div class="flex items-center gap-3">
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Bulan sebelumnya" @click="shiftMonth(-1)">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="text-sm font-medium text-gray-700">{{ monthLabel }}</span>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Bulan berikutnya" @click="shiftMonth(1)">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div v-if="!segments.length" class="rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                Belum ada rekap untuk tim ini pada bulan ini.
            </div>

            <div v-else class="space-y-6">
                <div v-for="seg in segments" :key="seg.project_id ?? 'none'" class="overflow-hidden rounded-md border bg-white">
                    <div class="border-b bg-gray-50 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-800">{{ seg.project_name }}</h3>
                    </div>

                    <Table class="w-full text-sm">
                        <TableHeader>
                            <TableRow class="border-b bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                                <TableHead class="text-left">Rencana Kinerja</TableHead>
                                <TableHead class="text-left text-xs">Kontributor</TableHead>
                                <TableHead class="text-right">Target</TableHead>
                                <TableHead class="text-right">Realisasi</TableHead>
                                <TableHead class="cursor-pointer select-none text-right" @click="toggleSort(String(seg.project_id ?? 'none'))">
                                    <span class="inline-flex items-center gap-1">
                                        Capaian
                                        <ChevronsUpDown class="h-3 w-3" :class="{ 'rotate-180': sortDir(String(seg.project_id ?? 'none')) === 'desc' }" />
                                    </span>
                                </TableHead>
                                <TableHead class="text-center">Konfirmasi</TableHead>
                                <TableHead class="w-8" />
                            </TableRow>
                        </TableHeader>
                        <TableBody class="divide-y divide-gray-100">
                            <template v-for="row in sortedRows(seg)" :key="row.performance_plan_id">
                                <TableRow class="hover:bg-gray-50">
                                    <TableCell class="align-top">
                                        <p class="font-medium text-gray-800">{{ row.rk_description }}</p>
                                        <p v-if="row.rk_code" class="text-xs text-gray-500">{{ row.rk_code }}</p>
                                        <p v-if="row.is_overridden" class="mt-0.5 text-xs italic text-blue-500">Telah diparafrase</p>
                                    </TableCell>
                                    <TableCell class="align-top text-xs text-gray-600">{{ row.contributors.join(', ') || '—' }}</TableCell>
                                    <TableCell class="text-right align-top text-gray-700">{{ row.target }} {{ row.target_unit ?? '' }}</TableCell>
                                    <TableCell class="text-right align-top text-gray-700">{{ row.realization }}</TableCell>
                                    <TableCell class="text-right align-top">
                                        <span v-if="row.achievement != null" :class="achievementColor(row.achievement)">{{ row.achievement.toFixed(2) }}%</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </TableCell>
                                    <TableCell class="text-center align-top">
                                        <template v-if="canManage">
                                            <button
                                                type="button"
                                                :class="['inline-flex h-5 w-5 items-center justify-center rounded border-2 transition-colors', row.is_confirmed ? 'border-green-500 bg-green-500 text-white' : 'border-gray-300 hover:border-green-400']"
                                                :title="row.is_confirmed ? `Dikonfirmasi oleh ${row.confirmed_by ?? 'PJ'}` : 'Klik untuk konfirmasi'"
                                                @click="toggleConfirm(row)"
                                            >
                                                <Check v-if="row.is_confirmed" class="h-3 w-3" />
                                            </button>
                                            <p v-if="row.is_confirmed && row.confirmed_by" class="mt-0.5 text-xs text-green-600">{{ row.confirmed_by }}</p>
                                        </template>
                                        <template v-else>
                                            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', row.is_confirmed ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                                                {{ row.is_confirmed ? 'Terkonfirmasi' : 'Belum' }}
                                            </span>
                                        </template>
                                    </TableCell>
                                    <TableCell class="text-center align-top">
                                        <button
                                            type="button"
                                            class="flex h-6 w-6 items-center justify-center rounded hover:bg-gray-100"
                                            @click="toggleExpand(row.performance_plan_id)"
                                        >
                                            <ChevronDown v-if="!expandedRows.has(row.performance_plan_id)" class="h-4 w-4 text-gray-500" />
                                            <ChevronUp v-else class="h-4 w-4 text-gray-500" />
                                        </button>
                                    </TableCell>
                                </TableRow>

                                <TableRow v-if="expandedRows.has(row.performance_plan_id)" :key="`${row.performance_plan_id}-panel`" class="bg-gray-50">
                                    <TableCell colspan="8" class="px-6 py-4">
                                        <div class="space-y-4">
                                            <div>
                                                <p class="mb-1 text-xs font-medium text-gray-500">Kendala (anggota)</p>
                                                <p class="rounded bg-white px-3 py-2 text-sm text-gray-700 ring-1 ring-gray-200">{{ row.obstacle_aggregated || '—' }}</p>
                                            </div>
                                            <template v-if="canManage">
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <Label class="text-xs">Kendala (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).obstacle" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Solusi (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).solution" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">RTL (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).follow_up_plan" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                </div>
                                                <div class="flex justify-end">
                                                    <Button size="sm" :disabled="getParaForm(row).saving" @click="saveParaphrase(row)">
                                                        Simpan parafrase
                                                    </Button>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">Kendala (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_obstacle || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">Solusi (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_solution || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">RTL (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_follow_up_plan || '—' }}</p>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
```

---

## Task 9: Rewrite QuarterlyRecap.vue

**Files:**
- Modify: `resources/js/Pages/Kinetik/QuarterlyRecap.vue`

Same pattern. The expand panel additionally includes the FRA follow-up inputs (evidence URL, PIC select from `pics`, deadline). Period payload: `{ period_type: 'quarter', period_year: props.year, period_quarter: props.quarter }`.

```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { RecapSegment, RecapRow, TeamOption } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';
import { ChevronLeft, ChevronRight, ChevronDown, ChevronUp, Check, ChevronsUpDown, ExternalLink } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';

const props = defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    segments: RecapSegment[];
    year: number;
    quarter: number;
    pics: TeamOption[];
    canManage: boolean;
}>();

const quarterLabel = computed(() => `Triwulan ${props.quarter} ${props.year}`);

function navigate(params: Record<string, string | number>) {
    router.get(route('team-recap.quarterly'), {
        team: props.selectedTeamId ?? undefined,
        year: props.year,
        quarter: props.quarter,
        ...params,
    }, { preserveState: false });
}

function shiftQuarter(delta: number) {
    let q = props.quarter + delta;
    let y = props.year;
    if (q < 1) { q = 4; y -= 1; }
    if (q > 4) { q = 1; y += 1; }
    navigate({ year: y, quarter: q });
}

function achievementColor(val: number | null): string {
    const n = Number(val ?? 0);
    if (n >= 80) return 'text-green-600 font-semibold';
    if (n >= 50) return 'text-yellow-600 font-semibold';
    return 'text-red-600 font-semibold';
}

// ── Per-segment sort ───────────────────────────────────────────────────────

const sortDirs = ref<Record<string, 'asc' | 'desc'>>({});

function sortDir(segKey: string): 'asc' | 'desc' {
    return sortDirs.value[segKey] ?? 'asc';
}

function toggleSort(segKey: string) {
    sortDirs.value[segKey] = sortDir(segKey) === 'asc' ? 'desc' : 'asc';
}

function sortedRows(seg: RecapSegment): RecapRow[] {
    const key = String(seg.project_id ?? 'none');
    const dir = sortDir(key);
    return [...seg.rows].sort((a, b) =>
        dir === 'asc'
            ? (a.achievement ?? 0) - (b.achievement ?? 0)
            : (b.achievement ?? 0) - (a.achievement ?? 0)
    );
}

// ── Expand state ───────────────────────────────────────────────────────────

const expandedRows = ref<Set<number>>(new Set());

function toggleExpand(planId: number) {
    if (expandedRows.value.has(planId)) {
        expandedRows.value.delete(planId);
    } else {
        expandedRows.value.add(planId);
    }
}

// ── Confirmation ───────────────────────────────────────────────────────────

function toggleConfirm(row: RecapRow) {
    router.post(route('team-recap.override.confirm'), {
        team_id: props.selectedTeamId,
        performance_plan_id: row.performance_plan_id,
        period_type: 'quarter',
        period_year: props.year,
        period_quarter: props.quarter,
        confirmed: !row.is_confirmed,
    }, { preserveScroll: true, preserveState: true });
}

// ── Paraphrase + FRA forms ─────────────────────────────────────────────────

type ParaForm = {
    obstacle: string;
    solution: string;
    follow_up_plan: string;
    follow_up_evidence_url: string;
    follow_up_pic_employee_id: number | null;
    follow_up_deadline: string;
    saving: boolean;
    errors: Record<string, string>;
};
const paraForms = ref<Record<number, ParaForm>>({});

function getParaForm(row: RecapRow): ParaForm {
    if (!paraForms.value[row.performance_plan_id]) {
        paraForms.value[row.performance_plan_id] = {
            obstacle: row.pj_obstacle ?? '',
            solution: row.pj_solution ?? '',
            follow_up_plan: row.pj_follow_up_plan ?? '',
            follow_up_evidence_url: row.follow_up_evidence_url ?? '',
            follow_up_pic_employee_id: row.follow_up_pic_employee_id ?? null,
            follow_up_deadline: row.follow_up_deadline ?? '',
            saving: false,
            errors: {},
        };
    }
    return paraForms.value[row.performance_plan_id];
}

function saveParaphrase(row: RecapRow) {
    const f = getParaForm(row);
    f.saving = true;
    router.post(route('team-recap.override.store'), {
        team_id: props.selectedTeamId,
        performance_plan_id: row.performance_plan_id,
        period_type: 'quarter',
        period_year: props.year,
        period_quarter: props.quarter,
        obstacle: f.obstacle,
        solution: f.solution,
        follow_up_plan: f.follow_up_plan,
        follow_up_evidence_url: f.follow_up_evidence_url || null,
        follow_up_pic_employee_id: f.follow_up_pic_employee_id,
        follow_up_deadline: f.follow_up_deadline || null,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => { f.errors = errors; },
        onFinish: () => { f.saving = false; },
    });
}
</script>

<template>
    <Head title="Rekap Triwulanan" />
    <AppLayout>
        <template #title>Rekap Triwulanan (FRA)</template>

        <div v-if="!teams.length" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Anda belum tergabung dalam tim mana pun.
        </div>

        <template v-else>
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-md border bg-white px-4 py-3">
                <Select
                    :model-value="selectedTeamId != null ? String(selectedTeamId) : undefined"
                    @update:model-value="(v) => navigate({ team: Number(v) })"
                >
                    <SelectTrigger class="w-auto min-w-[16rem]">
                        <SelectValue placeholder="Pilih tim" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                    </SelectContent>
                </Select>

                <div class="flex items-center gap-3">
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Triwulan sebelumnya" @click="shiftQuarter(-1)">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="text-sm font-medium text-gray-700">{{ quarterLabel }}</span>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Triwulan berikutnya" @click="shiftQuarter(1)">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div v-if="!segments.length" class="rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                Belum ada rekap untuk tim ini pada triwulan ini.
            </div>

            <div v-else class="space-y-6">
                <div v-for="seg in segments" :key="seg.project_id ?? 'none'" class="overflow-hidden rounded-md border bg-white">
                    <div class="border-b bg-gray-50 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-800">{{ seg.project_name }}</h3>
                    </div>

                    <Table class="w-full text-sm">
                        <TableHeader>
                            <TableRow class="border-b bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                                <TableHead class="text-left">Rencana Kinerja</TableHead>
                                <TableHead class="text-left text-xs">Kontributor</TableHead>
                                <TableHead class="text-right">Target</TableHead>
                                <TableHead class="text-right">Realisasi</TableHead>
                                <TableHead class="cursor-pointer select-none text-right" @click="toggleSort(String(seg.project_id ?? 'none'))">
                                    <span class="inline-flex items-center gap-1">
                                        Capaian
                                        <ChevronsUpDown class="h-3 w-3" :class="{ 'rotate-180': sortDir(String(seg.project_id ?? 'none')) === 'desc' }" />
                                    </span>
                                </TableHead>
                                <TableHead class="text-center">Konfirmasi</TableHead>
                                <TableHead class="w-8" />
                            </TableRow>
                        </TableHeader>
                        <TableBody class="divide-y divide-gray-100">
                            <template v-for="row in sortedRows(seg)" :key="row.performance_plan_id">
                                <TableRow class="hover:bg-gray-50">
                                    <TableCell class="align-top">
                                        <p class="font-medium text-gray-800">{{ row.rk_description }}</p>
                                        <p v-if="row.rk_code" class="text-xs text-gray-500">{{ row.rk_code }}</p>
                                        <p v-if="row.is_overridden" class="mt-0.5 text-xs italic text-blue-500">Telah diparafrase</p>
                                    </TableCell>
                                    <TableCell class="align-top text-xs text-gray-600">{{ row.contributors.join(', ') || '—' }}</TableCell>
                                    <TableCell class="text-right align-top text-gray-700">{{ row.target }} {{ row.target_unit ?? '' }}</TableCell>
                                    <TableCell class="text-right align-top text-gray-700">{{ row.realization }}</TableCell>
                                    <TableCell class="text-right align-top">
                                        <span v-if="row.achievement != null" :class="achievementColor(row.achievement)">{{ row.achievement.toFixed(2) }}%</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </TableCell>
                                    <TableCell class="text-center align-top">
                                        <template v-if="canManage">
                                            <button
                                                type="button"
                                                :class="['inline-flex h-5 w-5 items-center justify-center rounded border-2 transition-colors', row.is_confirmed ? 'border-green-500 bg-green-500 text-white' : 'border-gray-300 hover:border-green-400']"
                                                :title="row.is_confirmed ? `Dikonfirmasi oleh ${row.confirmed_by ?? 'PJ'}` : 'Klik untuk konfirmasi'"
                                                @click="toggleConfirm(row)"
                                            >
                                                <Check v-if="row.is_confirmed" class="h-3 w-3" />
                                            </button>
                                            <p v-if="row.is_confirmed && row.confirmed_by" class="mt-0.5 text-xs text-green-600">{{ row.confirmed_by }}</p>
                                        </template>
                                        <template v-else>
                                            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', row.is_confirmed ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                                                {{ row.is_confirmed ? 'Terkonfirmasi' : 'Belum' }}
                                            </span>
                                        </template>
                                    </TableCell>
                                    <TableCell class="text-center align-top">
                                        <button
                                            type="button"
                                            class="flex h-6 w-6 items-center justify-center rounded hover:bg-gray-100"
                                            @click="toggleExpand(row.performance_plan_id)"
                                        >
                                            <ChevronDown v-if="!expandedRows.has(row.performance_plan_id)" class="h-4 w-4 text-gray-500" />
                                            <ChevronUp v-else class="h-4 w-4 text-gray-500" />
                                        </button>
                                    </TableCell>
                                </TableRow>

                                <TableRow v-if="expandedRows.has(row.performance_plan_id)" :key="`${row.performance_plan_id}-panel`" class="bg-gray-50">
                                    <TableCell colspan="8" class="px-6 py-4">
                                        <div class="space-y-4">
                                            <div>
                                                <p class="mb-1 text-xs font-medium text-gray-500">Kendala (anggota)</p>
                                                <p class="rounded bg-white px-3 py-2 text-sm text-gray-700 ring-1 ring-gray-200">{{ row.obstacle_aggregated || '—' }}</p>
                                            </div>
                                            <template v-if="canManage">
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <Label class="text-xs">Kendala (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).obstacle" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Solusi (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).solution" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">RTL (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).follow_up_plan" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                </div>
                                                <!-- FRA follow-up fields -->
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <Label for="fra-pic" class="text-xs">PIC Tindak Lanjut</Label>
                                                        <Select
                                                            :model-value="getParaForm(row).follow_up_pic_employee_id != null ? String(getParaForm(row).follow_up_pic_employee_id) : undefined"
                                                            @update:model-value="(v) => getParaForm(row).follow_up_pic_employee_id = v ? Number(v) : null"
                                                        >
                                                            <SelectTrigger class="mt-1 w-full">
                                                                <SelectValue placeholder="— Pilih PIC —" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem v-for="p in pics" :key="p.id" :value="String(p.id)">{{ p.name }}</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Batas Waktu</Label>
                                                        <Input v-model="getParaForm(row).follow_up_deadline" type="date" class="mt-1" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Bukti Tindak Lanjut (URL)</Label>
                                                        <Input v-model="getParaForm(row).follow_up_evidence_url" type="url" class="mt-1" placeholder="https://..." />
                                                        <InputError :message="getParaForm(row).errors.follow_up_evidence_url" />
                                                    </div>
                                                </div>
                                                <div class="flex justify-end">
                                                    <Button size="sm" :disabled="getParaForm(row).saving" @click="saveParaphrase(row)">
                                                        Simpan parafrase
                                                    </Button>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">Kendala (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_obstacle || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">Solusi (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_solution || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">RTL (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_follow_up_plan || '—' }}</p>
                                                    </div>
                                                </div>
                                                <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-gray-500">
                                                    <span>PIC: <span class="text-gray-700">{{ row.follow_up_pic ?? '—' }}</span></span>
                                                    <span>Batas: <span class="text-gray-700">{{ row.follow_up_deadline ?? '—' }}</span></span>
                                                    <a v-if="row.follow_up_evidence_url" :href="row.follow_up_evidence_url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-primary hover:underline">
                                                        Bukti Tindak Lanjut <ExternalLink class="h-3 w-3" />
                                                    </a>
                                                </div>
                                            </template>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
```

---

## Task 10: Add tests for RecapAggregator (weekly override path)

**Files:**
- Modify: `tests/Feature/Kinetik/RecapAggregatorTest.php`

Append to the existing test file:

```php
// ── Weekly override (Phase 5 — confirm + paraphrase) ─────────────────────────

it('merges a weekly RecapOverride into pj_obstacle and marks is_overridden', function () {
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $weekStart = '2026-06-01';
    recapClaim($plan, [
        'week_start' => $weekStart,
        'period_year' => 2026,
        'period_month' => 6,
        'period_quarter' => 2,
        'obstacle' => 'kendala anggota',
    ]);

    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'period_month' => null,
        'period_quarter' => null,
        'week_start' => $weekStart,
        'obstacle' => 'kendala pj',
    ]);

    $row = $this->aggregator->weekly($team, $weekStart)[0]['rows'][0];

    expect($row['pj_obstacle'])->toBe('kendala pj');
    expect($row['obstacle_aggregated'])->toBe('kendala anggota');
    expect($row['is_overridden'])->toBeTrue();
});

it('surfaces is_confirmed true and confirmed_by name when override is confirmed', function () {
    $team = Team::factory()->create();
    $pj = Employee::factory()->create(['display_name' => 'Ahmad']);
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $weekStart = '2026-06-01';
    recapClaim($plan, [
        'week_start' => $weekStart,
        'period_year' => 2026,
        'period_month' => 6,
        'period_quarter' => 2,
    ]);

    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'period_month' => null,
        'period_quarter' => null,
        'week_start' => $weekStart,
        'confirmed_at' => now(),
        'confirmed_by' => $pj->id,
    ]);

    $row = $this->aggregator->weekly($team, $weekStart)[0]['rows'][0];

    expect($row['is_confirmed'])->toBeTrue();
    expect($row['confirmed_by'])->toBe('Ahmad');
});

it('is_confirmed is false when no override exists', function () {
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $weekStart = '2026-06-01';
    recapClaim($plan, [
        'week_start' => $weekStart,
        'period_year' => 2026,
        'period_month' => 6,
        'period_quarter' => 2,
    ]);

    $row = $this->aggregator->weekly($team, $weekStart)[0]['rows'][0];

    expect($row['is_confirmed'])->toBeFalse();
    expect($row['confirmed_by'])->toBeNull();
});
```

---

## Task 11: Add tests for TeamRecapController (weekly override + confirm)

**Files:**
- Modify: `tests/Feature/Http/TeamRecapControllerTest.php`

Append:

```php
// ── Weekly paraphrase override ─────────────────────────────────────────────

it('PJ stores a weekly paraphrase override and it surfaces in segments', function () {
    [$user, $employee, $team] = pjOfTeam();

    $project = Project::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id]);

    $member = Employee::factory()->create();
    $team->members()->attach($member->id, ['role' => 'member', 'is_primary' => true]);

    $weekStart = '2026-06-01';
    ActivityClaim::factory()->saved()->create([
        'employee_id' => $member->id,
        'performance_plan_id' => $plan->id,
        'week_start' => $weekStart,
        'period_year' => 2026,
        'period_month' => 6,
        'period_quarter' => 2,
        'obstacle' => 'kendala asli',
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.store'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'week_start' => $weekStart,
            'obstacle' => 'kendala PJ',
            'solution' => 'solusi PJ',
            'follow_up_plan' => 'rtl PJ',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('recap_overrides', [
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'obstacle' => 'kendala PJ',
    ]);

    // Round-trips into segments
    $this->actingAs($user)
        ->get(route('team-recap.weekly', ['week' => $weekStart]))
        ->assertInertia(fn ($page) => $page
            ->where('segments.0.rows.0.pj_obstacle', 'kendala PJ')
            ->where('segments.0.rows.0.obstacle_aggregated', 'kendala asli')
            ->where('segments.0.rows.0.is_overridden', true)
        );
});

it('non-PJ gets 403 on weekly override store', function () {
    [$user, , $team] = memberOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.store'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'week_start' => '2026-06-01',
            'obstacle' => 'x',
        ])
        ->assertForbidden();
});

// ── Confirm override ───────────────────────────────────────────────────────

it('PJ confirms an RK row via team-recap.override.confirm', function () {
    [$user, $employee, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.confirm'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'period_year' => 2026,
            'week_start' => '2026-06-01',
            'confirmed' => true,
        ])
        ->assertRedirect();

    $override = RecapOverride::where('performance_plan_id', $plan->id)->first();
    expect($override)->not->toBeNull();
    expect($override->confirmed_at)->not->toBeNull();
    expect($override->confirmed_by)->toBe($employee->id);
});

it('confirming does not wipe an existing paraphrase', function () {
    [$user, $employee, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    // First: store a paraphrase
    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'week_start' => '2026-06-01',
        'obstacle' => 'parafrase tersimpan',
        'confirmed_at' => null,
        'confirmed_by' => null,
    ]);

    // Then confirm
    $this->actingAs($user)
        ->post(route('team-recap.override.confirm'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'period_year' => 2026,
            'week_start' => '2026-06-01',
            'confirmed' => true,
        ])
        ->assertRedirect();

    $override = RecapOverride::where('performance_plan_id', $plan->id)->first();
    expect($override->obstacle)->toBe('parafrase tersimpan');
    expect($override->confirmed_at)->not->toBeNull();
});

it('re-saving paraphrase does not clear confirmed_at', function () {
    [$user, $employee, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    // Pre-create a confirmed override
    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'week_start' => '2026-06-01',
        'obstacle' => 'parafrase lama',
        'confirmed_at' => now()->subHour(),
        'confirmed_by' => $employee->id,
    ]);

    // Re-save paraphrase
    $this->actingAs($user)
        ->post(route('team-recap.override.store'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'week_start' => '2026-06-01',
            'obstacle' => 'parafrase baru',
        ])
        ->assertRedirect();

    $override = RecapOverride::where('performance_plan_id', $plan->id)->first();
    expect($override->obstacle)->toBe('parafrase baru');
    expect($override->confirmed_at)->not->toBeNull(); // still confirmed
});

it('non-PJ gets 403 on confirm', function () {
    [$user, , $team] = memberOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.confirm'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'period_year' => 2026,
            'week_start' => '2026-06-01',
            'confirmed' => true,
        ])
        ->assertForbidden();
});

it('unconfirming sets confirmed_at to null', function () {
    [$user, $employee, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'week_start' => '2026-06-01',
        'confirmed_at' => now(),
        'confirmed_by' => $employee->id,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.confirm'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'period_year' => 2026,
            'week_start' => '2026-06-01',
            'confirmed' => false,
        ])
        ->assertRedirect();

    $override = RecapOverride::where('performance_plan_id', $plan->id)->first();
    expect($override->confirmed_at)->toBeNull();
    expect($override->confirmed_by)->toBeNull();
});
```

---

## Task 12: Update RecapOverrideFactory

**Files:**
- Modify: `database/factories/RecapOverrideFactory.php`

Add `week_start`, `confirmed_at`, `confirmed_by` to the `definition()` return array (all `null` by default). This allows tests to override them explicitly.

```php
public function definition(): array
{
    return [
        'team_id' => Team::factory(),
        'performance_plan_id' => PerformancePlan::factory(),
        'period_type' => 'month',
        'period_year' => (int) now()->year,
        'period_quarter' => null,
        'period_month' => (int) now()->month,
        'week_start' => null,
        'obstacle' => $this->faker->sentence(),
        'solution' => $this->faker->sentence(),
        'follow_up_plan' => $this->faker->sentence(),
        'follow_up_evidence_url' => null,
        'follow_up_pic_employee_id' => null,
        'follow_up_deadline' => null,
        'created_by' => null,
        'confirmed_at' => null,
        'confirmed_by' => null,
    ];
}
```

---

## Task 13: Run pint + typecheck + tests

### Step 1: Run pint (auto-fix)

```bash
cd /Users/ryanaidilp/Documents/Projects/Web/performance_matrix
./vendor/bin/pint app tests database
```

### Step 2: Verify pint passes in check mode

```bash
./vendor/bin/pint --test
```

Expected: exit 0 (no violations)

### Step 3: TypeScript typecheck

```bash
npm run typecheck 2>&1 | head -60
```

Expected: zero errors in the files you edited. Note any pre-existing errors separately.

### Step 4: Run the two target test suites

```bash
php artisan test tests/Feature/Kinetik/RecapAggregatorTest.php tests/Feature/Http/TeamRecapControllerTest.php --stop-on-failure
```

Expected: all tests PASS. If any fail, diagnose and fix before proceeding.

---

## Key Notes

- **The `storeOverride` route only writes paraphrase/FRA fields** — it never touches `confirmed_at`/`confirmed_by`. The `confirmOverride` route only touches `confirmed_at`/`confirmed_by` — it never touches obstacle/solution/follow_up_plan. This separation is enforced in both controller methods and tested.
- **`RecapOverrideFactory` defaults**: All new columns default to `null`, so all existing tests continue to pass without modification.
- **Portable SQL**: `whereDate()` works on both PostgreSQL and SQLite — no raw SQL or date-casting tricks needed.
- **Existing `obstacle`/`solution`/`follow_up_plan` keys** in `aggregateRk()` remain as merged values (`$override ?? $aggregated`) — they are kept for backwards compatibility and used in read-only displays. The new `pj_*` keys expose the raw PJ text so the textarea binds to the right value.
- **Vue `Set` reactivity**: `expandedRows.value.has(...)` inside a computed/template expression re-evaluates because the ref reference itself is reactive; mutating `.add()/.delete()` inside `toggleExpand()` triggers re-render because we reassign a new Set or because Vue 3 tracks the ref. To be safe, replace with a reactive object: use `const expandedRows = ref<Record<number, boolean>>({})` and `expandedRows.value[planId] = !expandedRows.value[planId]` — simpler and guarantees reactivity.
