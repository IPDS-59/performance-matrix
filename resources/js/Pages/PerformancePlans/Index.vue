<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { PerformancePlan } from '@/types';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ref } from 'vue';

interface ProjectOption {
    id: number;
    name: string;
    year: number;
    team?: { id: number; name: string } | null;
}

const props = defineProps<{
    plans: PerformancePlan[];
    projects: ProjectOption[];
    projectId: number;
    canCreate: boolean;
}>();

const projectId = ref(props.projectId ? String(props.projectId) : 'all');

function applyFilters() {
    router.get(route('performance-plans.index'), { project_id: projectId.value === 'all' ? '' : projectId.value }, { preserveState: true });
}

const confirmOpen = ref(false);
const pendingId = ref<number | null>(null);
const pendingName = ref('');

function confirmDelete(id: number, name: string) {
    pendingId.value = id;
    pendingName.value = name;
    confirmOpen.value = true;
}

function executeDelete() {
    if (pendingId.value !== null) {
        router.delete(route('performance-plans.destroy', pendingId.value));
    }
}

const periodTypeLabel: Record<string, string> = {
    year: 'Tahunan',
    quarter: 'Triwulan',
};
</script>

<template>
    <Head title="Rencana Kinerja" />
    <AppLayout>
        <template #title>Rencana Kinerja (RK)</template>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Proyek</label>
                <Select v-model="projectId" @update:model-value="applyFilters">
                    <SelectTrigger class="w-64">
                        <SelectValue placeholder="Semua Proyek" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Proyek</SelectItem>
                        <SelectItem v-for="p in projects" :key="p.id" :value="String(p.id)">
                            {{ p.name }} ({{ p.year }})
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="ml-auto" v-if="canCreate">
                <Button as-child>
                    <Link :href="route('performance-plans.create')">Tambah RK</Link>
                </Button>
            </div>
        </div>

        <div class="rounded-md border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Kode</TableHead>
                        <TableHead>Deskripsi</TableHead>
                        <TableHead>Proyek</TableHead>
                        <TableHead>Periode</TableHead>
                        <TableHead>Target</TableHead>
                        <TableHead>PIC</TableHead>
                        <TableHead class="w-28 text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!plans.length">
                        <TableCell colspan="7" class="text-center text-gray-400 py-8">Belum ada data.</TableCell>
                    </TableRow>
                    <TableRow v-for="plan in plans" :key="plan.id">
                        <TableCell class="font-mono text-sm">{{ plan.code ?? '—' }}</TableCell>
                        <TableCell>{{ plan.description }}</TableCell>
                        <TableCell>{{ plan.project?.name ?? plan.team?.name ?? '—' }}</TableCell>
                        <TableCell>
                            {{ periodTypeLabel[plan.period_type] ?? plan.period_type }}
                            <template v-if="plan.period"> TW{{ plan.period }}</template>
                        </TableCell>
                        <TableCell>
                            <template v-if="plan.target != null">{{ plan.target }} {{ plan.target_unit }}</template>
                            <template v-else>—</template>
                        </TableCell>
                        <TableCell>{{ plan.pic?.display_name || plan.pic?.name || '—' }}</TableCell>
                        <TableCell class="text-right">
                            <div class="inline-flex gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="route('performance-plans.edit', plan.id)">Edit</Link>
                                </Button>
                                <Button variant="destructive" size="sm" @click="confirmDelete(plan.id, plan.description)">
                                    Hapus
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Hapus Rencana Kinerja"
            :description="`RK &quot;${pendingName}&quot; akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.`"
            confirm-label="Hapus RK"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>
