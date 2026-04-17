<script setup lang="ts">
import { computed } from 'vue';
import { useAchievementColor } from '@/composables/useAchievementColor';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Progress } from '@/Components/ui/progress';

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

const props = defineProps<{
    projects: ProjectWithItems[];
    heading?: string;
}>();

const { achievementColor, progressVariant } = useAchievementColor();

function projectAvg(project: ProjectWithItems): number {
    const reports = project.work_items.flatMap((wi) => wi.performance_reports);
    if (!reports.length) return 0;
    const sum = reports.reduce((acc, r) => acc + Number(r.achievement_percentage), 0);
    return sum / reports.length;
}

const projectsByTeam = computed(() => {
    const map = new Map<number, { teamName: string; projects: ProjectWithItems[] }>();

    for (const project of props.projects) {
        const teamId = project.team_id;
        const teamName = project.team?.name ?? 'Tanpa Tim';

        if (!map.has(teamId)) {
            map.set(teamId, { teamName, projects: [] });
        }

        map.get(teamId)!.projects.push(project);
    }

    return [...map.values()].sort((a, b) => a.teamName.localeCompare(b.teamName));
});
</script>

<template>
    <div>
        <!-- Optional heading -->
        <div v-if="heading" class="mt-8 mb-4 flex items-center gap-3">
            <h2 class="text-base font-semibold text-gray-800">{{ heading }}</h2>
            <span class="h-px flex-1 bg-gray-200"></span>
        </div>

        <!-- Empty state -->
        <div v-if="!projects.length" class="py-12 text-center text-gray-400">
            <p>Belum ada proyek untuk periode ini.</p>
        </div>

        <!-- Projects grouped by team -->
        <div v-else class="space-y-10">
            <div v-for="group in projectsByTeam" :key="group.teamName">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                    <span class="h-px flex-1 bg-primary/20"></span>
                    {{ group.teamName }}
                    <span class="h-px flex-1 bg-primary/20"></span>
                </h2>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="project in group.projects" :key="project.id" class="hover:shadow-md transition-shadow">
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
                            <Progress :model-value="projectAvg(project)" class="mt-2 h-2" :indicator-class="progressVariant(projectAvg(project))" />
                            <p class="mt-2 text-xs text-gray-400">{{ project.work_items.length }} item kerja</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </div>
</template>
