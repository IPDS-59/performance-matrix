<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { Project, Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/Components/ui/accordion';
import { ref, computed } from 'vue';

interface ProjectWithCount extends Project {
    members_count: number;
}

const props = defineProps<{
    projects: ProjectWithCount[];
    teams: Team[];
    year: number;
    teamId: number;
}>();

const year = ref(props.year);
const teamId = ref(props.teamId || null);

function applyFilters() {
    router.get(route('projects.index'), { year: year.value, team_id: teamId.value ?? '' }, { preserveState: true });
}

function confirmDelete(id: number, name: string) {
    if (confirm(`Hapus proyek "${name}"?`)) {
        router.delete(route('projects.destroy', id));
    }
}

const statusConfig: Record<string, { label: string; classes: string }> = {
    active:    { label: 'Aktif',      classes: 'bg-green-100 text-green-700 border-green-200' },
    completed: { label: 'Selesai',    classes: 'bg-blue-100 text-blue-700 border-blue-200' },
    cancelled: { label: 'Dibatalkan', classes: 'bg-red-100 text-red-700 border-red-200' },
};

// Group projects by team
const teamGroups = computed(() => {
    const groups: Record<number, {
        team: Team;
        projects: ProjectWithCount[];
        activeCount: number;
        totalMembers: number;
    }> = {};

    for (const p of props.projects) {
        if (!p.team) continue;
        const tid = p.team.id;
        if (!groups[tid]) {
            groups[tid] = {
                team: p.team,
                projects: [],
                activeCount: 0,
                totalMembers: 0,
            };
        }
        groups[tid].projects.push(p);
        if (p.status === 'active') groups[tid].activeCount++;
        groups[tid].totalMembers += p.members_count ?? 0;
    }

    return Object.values(groups).sort((a, b) => a.team.name.localeCompare(b.team.name));
});
</script>

<template>
    <Head title="Proyek" />
    <AppLayout>
        <template #title>Data Proyek</template>

        <!-- Filters + Add button -->
        <div class="mb-5 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Select v-model="year" @update:modelValue="applyFilters">
                    <SelectTrigger class="w-28">
                        <SelectValue placeholder="Tahun" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="teamId" @update:modelValue="applyFilters">
                    <SelectTrigger class="w-56">
                        <SelectValue placeholder="Semua tim" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="null">Semua tim</SelectItem>
                        <SelectItem v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <Button as-child>
                <Link :href="route('projects.create')">Tambah Proyek</Link>
            </Button>
        </div>

        <!-- Empty state -->
        <div v-if="!teamGroups.length" class="py-16 text-center text-gray-400">
            <p class="font-medium">Belum ada data proyek untuk tahun {{ year }}.</p>
        </div>

        <!-- Team accordion groups -->
        <Accordion v-else type="multiple" :default-value="teamGroups.map(g => String(g.team.id))" class="space-y-3">
            <AccordionItem
                v-for="group in teamGroups"
                :key="group.team.id"
                :value="String(group.team.id)"
                class="rounded-lg border bg-white shadow-sm"
            >
                <!-- Team header with summary -->
                <AccordionTrigger class="px-5 py-4 hover:no-underline">
                    <div class="flex min-w-0 flex-1 items-center gap-4 pr-2 text-left">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-800">{{ group.team.name }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ group.team.code }}</p>
                        </div>
                        <!-- Summary badges -->
                        <div class="flex shrink-0 items-center gap-3 text-sm">
                            <div class="flex items-center gap-1.5 text-gray-600">
                                <svg class="h-4 w-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <span class="font-medium">{{ group.projects.length }}</span>
                                <span class="text-gray-400">proyek</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-gray-600">
                                <svg class="h-4 w-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="font-medium">{{ group.totalMembers }}</span>
                                <span class="text-gray-400">anggota</span>
                            </div>
                            <div v-if="group.activeCount" class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                {{ group.activeCount }} aktif
                            </div>
                        </div>
                    </div>
                </AccordionTrigger>

                <AccordionContent class="px-0 pb-0">
                    <div class="border-t">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <th class="px-5 py-2.5">Nama Proyek</th>
                                    <th class="px-4 py-2.5">Ketua</th>
                                    <th class="px-4 py-2.5">Anggota</th>
                                    <th class="px-4 py-2.5">Status</th>
                                    <th class="px-4 py-2.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="project in group.projects"
                                    :key="project.id"
                                    class="hover:bg-gray-50/50"
                                >
                                    <td class="px-5 py-3 font-medium text-gray-800">{{ project.name }}</td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ project.leader?.display_name || project.leader?.name || '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ project.members_count }} orang
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="[
                                                'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                                statusConfig[project.status]?.classes ?? 'bg-gray-100 text-gray-600 border-gray-200'
                                            ]"
                                        >
                                            {{ statusConfig[project.status]?.label ?? project.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex gap-2">
                                            <Button variant="outline" size="sm" as-child>
                                                <Link :href="route('projects.edit', project.id)">Edit</Link>
                                            </Button>
                                            <Button variant="destructive" size="sm" @click="confirmDelete(project.id, project.name)">
                                                Hapus
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AccordionContent>
            </AccordionItem>
        </Accordion>
    </AppLayout>
</template>
