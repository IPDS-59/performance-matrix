<script setup lang="ts">
import type { TeamRankItem, EmployeeRankItem, TrendPoint } from '@/types';
import DashboardAdminSummary from '@/Components/Dashboard/DashboardAdminSummary.vue';
import DashboardTeamRanking from '@/Components/Dashboard/DashboardTeamRanking.vue';
import DashboardTrendChart from '@/Components/Dashboard/DashboardTrendChart.vue';
import DashboardSectionHeading from '@/Components/Dashboard/DashboardSectionHeading.vue';
import DashboardEmployeeRankings from '@/Components/Dashboard/DashboardEmployeeRankings.vue';

const props = defineProps<{
    orgAvg: number;
    teamList: TeamRankItem[];
    projectLeadersByTeam: Record<number, number[]> | undefined;
    topEmployeesByProjects: EmployeeRankItem[];
    topEmployeesByAchievement: EmployeeRankItem[];
    trend: TrendPoint[];
    year: number;
    months: { value: number; label: string }[];
    monthLabel: string;
}>();
</script>

<template>
    <DashboardAdminSummary
        :org-avg="orgAvg"
        :active-count="teamList.length"
        :good-count="teamList.filter(t => t.avg >= 80).length"
        :total="teamList.length"
    />

    <div v-if="!teamList.length" class="py-16 text-center text-gray-400">
        <p class="font-medium">Belum ada data laporan untuk periode ini.</p>
        <p class="mt-1 text-sm">Staf dapat memasukkan laporan kinerja bulan {{ monthLabel }}.</p>
    </div>

    <template v-else>
        <DashboardTeamRanking
            :team-list="teamList"
            :project-leaders-by-team="projectLeadersByTeam"
            :month-label="monthLabel"
            :chart-col-span="3"
            :rank-col-span="2"
        />

        <DashboardTrendChart :trend="trend" :year="year" :months="months" />
    </template>
</template>
