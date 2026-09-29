<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { OverviewTeam, RecapPeriodType, ReviewStatus } from '@/types';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { ArrowRight, ChevronDown, ChevronLeft, ChevronRight, Download, Lock, LockOpen } from 'lucide-vue-next';
import { useDateFormat } from '@/composables/useDateFormat';
import { useAchievementColor } from '@/composables/useAchievementColor';
import { useRecapExport } from '@/composables/useRecapExport';
import { REVIEW_STATUS_META, reviewStatus } from '@/composables/useReviewStatus';
import LeadershipNoteCell from '@/Components/Kinetik/LeadershipNoteCell.vue';

const props = defineProps<{
    periodType: RecapPeriodType;
    year: number;
    month: number;
    quarter: number;
    weekStart: string;
    weekEnd: string;
    teams: OverviewTeam[];
    canWriteNotes: boolean;
}>();

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const TABS: Array<{ type: RecapPeriodType; label: string }> = [
    { type: 'week', label: 'Mingguan' },
    { type: 'month', label: 'Bulanan' },
    { type: 'quarter', label: 'Triwulanan' },
];

const { formatWeekRange } = useDateFormat();
const { achievementColor, progressVariant } = useAchievementColor();
const { exporting, download } = useRecapExport();

const periodLabel = computed(() => ({
    week: formatWeekRange(props.weekStart, props.weekEnd),
    month: `${MONTHS[props.month - 1]} ${props.year}`,
    quarter: `Triwulan ${props.quarter} ${props.year}`,
}[props.periodType]));

// ── Navigation ─────────────────────────────────────────────────────────────

function periodParams(type: RecapPeriodType, shift = 0): Record<string, string | number> {
    if (type === 'week') {
        const d = new Date(`${props.weekStart}T00:00:00`);
        d.setDate(d.getDate() + shift * 7);
        const iso = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        return { period_type: 'week', week: iso };
    }
    if (type === 'month') {
        const d = new Date(props.year, props.month - 1 + shift, 1);
        return { period_type: 'month', year: d.getFullYear(), month: d.getMonth() + 1 };
    }
    const index = props.year * 4 + (props.quarter - 1) + shift;
    return { period_type: 'quarter', year: Math.floor(index / 4), quarter: (index % 4) + 1 };
}

function go(params: Record<string, string | number>) {
    router.get(route('team-recap.overview'), params, { preserveScroll: true });
}

function teamHref(team: OverviewTeam): string {
    if (props.periodType === 'week') return route('team-recap.weekly', { team: team.id, week: props.weekStart });
    if (props.periodType === 'month') return route('team-recap.monthly', { team: team.id, year: props.year, month: props.month });
    return route('team-recap.quarterly', { team: team.id, year: props.year, quarter: props.quarter });
}

function exportPeriod() {
    const file = { week: `Rapat Mingguan ${props.weekStart}`, month: `Rapat Bulanan ${periodLabel.value}`, quarter: `FRA ${periodLabel.value}` }[props.periodType];
    download({ ...periodParams(props.periodType), period_type: props.periodType }, `${file}.xlsx`);
}

// ── Summary ────────────────────────────────────────────────────────────────

// Teams with data first, then alphabetical: the head reads what exists.
const sortedTeams = computed(() => [...props.teams].sort((a, b) => Number(b.rows > 0) - Number(a.rows > 0) || a.name.localeCompare(b.name)));

// ── Review filters (client side: the page already holds every team) ────────

const STATUS_FILTERS: Array<ReviewStatus | 'all'> = ['all', 'achieved', 'progress', 'low', 'none'];
const teamFilter = ref('all');
const statusFilter = ref<ReviewStatus | 'all'>('all');
const statusCount = (status: ReviewStatus | 'all') =>
    status === 'all' ? props.teams.length : props.teams.filter(t => reviewStatus(t.avg_achievement) === status).length;
const visibleTeams = computed(() => sortedTeams.value.filter(t =>
    (teamFilter.value === 'all' || String(t.id) === teamFilter.value)
    && (statusFilter.value === 'all' || reviewStatus(t.avg_achievement) === statusFilter.value)));

