<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { Employee, Project, Team } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Progress } from '@/Components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

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
    projects?: (Project & { work_items: Array<{ id: number; description: string; performance_reports: Array<{ achievement_percentage: number }> }> })[];
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
    if (pct >= 50) return 'text-yellow-600';
    return 'text-red-600';
}

function progressVariant(pct: number): string {
    if (pct >= 80) return '[&>div]:bg-green-500';
    if (pct >= 50) return '[&>div]:bg-yellow-500';
    return '[&>div]:bg-red-500';
}

function projectAvg(project: typeof props.projects extends (infer T)[] | undefined ? T : never): number {
    const reports = (project as any).work_items.flatMap((wi: any) => wi.performance_reports);
    if (!reports.length) return 0;
    return reports.reduce((s: number, r: any) => s + r.achievement_percentage, 0) / reports.length;
}

const teamList = computed(() => {
    if (!props.teams || !props.team_progress) return [];
    return props.teams.map(t => ({
        ...t,
        avg: props.team_progress![t.id]?.avg_achievement ?? 0,
        count: props.team_progress![t.id]?.report_count ?? 0,
    })).sort((a, b) => b.avg - a.avg);
});
</script>

<template>
    <Head title="Beranda" />
    <AppLayout>
        <template #title>Beranda — {{ monthLabel }} {{ filters.year }}</template>

        <!-- Filters -->
        <div class="mb-6 flex items-center gap-3">
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
                    <SelectItem v-for="y in [2025, 2026, 2027]" :key="y" :value="y">{{ y }}</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- STAFF view -->
        <template v-if="role === 'staff'">
            <div v-if="!employee" class="text-center py-16 text-gray-500">
                Akun belum terhubung ke data pegawai. Hubungi administrator.
            </div>
            <template v-else>
                <div class="mb-4">
                    <p class="text-sm text-gray-500">Selamat datang, <span class="font-medium text-gray-800">{{ employee.display_name || employee.name }}</span></p>
                </div>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="project in projects" :key="project.id">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">{{ project.name }}</CardTitle>
                            <Badge variant="outline" class="w-fit text-xs">{{ project.team?.name }}</Badge>
                        </CardHeader>
                        <CardContent>
                            <div class="mt-1">
                                <div class="flex items-baseline justify-between text-sm">
                                    <span class="text-gray-500">Capaian</span>
                                    <span :class="achievementColor(projectAvg(project))" class="font-semibold">
                                        {{ projectAvg(project).toFixed(1) }}%
                                    </span>
                                </div>
                                <Progress :model-value="projectAvg(project)" :class="['mt-1', progressVariant(projectAvg(project))]" />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </template>
        </template>

        <!-- HEAD / ADMIN view -->
        <template v-else>
            <!-- Admin org avg card -->
            <div v-if="role === 'admin'" class="mb-6 grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-medium text-gray-500">Rata-rata Capaian Organisasi</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p :class="achievementColor(org_avg ?? 0)" class="text-3xl font-bold">
                            {{ (org_avg ?? 0).toFixed(1) }}%
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Team summary table -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Capaian Per Tim</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div v-if="!teamList.length" class="py-8 text-center text-sm text-gray-400">
                            Belum ada data laporan untuk periode ini.
                        </div>
                        <div v-for="team in teamList" :key="team.id" class="flex items-center gap-4">
                            <div class="w-48 truncate text-sm font-medium">{{ team.name }}</div>
                            <div class="flex-1">
                                <Progress :model-value="team.avg" :class="['h-2', progressVariant(team.avg)]" />
                            </div>
                            <div :class="achievementColor(team.avg)" class="w-16 text-right text-sm font-semibold">
                                {{ team.avg.toFixed(1) }}%
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </template>
    </AppLayout>
</template>
