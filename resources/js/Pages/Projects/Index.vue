<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { Project, Team } from '@/types';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ref } from 'vue';

const props = defineProps<{
    projects: Project[];
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

const statusLabels: Record<string, string> = {
    active: 'Aktif',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};
const statusVariants: Record<string, string> = {
    active: 'default',
    completed: 'secondary',
    cancelled: 'destructive',
};
</script>

<template>
    <Head title="Proyek" />
    <AppLayout>
        <template #title>Data Proyek</template>

        <!-- Filters -->
        <div class="mb-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Select v-model="year" @update:modelValue="applyFilters">
                    <SelectTrigger class="w-28">
                        <SelectValue placeholder="Tahun" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="y in [2025, 2026, 2027]" :key="y" :value="y">{{ y }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="teamId" @update:modelValue="applyFilters">
                    <SelectTrigger class="w-52">
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

        <div class="rounded-md border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nama Proyek</TableHead>
                        <TableHead>Tim</TableHead>
                        <TableHead>Ketua</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="w-28 text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!projects.length">
                        <TableCell colspan="5" class="text-center text-gray-400 py-8">Belum ada data proyek.</TableCell>
                    </TableRow>
                    <TableRow v-for="project in projects" :key="project.id">
                        <TableCell class="font-medium">{{ project.name }}</TableCell>
                        <TableCell>{{ project.team?.name ?? '—' }}</TableCell>
                        <TableCell>{{ project.leader?.display_name || project.leader?.name || '—' }}</TableCell>
                        <TableCell>
                            <Badge :variant="statusVariants[project.status] as any">
                                {{ statusLabels[project.status] }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="route('projects.edit', project.id)">Edit</Link>
                                </Button>
                                <Button variant="destructive" size="sm" @click="confirmDelete(project.id, project.name)">
                                    Hapus
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
