<script setup lang="ts">
import { useAchievementColor } from '@/composables/useAchievementColor';
import { Progress } from '@/Components/ui/progress';

interface PersonalStats {
    teams_count: number;
    projects_count: number;
    items_count: number;
    avg_achievement: number;
    is_team_lead: boolean;
}

defineProps<{
    stats: PersonalStats;
}>();

const { achievementColor, progressVariant, avgIconBgColor, avgIconColor } = useAchievementColor();
</script>

<template>
    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Tim Kerja -->
        <div class="rounded-lg border bg-white p-6 shadow-sm">
            <div class="flex items-start gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100"
                    aria-hidden="true"
                >
                    <svg
                        class="h-6 w-6 text-blue-600"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"
                        />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-500">Tim Kerja</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ stats.teams_count }}</p>
                </div>
            </div>
        </div>

        <!-- Proyek -->
        <div class="rounded-lg border bg-white p-6 shadow-sm">
            <div class="flex items-start gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-100"
                    aria-hidden="true"
                >
                    <svg
                        class="h-6 w-6 text-indigo-600"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                        />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-500">Proyek</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ stats.projects_count }}</p>
                </div>
            </div>
        </div>

        <!-- Item Kerja -->
        <div class="rounded-lg border bg-white p-6 shadow-sm">
            <div class="flex items-start gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-teal-100"
                    aria-hidden="true"
                >
                    <svg
                        class="h-6 w-6 text-teal-600"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                        />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-500">Item Kerja</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ stats.items_count }}</p>
                </div>
            </div>
        </div>

        <!-- Rata-rata Capaian -->
        <div class="rounded-lg border bg-white p-6 shadow-sm">
            <div class="flex items-start gap-4">
                <div
                    :class="[
                        'flex h-12 w-12 shrink-0 items-center justify-center rounded-full',
                        avgIconBgColor(stats.avg_achievement),
                    ]"
                    aria-hidden="true"
                >
                    <svg
                        :class="['h-6 w-6', avgIconColor(stats.avg_achievement)]"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                        />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-500">Rata-rata Capaian</p>
                    <p :class="['mt-1 text-2xl font-bold', achievementColor(stats.avg_achievement)]">
                        {{ stats.avg_achievement.toFixed(1) }}%
                    </p>
                    <Progress
                        :model-value="stats.avg_achievement"
                        class="mt-2"
                        :indicator-class="progressVariant(stats.avg_achievement)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
