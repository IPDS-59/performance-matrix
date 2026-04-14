<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import type { Employee, Team } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Badge } from '@/Components/ui/badge';
import { Progress } from '@/Components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { VisXYContainer, VisGroupedBar, VisLine, VisArea, VisAxis, VisTooltip } from '@unovis/vue';
import { GroupedBar, Line as UnovisLine, Area as UnovisArea } from '@unovis/ts';
import { CurveType } from '@unovis/ts';

// ── Types ──────────────────────────────────────────────────────────────────

interface TeamProgress {
    team_id: number;
    avg_achievement: number;
    report_count: number;
}

interface TrendPoint {
    period_month: number;
    avg_achievement: number;
}

interface PersonalStats {
    teams_count: number;
    projects_count: number;
    items_count: number;
    avg_achievement: number;
    is_team_lead: boolean;
}

interface WorkItemReport {
    id: number;
    realization: number;
    achievement_percentage: number;
    reported_by: number | null;
    reporter: { id: number; name: string; display_name: string | null } | null;
}

interface TeamWorkItem {
    id: number;
    description: string;
    target: number;
    target_unit: string;
    performance_reports: WorkItemReport[];
}

interface TeamMember extends Employee {
    pivot: { role: string };
}

interface TeamProjectWithMembers {
    id: number;
    name: string;
    team: { id: number; name: string } | null;
    members: TeamMember[];
    work_items: TeamWorkItem[];
}

interface ProjectWithItems {
    id: number;
    team_id: number;
    name: string;
    team?: { id: number; name: string } | null;
    work_items: Array<{
        id: number;
        description: string;
        performance_reports: Array<{ achievement_percentage: number }>;
    }>;
}

interface TeamWithMembers extends Team {
    employees?: TeamMember[];
}

interface EmployeeRankItem {
    id: number;
    name: string;
    display_name: string | null;
    project_count?: number;
    leader_count?: number;
    member_count?: number;
    avg_achievement?: number;
}

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    role: 'admin' | 'head' | 'staff';
    employee?: Employee;
    personal_stats?: PersonalStats;
    projects?: ProjectWithItems[];
    team_projects?: TeamProjectWithMembers[];
    teams?: TeamWithMembers[];
    project_leader_ids?: number[];
    team_progress?: Record<string, TeamProgress>;
    org_avg?: number;
    trend?: TrendPoint[];
    top_employees_by_projects?: EmployeeRankItem[];
    top_employees_by_achievement?: EmployeeRankItem[];
    filters: { year: number; month: number };
}>();

// ── Filters ────────────────────────────────────────────────────────────────

const year = ref(props.filters.year);
const month = ref(props.filters.month);

function applyFilters() {
    router.get(route('dashboard'), { year: year.value, month: month.value }, { preserveState: true });
}