// Store-endpoint fields for a note on this period.
const periodPayload = computed(() => ({
    period_type: props.periodType,
    period_year: props.year,
    week_start: props.periodType === 'week' ? props.weekStart : null,
    period_month: props.periodType === 'month' ? props.month : null,
    period_quarter: props.periodType === 'quarter' ? props.quarter : null,
}));
const withData = computed(() => props.teams.filter(t => t.rows > 0));
const officeAverage = computed(() => {
    const values = withData.value.map(t => t.avg_achievement).filter((v): v is number => v !== null);
    return values.length ? values.reduce((sum, v) => sum + v, 0) / values.length : null;
});
const lockedCount = computed(() => props.teams.filter(t => t.locked).length);
const isWeek = computed(() => props.periodType === 'week');
const columnCount = computed(() => (isWeek.value ? 9 : 8));

// Expanded team rows (project breakdown with each project's PIC).
const expanded = ref<Set<number>>(new Set());
function toggle(teamId: number) {
    const next = new Set(expanded.value);
    next.has(teamId) ? next.delete(teamId) : next.add(teamId);
    expanded.value = next;
}
</script>

<template>
    <Head title="Review Bersama" />
    <AppLayout>
        <template #title>Review Bersama</template>

        <!-- Period controls -->
        <div class="mb-4 flex flex-col gap-3 rounded-lg border bg-white p-3 sm:p-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="inline-flex rounded-md border bg-gray-50 p-0.5" role="tablist" aria-label="Jenis periode">
                <button
                    v-for="tab in TABS"
                    :key="tab.type"
                    type="button"
                    role="tab"
                    :aria-selected="periodType === tab.type"
                    :class="[
                        'flex-1 rounded px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:flex-none',
                        periodType === tab.type ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900',
                    ]"
                    @click="go(periodParams(tab.type))"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div class="flex items-center justify-between rounded-md border bg-gray-50/60 lg:justify-center">
                <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Periode sebelumnya" @click="go(periodParams(periodType, -1))">
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <span class="min-w-[13rem] px-2 text-center text-sm font-semibold text-gray-800 tabular-nums">{{ periodLabel }}</span>
                <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Periode berikutnya" @click="go(periodParams(periodType, 1))">
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>

            <Button variant="outline" size="sm" :disabled="exporting" @click="exportPeriod">
                <Download class="mr-1.5 h-4 w-4" />
                {{ exporting ? 'Menyiapkan…' : 'Unduh Excel semua tim' }}
            </Button>
        </div>

        <!-- Office summary -->
        <dl class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-lg border bg-white px-4 py-3">
                <dt class="text-xs text-gray-500">Tim dengan rekap</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ withData.length }}<span class="text-sm font-normal text-gray-500"> / {{ teams.length }}</span></dd>
            </div>
            <div class="rounded-lg border bg-white px-4 py-3">
                <dt class="text-xs text-gray-500">Rata-rata capaian</dt>
                <dd v-if="officeAverage !== null" :class="['mt-1 text-xl font-semibold tabular-nums', achievementColor(officeAverage)]">{{ officeAverage.toFixed(1) }}%</dd>
                <dd v-else class="mt-1 text-xl font-semibold text-gray-300">—</dd>
            </div>
            <div class="rounded-lg border bg-white px-4 py-3">
                <dt class="text-xs text-gray-500">Rekap dikunci PJ</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ lockedCount }}<span class="text-sm font-normal text-gray-500"> / {{ teams.length }}</span></dd>
            </div>
            <div class="rounded-lg border bg-white px-4 py-3">
                <dt class="text-xs text-gray-500">Baris dikonfirmasi</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-900">
                    {{ teams.reduce((n, t) => n + t.confirmed, 0) }}<span class="text-sm font-normal text-gray-500"> / {{ teams.reduce((n, t) => n + t.rows, 0) }}</span>
                </dd>
            </div>
        </dl>

        <!-- Review filters -->
        <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
            <Select v-model="teamFilter">
                <SelectTrigger class="w-full sm:w-72" aria-label="Filter tim">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Semua tim</SelectItem>
                    <SelectItem v-for="t in sortedTeams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                </SelectContent>
            </Select>
            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Filter status capaian">
                <button
                    v-for="status in STATUS_FILTERS"
                    :key="status"
                    type="button"
                    :aria-pressed="statusFilter === status"
                    :class="[
                        'inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                        statusFilter === status ? 'border-primary bg-primary text-primary-foreground' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900',
                    ]"
                    @click="statusFilter = status"
                >
                    {{ status === 'all' ? 'Semua' : REVIEW_STATUS_META[status].label }}
                    <span class="tabular-nums opacity-75">{{ statusCount(status) }}</span>
                </button>
            </div>
        </div>

        <!-- Per-team table -->
        <div class="overflow-x-auto rounded-lg border bg-white">
            <Table class="text-sm">
                <TableHeader>
                    <TableRow class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <TableHead class="min-w-[12rem]">Tim</TableHead>
                        <TableHead class="min-w-[9rem]">PJ</TableHead>
                        <TableHead class="text-right">Baris RK</TableHead>
                        <TableHead class="min-w-[9rem]">Capaian</TableHead>
                        <TableHead class="text-right">Dikonfirmasi</TableHead>
                        <TableHead v-if="isWeek" class="text-right">Anggota lengkap</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="min-w-[14rem]">Catatan Pimpinan</TableHead>
                        <TableHead class="w-10"><span class="sr-only">Buka</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="divide-y divide-gray-100">
                    <template v-for="team in visibleTeams" :key="team.id">
                    <TableRow :class="team.rows ? 'hover:bg-gray-50' : 'text-gray-500'">
                        <TableCell class="whitespace-normal font-medium leading-snug">
                            <div class="flex items-start gap-1.5">
                                <button
                                    type="button"
                                    class="-ml-1 mt-px rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                    :aria-expanded="expanded.has(team.id)"
                                    :aria-label="`${expanded.has(team.id) ? 'Tutup' : 'Lihat'} projek ${team.name}`"
                                    @click="toggle(team.id)"
                                >
                                    <ChevronDown :class="['h-4 w-4 transition-transform', expanded.has(team.id) ? 'rotate-180' : '-rotate-90']" />
                                </button>
                                <span class="min-w-0">
                                    <Link :href="teamHref(team)" class="text-gray-900 hover:text-primary hover:underline">{{ team.name }}</Link>
                                    <span class="block text-xs font-normal text-gray-500">{{ team.projects.filter(p => p.id !== null).length }} projek</span>
                                </span>
                            </div>
                        </TableCell>
                        <TableCell class="whitespace-normal text-sm leading-snug text-gray-700">{{ team.leader ?? '—' }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ team.rows || '—' }}</TableCell>
                        <TableCell>
                            <div v-if="team.avg_achievement !== null" class="flex items-center gap-2">
                                <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-100">
                                    <div :class="['h-full rounded-full', progressVariant(team.avg_achievement)]" :style="{ width: `${Math.min(team.avg_achievement, 100)}%` }" />
                                </div>
                                <span :class="['font-semibold tabular-nums', achievementColor(team.avg_achievement)]">{{ team.avg_achievement.toFixed(1) }}%</span>
                            </div>
                            <span v-else class="text-xs text-gray-400">Belum ada data</span>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            <template v-if="team.rows">{{ team.confirmed }}/{{ team.rows }}</template>
                            <template v-else>—</template>
                        </TableCell>
                        <TableCell v-if="isWeek" class="text-right tabular-nums">
                            <template v-if="team.members_active">{{ team.members_complete }}/{{ team.members_active }}</template>
                            <template v-else>—</template>
                        </TableCell>
                        <TableCell>
                            <span :class="['inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium', REVIEW_STATUS_META[reviewStatus(team.avg_achievement)].chip]">
                                {{ REVIEW_STATUS_META[reviewStatus(team.avg_achievement)].label }}
                            </span>
                            <span v-if="team.locked" class="mt-1 flex items-center gap-1 whitespace-nowrap text-xs font-medium text-amber-700">
                                <Lock class="h-3 w-3" aria-hidden="true" /> Dikunci PJ
                            </span>
                            <span v-else class="mt-1 flex items-center gap-1 whitespace-nowrap text-xs text-gray-500">
                                <LockOpen class="h-3 w-3" aria-hidden="true" /> Terbuka
                            </span>
                        </TableCell>
                        <TableCell class="whitespace-normal">
                            <LeadershipNoteCell :note="team.note" :can-write="canWriteNotes" :subject="team.name" :payload="{ ...periodPayload, team_id: team.id, project_id: null }" />
                        </TableCell>
                        <TableCell>
                            <Link :href="teamHref(team)" class="flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-primary" :aria-label="`Buka rekap ${team.name}`">
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </TableCell>
                    </TableRow>

                    <!-- Project breakdown: who is in charge of each project -->
                    <TableRow v-if="expanded.has(team.id)" class="bg-gray-50/70 hover:bg-gray-50/70">
                        <TableCell :colspan="columnCount" class="whitespace-normal px-4 py-3 sm:pl-10">
                            <table v-if="team.projects.length" class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-[11px] uppercase tracking-wide text-gray-500">
                                        <th class="pb-1.5 pr-4 font-medium">Projek</th>
                                        <th class="pb-1.5 pr-4 font-medium">PIC / Ketua projek</th>
                                        <th class="pb-1.5 pr-4 text-right font-medium">Anggota</th>
                                        <th class="pb-1.5 pr-4 text-right font-medium">Baris RK</th>
                                        <th class="pb-1.5 pr-4 text-right font-medium">Capaian</th>
                                        <th class="pb-1.5 font-medium">Catatan Pimpinan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200/70">
                                    <tr v-for="project in team.projects" :key="project.id ?? 'none'">
                                        <td class="py-1.5 pr-4 leading-snug text-gray-800" :class="project.id === null ? 'italic text-gray-500' : ''">{{ project.name }}</td>
                                        <td class="py-1.5 pr-4 leading-snug text-gray-700">{{ project.leader ?? '—' }}</td>
                                        <td class="py-1.5 pr-4 text-right tabular-nums text-gray-600">{{ project.members ?? '—' }}</td>
                                        <td class="py-1.5 pr-4 text-right tabular-nums text-gray-600">{{ project.rows || '—' }}</td>
                                        <td class="py-1.5 pr-4 text-right tabular-nums">
                                            <span v-if="project.avg_achievement !== null" :class="['font-semibold', achievementColor(project.avg_achievement)]">{{ project.avg_achievement.toFixed(1) }}%</span>
                                            <span v-else class="text-xs text-gray-400">Belum ada data</span>
                                        </td>
                                        <td class="min-w-[14rem] py-1.5">
                                            <LeadershipNoteCell
                                                v-if="project.id !== null"
                                                :note="project.note"
                                                :can-write="canWriteNotes"
                                                :subject="project.name"
                                                :payload="{ ...periodPayload, team_id: team.id, project_id: project.id }"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-else class="text-sm text-gray-500">Tim ini belum punya projek pada tahun ini.</p>
                        </TableCell>
                    </TableRow>
                    </template>
                    <TableRow v-if="!teams.length">
                        <TableCell :colspan="columnCount" class="py-10 text-center text-sm text-gray-500">Tidak ada tim yang dapat Anda lihat.</TableCell>
                    </TableRow>
                    <TableRow v-else-if="!visibleTeams.length">
                        <TableCell :colspan="columnCount" class="py-10 text-center text-sm text-gray-500">
                            Tidak ada tim dengan filter ini.
                            <button type="button" class="ml-1 font-medium text-primary hover:underline" @click="teamFilter = 'all'; statusFilter = 'all'">Tampilkan semua</button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
