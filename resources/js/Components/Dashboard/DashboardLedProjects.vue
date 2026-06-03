<script setup lang="ts">
import { reactive } from 'vue';
import type { TeamMember, TeamProjectWithMembers } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
    isProjectLeader,
    leaderBadgeLabel as _leaderBadgeLabel,
    ledProjectMemberCount,
    ledProjectSubmittedCount,
} from '@/composables/useLedProjects';

const props = defineProps<{
    ledProjects: TeamProjectWithMembers[];
    teamLeaderMap: Map<number, number>;
}>();

function leaderBadgeLabel(employeeId: number, teamId: number | null | undefined): string {
    return _leaderBadgeLabel(employeeId, teamId, props.teamLeaderMap);
}

// ── Chip scroll-hint ─────────────────────────────────────────────────────────

const chipScrollable = reactive<Record<number, boolean>>({});
const chipResizeObservers = new Map<number, ResizeObserver>();

function initChipScrollable(el: HTMLElement | null, projectId: number) {
    chipResizeObservers.get(projectId)?.disconnect();
    if (!el) return;
    const update = () => { chipScrollable[projectId] = el.scrollWidth > el.clientWidth; };
    update();
    const ro = new ResizeObserver(update);
    ro.observe(el);
    chipResizeObservers.set(projectId, ro);
}
</script>

<template>
    <div class="space-y-4">
        <Card v-for="ledProject in ledProjects" :key="ledProject.id" class="overflow-hidden">
            <CardHeader class="pb-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle class="text-sm font-semibold text-gray-800">{{ ledProject.name }}</CardTitle>
                        <p v-if="ledProject.team" class="mt-0.5 text-xs text-gray-500">{{ ledProject.team.name }}</p>
                    </div>
                    <div class="shrink-0 text-right text-xs text-gray-500">
                        <span class="font-medium text-gray-700">{{ ledProjectSubmittedCount(ledProject) }}</span>
                        <span class="text-gray-400"> / {{ ledProjectMemberCount(ledProject) }}</span>
                        <p class="text-gray-400">sudah input</p>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="pt-0">
                <div class="flex items-center gap-2">
                    <template v-for="member in ledProject.members" :key="'lead-'+member.id">
                        <div
                            v-if="isProjectLeader(member)"
                            class="flex shrink-0 items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs text-amber-800"
                        >
                            <span class="text-amber-500" :aria-label="leaderBadgeLabel(member.id, ledProject.team?.id)">&#9733;</span>
                            <span>{{ member.display_name || member.name }}</span>
                            <Badge class="ml-0.5 h-4 bg-amber-500 px-1.5 text-[10px] text-white hover:bg-amber-500">{{ leaderBadgeLabel(member.id, ledProject.team?.id) }}</Badge>
                        </div>
                    </template>
                    <span
                        v-if="ledProject.members.some(m => isProjectLeader(m)) && ledProject.members.some(m => !isProjectLeader(m))"
                        class="h-6 w-px shrink-0 bg-gray-200"
                    />
                    <div v-if="ledProject.members.some(m => !isProjectLeader(m))" class="relative min-w-0 flex-1">
                        <div
                            class="flex gap-2 overflow-x-auto [&::-webkit-scrollbar]:hidden [scrollbar-width:none]"
                            :ref="(el) => initChipScrollable(el as HTMLElement | null, ledProject.id)"
                        >
                            <template v-for="member in ledProject.members" :key="member.id">
                                <div
                                    v-if="!isProjectLeader(member)"
                                    class="flex shrink-0 items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs text-gray-700"
                                >
                                    <span>{{ member.display_name || member.name }}</span>
                                </div>
                            </template>
                        </div>
                        <div v-if="chipScrollable[ledProject.id]" class="pointer-events-none absolute inset-y-0 right-0 flex items-center bg-gradient-to-l from-white via-white/70 to-transparent pl-6 pr-1">
                            <svg class="h-4 w-4 animate-bounce-x text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
