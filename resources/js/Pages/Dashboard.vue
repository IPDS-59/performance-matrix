<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { Employee, Team } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Progress } from '@/Components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Bar, Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    Title,
    Tooltip,
    Legend,
    Filler,
);

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
    members?: TeamMember[];
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
    if (pct >= 80) return '[&>div]:bg-green-500';
    if (pct >= 50) return '[&>div]:bg-yellow-500';
    return '[&>div]:bg-red-500';
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
    return reports.reduce((s, r) => s + r.achievement_percentage, 0) / reports.length;
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

// ── Bar chart ──────────────────────────────────────────────────────────────

const barChartData = computed(() => ({
    labels: teamList.value.map(t => t.name),
    datasets: [{
        label: 'Capaian (%)',
        data: teamList.value.map(t => t.avg),
        backgroundColor: teamList.value.map(t =>
            t.avg >= 80 ? 'rgba(34,197,94,0.75)' :
            t.avg >= 50 ? 'rgba(234,179,8,0.75)' :
            'rgba(239,68,68,0.75)'
        ),
        borderRadius: 6,
        borderSkipped: false,
    }],
}));

const barChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                label: (ctx: any) => ` ${ctx.parsed.y.toFixed(1)}%`,
            },
        },
    },
    scales: {
        y: {
            min: 0,
            max: 100,
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            ticks: { callback: (v: any) => `${v}%` },
            grid: { color: 'rgba(0,0,0,0.05)' },
        },
        x: {
            grid: { display: false },
            ticks: {
                maxRotation: 30,
                font: { size: 11 },
            },
        },
    },
};

// ── Line chart ─────────────────────────────────────────────────────────────

const trendLabels = months.map(m => m.label.substring(0, 3));

const lineChartData = computed(() => {
    const dataByMonth: (number | null)[] = Array(12).fill(null);
    for (const point of props.trend ?? []) {
        dataByMonth[point.period_month - 1] = point.avg_achievement;
    }
    return {
        labels: trendLabels,
        datasets: [{
            label: 'Rata-rata Capaian',
            data: dataByMonth,
            borderColor: '#1B4B8A',
            backgroundColor: 'rgba(27,75,138,0.08)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#1B4B8A',
        }],
    };
});

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                label: (ctx: any) => ` ${ctx.parsed.y?.toFixed(1) ?? '-'}%`,
            },
        },
    },
    scales: {
        y: {
            min: 0,
            max: 100,
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            ticks: { callback: (v: any) => `${v}%` },
            grid: { color: 'rgba(0,0,0,0.05)' },
        },
        x: { grid: { display: false } },
    },
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
                <!-- Welcome card -->
                <div class="mb-6 flex items-center gap-4 rounded-lg border bg-white p-5 shadow-sm">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xl font-bold text-primary">
                        {{ (employee.display_name || employee.name).charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">{{ employee.display_name || employee.name }}</p>
                        <p class="text-sm text-gray-500">{{ monthLabel }} {{ filters.year }}</p>
                    </div>
                </div>

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
                                    :class="['mt-2 h-1.5', progressVariant(personal_stats.avg_achievement)]"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No projects -->
                <div v-if="!projects?.length" class="py-12 text-center text-gray-400">
                    <p>Belum ada proyek untuk periode ini.</p>
                </div>

                <!-- Personal projects grouped by team -->
                <div v-else class="space-y-6">
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
                                        :class="['mt-2 h-2', progressVariant(projectAvg(project))]"
                                    />
                                    <p class="mt-2 text-xs text-gray-400">
                                        {{ project.work_items.length }} item kerja
                                    </p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>

                <!-- Led projects section (team lead staff) -->
                <template v-if="personal_stats?.is_team_lead && team_projects?.length">
                    <div class="mt-10">
                        <div class="mb-4 flex items-center gap-3">
                            <h2 class="text-base font-semibold text-gray-800">Proyek yang Saya Pimpin</h2>
                            <span class="h-px flex-1 bg-gray-200"></span>
                            <Badge variant="outline" class="text-xs">{{ team_projects.length }} proyek</Badge>
                        </div>
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
                                    <div class="flex flex-wrap gap-2">
                                        <div
                                            v-for="member in ledProject.members"
                                            :key="member.id"
                                            :class="[
                                                'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs',
                                                isProjectLeader(member)
                                                    ? 'border-amber-300 bg-amber-50 text-amber-800'
                                                    : 'border-gray-200 bg-gray-50 text-gray-700'
                                            ]"
                                        >
                                            <span v-if="isProjectLeader(member)" class="text-amber-500" aria-label="Ketua">&#9733;</span>
                                            <span>{{ member.display_name || member.name }}</span>
                                            <Badge
                                                v-if="isProjectLeader(member)"
                                                class="ml-0.5 h-4 bg-amber-500 px-1.5 text-[10px] text-white hover:bg-amber-500"
                                            >Ketua</Badge>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
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
                                    :class="['mt-2 h-1.5', progressVariant(personal_stats.avg_achievement)]"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4 flex items-center gap-3">
                    <h2 class="text-base font-semibold text-gray-800">Ringkasan Tim</h2>
                    <span class="h-px flex-1 bg-gray-200"></span>
                </div>
            </template>

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
                                <Bar :data="barChartData" :options="barChartOptions" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Team ranking with expandable member list -->
                    <Card class="lg:col-span-2">
                        <CardHeader>
                            <CardTitle class="text-base">Peringkat Tim</CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="max-h-64 overflow-y-auto divide-y divide-gray-100">
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
                                            <Progress :model-value="team.avg" :class="['mt-1 h-1.5', progressVariant(team.avg)]" />
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
                                        v-if="isTeamExpanded(team.id) && (team as TeamWithMembers).members?.length"
                                        class="border-t border-gray-100 bg-gray-50 px-4 py-3"
                                    >
                                        <p class="mb-2 text-xs font-medium text-gray-500 uppercase tracking-wide">Anggota Tim</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span
                                                v-for="member in (team as TeamWithMembers).members"
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
                                                >Ketua</Badge>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
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
                        <Progress :model-value="org_avg ?? 0" :class="['mt-2 h-1.5', progressVariant(org_avg ?? 0)]" />
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
                                <Bar :data="barChartData" :options="barChartOptions" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Team ranking with expandable member list -->
                    <Card class="lg:col-span-2">
                        <CardHeader>
                            <CardTitle class="text-base">Peringkat Tim</CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="max-h-64 overflow-y-auto divide-y divide-gray-100">
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
                                            <Progress :model-value="team.avg" :class="['mt-1 h-1.5', progressVariant(team.avg)]" />
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
                                        v-if="isTeamExpanded(team.id) && (team as TeamWithMembers).members?.length"
                                        class="border-t border-gray-100 bg-gray-50 px-4 py-3"
                                    >
                                        <p class="mb-2 text-xs font-medium text-gray-500 uppercase tracking-wide">Anggota Tim</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span
                                                v-for="member in (team as TeamWithMembers).members"
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
                                                >Ketua</Badge>
                                            </span>
                                        </div>
                                    </div>
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
                            <Line :data="lineChartData" :options="lineChartOptions" />
                        </div>
                    </CardContent>
                </Card>
            </template>
        </template>
    </AppLayout>
</template>
