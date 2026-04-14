<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { VisXYContainer, VisGroupedBar, VisAxis, VisTooltip } from '@unovis/vue';
import { GroupedBar } from '@unovis/ts';
import { computed, ref } from 'vue';

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

// Chart 1 — top 10 by achievement (period-filtered, Unovis)
interface AchievementDatum { label: string; value: number }

const achievementUnovisData = computed<AchievementDatum[]>(() =>
    [...props.top10].reverse().map(e => ({
        label: e.display_name ?? e.name,
        value: Math.round(e.avg_achievement * 10) / 10,
    })),
);

const achX = (_d: AchievementDatum, i: number) => i;
const achY = [(d: AchievementDatum) => d.value];
const achColor = 'rgba(27, 75, 138, 0.75)';
const achYTickFormat = (tick: number) => {
    const label = achievementUnovisData.value[tick]?.label ?? '';
    return label.length > 20 ? label.substring(0, 18) + '\u2026' : label;
};
const achXTickFormat = (v: number) => `${v}%`;
const achTooltipTriggers = {
    [GroupedBar.selectors.bar]: (d: AchievementDatum) =>
        `<div style="padding:4px 8px;font-size:13px"><strong>${d.label}</strong><br/>${d.value}%</div>`,
};

// Chart 2 — top 10 by project role (year-filtered)
const projectTab = ref<'semua' | 'ketua' | 'anggota'>('semua');

const projectFiltered = computed(() => {
    const all = props.top10ByProjects;
    if (projectTab.value === 'ketua') {
        return [...all].sort((a, b) => b.leader_count - a.leader_count).slice(0, 10);
    }
    if (projectTab.value === 'anggota') {
        return [...all].sort((a, b) => b.member_count - a.member_count).slice(0, 10);
    }
    return all.slice(0, 10);
});

const projectTabLabel = computed(() => {
    if (projectTab.value === 'ketua') return 'sebagai ketua';
    if (projectTab.value === 'anggota') return 'sebagai anggota';
    return 'proyek';
});

// Chart 2 — top 10 by project role (Unovis)
interface ProjectDatum { label: string; value: number }

const projectUnovisData = computed<ProjectDatum[]>(() =>
    [...projectFiltered.value].reverse().map(e => ({
        label: e.display_name ?? e.name,
        value: projectTab.value === 'ketua' ? e.leader_count :
               projectTab.value === 'anggota' ? e.member_count :
               e.total_projects,
    })),
);

const projX = (_d: ProjectDatum, i: number) => i;
const projY = [(d: ProjectDatum) => d.value];
const projColor = 'rgba(5, 150, 105, 0.75)';
const projYTickFormat = (tick: number) => {
    const label = projectUnovisData.value[tick]?.label ?? '';
    return label.length > 20 ? label.substring(0, 18) + '\u2026' : label;
};
const projXTickFormat = (v: number) => `${v}`;
const projTooltipTriggers = computed(() => ({
    [GroupedBar.selectors.bar]: (d: ProjectDatum) =>
        `<div style="padding:4px 8px;font-size:13px"><strong>${d.label}</strong><br/>${d.value} ${projectTabLabel.value}</div>`,
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
                        <SelectItem v-for="(m, idx) in MONTHS" :key="m" :value="idx + 1">{{ m }}</SelectItem>
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
                    <VisXYContainer :data="achievementUnovisData" :yDomain="[0, 100]" :style="{ height: '100%' }">
                        <VisGroupedBar orientation="horizontal" :x="achX" :y="achY" :color="achColor" :roundedCorners="4" :barMinHeight="0" />
                        <VisAxis type="x" :tickFormat="achXTickFormat" />
                        <VisAxis type="y" :tickFormat="achYTickFormat" :gridLine="false" :tickTextFontSize="'12px'" :numTicks="achievementUnovisData.length" />
                        <VisTooltip :triggers="achTooltipTriggers" />
                    </VisXYContainer>
                </div>
                <p v-else class="py-10 text-center text-sm text-gray-400">
                    Belum ada data capaian untuk periode ini.
                </p>
            </div>

            <!-- Chart 2: Top 10 proyek terbanyak -->
            <div class="rounded-lg border bg-white p-6">
                <div class="mb-1 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-3 w-3 rounded-full bg-emerald-600" />
                        <h2 class="text-sm font-semibold text-gray-700">Top 10 Proyek Terbanyak</h2>
                    </div>
                    <div class="flex divide-x rounded-md border text-xs overflow-hidden">
                        <button @click="projectTab = 'semua'" :class="[projectTab === 'semua' ? 'bg-emerald-600 text-white' : 'text-gray-500 hover:bg-gray-50', 'px-2 py-1']">Semua</button>
                        <button @click="projectTab = 'ketua'" :class="[projectTab === 'ketua' ? 'bg-emerald-600 text-white' : 'text-gray-500 hover:bg-gray-50', 'px-2 py-1']">Ketua</button>
                        <button @click="projectTab = 'anggota'" :class="[projectTab === 'anggota' ? 'bg-emerald-600 text-white' : 'text-gray-500 hover:bg-gray-50', 'px-2 py-1']">Anggota</button>
                    </div>
                </div>
                <p class="mb-4 text-xs text-gray-400">Total keterlibatan proyek — {{ filters.year }}</p>
                <div v-if="projectFiltered.length" style="height: 300px;">
                    <VisXYContainer :data="projectUnovisData" :style="{ height: '100%' }">
                        <VisGroupedBar orientation="horizontal" :x="projX" :y="projY" :color="projColor" :roundedCorners="4" :barMinHeight="0" />
                        <VisAxis type="x" :tickFormat="projXTickFormat" />
                        <VisAxis type="y" :tickFormat="projYTickFormat" :gridLine="false" :tickTextFontSize="'12px'" :numTicks="projectUnovisData.length" />
                        <VisTooltip :triggers="projTooltipTriggers" />
                    </VisXYContainer>
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
