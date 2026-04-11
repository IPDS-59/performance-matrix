<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { Employee, Project, Team } from '@/types';
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

interface TeamProgress {
    team_id: number;
    avg_achievement: number;
    report_count: number;
}

interface TrendPoint {
    period_month: number;
    avg_achievement: number;
}

const props = defineProps<{
    role: 'admin' | 'head' | 'staff';
    employee?: Employee;
    projects?: (Project & {
        work_items: Array<{
            id: number;
            description: string;
            performance_reports: Array<{ achievement_percentage: number }>;
        }>;
    })[];
    teams?: Team[];
    team_progress?: Record<string, TeamProgress>;
    org_avg?: number;
    trend?: TrendPoint[];
    filters: { year: number; month: number };
}>();

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

function achievementColor(pct: number): string {
    if (pct >= 80) return 'text-green-600';
    if (pct >= 50) return 'text-yellow-500';
    return 'text-red-500';
}

function achievementBg(pct: number): string {
    if (pct >= 80) return 'bg-green-500';
    if (pct >= 50) return 'bg-yellow-500';
    return 'bg-red-500';
}

function progressVariant(pct: number): string {
    if (pct >= 80) return '[&>div]:bg-green-500';
    if (pct >= 50) return '[&>div]:bg-yellow-500';
    return '[&>div]:bg-red-500';
}

function projectAvg(project: NonNullable<typeof props.projects>[number]): number {
    const reports = project.work_items.flatMap(wi => wi.performance_reports);
    if (!reports.length) return 0;
    return reports.reduce((s, r) => s + r.achievement_percentage, 0) / reports.length;
}

// Staff: group projects by team
const projectsByTeam = computed(() => {
    if (!props.projects) return [];
    const groups: Record<number, { teamName: string; projects: NonNullable<typeof props.projects> }> = {};
    for (const p of props.projects) {
        const tid = p.team_id;
        const tname = p.team?.name ?? 'Tim Tidak Diketahui';
        if (!groups[tid]) groups[tid] = { teamName: tname, projects: [] };
        groups[tid].projects.push(p);
    }
    return Object.values(groups).sort((a, b) => a.teamName.localeCompare(b.teamName));
});

// Staff: overall personal avg across all projects
const personalAvg = computed(() => {
    if (!props.projects?.length) return 0;
    const avgs = props.projects.map(p => projectAvg(p));
    return avgs.reduce((s, a) => s + a, 0) / avgs.length;
});

// Head/Admin: sorted team list
const teamList = computed(() => {
    if (!props.teams || !props.team_progress) return [];
    return props.teams.map(t => ({
        ...t,
        avg: props.team_progress![t.id]?.avg_achievement ?? 0,
        count: props.team_progress![t.id]?.report_count ?? 0,
    })).sort((a, b) => b.avg - a.avg);
});

// Bar chart for team progress (head/admin)
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
                label: (ctx: any) => ` ${ctx.parsed.y.toFixed(1)}%`,
            },
        },
    },
    scales: {
        y: {
            min: 0,
            max: 100,
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

// Line chart for admin trend
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
                label: (ctx: any) => ` ${ctx.parsed.y?.toFixed(1) ?? '-'}%`,
            },
        },
    },
    scales: {
        y: {
            min: 0,
            max: 100,
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

        <!-- ── STAFF view ── -->
        <template v-if="role === 'staff'">
            <div v-if="!employee" class="py-20 text-center text-gray-400">
                <p class="text-lg font-medium">Akun Belum Terhubung</p>
                <p class="mt-1 text-sm">Hubungi administrator untuk menghubungkan akun ke data pegawai.</p>
            </div>
            <template v-else>
                <!-- Welcome + personal KPI -->
                <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card class="sm:col-span-2 lg:col-span-2">
                        <CardContent class="flex items-center gap-4 pt-6">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#1B4B8A]/10 text-xl font-bold text-[#1B4B8A]">
                                {{ (employee.display_name || employee.name).charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800">{{ employee.display_name || employee.name }}</p>
                                <p class="text-sm text-gray-500">{{ monthLabel }} {{ filters.year }}</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-xs font-medium text-gray-500 uppercase tracking-wide">Rata-rata Capaian</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p :class="achievementColor(personalAvg)" class="text-3xl font-bold">
                                {{ personalAvg.toFixed(1) }}%
                            </p>
                            <Progress :model-value="personalAvg" :class="['mt-2 h-1.5', progressVariant(personalAvg)]" />
                        </CardContent>
                    </Card>
                </div>

                <!-- No projects -->
                <div v-if="!projects?.length" class="py-12 text-center text-gray-400">
                    <p>Belum ada proyek untuk periode ini.</p>
                </div>

                <!-- Projects grouped by team -->
                <div v-else class="space-y-6">
                    <div v-for="group in projectsByTeam" :key="group.teamName">
                        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-[#1B4B8A] uppercase tracking-wide">
                            <span class="h-px flex-1 bg-[#1B4B8A]/20"></span>
                            {{ group.teamName }}
                            <span class="h-px flex-1 bg-[#1B4B8A]/20"></span>
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
            </template>
        </template>

        <!-- ── HEAD / ADMIN view ── -->
        <template v-else>
            <!-- Admin KPI cards -->
            <div v-if="role === 'admin'" class="mb-6 grid gap-4 sm:grid-cols-3">
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
                            Tim Capaian ≥ 80%
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
                <!-- Bar chart + table side-by-side on large screens -->
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

                    <!-- Team ranking list -->
                    <Card class="lg:col-span-2">
                        <CardHeader>
                            <CardTitle class="text-base">Peringkat Tim</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div v-for="(team, idx) in teamList" :key="team.id" class="flex items-center gap-3">
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
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Admin: 12-month trend line chart -->
                <Card v-if="role === 'admin'" class="mt-6">
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
