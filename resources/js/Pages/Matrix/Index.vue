<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import type { Employee, Project, Team } from '@/types';
import { ref, computed } from 'vue';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/Components/ui/tabs';

interface Assignment {
    project_id: number;
    employee_id: number;
    role: string;
}

const props = defineProps<{
    employees: Employee[];
    projects: Project[];
    assignments: Record<number, Assignment[]>;
    progress: Record<number, number>;
    teams: Team[];
    year: number;
    month: number;
    teamId: number | null;
}>();

const viewMode = ref<'assignment' | 'progress'>('assignment');
const year = ref(props.year);
const month = ref(props.month);
const teamId = ref(props.teamId);

function applyFilters() {
    router.get(route('matrix'), {
        year: year.value,
        month: month.value,
        team_id: teamId.value ?? '',
    }, { preserveState: true });
}

const months = [
    { value: 1, label: 'Jan' }, { value: 2, label: 'Feb' }, { value: 3, label: 'Mar' },
    { value: 4, label: 'Apr' }, { value: 5, label: 'Mei' }, { value: 6, label: 'Jun' },
    { value: 7, label: 'Jul' }, { value: 8, label: 'Ags' }, { value: 9, label: 'Sep' },
    { value: 10, label: 'Okt' }, { value: 11, label: 'Nov' }, { value: 12, label: 'Des' },
];

// Build assignment lookup: Set of "employeeId:projectId"
const assignmentSet = computed(() => {
    const set = new Set<string>();
    Object.entries(props.assignments).forEach(([projectId, members]) => {
        members.forEach(m => set.add(`${m.employee_id}:${projectId}`));
    });
    return set;
});

function isAssigned(employeeId: number, projectId: number): boolean {
    return assignmentSet.value.has(`${employeeId}:${projectId}`);
}

function getProgress(projectId: number): number {
    return Number(props.progress[projectId] ?? 0);
}

function cellBgColor(pct: number): string {
    if (pct >= 80) return 'bg-green-100 text-green-800';
    if (pct >= 50) return 'bg-yellow-100 text-yellow-800';
    return 'bg-red-100 text-red-800';
}
</script>

<template>
    <Head title="Matriks Kinerja" />
    <AppLayout>
        <template #title>Matriks Penugasan & Kinerja</template>

        <!-- Controls -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <Tabs v-model="viewMode" class="w-auto">
                <TabsList>
                    <TabsTrigger value="assignment">Penugasan</TabsTrigger>
                    <TabsTrigger value="progress">Capaian</TabsTrigger>
                </TabsList>
            </Tabs>

            <Select v-model="teamId" @update:modelValue="applyFilters">
                <SelectTrigger class="w-52">
                    <SelectValue placeholder="Semua tim" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="null">Semua tim</SelectItem>
                    <SelectItem v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</SelectItem>
                </SelectContent>
            </Select>

            <Select v-if="viewMode === 'progress'" v-model="month" @update:modelValue="applyFilters">
                <SelectTrigger class="w-24">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="year" @update:modelValue="applyFilters">
                <SelectTrigger class="w-24">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="y in [2025, 2026, 2027]" :key="y" :value="y">{{ y }}</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Legend -->
        <div v-if="viewMode === 'progress'" class="mb-3 flex items-center gap-4 text-xs">
            <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded bg-green-200"></span> ≥80%</span>
            <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded bg-yellow-200"></span> 50–79%</span>
            <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded bg-red-200"></span> &lt;50%</span>
        </div>

        <!-- Grid (virtual scroll via overflow) -->
        <div class="overflow-auto rounded-md border bg-white" style="max-height: calc(100vh - 260px)">
            <table class="min-w-max text-xs">
                <thead class="sticky top-0 z-10 bg-white">
                    <tr>
                        <th class="sticky left-0 bg-white px-3 py-2 text-left font-medium border-b border-r min-w-[160px]">
                            Pegawai
                        </th>
                        <th
                            v-for="project in projects"
                            :key="project.id"
                            class="border-b border-r px-2 py-2 font-medium text-center max-w-[100px]"
                        >
                            <div class="w-20 overflow-hidden text-ellipsis whitespace-nowrap" :title="project.name">
                                {{ project.name }}
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="employee in employees" :key="employee.id" class="border-b hover:bg-gray-50">
                        <td class="sticky left-0 bg-white border-r px-3 py-1.5 font-medium">
                            {{ employee.display_name || employee.name }}
                        </td>
                        <td
                            v-for="project in projects"
                            :key="project.id"
                            class="border-r px-1 py-1.5 text-center"
                        >
                            <!-- Assignment mode -->
                            <template v-if="viewMode === 'assignment'">
                                <span v-if="isAssigned(employee.id, project.id)" class="text-[#1B4B8A]">✓</span>
                                <span v-else class="text-gray-200">·</span>
                            </template>

                            <!-- Progress mode -->
                            <template v-else>
                                <span
                                    v-if="isAssigned(employee.id, project.id)"
                                    :class="['inline-block rounded px-1 font-semibold tabular-nums', cellBgColor(getProgress(project.id))]"
                                >
                                    {{ getProgress(project.id).toFixed(0) }}%
                                </span>
                                <span v-else class="text-gray-200">·</span>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-2 text-xs text-gray-400">
            {{ employees.length }} pegawai × {{ projects.length }} proyek
        </div>
    </AppLayout>
</template>
