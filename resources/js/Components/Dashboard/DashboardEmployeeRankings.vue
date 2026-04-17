<script setup lang="ts">
import { computed, ref } from 'vue';
import { useAchievementColor } from '@/composables/useAchievementColor';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Progress } from '@/Components/ui/progress';
import { VisXYContainer, VisGroupedBar, VisAxis, VisTooltip } from '@unovis/vue';
import { GroupedBar } from '@unovis/ts';

interface EmployeeRankItem {
    id: number;
    name: string;
    display_name: string | null;
    project_count?: number;
    leader_count?: number;
    member_count?: number;
    avg_achievement?: number;
}

const props = defineProps<{
    topByProjects: EmployeeRankItem[];
    topByAchievement: EmployeeRankItem[];
    currentEmployeeId?: number;
}>();

const { achievementColor, progressVariant } = useAchievementColor();

// ── Projects ranking ────────────────────────────────────────────────────────

const topByProjectsTab = ref<'semua' | 'ketua' | 'anggota'>('semua');

const topByProjectsFiltered = computed(() => {
    const all = props.topByProjects ?? [];
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

// ── Projects chart (Unovis, horizontal) ─────────────────────────────────────

interface EmpProjectsDatum { label: string; value: number; isCurrentUser: boolean }

const empByProjectsUnovisData = computed<EmpProjectsDatum[]>(() =>
    [...topByProjectsFiltered.value].reverse().map(e => ({
        label: e.display_name || e.name,
        value: topByProjectsCount(e),
        isCurrentUser: e.id === props.currentEmployeeId,
    })),
);

const empProjectsX = (_d: EmpProjectsDatum, i: number) => i;
const empProjectsY = [(d: EmpProjectsDatum) => d.value];
const empProjectsColor = (d: EmpProjectsDatum) =>
    d.isCurrentUser ? 'rgba(27,75,138,0.9)' : 'rgba(99,102,241,0.65)';
const empProjectsYTickFormat = (tick: number) => {
    const label = empByProjectsUnovisData.value[tick]?.label ?? '';
    return label.length > 20 ? label.substring(0, 18) + '\u2026' : label;
};
const empProjectsXTickFormat = (v: number) => `${v}`;
const empProjectsTooltipTriggers = {
    [GroupedBar.selectors.bar]: (d: EmpProjectsDatum) =>
        `<div style="padding:4px 8px;font-size:13px"><strong>${d.label}</strong><br/>${d.value} proyek</div>`,
};

// ── Achievement chart (Unovis, horizontal) ──────────────────────────────────

interface EmpAchievementDatum { label: string; value: number; isCurrentUser: boolean }

const empByAchievementUnovisData = computed<EmpAchievementDatum[]>(() =>
    [...(props.topByAchievement ?? [])].reverse().map(e => ({
        label: e.display_name || e.name,
        value: e.avg_achievement ?? 0,
        isCurrentUser: e.id === props.currentEmployeeId,
    })),
);

const empAchievementX = (_d: EmpAchievementDatum, i: number) => i;
const empAchievementY = [(d: EmpAchievementDatum) => d.value];
const empAchievementColor = (d: EmpAchievementDatum) =>
    d.isCurrentUser ? 'rgba(27,75,138,0.9)' :
    d.value >= 80 ? 'rgba(34,197,94,0.65)' :
    d.value >= 50 ? 'rgba(234,179,8,0.65)' :
    'rgba(239,68,68,0.65)';
const empAchievementYTickFormat = (tick: number) => {
    const label = empByAchievementUnovisData.value[tick]?.label ?? '';
    return label.length > 20 ? label.substring(0, 18) + '\u2026' : label;
};
const empAchievementXTickFormat = (v: number) => `${v}%`;
const empAchievementTooltipTriggers = {
    [GroupedBar.selectors.bar]: (d: EmpAchievementDatum) =>
        `<div style="padding:4px 8px;font-size:13px"><strong>${d.label}</strong><br/>${d.value.toFixed(1)}%</div>`,
};
</script>

<template>
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
                        <VisGroupedBar orientation="horizontal" :x="empProjectsX" :y="empProjectsY" :color="empProjectsColor" :roundedCorners="4" :barMinHeight="0" />
                        <VisAxis type="x" :tickFormat="empProjectsXTickFormat" />
                        <VisAxis type="y" :tickFormat="empProjectsYTickFormat" :gridLine="false" :tickTextFontSize="'10px'" :numTicks="empByProjectsUnovisData.length" />
                        <VisTooltip :triggers="empProjectsTooltipTriggers" />
                    </VisXYContainer>
                </div>
                <div v-else class="mb-3 flex h-52 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50">
                    <p class="text-sm text-gray-400">Belum ada data proyek bulan ini</p>
                </div>
                <div class="relative">
                    <div class="max-h-52 overflow-y-auto divide-y divide-gray-100 rounded-md border [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                        <template v-if="topByProjectsFiltered.length">
                            <div v-for="(emp, idx) in topByProjectsFiltered" :key="emp.id" :class="['flex items-center gap-3 px-3 py-2', emp.id === currentEmployeeId ? 'bg-primary/5' : '']" :data-current-user="emp.id === currentEmployeeId || undefined">
                                <span class="w-5 shrink-0 text-right text-xs font-bold text-gray-400">{{ idx + 1 }}</span>
                                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-bold text-indigo-700">{{ (emp.display_name || emp.name).charAt(0).toUpperCase() }}</div>
                                <span :class="['min-w-0 flex-1 truncate text-xs', emp.id === currentEmployeeId ? 'font-semibold text-primary' : 'text-gray-700']">{{ emp.display_name || emp.name }}<span v-if="emp.id === currentEmployeeId" class="ml-1 font-normal text-primary/70">(Anda)</span></span>
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
                <div v-if="topByAchievement?.length" class="mb-3 h-52">
                    <VisXYContainer :data="empByAchievementUnovisData" :yDomain="[0, 100]" :style="{ height: '100%' }">
                        <VisGroupedBar orientation="horizontal" :x="empAchievementX" :y="empAchievementY" :color="empAchievementColor" :roundedCorners="4" :barMinHeight="0" />
                        <VisAxis type="x" :tickFormat="empAchievementXTickFormat" />
                        <VisAxis type="y" :tickFormat="empAchievementYTickFormat" :gridLine="false" :tickTextFontSize="'10px'" :numTicks="empByAchievementUnovisData.length" />
                        <VisTooltip :triggers="empAchievementTooltipTriggers" />
                    </VisXYContainer>
                </div>
                <div v-else class="mb-3 flex h-52 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50">
                    <p class="text-sm text-gray-400">Belum ada data capaian bulan ini</p>
                </div>
                <div class="relative">
                    <div class="max-h-52 overflow-y-auto divide-y divide-gray-100 rounded-md border [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                        <template v-if="topByAchievement?.length">
                            <div v-for="(emp, idx) in topByAchievement" :key="emp.id" :class="['flex items-center gap-3 px-3 py-2', emp.id === currentEmployeeId ? 'bg-primary/5' : '']" :data-current-user="emp.id === currentEmployeeId || undefined">
                                <span class="w-5 shrink-0 text-right text-xs font-bold text-gray-400">{{ idx + 1 }}</span>
                                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700">{{ (emp.display_name || emp.name).charAt(0).toUpperCase() }}</div>
                                <div class="min-w-0 flex-1">
                                    <p :class="['truncate text-xs', emp.id === currentEmployeeId ? 'font-semibold text-primary' : 'text-gray-700']">{{ emp.display_name || emp.name }}<span v-if="emp.id === currentEmployeeId" class="ml-1 font-normal text-primary/70">(Anda)</span></p>
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
</template>
