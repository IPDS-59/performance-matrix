<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Bar } from 'vue-chartjs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    LinearScale,
    Title,
    Tooltip,
} from 'chart.js';
import { computed, ref } from 'vue';

ChartJS.register(Title, Tooltip, BarElement, CategoryScale, LinearScale);

interface TopEmployee {
    id: number;
    name: string;
    display_name: string | null;
    avg_achievement: number;
}

interface TopProjectEmployee {
    id: number;
    name: string;
    display_name: string | null;
    total_projects: number;
    leader_count: number;
    member_count: number;
}

interface EmployeeStat {
    id: number;
    name: string;
    display_name: string | null;
    employee_number: string | null;
    position: string | null;
    team: { id: number; name: string } | null;
    total_projects: number;
    leader_count: number;
    member_count: number;
    avg_achievement: number | null;
}

const props = defineProps<{
    top10: TopEmployee[];
    top10ByProjects: TopProjectEmployee[];
    employees: EmployeeStat[];
    filters: { year: number; month: number };
}>();

const MONTHS = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

const year = ref(props.filters.year);
const month = ref(props.filters.month);

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - i);

function applyFilter() {
    router.get(route('laporan.pegawai'), { year: year.value, month: month.value }, { preserveState: true });
}

// Chart 1 — top 10 by achievement (period-filtered)
const achievementChartData = computed(() => ({
    labels: props.top10.map((e) => e.display_name ?? e.name),
    datasets: [
        {
            label: 'Rata-rata Capaian (%)',
            data: props.top10.map((e) => Math.round(e.avg_achievement * 10) / 10),
            backgroundColor: 'rgba(27, 75, 138, 0.75)',
            borderColor: 'rgba(27, 75, 138, 1)',
            borderWidth: 1,
            borderRadius: 4,
        },
    ],
}));

const achievementChartOptions = {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        title: { display: false },
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx: import('chart.js').TooltipItem<'bar'>) => ` ${ctx.parsed.x ?? 0}%`,
            },
        },
    },
    scales: {
        x: {
            min: 0,
            max: 100,
            ticks: { callback: (v: unknown) => `${v}%` },
            grid: { color: 'rgba(0,0,0,0.05)' },
        },
        y: { ticks: { font: { size: 12 } } },
    },
};

// Chart 2 — top 10 by total projects (all-time)
const projectChartData = computed(() => ({
    labels: props.top10ByProjects.map((e) => e.display_name ?? e.name),
    datasets: [
        {
            label: 'Total Proyek',
            data: props.top10ByProjects.map((e) => e.total_projects),
            backgroundColor: 'rgba(5, 150, 105, 0.75)',
            borderColor: 'rgba(5, 150, 105, 1)',
            borderWidth: 1,
            borderRadius: 4,
        },
    ],
}));

const maxProjects = computed(() =>
    props.top10ByProjects.length
        ? Math.ceil(Math.max(...props.top10ByProjects.map((e) => e.total_projects)) * 1.2)
        : 10,
);

const projectChartOptions = computed(() => ({
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        title: { display: false },
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx: import('chart.js').TooltipItem<'bar'>) => ` ${ctx.parsed.x ?? 0} proyek`,
            },
        },
    },
    scales: {
        x: {
            min: 0,
            max: maxProjects.value,
            ticks: { stepSize: 1, callback: (v: unknown) => String(v) },
            grid: { color: 'rgba(0,0,0,0.05)' },
        },
        y: { ticks: { font: { size: 12 } } },
    },
}));

function achievementColor(val: number | null) {
    if (val === null) return 'bg-gray-200';
    if (val >= 80) return 'bg-green-500';
    if (val >= 60) return 'bg-yellow-400';
    return 'bg-red-400';
}
</script>

<template>
    <Head title="Laporan Pegawai" />
    <AppLayout>
        <template #title>Laporan Pegawai</template>

        <!-- Filter bar (only relevant for chart 1) -->
        <div class="mb-6 flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs text-gray-500">Bulan</label>
                <Select v-model="month">
                    <SelectTrigger class="w-36">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="(m, idx) in MONTHS" :key="idx" :value="idx + 1">{{ m }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Tahun</label>
                <Select v-model="year">
                    <SelectTrigger class="w-28">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="y in years" :key="y" :value="y">{{ y }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <button
                @click="applyFilter"
                class="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-white transition-colors hover:bg-primary/90"
            >
                Tampilkan
            </button>
        </div>

        <!-- Two charts side by side -->
        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Chart 1: Top 10 capaian tertinggi -->
            <div class="rounded-lg border bg-white p-6">
                <div class="mb-1 flex items-center gap-2">
                    <span class="inline-block h-3 w-3 rounded-full bg-[#1B4B8A]" />
                    <h2 class="text-sm font-semibold text-gray-700">Top 10 Capaian Tertinggi</h2>
                </div>
                <p class="mb-4 text-xs text-gray-400">
                    Rata-rata capaian — {{ MONTHS[filters.month - 1] }} {{ filters.year }}
                </p>
                <div v-if="top10.length" style="height: 300px;">
                    <Bar :data="achievementChartData" :options="achievementChartOptions" />
                </div>
                <p v-else class="py-10 text-center text-sm text-gray-400">
                    Belum ada data capaian untuk periode ini.
                </p>
            </div>

            <!-- Chart 2: Top 10 proyek terbanyak -->
            <div class="rounded-lg border bg-white p-6">
                <div class="mb-1 flex items-center gap-2">
                    <span class="inline-block h-3 w-3 rounded-full bg-emerald-600" />
                    <h2 class="text-sm font-semibold text-gray-700">Top 10 Proyek Terbanyak</h2>
                </div>
                <p class="mb-4 text-xs text-gray-400">Total keterlibatan proyek (semua tahun)</p>
                <div v-if="top10ByProjects.length" style="height: 300px;">
                    <Bar :data="projectChartData" :options="projectChartOptions" />
                </div>
                <p v-else class="py-10 text-center text-sm text-gray-400">
                    Belum ada data proyek.
                </p>
            </div>
        </div>

        <!-- Employee stats table -->
        <div class="rounded-lg border bg-white">
            <div class="border-b px-6 py-4">
                <h2 class="text-sm font-semibold text-gray-700">Semua Pegawai Aktif</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <th class="px-6 py-3">Nama</th>
                            <th class="px-4 py-3">Tim</th>
                            <th class="px-4 py-3 text-center">Total Proyek</th>
                            <th class="px-4 py-3 text-center">Ketua</th>
                            <th class="px-4 py-3 text-center">Anggota</th>
                            <th class="px-4 py-3">Capaian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="emp in employees" :key="emp.id" class="hover:bg-gray-50">
                            <td class="px-6 py-3">
                                <p class="font-medium text-gray-900">{{ emp.display_name ?? emp.name }}</p>
                                <p v-if="emp.position" class="text-xs text-gray-400">{{ emp.position }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ emp.team?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center font-medium">{{ emp.total_projects }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                                    {{ emp.leader_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                    {{ emp.member_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="emp.avg_achievement !== null" class="flex items-center gap-2">
                                    <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-100">
                                        <div
                                            :class="achievementColor(emp.avg_achievement)"
                                            :style="{ width: Math.min(100, emp.avg_achievement) + '%' }"
                                            class="h-full rounded-full transition-all"
                                        />
                                    </div>
                                    <span class="text-xs font-medium text-gray-700">
                                        {{ Math.round(emp.avg_achievement * 10) / 10 }}%
                                    </span>
                                </div>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
