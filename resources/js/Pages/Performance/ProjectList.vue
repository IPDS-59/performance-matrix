<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import type { Employee } from '@/types';
import { ref, computed } from 'vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Badge } from '@/Components/ui/badge';

// ── Types ──────────────────────────────────────────────────────────────────

interface EmployeeProject {
    id: number;
    name: string;
    year: number;
    leader_id: number | null;
    team: { id: number; name: string } | null;
    assigned_items_count: number;
    submitted_items_count: number;
    pending_review_count: number;
    rejected_count: number;
}

interface LeadProject {
    id: number;
    name: string;
    year: number;
    team: { id: number; name: string } | null;
    work_items_count: number;
    members_count: number;
    pending_reviews_count: number;
    total_reports_count: number;
}

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    employee: Pick<Employee, 'id' | 'name' | 'display_name'>;
    employee_projects: EmployeeProject[];
    lead_projects: LeadProject[];
    is_team_lead: boolean;
    filters: { year: number };
}>();

// ── Filters ────────────────────────────────────────────────────────────────

const year = ref(props.filters.year);

function applyFilters() {
    router.get(route('performance.index'), { year: year.value }, { preserveState: true, preserveScroll: true });
}

// ── Group by team ──────────────────────────────────────────────────────────

type WithTeam<T> = T & { team: { id: number; name: string } | null };

function groupByTeam<T extends WithTeam<object>>(
    projects: T[],
): Array<{ teamId: number; teamName: string; projects: T[] }> {
    const groups: Record<number, { teamId: number; teamName: string; projects: T[] }> = {};
    for (const p of projects) {
        const tid = p.team?.id ?? 0;
        const tname = p.team?.name ?? 'Tim Tidak Diketahui';
        if (!groups[tid]) groups[tid] = { teamId: tid, teamName: tname, projects: [] };
        groups[tid].projects.push(p);
    }
    return Object.values(groups).sort((a, b) => a.teamName.localeCompare(b.teamName));
}

const employeeProjectsByTeam = computed(() => groupByTeam(props.employee_projects));
const leadProjectsByTeam = computed(() => groupByTeam(props.lead_projects));

// ── Helpers ────────────────────────────────────────────────────────────────

function submittedPct(p: EmployeeProject): number {
    if (!p.assigned_items_count) return 0;
    return Math.round((p.submitted_items_count / p.assigned_items_count) * 100);
}

function progressBarColor(pct: number): string {
    if (pct >= 80) return 'bg-green-500';
    if (pct >= 50) return 'bg-yellow-400';
    return 'bg-red-400';
}
</script>

