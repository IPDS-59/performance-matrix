<script setup lang="ts">
import type { Employee, PersonalStats, ProjectWithItems, TeamRankItem, EmployeeRankItem } from '@/types';
import DashboardWelcomeCard from '@/Components/Dashboard/DashboardWelcomeCard.vue';
import DashboardStatCards from '@/Components/Dashboard/DashboardStatCards.vue';
import DashboardTeamRanking from '@/Components/Dashboard/DashboardTeamRanking.vue';
import DashboardEmployeeRankings from '@/Components/Dashboard/DashboardEmployeeRankings.vue';
import DashboardPersonalProjects from '@/Components/Dashboard/DashboardPersonalProjects.vue';
import DashboardSectionHeading from '@/Components/Dashboard/DashboardSectionHeading.vue';

const props = defineProps<{
    employee: Employee | undefined;
    personalStats: PersonalStats | undefined;
    projects: ProjectWithItems[];
    teamList: TeamRankItem[];
    projectLeadersByTeam: Record<number, number[]> | undefined;
    topEmployeesByProjects: EmployeeRankItem[];
    topEmployeesByAchievement: EmployeeRankItem[];
    periodLabel: string;
    monthLabel: string;
}>();
</script>

<template>
    <template v-if="employee && personalStats">
        <DashboardWelcomeCard :employee="employee" :period-label="periodLabel" />
        <DashboardStatCards :stats="personalStats" />
    </template>

    <div class="mb-4 flex items-center gap-3">
        <h2 class="text-base font-semibold text-gray-800">Ringkasan Tim</h2>
        <span class="h-px flex-1 bg-gray-200"></span>
    </div>

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

        <DashboardSectionHeading>Peringkat Pegawai</DashboardSectionHeading>
        <DashboardEmployeeRankings
            :top-by-projects="topEmployeesByProjects"
            :top-by-achievement="topEmployeesByAchievement"
        />
    </template>

    <DashboardPersonalProjects v-if="projects.length" :projects="projects" heading="Proyek Saya" />
</template>
