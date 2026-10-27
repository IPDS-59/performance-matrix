<script setup lang="ts">
import RowActions from '@/Components/RowActions.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import type { Employee, PerformancePlan } from '@/types';
import type { ProjectChoice } from '@/types/crud';
import PlanFormDialog from '@/Components/PerformancePlans/PlanFormDialog.vue';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { computed, ref } from 'vue';

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
    formProjects: ProjectChoice[];
    employees: Employee[];
    manager: boolean;
}>();

const writeKip = computed(() => Boolean((usePage().props as { can?: { write_kip_structure?: boolean } }).can?.write_kip_structure));
const pushPlan = (id: number) => router.post(route('performance-plans.push', id), {}, { preserveScroll: true });

const formOpen = ref(false);
const editing = ref<PerformancePlan | null>(null);
function openForm(plan: PerformancePlan | null) { editing.value = plan; formOpen.value = true; }

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
                <Button @click="openForm(null)">Tambah RK</Button>
            </div>
        </div>

        <div class="rounded-md border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
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
                        <TableCell colspan="6" class="text-center text-gray-400 py-8">Belum ada data.</TableCell>
                    </TableRow>
                    <TableRow v-for="plan in plans" :key="plan.id">
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
                            <RowActions :items="[
                                { label: 'Edit', onSelect: () => openForm(plan), hidden: !plan.can_update },
                                { label: 'Kirim ke kipApp', onSelect: () => pushPlan(plan.id), hidden: !(writeKip && plan.can_update && !plan.kip_rkproyek_id) },
                                { label: 'Hapus', destructive: true, onSelect: () => confirmDelete(plan.id, plan.description), hidden: !plan.can_delete },
                            ]" />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <PlanFormDialog v-model:open="formOpen" :item="editing" :projects="formProjects" :employees="employees" :manager="manager" />

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Hapus Rencana Kinerja"
            :description="`RK &quot;${pendingName}&quot; akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.`"
            confirm-label="Hapus RK"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>