const months = [
    { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' }, { value: 4, label: 'April' },
    { value: 5, label: 'Mei' }, { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' }, { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' }, { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' }, { value: 12, label: 'Desember' },
];

const monthLabel = computed(() => months.find(m => m.value === props.filters.month)?.label ?? '');

// ── Color helpers ──────────────────────────────────────────────────────────

function achievementColor(pct: number): string {
    if (pct >= 80) return 'text-green-600';
    if (pct >= 50) return 'text-yellow-500';
    return 'text-red-500';
}

function achievementBgClass(pct: number): string {
    if (pct >= 80) return 'bg-green-100 text-green-700';
    if (pct >= 50) return 'bg-yellow-100 text-yellow-700';
    return 'bg-red-100 text-red-700';
}

function progressVariant(pct: number): string {
    if (pct >= 80) return 'bg-green-500';
    if (pct >= 50) return 'bg-yellow-500';
    return 'bg-red-500';
}

function avgIconBgColor(pct: number): string {
    if (pct >= 80) return 'bg-green-100';
    if (pct >= 50) return 'bg-yellow-100';
    return 'bg-red-100';
}

function avgIconColor(pct: number): string {
    if (pct >= 80) return 'text-green-600';
    if (pct >= 50) return 'text-yellow-500';
    return 'text-red-500';
}

// ── Personal stats helpers ─────────────────────────────────────────────────

function projectAvg(project: ProjectWithItems): number {
    const reports = project.work_items.flatMap(wi => wi.performance_reports);
    if (!reports.length) return 0;
    return reports.reduce((s, r) => s + Number(r.achievement_percentage), 0) / reports.length;
}

// Staff: group personal projects by team
const projectsByTeam = computed(() => {
    if (!props.projects) return [];
    const groups: Record<number, { teamName: string; projects: ProjectWithItems[] }> = {};
    for (const p of props.projects) {
        const tid = p.team_id;
        const tname = p.team?.name ?? 'Tim Tidak Diketahui';
        if (!groups[tid]) groups[tid] = { teamName: tname, projects: [] };
        groups[tid].projects.push(p);
    }
    return Object.values(groups).sort((a, b) => a.teamName.localeCompare(b.teamName));
});

// Staff: overall personal avg across all personal projects
const personalAvg = computed(() => {
    if (!props.projects?.length) return 0;
    const avgs = props.projects.map(p => projectAvg(p));
    return avgs.reduce((s, a) => s + a, 0) / avgs.length;
});

// Led project helpers
function ledProjectMemberCount(project: TeamProjectWithMembers): number {
    return project.members.length;
}

function ledProjectSubmittedCount(project: TeamProjectWithMembers): number {
    const reportedBySet = new Set<number>();
    for (const wi of project.work_items) {
        for (const r of wi.performance_reports) {
            if (r.reported_by !== null) {
                reportedBySet.add(r.reported_by);
            }
        }
    }
    return reportedBySet.size;
}

function isProjectLeader(member: TeamMember): boolean {
    return member.pivot.role === 'leader' || member.pivot.role === 'ketua';
}

// ── Head/Admin: team ranking ───────────────────────────────────────────────

const teamList = computed(() => {
    if (!props.teams || !props.team_progress) return [];
    return props.teams.map(t => ({
        ...t,
        avg: props.team_progress![t.id]?.avg_achievement ?? 0,
        count: props.team_progress![t.id]?.report_count ?? 0,
    })).sort((a, b) => b.avg - a.avg);
});

const expandedTeams = ref<Set<number>>(new Set());

function toggleTeam(teamId: number): void {
    const next = new Set(expandedTeams.value);
    if (next.has(teamId)) {
        next.delete(teamId);
    } else {
        next.add(teamId);
    }
    expandedTeams.value = next;
}

function isTeamExpanded(teamId: number): boolean {
    return expandedTeams.value.has(teamId);
}

function isProjectLeaderById(employeeId: number): boolean {
    return props.project_leader_ids?.includes(employeeId) ?? false;
}

// ── Chip scroll indicator (always visible when container is scrollable) ────

const chipScrollable = reactive<Record<number, boolean>>({});
const chipResizeObservers = new Map<number, ResizeObserver>();

function initChipScrollable(el: HTMLElement | null, projectId: number) {
    chipResizeObservers.get(projectId)?.disconnect();
    if (!el) return;
    const update = () => { chipScrollable[projectId] = el.scrollWidth > el.clientWidth; };
    update();
    const ro = new ResizeObserver(update);
    ro.observe(el);
    chipResizeObservers.set(projectId, ro);
}

// ── Bar chart (Unovis) ────────────────────────────────────────────────────

interface BarChartDatum { label: string; value: number }

const barChartUnovisData = computed<BarChartDatum[]>(() =>
    teamList.value.map(t => ({ label: t.name, value: t.avg })),
);

const barX = (_d: BarChartDatum, i: number) => i;
const barY = [(d: BarChartDatum) => d.value];
const barColor = (d: BarChartDatum) =>
    d.value >= 80 ? 'rgba(34,197,94,0.75)' :
    d.value >= 50 ? 'rgba(234,179,8,0.75)' :
    'rgba(239,68,68,0.75)';
const barXTickFormat = (_tick: number, i: number) => barChartUnovisData.value[i]?.label ?? '';
const barYTickFormat = (v: number) => `${v}%`;
const barTooltipTriggers = {
    [GroupedBar.selectors.bar]: (d: BarChartDatum) =>
        `<div style="padding:4px 8px;font-size:13px"><strong>${d.label}</strong><br/>${d.value.toFixed(1)}%</div>`,
};

// ── Line/Area chart (Unovis) ──────────────────────────────────────────────

const trendLabels = months.map(m => m.label.substring(0, 3));

interface TrendDatum { month: number; label: string; value: number | null }

const trendUnovisData = computed<TrendDatum[]>(() => {
    const dataByMonth: (number | null)[] = Array(12).fill(null);
    for (const point of props.trend ?? []) {
        dataByMonth[point.period_month - 1] = point.avg_achievement;
    }
    return dataByMonth.map((v, i) => ({ month: i, label: trendLabels[i], value: v }));
});

const trendX = (d: TrendDatum) => d.month;
const trendY = [(d: TrendDatum) => d.value ?? undefined];
const trendXTickFormat = (_tick: number, i: number) => trendUnovisData.value[i]?.label ?? '';
const trendYTickFormat = (v: number) => `${v}%`;
const trendLineColor = '#1B4B8A';
const trendAreaColor = 'rgba(27,75,138,0.08)';
const trendTooltipTriggers = computed(() => ({
    [UnovisLine.selectors.line]: (d: TrendDatum) =>
        `<div style="padding:4px 8px;font-size:13px"><strong>${d.label}</strong><br/>${d.value?.toFixed(1) ?? '-'}%</div>`,
    [UnovisArea.selectors.area]: (d: TrendDatum) =>
        `<div style="padding:4px 8px;font-size:13px"><strong>${d.label}</strong><br/>${d.value?.toFixed(1) ?? '-'}%</div>`,
}));

// ── Employee ranking charts ────────────────────────────────────────────────

const topByProjectsTab = ref<'semua' | 'ketua' | 'anggota'>('semua');

const topByProjectsFiltered = computed(() => {
    const all = props.top_employees_by_projects ?? [];
    if (topByProjectsTab.value === 'ketua') {
        return [...all].sort((a, b) => (b.leader_count ?? 0) - (a.leader_count ?? 0)).slice(0, 10);
    }
    if (topByProjectsTab.value === 'anggota') {
        return [...all].sort((a, b) => (b.member_count ?? 0) - (a.member_count ?? 0)).slice(0, 10);
    }
    return all.slice(0, 10);
});

function topByProjectsCount(e: EmployeeRankItem): number {
    if (topByProjectsTab.value === 'ketua') return e.leader_count ?? 0;
    if (topByProjectsTab.value === 'anggota') return e.member_count ?? 0;
    return e.project_count ?? 0;
}

function topByProjectsLabel(): string {
    if (topByProjectsTab.value === 'ketua') return 'ketua';
    if (topByProjectsTab.value === 'anggota') return 'anggota';
    return 'proyek';
}

// ── Employee by projects chart (Unovis, horizontal) ──────────────────────

interface EmpProjectsDatum { label: string; value: number; isCurrentUser: boolean }

const empByProjectsUnovisData = computed<EmpProjectsDatum[]>(() =>
    topByProjectsFiltered.value.map(e => ({
        label: e.display_name || e.name,
        value: topByProjectsCount(e),
        isCurrentUser: e.id === props.employee?.id,
    })),
);

const empProjectsX = (_d: EmpProjectsDatum, i: number) => i;
const empProjectsY = [(d: EmpProjectsDatum) => d.value];
const empProjectsColor = (d: EmpProjectsDatum) =>
    d.isCurrentUser ? 'rgba(27,75,138,0.9)' : 'rgba(99,102,241,0.65)';
const empProjectsYTickFormat = (_tick: number, i: number) => {
    const label = empByProjectsUnovisData.value[i]?.label ?? '';
    return label.length > 16 ? label.substring(0, 14) + '\u2026' : label;
};
const empProjectsXTickFormat = (v: number) => `${v}`;
const empProjectsTooltipTriggers = {
    [GroupedBar.selectors.bar]: (d: EmpProjectsDatum) =>
        `<div style="padding:4px 8px;font-size:13px"><strong>${d.label}</strong><br/>${d.value} proyek</div>`,
};

// ── Employee by achievement chart (Unovis, horizontal) ───────────────────

interface EmpAchievementDatum { label: string; value: number; isCurrentUser: boolean }

const empByAchievementUnovisData = computed<EmpAchievementDatum[]>(() =>
    (props.top_employees_by_achievement ?? []).map(e => ({
        label: e.display_name || e.name,
        value: e.avg_achievement ?? 0,
        isCurrentUser: e.id === props.employee?.id,
    })),
);

const empAchievementX = (_d: EmpAchievementDatum, i: number) => i;
const empAchievementY = [(d: EmpAchievementDatum) => d.value];
const empAchievementColor = (d: EmpAchievementDatum) =>
    d.isCurrentUser ? 'rgba(27,75,138,0.9)' :
    d.value >= 80 ? 'rgba(34,197,94,0.65)' :
    d.value >= 50 ? 'rgba(234,179,8,0.65)' :
    'rgba(239,68,68,0.65)';
const empAchievementYTickFormat = (_tick: number, i: number) => {
    const label = empByAchievementUnovisData.value[i]?.label ?? '';
    return label.length > 16 ? label.substring(0, 14) + '\u2026' : label;
};
const empAchievementXTickFormat = (v: number) => `${v}%`;
const empAchievementTooltipTriggers = {
    [GroupedBar.selectors.bar]: (d: EmpAchievementDatum) =>
        `<div style="padding:4px 8px;font-size:13px"><strong>${d.label}</strong><br/>${d.value.toFixed(1)}%</div>`,
};
</script>

<template>
    <Head title="Beranda" />
    <AppLayout>
        <template #title>Beranda — {{ monthLabel }} {{ filters.year }}</template>

        <!-- Period filters -->
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <Select v-model="month" @update:modelValue="applyFilters">
                <SelectTrigger class="w-40">
                    <SelectValue placeholder="Bulan" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="m in months" :key="m.value" :value="m.value">
                        {{ m.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="year" @update:modelValue="applyFilters">
                <SelectTrigger class="w-28">
                    <SelectValue placeholder="Tahun" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- ── STAFF view ────────────────────────────────────────────────── -->
        <template v-if="role === 'staff'">
            <div v-if="!employee" class="py-20 text-center text-gray-400">
                <p class="text-lg font-medium">Akun Belum Terhubung</p>
                <p class="mt-1 text-sm">Hubungi administrator untuk menghubungkan akun ke data pegawai.</p>
            </div>
            <template v-else>
                <!-- Welcome card (always visible, outside tabs) -->
                <div class="mb-6 flex items-center gap-4 rounded-lg border bg-white p-5 shadow-sm">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xl font-bold text-primary">
                        {{ (employee.display_name || employee.name).charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">{{ employee.display_name || employee.name }}</p>
                        <p class="text-sm text-gray-500">{{ monthLabel }} {{ filters.year }}</p>
                    </div>
                </div>

                <!-- Tabs only when team lead, otherwise just show personal content -->
                <template v-if="personal_stats?.is_team_lead">
                    <Tabs default-value="personal" class="w-full">
                        <TabsList class="mb-6">
                            <TabsTrigger value="personal">Kinerja Saya</TabsTrigger>
                            <TabsTrigger value="team">
                                Tim yang Saya Pimpin
                                <Badge variant="secondary" class="ml-2 text-xs">{{ team_projects?.length ?? 0 }}</Badge>
                            </TabsTrigger>
                        </TabsList>
                        <TabsContent value="personal">
                            <!-- Personal stat cards -->
                            <div v-if="personal_stats" class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <!-- Tim Kerja -->
                                <div class="rounded-lg border bg-white p-6 shadow-sm">
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100" aria-hidden="true">
                                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-500">Tim Kerja</p>
                                            <p class="mt-1 text-2xl font-bold text-gray-900">{{ personal_stats.teams_count }}</p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Proyek -->
                                <div class="rounded-lg border bg-white p-6 shadow-sm">
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-100" aria-hidden="true">
                                            <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-500">Proyek</p>
                                            <p class="mt-1 text-2xl font-bold text-gray-900">{{ personal_stats.projects_count }}</p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Item Kerja -->
                                <div class="rounded-lg border bg-white p-6 shadow-sm">
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-teal-100" aria-hidden="true">
                                            <svg class="h-6 w-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-500">Item Kerja</p>
                                            <p class="mt-1 text-2xl font-bold text-gray-900">{{ personal_stats.items_count }}</p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Rata-rata Capaian -->
                                <div class="rounded-lg border bg-white p-6 shadow-sm">
                                    <div class="flex items-start gap-4">
                                        <div :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded-full', avgIconBgColor(personal_stats.avg_achievement)]" aria-hidden="true">
                                            <svg :class="['h-6 w-6', avgIconColor(personal_stats.avg_achievement)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-500">Rata-rata Capaian</p>
                                            <p :class="['mt-1 text-2xl font-bold', achievementColor(personal_stats.avg_achievement)]">
                                                {{ personal_stats.avg_achievement.toFixed(1) }}%
                                            </p>
                                            <Progress
                                                :model-value="personal_stats.avg_achievement"
                                                class="mt-2 h-1.5" :indicator-class="progressVariant(personal_stats.avg_achievement)"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Team comparison (right after stat cards) -->
                            <h2 class="mt-10 mb-4 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                                <span class="h-px flex-1 bg-primary/20"></span>
                                Perbandingan Kinerja Tim
                                <span class="h-px flex-1 bg-primary/20"></span>
                            </h2>
                            <div class="grid gap-4 lg:grid-cols-3">
                                <Card class="lg:col-span-2">
                                    <CardHeader class="pb-2">
                                        <CardTitle class="text-base">Capaian Per Tim</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div v-if="teamList.length" class="h-64">
                                            <VisXYContainer :data="barChartUnovisData" :yDomain="[0, 100]" :style="{ height: '100%' }">
                                                <VisGroupedBar :x="barX" :y="barY" :color="barColor" :roundedCorners="6" />
                                                <VisAxis type="x" :tickFormat="barXTickFormat" :gridLine="false" :tickTextFontSize="'11px'" :tickTextAngle="-30" />
                                                <VisAxis type="y" :tickFormat="barYTickFormat" />
                                                <VisTooltip :triggers="barTooltipTriggers" />
                                            </VisXYContainer>
                                        </div>
                                        <div v-else class="flex h-64 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50">
                                            <p class="text-sm text-gray-400">Belum ada data capaian tim bulan ini</p>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader>
                                        <CardTitle class="text-base">Peringkat Tim</CardTitle>
                                    </CardHeader>
                                    <CardContent class="p-0">
                                        <div class="relative">
                                            <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                                                <template v-if="teamList.length">
                                                    <div v-for="(team, idx) in teamList" :key="team.id">
                                                        <button
                                                            type="button"
                                                            class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors"
                                                            :aria-expanded="isTeamExpanded(team.id)"
                                                            @click="toggleTeam(team.id)"
                                                        >
                                                            <span class="w-5 shrink-0 text-right text-xs font-bold text-gray-400">{{ idx + 1 }}</span>
                                                            <div class="min-w-0 flex-1">
                                                                <p class="truncate text-sm font-medium">{{ team.name }}</p>
                                                                <div class="mt-1 h-1.5 w-full rounded-full bg-gray-200 overflow-hidden">
                                                                    <div :class="['h-full rounded-full transition-all', progressVariant(team.avg)]" :style="`width: ${team.avg}%`" />
                                                                </div>
                                                            </div>
                                                            <span :class="['shrink-0 text-sm font-bold', achievementColor(team.avg)]">{{ team.avg.toFixed(1) }}%</span>
                                                            <svg :class="['h-4 w-4 shrink-0 text-gray-400 transition-transform', isTeamExpanded(team.id) ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                            </svg>
                                                        </button>
                                                        <div
                                                            v-if="isTeamExpanded(team.id)"
                                                            class="border-t border-gray-100 bg-gray-50 px-4 py-3"
                                                        >
                                                            <p class="mb-2 text-xs font-medium text-gray-500 uppercase tracking-wide">Anggota Tim</p>
                                                            <div class="flex flex-wrap gap-1.5">
                                                                <span
                                                                    v-for="member in (team as TeamWithMembers).employees"
                                                                    :key="member.id"
                                                                    :class="[
                                                                        'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs',
                                                                        isProjectLeaderById(member.id)
                                                                            ? 'border-amber-300 bg-amber-50 text-amber-800'
                                                                            : 'border-gray-200 bg-white text-gray-600'
                                                                    ]"
                                                                >
                                                                    <span v-if="isProjectLeaderById(member.id)" class="text-amber-500" aria-label="Ketua Proyek">&#9733;</span>
                                                                    {{ member.display_name || member.name }}
                                                                    <Badge
                                                                        v-if="isProjectLeaderById(member.id)"
                                                                        class="ml-0.5 h-3.5 bg-amber-500 px-1 text-[9px] leading-none text-white hover:bg-amber-500"
                                                                    >Ketua Proyek</Badge>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <div v-else class="px-4 py-8 text-center text-sm text-gray-400">Belum ada data tim</div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <h2 class="mt-10 mb-4 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                                <span class="h-px flex-1 bg-primary/20"></span>
                                Peringkat Pegawai
                                <span class="h-px flex-1 bg-primary/20"></span>
                            </h2>
                            <div class="grid gap-4 md:grid-cols-2">
                                <Card>
                                    <CardHeader class="pb-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <CardTitle class="text-sm font-semibold">Top 10 Proyek Terbanyak</CardTitle>
                                            <div class="flex divide-x rounded-md border text-xs overflow-hidden">
                                                <button @click="topByProjectsTab = 'semua'" :class="[topByProjectsTab === 'semua' ? 'bg-primary text-white' : 'text-gray-500 hover:bg-gray-50', 'px-2 py-1']">Semua</button>
                                                <button @click="topByProjectsTab = 'ketua'" :class="[topByProjectsTab === 'ketua' ? 'bg-primary text-white' : 'text-gray-500 hover:bg-gray-50', 'px-2 py-1']">Ketua</button>
                                                <button @click="topByProjectsTab = 'anggota'" :class="[topByProjectsTab === 'anggota' ? 'bg-primary text-white' : 'text-gray-500 hover:bg-gray-50', 'px-2 py-1']">Anggota</button>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <div v-if="topByProjectsFiltered.length" class="mb-3 h-52">
                                            <VisXYContainer :data="empByProjectsUnovisData" :style="{ height: '100%' }">
                                                <VisGroupedBar orientation="horizontal" :x="empProjectsX" :y="empProjectsY" :color="empProjectsColor" :roundedCorners="4" />
                                                <VisAxis type="x" :tickFormat="empProjectsXTickFormat" />
                                                <VisAxis type="y" :tickFormat="empProjectsYTickFormat" :gridLine="false" :tickTextFontSize="'10px'" />
                                                <VisTooltip :triggers="empProjectsTooltipTriggers" />
                                            </VisXYContainer>
                                        </div>
                                        <div v-else class="mb-3 flex h-52 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50">
                                            <p class="text-sm text-gray-400">Belum ada data proyek bulan ini</p>
                                        </div>
                                        <div class="relative">
                                            <div class="max-h-52 overflow-y-auto divide-y divide-gray-100 rounded-md border [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                                                <template v-if="topByProjectsFiltered.length">
                                                    <div v-for="(emp, idx) in topByProjectsFiltered" :key="emp.id" :class="['flex items-center gap-3 px-3 py-2', emp.id === employee?.id ? 'bg-primary/5' : '']">
                                                        <span class="w-5 shrink-0 text-right text-xs font-bold text-gray-400">{{ idx + 1 }}</span>
                                                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-700">{{ (emp.display_name || emp.name).charAt(0).toUpperCase() }}</div>
                                                        <span :class="['min-w-0 flex-1 truncate text-xs', emp.id === employee?.id ? 'font-semibold text-primary' : 'text-gray-700']">{{ emp.display_name || emp.name }}<span v-if="emp.id === employee?.id" class="ml-1 font-normal text-primary/70">(Anda)</span></span>
                                                        <span class="shrink-0 text-xs font-bold text-indigo-600">{{ topByProjectsCount(emp) }} {{ topByProjectsLabel() }}</span>
                                                    </div>
                                                </template>
                                                <div v-else class="px-4 py-6 text-center text-xs text-gray-400">Belum ada data</div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader class="pb-2">
                                        <CardTitle class="text-sm font-semibold">Top 10 Capaian Terbesar</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div v-if="top_employees_by_achievement?.length" class="mb-3 h-52">
                                            <VisXYContainer :data="empByAchievementUnovisData" :yDomain="[0, 100]" :style="{ height: '100%' }">
                                                <VisGroupedBar orientation="horizontal" :x="empAchievementX" :y="empAchievementY" :color="empAchievementColor" :roundedCorners="4" />
                                                <VisAxis type="x" :tickFormat="empAchievementXTickFormat" />
                                                <VisAxis type="y" :tickFormat="empAchievementYTickFormat" :gridLine="false" :tickTextFontSize="'10px'" />
                                                <VisTooltip :triggers="empAchievementTooltipTriggers" />
                                            </VisXYContainer>
                                        </div>
                                        <div v-else class="mb-3 flex h-52 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50">
                                            <p class="text-sm text-gray-400">Belum ada data capaian bulan ini</p>
                                        </div>
                                        <div class="relative">
                                            <div class="max-h-52 overflow-y-auto divide-y divide-gray-100 rounded-md border [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                                                <template v-if="top_employees_by_achievement?.length">
                                                    <div v-for="(emp, idx) in top_employees_by_achievement" :key="emp.id" :class="['flex items-center gap-3 px-3 py-2', emp.id === employee?.id ? 'bg-primary/5' : '']">
                                                        <span class="w-5 shrink-0 text-right text-xs font-bold text-gray-400">{{ idx + 1 }}</span>
                                                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700">{{ (emp.display_name || emp.name).charAt(0).toUpperCase() }}</div>
                                                        <div class="min-w-0 flex-1">
                                                            <p :class="['truncate text-xs', emp.id === employee?.id ? 'font-semibold text-primary' : 'text-gray-700']">{{ emp.display_name || emp.name }}<span v-if="emp.id === employee?.id" class="ml-1 font-normal text-primary/70">(Anda)</span></p>
                                                            <Progress :model-value="emp.avg_achievement ?? 0" class="mt-0.5 h-1" :indicator-class="progressVariant(emp.avg_achievement ?? 0)" />
                                                        </div>
                                                        <span :class="['shrink-0 text-xs font-bold', achievementColor(emp.avg_achievement ?? 0)]">{{ (emp.avg_achievement ?? 0).toFixed(1) }}%</span>
                                                    </div>
                                                </template>
                                                <div v-else class="px-4 py-6 text-center text-xs text-gray-400">Belum ada data</div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <!-- No projects -->
                            <div v-if="!projects?.length" class="mt-10 py-12 text-center text-gray-400">
                                <p>Belum ada proyek untuk periode ini.</p>
                            </div>

                            <!-- Personal projects grouped by team -->
                            <div v-else class="mt-10 space-y-10">
                                <div v-for="group in projectsByTeam" :key="group.teamName">
                                    <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                                        <span class="h-px flex-1 bg-primary/20"></span>
                                        {{ group.teamName }}
                                        <span class="h-px flex-1 bg-primary/20"></span>
                                    </h2>
                                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        <Card
                                            v-for="project in group.projects"
                                            :key="project.id"
                                            class="hover:shadow-md transition-shadow"
                                        >
                                            <CardHeader class="pb-2">
                                                <CardTitle class="text-sm font-medium leading-tight">{{ project.name }}</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div class="flex items-baseline justify-between">
                                                    <span class="text-xs text-gray-500">Capaian bulan ini</span>
                                                    <span :class="['text-xl font-bold', achievementColor(projectAvg(project))]">
                                                        {{ projectAvg(project).toFixed(1) }}%
                                                    </span>
                                                </div>
                                                <Progress
                                                    :model-value="projectAvg(project)"
                                                    class="mt-2 h-2" :indicator-class="progressVariant(projectAvg(project))"
                                                />
                                                <p class="mt-2 text-xs text-gray-400">
                                                    {{ project.work_items.length }} item kerja
                                                </p>
                                            </CardContent>
                                        </Card>
                                    </div>
                                </div>
                            </div>
                        </TabsContent>
                        <TabsContent value="team">
                            <div class="space-y-4">
                                <Card v-for="ledProject in team_projects" :key="ledProject.id" class="overflow-hidden">
                                    <CardHeader class="pb-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle class="text-sm font-semibold text-gray-800">{{ ledProject.name }}</CardTitle>
                                                <p v-if="ledProject.team" class="mt-0.5 text-xs text-gray-500">
                                                    {{ ledProject.team.name }}
                                                </p>
                                            </div>
                                            <div class="shrink-0 text-right text-xs text-gray-500">
                                                <span class="font-medium text-gray-700">{{ ledProjectSubmittedCount(ledProject) }}</span>
                                                <span class="text-gray-400"> / {{ ledProjectMemberCount(ledProject) }}</span>
                                                <p class="text-gray-400">sudah input</p>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent class="pt-0">
                                        <!-- Member list -->
                                        <div class="flex items-center gap-2">
                                            <!-- Pinned ketua -->
                                            <template v-for="member in ledProject.members" :key="'lead-'+member.id">
                                                <div
                                                    v-if="isProjectLeader(member)"
                                                    class="flex shrink-0 items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs text-amber-800"
                                                >
                                                    <span class="text-amber-500" aria-label="Ketua Proyek">&#9733;</span>
                                                    <span>{{ member.display_name || member.name }}</span>
                                                    <Badge class="ml-0.5 h-4 bg-amber-500 px-1.5 text-[10px] text-white hover:bg-amber-500">Ketua Proyek</Badge>
                                                </div>
                                            </template>

                                            <span
                                                v-if="ledProject.members.some(m => isProjectLeader(m)) && ledProject.members.some(m => !isProjectLeader(m))"
                                                class="h-6 w-px shrink-0 bg-gray-200"
                                            />

                                            <div v-if="ledProject.members.some(m => !isProjectLeader(m))" class="relative min-w-0 flex-1">
                                                <div
                                                    class="flex gap-2 overflow-x-auto [&::-webkit-scrollbar]:hidden [scrollbar-width:none]"
                                                    :ref="(el) => initChipScrollable(el as HTMLElement | null, ledProject.id)"
                                                >
                                                    <template v-for="member in ledProject.members" :key="member.id">
                                                        <div
                                                            v-if="!isProjectLeader(member)"
                                                            class="flex shrink-0 items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs text-gray-700"
                                                        >
                                                            <span>{{ member.display_name || member.name }}</span>
                                                        </div>
                                                    </template>
                                                </div>
                                                <div v-if="chipScrollable[ledProject.id]" class="pointer-events-none absolute inset-y-0 right-0 flex items-center bg-gradient-to-l from-white via-white/70 to-transparent pl-6 pr-1">
                                                    <svg class="h-4 w-4 animate-bounce-x text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </TabsContent>
                    </Tabs>
                </template>
                <template v-else>
                    <!-- Non-lead: show personal stats and projects directly -->
                    <!-- Personal stat cards -->
                    <div v-if="personal_stats" class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Tim Kerja -->
                        <div class="rounded-lg border bg-white p-6 shadow-sm">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100" aria-hidden="true">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-500">Tim Kerja</p>
                                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ personal_stats.teams_count }}</p>
                                </div>
                            </div>
                        </div>
                        <!-- Proyek -->
                        <div class="rounded-lg border bg-white p-6 shadow-sm">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-100" aria-hidden="true">
                                    <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-500">Proyek</p>
                                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ personal_stats.projects_count }}</p>
                                </div>
                            </div>
                        </div>
                        <!-- Item Kerja -->
                        <div class="rounded-lg border bg-white p-6 shadow-sm">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-teal-100" aria-hidden="true">
                                    <svg class="h-6 w-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-500">Item Kerja</p>
                                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ personal_stats.items_count }}</p>
                                </div>
                            </div>
                        </div>
                        <!-- Rata-rata Capaian -->
                        <div class="rounded-lg border bg-white p-6 shadow-sm">
                            <div class="flex items-start gap-4">
                                <div :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded-full', avgIconBgColor(personal_stats.avg_achievement)]" aria-hidden="true">
                                    <svg :class="['h-6 w-6', avgIconColor(personal_stats.avg_achievement)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-500">Rata-rata Capaian</p>
                                    <p :class="['mt-1 text-2xl font-bold', achievementColor(personal_stats.avg_achievement)]">
                                        {{ personal_stats.avg_achievement.toFixed(1) }}%
                                    </p>
                                    <Progress
                                        :model-value="personal_stats.avg_achievement"
                                        class="mt-2 h-1.5" :indicator-class="progressVariant(personal_stats.avg_achievement)"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Team ranking (visible to all staff for comparison) — always shown -->
                    <h2 class="mt-10 mb-4 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                        <span class="h-px flex-1 bg-primary/20"></span>
                        Perbandingan Kinerja Tim
                        <span class="h-px flex-1 bg-primary/20"></span>
                    </h2>
                    <div class="grid gap-4 lg:grid-cols-3">
                        <!-- Bar chart -->
                        <Card class="lg:col-span-2">
                            <CardHeader class="pb-2">
                                <CardTitle class="text-base">Capaian Per Tim</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div v-if="teamList.length" class="h-64">
                                    <VisXYContainer :data="barChartUnovisData" :yDomain="[0, 100]" :style="{ height: '100%' }">
                                                <VisGroupedBar :x="barX" :y="barY" :color="barColor" :roundedCorners="6" />
                                                <VisAxis type="x" :tickFormat="barXTickFormat" :gridLine="false" :tickTextFontSize="'11px'" :tickTextAngle="-30" />
                                                <VisAxis type="y" :tickFormat="barYTickFormat" />
                                                <VisTooltip :triggers="barTooltipTriggers" />
                                            </VisXYContainer>
                                </div>
                                <div v-else class="flex h-64 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50">
                                    <p class="text-sm text-gray-400">Belum ada data capaian tim bulan ini</p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Team ranking list -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-base">Peringkat Tim</CardTitle>
                            </CardHeader>
                            <CardContent class="p-0">
                                <div class="relative">
                                    <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                                        <template v-if="teamList.length">
                                            <div v-for="(team, idx) in teamList" :key="team.id">
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors"
                                                    :aria-expanded="isTeamExpanded(team.id)"
                                                    @click="toggleTeam(team.id)"
                                                >
                                                    <span class="w-5 shrink-0 text-right text-xs font-bold text-gray-400">
                                                        {{ idx + 1 }}
                                                    </span>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-sm font-medium">{{ team.name }}</p>
                                                        <div class="mt-1 h-1.5 w-full rounded-full bg-gray-200 overflow-hidden">
                                                            <div :class="['h-full rounded-full transition-all', progressVariant(team.avg)]" :style="`width: ${team.avg}%`" />
                                                        </div>
                                                    </div>
                                                    <span :class="['shrink-0 text-sm font-bold', achievementColor(team.avg)]">
                                                        {{ team.avg.toFixed(1) }}%
                                                    </span>
                                                    <svg
                                                        :class="['h-4 w-4 shrink-0 text-gray-400 transition-transform', isTeamExpanded(team.id) ? 'rotate-180' : '']"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    >
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                    </svg>
                                                </button>
                                                <div
                                                    v-if="isTeamExpanded(team.id)"
                                                    class="border-t border-gray-100 bg-gray-50 px-4 py-3"
                                                >
                                                    <p class="mb-2 text-xs font-medium text-gray-500 uppercase tracking-wide">Anggota Tim</p>
                                                    <div class="flex flex-wrap gap-1.5">
                                                        <span
                                                            v-for="member in (team as TeamWithMembers).employees"
                                                            :key="member.id"
                                                            :class="[
                                                                'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs',
                                                                isProjectLeaderById(member.id)
                                                                    ? 'border-amber-300 bg-amber-50 text-amber-800'
                                                                    : 'border-gray-200 bg-white text-gray-600'
                                                            ]"
                                                        >
                                                            <span v-if="isProjectLeaderById(member.id)" class="text-amber-500" aria-label="Ketua Proyek">&#9733;</span>
                                                            {{ member.display_name || member.name }}
                                                            <Badge
                                                                v-if="isProjectLeaderById(member.id)"
                                                                class="ml-0.5 h-3.5 bg-amber-500 px-1 text-[9px] leading-none text-white hover:bg-amber-500"
                                                            >Ketua Proyek</Badge>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <div v-else class="px-4 py-8 text-center text-sm text-gray-400">
                                            Belum ada data tim
                                        </div>
                                    </div>
                                    <div v-if="teamList.length > 4" class="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center bg-gradient-to-t from-white via-white/60 to-transparent py-1.5">
                                        <svg class="h-4 w-4 animate-bounce text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Top 10 Pegawai rankings — always visible -->
                    <h2 class="mt-10 mb-4 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                        <span class="h-px flex-1 bg-primary/20"></span>
                        Peringkat Pegawai
                        <span class="h-px flex-1 bg-primary/20"></span>
                    </h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <!-- Top 10 by project count -->
                        <Card>
                            <CardHeader class="pb-2">
                                <div class="flex items-center justify-between gap-2">
                                    <CardTitle class="text-sm font-semibold">Top 10 Proyek Terbanyak</CardTitle>
                                    <div class="flex divide-x rounded-md border text-xs overflow-hidden">
                                        <button @click="topByProjectsTab = 'semua'" :class="[topByProjectsTab === 'semua' ? 'bg-primary text-white' : 'text-gray-500 hover:bg-gray-50', 'px-2 py-1']">Semua</button>
                                        <button @click="topByProjectsTab = 'ketua'" :class="[topByProjectsTab === 'ketua' ? 'bg-primary text-white' : 'text-gray-500 hover:bg-gray-50', 'px-2 py-1']">Ketua</button>
                                        <button @click="topByProjectsTab = 'anggota'" :class="[topByProjectsTab === 'anggota' ? 'bg-primary text-white' : 'text-gray-500 hover:bg-gray-50', 'px-2 py-1']">Anggota</button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <!-- Chart -->
                                <div v-if="topByProjectsFiltered.length" class="mb-3 h-52">
                                    <VisXYContainer :data="empByProjectsUnovisData" :style="{ height: '100%' }">
                                                <VisGroupedBar orientation="horizontal" :x="empProjectsX" :y="empProjectsY" :color="empProjectsColor" :roundedCorners="4" />
                                                <VisAxis type="x" :tickFormat="empProjectsXTickFormat" />
                                                <VisAxis type="y" :tickFormat="empProjectsYTickFormat" :gridLine="false" :tickTextFontSize="'10px'" />
                                                <VisTooltip :triggers="empProjectsTooltipTriggers" />
                                            </VisXYContainer>
                                </div>
                                <div v-else class="mb-3 flex h-52 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50">
                                    <p class="text-sm text-gray-400">Belum ada data proyek bulan ini</p>
                                </div>
                                <!-- Scrollable list -->
                                <div class="relative">
                                    <div class="max-h-52 overflow-y-auto divide-y divide-gray-100 rounded-md border [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                                        <template v-if="topByProjectsFiltered.length">
                                            <div
                                                v-for="(emp, idx) in topByProjectsFiltered"
                                                :key="emp.id"
                                                :class="['flex items-center gap-3 px-3 py-2', emp.id === employee?.id ? 'bg-primary/5' : '']"
                                            >
                                                <span class="w-5 shrink-0 text-right text-xs font-bold text-gray-400">{{ idx + 1 }}</span>
                                                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-700">
                                                    {{ (emp.display_name || emp.name).charAt(0).toUpperCase() }}
                                                </div>
                                                <span :class="['min-w-0 flex-1 truncate text-xs', emp.id === employee?.id ? 'font-semibold text-primary' : 'text-gray-700']">
                                                    {{ emp.display_name || emp.name }}
                                                    <span v-if="emp.id === employee?.id" class="ml-1 font-normal text-primary/70">(Anda)</span>
                                                </span>
                                                <span class="shrink-0 text-xs font-bold text-indigo-600">{{ topByProjectsCount(emp) }} {{ topByProjectsLabel() }}</span>
                                            </div>
                                        </template>
                                        <div v-else class="px-4 py-6 text-center text-xs text-gray-400">
                                            Belum ada data
                                        </div>
                                    </div>
                                    <div v-if="topByProjectsFiltered.length > 6" class="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center rounded-b-md bg-gradient-to-t from-white via-white/60 to-transparent py-1">
                                        <svg class="h-3.5 w-3.5 animate-bounce text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Top 10 by achievement -->
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-semibold">Top 10 Capaian Terbesar</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <!-- Chart -->
                                <div v-if="top_employees_by_achievement?.length" class="mb-3 h-52">
                                    <VisXYContainer :data="empByAchievementUnovisData" :yDomain="[0, 100]" :style="{ height: '100%' }">
                                                <VisGroupedBar orientation="horizontal" :x="empAchievementX" :y="empAchievementY" :color="empAchievementColor" :roundedCorners="4" />
                                                <VisAxis type="x" :tickFormat="empAchievementXTickFormat" />
                                                <VisAxis type="y" :tickFormat="empAchievementYTickFormat" :gridLine="false" :tickTextFontSize="'10px'" />
                                                <VisTooltip :triggers="empAchievementTooltipTriggers" />
                                            </VisXYContainer>
                                </div>
                                <div v-else class="mb-3 flex h-52 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50">
                                    <p class="text-sm text-gray-400">Belum ada data capaian bulan ini</p>
                                </div>
                                <!-- Scrollable list -->
                                <div class="relative">
                                    <div class="max-h-52 overflow-y-auto divide-y divide-gray-100 rounded-md border [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                                        <template v-if="top_employees_by_achievement?.length">
                                            <div
                                                v-for="(emp, idx) in top_employees_by_achievement"
                                                :key="emp.id"
                                                :class="['flex items-center gap-3 px-3 py-2', emp.id === employee?.id ? 'bg-primary/5' : '']"
                                            >
                                                <span class="w-5 shrink-0 text-right text-xs font-bold text-gray-400">{{ idx + 1 }}</span>
                                                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700">
                                                    {{ (emp.display_name || emp.name).charAt(0).toUpperCase() }}
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p :class="['truncate text-xs', emp.id === employee?.id ? 'font-semibold text-primary' : 'text-gray-700']">
                                                        {{ emp.display_name || emp.name }}
                                                        <span v-if="emp.id === employee?.id" class="ml-1 font-normal text-primary/70">(Anda)</span>
                                                    </p>
                                                    <Progress
                                                        :model-value="emp.avg_achievement ?? 0"
                                                        class="mt-0.5 h-1" :indicator-class="progressVariant(emp.avg_achievement ?? 0)"
                                                    />
                                                </div>
                                                <span :class="['shrink-0 text-xs font-bold', achievementColor(emp.avg_achievement ?? 0)]">
                                                    {{ (emp.avg_achievement ?? 0).toFixed(1) }}%
                                                </span>
                                            </div>
                                        </template>
                                        <div v-else class="px-4 py-6 text-center text-xs text-gray-400">
                                            Belum ada data
                                        </div>
                                    </div>
                                    <div v-if="(top_employees_by_achievement?.length ?? 0) > 6" class="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center rounded-b-md bg-gradient-to-t from-white via-white/60 to-transparent py-1">
                                        <svg class="h-3.5 w-3.5 animate-bounce text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- No projects -->
                    <div v-if="!projects?.length" class="py-12 text-center text-gray-400">
                        <p>Belum ada proyek untuk periode ini.</p>
                    </div>

                    <!-- Personal projects grouped by team -->
                    <div v-else class="space-y-10">
                        <div v-for="group in projectsByTeam" :key="group.teamName">
                            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                                <span class="h-px flex-1 bg-primary/20"></span>
                                {{ group.teamName }}
                                <span class="h-px flex-1 bg-primary/20"></span>
                            </h2>
                            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <Card
                                    v-for="project in group.projects"
                                    :key="project.id"
                                    class="hover:shadow-md transition-shadow"
                                >
                                    <CardHeader class="pb-2">
                                        <CardTitle class="text-sm font-medium leading-tight">{{ project.name }}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="flex items-baseline justify-between">
                                            <span class="text-xs text-gray-500">Capaian bulan ini</span>
                                            <span :class="['text-xl font-bold', achievementColor(projectAvg(project))]">
                                                {{ projectAvg(project).toFixed(1) }}%
                                            </span>
                                        </div>
                                        <Progress
                                            :model-value="projectAvg(project)"
                                            class="mt-2 h-2" :indicator-class="progressVariant(projectAvg(project))"
                                        />
                                        <p class="mt-2 text-xs text-gray-400">
                                            {{ project.work_items.length }} item kerja
                                        </p>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                </template>
            </template>
        </template>

        <!-- ── HEAD view ──────────────────────────────────────────────────── -->
        <template v-else-if="role === 'head'">
            <!-- Personal stat cards for linked head employee -->
            <template v-if="employee && personal_stats">
                <div class="mb-6 flex items-center gap-4 rounded-lg border bg-white p-5 shadow-sm">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xl font-bold text-primary">
                        {{ (employee.display_name || employee.name).charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">{{ employee.display_name || employee.name }}</p>
                        <p class="text-sm text-gray-500">{{ monthLabel }} {{ filters.year }}</p>
                    </div>
                </div>

                <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Tim Kerja -->
                    <div class="rounded-lg border bg-white p-6 shadow-sm">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100" aria-hidden="true">
                                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-500">Tim Kerja</p>
                                <p class="mt-1 text-2xl font-bold text-gray-900">{{ personal_stats.teams_count }}</p>
                            </div>
                        </div>
                    </div>
                    <!-- Proyek -->
                    <div class="rounded-lg border bg-white p-6 shadow-sm">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-100" aria-hidden="true">
                                <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-500">Proyek</p>
                                <p class="mt-1 text-2xl font-bold text-gray-900">{{ personal_stats.projects_count }}</p>
                            </div>
                        </div>
                    </div>
                    <!-- Item Kerja -->
                    <div class="rounded-lg border bg-white p-6 shadow-sm">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-teal-100" aria-hidden="true">
                                <svg class="h-6 w-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-500">Item Kerja</p>
                                <p class="mt-1 text-2xl font-bold text-gray-900">{{ personal_stats.items_count }}</p>
                            </div>
                        </div>
                    </div>
                    <!-- Rata-rata Capaian -->
                    <div class="rounded-lg border bg-white p-6 shadow-sm">
                        <div class="flex items-start gap-4">
                            <div :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded-full', avgIconBgColor(personal_stats.avg_achievement)]" aria-hidden="true">
                                <svg :class="['h-6 w-6', avgIconColor(personal_stats.avg_achievement)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-500">Rata-rata Capaian</p>
                                <p :class="['mt-1 text-2xl font-bold', achievementColor(personal_stats.avg_achievement)]">
                                    {{ personal_stats.avg_achievement.toFixed(1) }}%
                                </p>
                                <Progress
                                    :model-value="personal_stats.avg_achievement"
                                    class="mt-2 h-1.5" :indicator-class="progressVariant(personal_stats.avg_achievement)"
                                />
                            </div>
                        </div>
                    </div>
                </div>

            </template>

            <!-- Ringkasan Tim: always shown for head -->
            <div class="mb-4 flex items-center gap-3">
                <h2 class="text-base font-semibold text-gray-800">Ringkasan Tim</h2>
                <span class="h-px flex-1 bg-gray-200"></span>
            </div>

            <!-- No data state -->
            <div v-if="!teamList.length" class="py-16 text-center text-gray-400">
                <p class="font-medium">Belum ada data laporan untuk periode ini.</p>
                <p class="mt-1 text-sm">Staf dapat memasukkan laporan kinerja bulan {{ monthLabel }}.</p>
            </div>

            <template v-else>
                <div class="grid gap-6 lg:grid-cols-5">
                    <!-- Bar chart -->
                    <Card class="lg:col-span-3">
                        <CardHeader>
                            <CardTitle class="text-base">Capaian Per Tim — {{ monthLabel }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="h-64">
                                <VisXYContainer :data="barChartUnovisData" :yDomain="[0, 100]" :style="{ height: '100%' }">
                                                <VisGroupedBar :x="barX" :y="barY" :color="barColor" :roundedCorners="6" />
                                                <VisAxis type="x" :tickFormat="barXTickFormat" :gridLine="false" :tickTextFontSize="'11px'" :tickTextAngle="-30" />
                                                <VisAxis type="y" :tickFormat="barYTickFormat" />
                                                <VisTooltip :triggers="barTooltipTriggers" />
                                            </VisXYContainer>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Team ranking with expandable member list -->
                    <Card class="lg:col-span-2">
                        <CardHeader>
                            <CardTitle class="text-base">Peringkat Tim</CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="relative">
                                <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                                    <div v-for="(team, idx) in teamList" :key="team.id">
                                        <!-- Team row (clickable to expand members) -->
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors"
                                            :aria-expanded="isTeamExpanded(team.id)"
                                            @click="toggleTeam(team.id)"
                                        >
                                            <span class="w-5 shrink-0 text-right text-xs font-bold text-gray-400">
                                                {{ idx + 1 }}
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium">{{ team.name }}</p>
                                                <Progress :model-value="team.avg" class="mt-1 h-1.5" :indicator-class="progressVariant(team.avg)" />
                                            </div>
                                            <span :class="['shrink-0 text-sm font-bold', achievementColor(team.avg)]">
                                                {{ team.avg.toFixed(1) }}%
                                            </span>
                                            <!-- Chevron -->
                                            <svg
                                                :class="['h-4 w-4 shrink-0 text-gray-400 transition-transform', isTeamExpanded(team.id) ? 'rotate-180' : '']"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                        <!-- Expandable member chips -->
                                        <div
                                            v-if="isTeamExpanded(team.id)"
                                            class="border-t border-gray-100 bg-gray-50 px-4 py-3"
                                        >
                                            <p class="mb-2 text-xs font-medium text-gray-500 uppercase tracking-wide">Anggota Tim</p>
                                            <div class="flex flex-wrap gap-1.5">
                                                <span
                                                    v-for="member in (team as TeamWithMembers).employees"
                                                    :key="member.id"
                                                    :class="[
                                                        'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs',
                                                        isProjectLeaderById(member.id)
                                                            ? 'border-amber-300 bg-amber-50 text-amber-800'
                                                            : 'border-gray-200 bg-white text-gray-600'
                                                    ]"
                                                >
                                                    <span v-if="isProjectLeaderById(member.id)" class="text-amber-500" aria-label="Ketua Proyek">&#9733;</span>
                                                    {{ member.display_name || member.name }}
                                                    <Badge
                                                        v-if="isProjectLeaderById(member.id)"
                                                        class="ml-0.5 h-3.5 bg-amber-500 px-1 text-[9px] leading-none text-white hover:bg-amber-500"
                                                    >Ketua Proyek</Badge>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center bg-gradient-to-t from-white via-white/60 to-transparent py-1.5">
                                    <svg class="h-4 w-4 animate-bounce text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Employee rankings (same as staff view) -->
                <h2 class="mt-10 mb-4 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                    <span class="h-px flex-1 bg-primary/20"></span>
                    Peringkat Pegawai
                    <span class="h-px flex-1 bg-primary/20"></span>
                </h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <!-- Top 10 by project count -->
                    <Card>
                        <CardHeader class="pb-2">
                            <div class="flex items-center justify-between gap-2">
                                <CardTitle class="text-sm font-semibold">Top 10 Proyek Terbanyak</CardTitle>
                                <div class="flex divide-x rounded-md border text-xs overflow-hidden">
                                    <button @click="topByProjectsTab = 'semua'" :class="[topByProjectsTab === 'semua' ? 'bg-primary text-white' : 'text-gray-500 hover:bg-gray-50', 'px-2 py-1']">Semua</button>
                                    <button @click="topByProjectsTab = 'ketua'" :class="[topByProjectsTab === 'ketua' ? 'bg-primary text-white' : 'text-gray-500 hover:bg-gray-50', 'px-2 py-1']">Ketua</button>
                                    <button @click="topByProjectsTab = 'anggota'" :class="[topByProjectsTab === 'anggota' ? 'bg-primary text-white' : 'text-gray-500 hover:bg-gray-50', 'px-2 py-1']">Anggota</button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="topByProjectsFiltered.length" class="mb-3 h-52">
                                <VisXYContainer :data="empByProjectsUnovisData" :style="{ height: '100%' }">
                                                <VisGroupedBar orientation="horizontal" :x="empProjectsX" :y="empProjectsY" :color="empProjectsColor" :roundedCorners="4" />
                                                <VisAxis type="x" :tickFormat="empProjectsXTickFormat" />
                                                <VisAxis type="y" :tickFormat="empProjectsYTickFormat" :gridLine="false" :tickTextFontSize="'10px'" />
                                                <VisTooltip :triggers="empProjectsTooltipTriggers" />
                                            </VisXYContainer>
                            </div>
                            <div v-else class="mb-3 flex h-52 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50">
                                <p class="text-sm text-gray-400">Belum ada data proyek bulan ini</p>
                            </div>
                            <div class="relative">
                                <div class="max-h-52 overflow-y-auto divide-y divide-gray-100 rounded-md border [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                                    <template v-if="topByProjectsFiltered.length">
                                        <div v-for="(emp, idx) in topByProjectsFiltered" :key="emp.id" class="flex items-center gap-3 px-3 py-2">
                                            <span class="w-5 shrink-0 text-right text-xs font-bold text-gray-400">{{ idx + 1 }}</span>
                                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-700">
                                                {{ (emp.display_name || emp.name).charAt(0).toUpperCase() }}
                                            </div>
                                            <span class="min-w-0 flex-1 truncate text-xs text-gray-700">{{ emp.display_name || emp.name }}</span>
                                            <span class="shrink-0 text-xs font-bold text-indigo-600">{{ topByProjectsCount(emp) }} {{ topByProjectsLabel() }}</span>
                                        </div>
                                    </template>
                                    <div v-else class="px-4 py-6 text-center text-xs text-gray-400">Belum ada data</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <!-- Top 10 by achievement -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-semibold">Top 10 Capaian Terbesar</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="top_employees_by_achievement?.length" class="mb-3 h-52">
                                <VisXYContainer :data="empByAchievementUnovisData" :yDomain="[0, 100]" :style="{ height: '100%' }">
                                                <VisGroupedBar orientation="horizontal" :x="empAchievementX" :y="empAchievementY" :color="empAchievementColor" :roundedCorners="4" />
                                                <VisAxis type="x" :tickFormat="empAchievementXTickFormat" />
                                                <VisAxis type="y" :tickFormat="empAchievementYTickFormat" :gridLine="false" :tickTextFontSize="'10px'" />
                                                <VisTooltip :triggers="empAchievementTooltipTriggers" />
                                            </VisXYContainer>
                            </div>
                            <div v-else class="mb-3 flex h-52 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50">
                                <p class="text-sm text-gray-400">Belum ada data capaian bulan ini</p>
                            </div>
                            <div class="relative">
                                <div class="max-h-52 overflow-y-auto divide-y divide-gray-100 rounded-md border [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                                    <template v-if="top_employees_by_achievement?.length">
                                        <div v-for="(emp, idx) in top_employees_by_achievement" :key="emp.id" class="flex items-center gap-3 px-3 py-2">
                                            <span class="w-5 shrink-0 text-right text-xs font-bold text-gray-400">{{ idx + 1 }}</span>
                                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700">
                                                {{ (emp.display_name || emp.name).charAt(0).toUpperCase() }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-xs text-gray-700">{{ emp.display_name || emp.name }}</p>
                                                <Progress :model-value="emp.avg_achievement ?? 0" class="mt-0.5 h-1" :indicator-class="progressVariant(emp.avg_achievement ?? 0)" />
                                            </div>
                                            <span :class="['shrink-0 text-xs font-bold', achievementColor(emp.avg_achievement ?? 0)]">
                                                {{ (emp.avg_achievement ?? 0).toFixed(1) }}%
                                            </span>
                                        </div>
                                    </template>
                                    <div v-else class="px-4 py-6 text-center text-xs text-gray-400">Belum ada data</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <!-- Personal projects for head (shown after team summary) -->
            <template v-if="projectsByTeam.length">
                <div class="mt-8 mb-4 flex items-center gap-3">
                    <h2 class="text-base font-semibold text-gray-800">Proyek Saya</h2>
                    <span class="h-px flex-1 bg-gray-200"></span>
                </div>
                <div class="space-y-10">
                    <div v-for="group in projectsByTeam" :key="group.teamName">
                        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                            <span class="h-px flex-1 bg-primary/20"></span>
                            {{ group.teamName }}
                            <span class="h-px flex-1 bg-primary/20"></span>
                        </h3>
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <Card v-for="project in group.projects" :key="project.id" class="transition-shadow hover:shadow-md">
                                <CardHeader class="pb-2">
                                    <CardTitle class="text-sm font-medium leading-tight">{{ project.name }}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="flex items-baseline justify-between">
                                        <span class="text-xs text-gray-500">Capaian</span>
                                        <span :class="['text-xl font-bold', achievementColor(projectAvg(project))]">
                                            {{ projectAvg(project).toFixed(1) }}%
                                        </span>
                                    </div>
                                    <Progress :model-value="projectAvg(project)" class="mt-2 h-2" :indicator-class="progressVariant(projectAvg(project))" />
                                    <p class="mt-2 text-xs text-gray-400">{{ project.work_items.length }} item kerja</p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </template>
        </template>

        <!-- ── ADMIN view ──────────────────────────────────────────────────── -->
        <template v-else>
            <!-- Admin KPI cards -->
            <div class="mb-6 grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            Rata-rata Organisasi
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p :class="['text-3xl font-bold', achievementColor(org_avg ?? 0)]">
                            {{ (org_avg ?? 0).toFixed(1) }}%
                        </p>
                        <Progress :model-value="org_avg ?? 0" class="mt-2 h-1.5" :indicator-class="progressVariant(org_avg ?? 0)" />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            Tim Aktif
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-gray-800">{{ teamList.length }}</p>
                        <p class="mt-1 text-xs text-gray-400">tim dengan laporan</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            Tim Capaian &#8805; 80%
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-green-600">
                            {{ teamList.filter(t => t.avg >= 80).length }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">dari {{ teamList.length }} tim</p>
                    </CardContent>
                </Card>
            </div>

            <!-- No data state -->
            <div v-if="!teamList.length" class="py-16 text-center text-gray-400">
                <p class="font-medium">Belum ada data laporan untuk periode ini.</p>
                <p class="mt-1 text-sm">Staf dapat memasukkan laporan kinerja bulan {{ monthLabel }}.</p>
            </div>

            <template v-else>
                <!-- Bar chart + team ranking side-by-side -->
                <div class="grid gap-6 lg:grid-cols-5">
                    <!-- Bar chart -->
                    <Card class="lg:col-span-3">
                        <CardHeader>
                            <CardTitle class="text-base">Capaian Per Tim — {{ monthLabel }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="h-64">
                                <VisXYContainer :data="barChartUnovisData" :yDomain="[0, 100]" :style="{ height: '100%' }">
                                                <VisGroupedBar :x="barX" :y="barY" :color="barColor" :roundedCorners="6" />
                                                <VisAxis type="x" :tickFormat="barXTickFormat" :gridLine="false" :tickTextFontSize="'11px'" :tickTextAngle="-30" />
                                                <VisAxis type="y" :tickFormat="barYTickFormat" />
                                                <VisTooltip :triggers="barTooltipTriggers" />
                                            </VisXYContainer>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Team ranking with expandable member list -->
                    <Card class="lg:col-span-2">
                        <CardHeader>
                            <CardTitle class="text-base">Peringkat Tim</CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="relative">
                                <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                                    <div v-for="(team, idx) in teamList" :key="team.id">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors"
                                            :aria-expanded="isTeamExpanded(team.id)"
                                            @click="toggleTeam(team.id)"
                                        >
                                            <span class="w-5 shrink-0 text-right text-xs font-bold text-gray-400">
                                                {{ idx + 1 }}
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium">{{ team.name }}</p>
                                                <Progress :model-value="team.avg" class="mt-1 h-1.5" :indicator-class="progressVariant(team.avg)" />
                                            </div>
                                            <span :class="['shrink-0 text-sm font-bold', achievementColor(team.avg)]">
                                                {{ team.avg.toFixed(1) }}%
                                            </span>
                                            <svg
                                                :class="['h-4 w-4 shrink-0 text-gray-400 transition-transform', isTeamExpanded(team.id) ? 'rotate-180' : '']"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                        <!-- Expandable member chips -->
                                        <div
                                            v-if="isTeamExpanded(team.id)"
                                            class="border-t border-gray-100 bg-gray-50 px-4 py-3"
                                        >
                                            <p class="mb-2 text-xs font-medium text-gray-500 uppercase tracking-wide">Anggota Tim</p>
                                            <div class="flex flex-wrap gap-1.5">
                                                <span
                                                    v-for="member in (team as TeamWithMembers).employees"
                                                    :key="member.id"
                                                    :class="[
                                                        'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs',
                                                        isProjectLeaderById(member.id)
                                                            ? 'border-amber-300 bg-amber-50 text-amber-800'
                                                            : 'border-gray-200 bg-white text-gray-600'
                                                    ]"
                                                >
                                                    <span v-if="isProjectLeaderById(member.id)" class="text-amber-500" aria-label="Ketua Proyek">&#9733;</span>
                                                    {{ member.display_name || member.name }}
                                                    <Badge
                                                        v-if="isProjectLeaderById(member.id)"
                                                        class="ml-0.5 h-3.5 bg-amber-500 px-1 text-[9px] leading-none text-white hover:bg-amber-500"
                                                    >Ketua Proyek</Badge>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center bg-gradient-to-t from-white via-white/60 to-transparent py-1.5">
                                    <svg class="h-4 w-4 animate-bounce text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Admin: 12-month trend line chart -->
                <Card class="mt-6">
                    <CardHeader>
                        <CardTitle class="text-base">Tren Capaian 12 Bulan — {{ filters.year }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="h-52">
                            <VisXYContainer :data="trendUnovisData" :yDomain="[0, 100]" :style="{ height: '100%' }">
                                <VisArea :x="trendX" :y="trendY" :color="trendAreaColor" :curveType="CurveType.MonotoneX" :opacity="1" />
                                <VisLine :x="trendX" :y="trendY" :color="trendLineColor" :curveType="CurveType.MonotoneX" />
                                <VisAxis type="x" :tickFormat="trendXTickFormat" :numTicks="12" :gridLine="false" />
                                <VisAxis type="y" :tickFormat="trendYTickFormat" />
                                <VisTooltip :triggers="trendTooltipTriggers" />
                            </VisXYContainer>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </template>
    </AppLayout>
</template>
