<script setup lang="ts">
import { computed, ref } from 'vue';
import type { TeamRankItem } from '@/types';
import { useAchievementColor } from '@/composables/useAchievementColor';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Progress } from '@/Components/ui/progress';
import { VisXYContainer, VisGroupedBar, VisAxis, VisTooltip } from '@unovis/vue';
import { GroupedBar } from '@unovis/ts';

// ── Types ──────────────────────────────────────────────────────────────────

interface BarChartDatum { label: string; code: string; value: number }

// ── Props ──────────────────────────────────────────────────────────────────

const props = withDefaults(defineProps<{
    teamList: TeamRankItem[];
    title?: string;
    monthLabel?: string;
    projectLeadersByTeam?: Record<number, number[]>;
    chartColSpan?: 2 | 3;
    rankColSpan?: 1 | 2;
}>(), {
    title: 'Capaian Per Tim',
    chartColSpan: 2,
    rankColSpan: 1,
});

// ── Color helpers ──────────────────────────────────────────────────────────

const { achievementColor, progressVariant } = useAchievementColor();

// ── Bar chart (Unovis) ────────────────────────────────────────────────────

const barChartUnovisData = computed<BarChartDatum[]>(() =>
    props.teamList.map(t => ({ label: t.name, code: t.code ?? t.name, value: t.avg })),
);

const barX = (_d: BarChartDatum, i: number) => i;
const barY = [(d: BarChartDatum) => d.value];
const barColor = (d: BarChartDatum) =>
    d.value >= 80 ? 'rgba(34,197,94,0.75)' :
    d.value >= 50 ? 'rgba(234,179,8,0.75)' :
    'rgba(239,68,68,0.75)';
const barXTickFormat = (tick: number) => barChartUnovisData.value[tick]?.code ?? '';
const barYTickFormat = (v: number) => `${v}%`;
const barTooltipTriggers = {
    [GroupedBar.selectors.bar]: (d: BarChartDatum) =>
        `<div style="padding:4px 8px;font-size:13px"><strong>${d.label}</strong><br/>${d.value.toFixed(1)}%</div>`,
};

// ── Grid class ────────────────────────────────────────────────────────────

const gridClass = computed(() =>
    props.chartColSpan === 3 && props.rankColSpan === 2
        ? 'grid gap-6 lg:grid-cols-5'
        : 'grid gap-4 lg:grid-cols-3',
);

const chartClass = computed(() =>
    props.chartColSpan === 3 ? 'lg:col-span-3' : 'lg:col-span-2',
);

const rankClass = computed(() =>
    props.rankColSpan === 2 ? 'lg:col-span-2' : '',
);

// ── Team expand/collapse ──────────────────────────────────────────────────

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

// ── Leader helpers ────────────────────────────────────────────────────────

function isProjectLeaderInTeam(employeeId: number, teamId: number): boolean {
    return props.projectLeadersByTeam?.[teamId]?.includes(employeeId) ?? false;
}

function isTeamLeaderOf(employeeId: number, teamLeaderId: number | null | undefined): boolean {
    return teamLeaderId != null && employeeId === teamLeaderId;
}

function hasLeaderBadge(employeeId: number, team: { id: number; leader_id?: number | null }): boolean {
    return isTeamLeaderOf(employeeId, team.leader_id) || isProjectLeaderInTeam(employeeId, team.id);
}

function leaderBadgeLabel(employeeId: number, team: { id: number; leader_id?: number | null }): string {
    return isTeamLeaderOf(employeeId, team.leader_id) ? 'Ketua Tim' : 'Ketua Proyek';
}
</script>

<template>
    <div :class="gridClass">
        <!-- Bar chart card -->
        <Card :class="chartClass">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">{{ title }}{{ monthLabel ? ` — ${monthLabel}` : '' }}</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="teamList.length" class="h-64">
                    <VisXYContainer :data="barChartUnovisData" :yDomain="[0, 100]" :style="{ height: '100%' }">
                        <VisGroupedBar :x="barX" :y="barY" :color="barColor" :roundedCorners="6" :barMinHeight="0" />
                        <VisAxis type="x" :tickFormat="barXTickFormat" :gridLine="false" :tickTextFontSize="'11px'" :numTicks="barChartUnovisData.length" />
                        <VisAxis type="y" :tickFormat="barYTickFormat" />
                        <VisTooltip :triggers="barTooltipTriggers" />
                    </VisXYContainer>
                </div>
                <div v-else class="flex h-64 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50">
                    <p class="text-sm text-gray-400">Belum ada data capaian tim bulan ini</p>
                </div>
            </CardContent>
        </Card>

        <!-- Team ranking list card -->
        <Card :class="rankClass">
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
                                            v-for="member in team.employees"
                                            :key="member.id"
                                            :class="[
                                                'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs',
                                                hasLeaderBadge(member.id, team)
                                                    ? 'border-amber-300 bg-amber-50 text-amber-800'
                                                    : 'border-gray-200 bg-white text-gray-600'
                                            ]"
                                        >
                                            <span v-if="hasLeaderBadge(member.id, team)" class="text-amber-500" :aria-label="leaderBadgeLabel(member.id, team)">&#9733;</span>
                                            {{ member.display_name || member.name }}
                                            <Badge
                                                v-if="hasLeaderBadge(member.id, team)"
                                                class="ml-0.5 h-3.5 bg-amber-500 px-1 text-[9px] leading-none text-white hover:bg-amber-500"
                                            >{{ leaderBadgeLabel(member.id, team) }}</Badge>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div v-else class="px-4 py-8 text-center text-sm text-gray-400">Belum ada data tim</div>
                    </div>
                    <!-- Scroll indicator -->
                    <div v-if="teamList.length > 4" class="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center bg-gradient-to-t from-white via-white/60 to-transparent py-1.5">
                        <svg class="h-4 w-4 animate-bounce text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
