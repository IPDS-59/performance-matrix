<script setup lang="ts">
import type { Employee, PersonalStats, ProjectWithItems, TeamProjectWithMembers, TeamRankItem, EmployeeRankItem } from '@/types';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Badge } from '@/Components/ui/badge';
import DashboardWelcomeCard from '@/Components/Dashboard/DashboardWelcomeCard.vue';
import DashboardStatCards from '@/Components/Dashboard/DashboardStatCards.vue';
import DashboardTeamRanking from '@/Components/Dashboard/DashboardTeamRanking.vue';
import DashboardEmployeeRankings from '@/Components/Dashboard/DashboardEmployeeRankings.vue';
import DashboardPersonalProjects from '@/Components/Dashboard/DashboardPersonalProjects.vue';
import DashboardSectionHeading from '@/Components/Dashboard/DashboardSectionHeading.vue';
import DashboardLedProjects from '@/Components/Dashboard/DashboardLedProjects.vue';

const props = defineProps<{
    employee: Employee | undefined;
    personalStats: PersonalStats | undefined;
    projects: ProjectWithItems[];
    teamProjects: TeamProjectWithMembers[];
    teamList: TeamRankItem[];
    teamLeaderMap: Map<number, number>;
    projectLeadersByTeam: Record<number, number[]> | undefined;
    topEmployeesByProjects: EmployeeRankItem[];
    topEmployeesByAchievement: EmployeeRankItem[];
    periodLabel: string;
}>();
</script>

<template>
    <div v-if="!employee" class="py-20 text-center text-gray-400">
        <p class="text-lg font-medium">Akun Belum Terhubung</p>
        <p class="mt-1 text-sm">Hubungi administrator untuk menghubungkan akun ke data pegawai.</p>
    </div>
    <template v-else>
        <DashboardWelcomeCard :employee="employee" :period-label="periodLabel" />

        <!-- Team lead: Tabs layout -->
        <template v-if="personalStats?.is_team_lead">
            <Tabs default-value="personal" class="w-full">
                <TabsList class="mb-6">
                    <TabsTrigger value="personal">Kinerja Saya</TabsTrigger>
                    <TabsTrigger value="team">
                        Tim yang Saya Pimpin
                        <Badge variant="secondary" class="ml-2 text-xs">{{ teamProjects.length }}</Badge>
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="personal">
                    <DashboardStatCards v-if="personalStats" :stats="personalStats" />

                    <DashboardSectionHeading>Perbandingan Kinerja Tim</DashboardSectionHeading>
                    <DashboardTeamRanking
                        :team-list="teamList"
                        :project-leaders-by-team="projectLeadersByTeam"
                    />

                    <DashboardSectionHeading>Peringkat Pegawai</DashboardSectionHeading>
                    <DashboardEmployeeRankings
                        :top-by-projects="topEmployeesByProjects"
                        :top-by-achievement="topEmployeesByAchievement"
                        :current-employee-id="employee.id"
                    />

                    <div class="mt-10">
                        <DashboardPersonalProjects :projects="projects" />
                    </div>
                </TabsContent>
                <TabsContent value="team">
                    <DashboardLedProjects :led-projects="teamProjects" :team-leader-map="teamLeaderMap" />
                </TabsContent>
            </Tabs>
        </template>

        <!-- Non-lead staff: direct layout -->
        <template v-else>
            <DashboardStatCards v-if="personalStats" :stats="personalStats" />

            <DashboardSectionHeading>Perbandingan Kinerja Tim</DashboardSectionHeading>
            <DashboardTeamRanking
                :team-list="teamList"
                :project-leaders-by-team="projectLeadersByTeam"
            />

            <DashboardSectionHeading>Peringkat Pegawai</DashboardSectionHeading>
            <DashboardEmployeeRankings
                :top-by-projects="topEmployeesByProjects"
                :top-by-achievement="topEmployeesByAchievement"
                :current-employee-id="employee.id"
            />

            <DashboardPersonalProjects :projects="projects" />
        </template>
    </template>
</template>