<template>
    <Head title="Kinerja" />
    <AppLayout>
        <template #title>
            Kinerja — {{ employee.display_name || employee.name }}
        </template>

        <!-- Year filter -->
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <Select v-model="year" @update:modelValue="applyFilters">
                <SelectTrigger class="w-28">
                    <SelectValue placeholder="Tahun" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</SelectItem>
                </SelectContent>
            </Select>
            <span class="ml-auto text-sm text-gray-500">Tahun: <strong>{{ filters.year }}</strong></span>
        </div>

        <!-- Tabs: only shown for team leads -->
        <Tabs v-if="is_team_lead" default-value="personal" class="w-full">
            <TabsList class="mb-6">
                <TabsTrigger value="personal">Kinerja Saya</TabsTrigger>
                <TabsTrigger value="team">
                    Tim Saya
                    <Badge v-if="lead_projects.length" variant="secondary" class="ml-2 text-xs">
                        {{ lead_projects.length }}
                    </Badge>
                </TabsTrigger>
            </TabsList>

            <TabsContent value="personal">
                <div v-if="!employee_projects.length" class="py-16 text-center text-gray-400">
                    <p class="font-medium">Tidak ada proyek untuk tahun ini.</p>
                    <p class="mt-1 text-sm">Anda belum ditugaskan ke proyek aktif tahun {{ filters.year }}.</p>
                </div>
                <template v-else>
                    <div v-for="group in employeeProjectsByTeam" :key="group.teamId" class="mb-8">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">
                            {{ group.teamName }}
                        </h3>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <a
                                v-for="project in group.projects"
                                :key="project.id"
                                :href="route('performance.projects.show', project.id)"
                                class="group flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-blue-300 hover:shadow-md"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <p class="font-medium text-gray-800 group-hover:text-blue-700 leading-snug">
                                        {{ project.name }}
                                    </p>
                                    <div class="flex shrink-0 flex-col items-end gap-1">
                                        <Badge
                                            v-if="project.rejected_count > 0"
                                            class="border-red-200 bg-red-50 text-red-700 text-[10px]"
                                        >
                                            {{ project.rejected_count }} ditolak
                                        </Badge>
                                        <Badge
                                            v-if="project.pending_review_count > 0"
                                            class="border-yellow-200 bg-yellow-50 text-yellow-700 text-[10px]"
                                        >
                                            {{ project.pending_review_count }} menunggu
                                        </Badge>
                                    </div>
                                </div>

                                <div v-if="project.assigned_items_count > 0">
                                    <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                                        <span>{{ project.submitted_items_count }}/{{ project.assigned_items_count }} rincian dilaporkan</span>
                                        <span :class="['font-semibold', submittedPct(project) >= 80 ? 'text-green-600' : submittedPct(project) >= 50 ? 'text-yellow-600' : 'text-red-500']">
                                            {{ submittedPct(project) }}%
                                        </span>
                                    </div>
                                    <div class="h-1.5 w-full rounded-full bg-gray-200 overflow-hidden">
                                        <div
                                            :class="['h-full rounded-full transition-all', progressBarColor(submittedPct(project))]"
                                            :style="`width: ${submittedPct(project)}%`"
                                        />
                                    </div>
                                </div>
                                <p v-else class="text-xs text-gray-400">Belum ada rincian kegiatan.</p>
                            </a>
                        </div>
                    </div>
                </template>
            </TabsContent>

            <TabsContent value="team">
                <div v-if="!lead_projects.length" class="py-16 text-center text-gray-400">
                    <p class="font-medium">Tidak ada proyek yang Anda pimpin tahun ini.</p>
                </div>
                <template v-else>
                    <div v-for="group in leadProjectsByTeam" :key="group.teamId" class="mb-8">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">
                            {{ group.teamName }}
                        </h3>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <a
                                v-for="project in group.projects"
                                :key="project.id"
                                :href="route('performance.projects.show', project.id)"
                                class="group flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-blue-300 hover:shadow-md"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <p class="font-medium text-gray-800 group-hover:text-blue-700 leading-snug">
                                        {{ project.name }}
                                    </p>
                                    <Badge
                                        v-if="project.pending_reviews_count > 0"
                                        class="shrink-0 border-yellow-200 bg-yellow-50 text-yellow-700 text-[10px]"
                                    >
                                        {{ project.pending_reviews_count }} perlu ditinjau
                                    </Badge>
                                </div>

                                <div class="flex items-center gap-3 text-xs text-gray-500">
                                    <span>{{ project.members_count }} anggota</span>
                                    <span class="text-gray-300">·</span>
                                    <span>{{ project.work_items_count }} rincian</span>
                                    <span class="text-gray-300">·</span>
                                    <span>{{ project.total_reports_count }} laporan</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </template>
            </TabsContent>
        </Tabs>

        <!-- Non-lead: just show personal projects directly -->
        <template v-else>
            <div v-if="!employee_projects.length" class="py-16 text-center text-gray-400">
                <p class="font-medium">Tidak ada proyek untuk tahun ini.</p>
                <p class="mt-1 text-sm">Anda belum ditugaskan ke proyek aktif tahun {{ filters.year }}.</p>
            </div>
            <template v-else>
                <div v-for="group in employeeProjectsByTeam" :key="group.teamId" class="mb-8">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">
                        {{ group.teamName }}
                    </h3>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <a
                            v-for="project in group.projects"
                            :key="project.id"
                            :href="route('performance.projects.show', project.id)"
                            class="group flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-blue-300 hover:shadow-md"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-medium text-gray-800 group-hover:text-blue-700 leading-snug">
                                    {{ project.name }}
                                </p>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <Badge
                                        v-if="project.rejected_count > 0"
                                        class="border-red-200 bg-red-50 text-red-700 text-[10px]"
                                    >
                                        {{ project.rejected_count }} ditolak
                                    </Badge>
                                    <Badge
                                        v-if="project.pending_review_count > 0"
                                        class="border-yellow-200 bg-yellow-50 text-yellow-700 text-[10px]"
                                    >
                                        {{ project.pending_review_count }} menunggu
                                    </Badge>
                                </div>
                            </div>

                            <div v-if="project.assigned_items_count > 0">
                                <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                                    <span>{{ project.submitted_items_count }}/{{ project.assigned_items_count }} rincian dilaporkan</span>
                                    <span :class="['font-semibold', submittedPct(project) >= 80 ? 'text-green-600' : submittedPct(project) >= 50 ? 'text-yellow-600' : 'text-red-500']">
                                        {{ submittedPct(project) }}%
                                    </span>
                                </div>
                                <Progress
                                    :value="submittedPct(project)"
                                    :class="['h-1.5', progressColor(submittedPct(project))]"
                                />
                            </div>
                            <p v-else class="text-xs text-gray-400">Belum ada rincian kegiatan.</p>
                        </a>
                    </div>
                </div>
            </template>
        </template>
    </AppLayout>
</template>
