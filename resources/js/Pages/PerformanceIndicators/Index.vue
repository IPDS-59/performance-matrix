<script setup lang="ts">
import RowActions from '@/Components/RowActions.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import type { PerformanceIndicator, Team } from '@/types';
import type { TeamPickProps } from '@/types/crud';
import IndicatorFormDialog from '@/Components/PerformanceIndicators/IndicatorFormDialog.vue';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ref } from 'vue';

const props = defineProps<TeamPickProps & {
    indicators: PerformanceIndicator[];
    teams: Team[];
    year: number;
    teamId: number;
    canCreate: boolean;
}>();

const year = ref(String(props.year));
const formOpen = ref(false);
const editing = ref<PerformanceIndicator | null>(null);
function openForm(item: PerformanceIndicator | null) { editing.value = item; formOpen.value = true; }
const teamId = ref(props.teamId ? String(props.teamId) : 'all');

function applyFilters() {
    router.get(route('performance-indicators.index'), { year: year.value, team_id: teamId.value === 'all' ? '' : teamId.value }, { preserveState: true });
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
        router.delete(route('performance-indicators.destroy', pendingId.value));
    }
}
</script>

<template>
    <Head title="IKU" />
    <AppLayout>
        <template #title>Indikator Kinerja Utama (IKU)</template>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tahun</label>
                <Select v-model="year" @update:model-value="applyFilters">
                    <SelectTrigger class="w-28">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="y in [2023, 2024, 2025, 2026, 2027]" :key="y" :value="String(y)">{{ y }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div v-if="teams.length > 1">
                <label class="block text-xs font-medium text-gray-600 mb-1">Tim Kerja</label>
                <Select v-model="teamId" @update:model-value="applyFilters">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Semua Tim" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Tim</SelectItem>
                        <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="ml-auto" v-if="canCreate">
                <Button @click="openForm(null)">Tambah IKU</Button>
            </div>
        </div>

        <div class="rounded-md border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nama IKU</TableHead>
                        <TableHead>Tim</TableHead>
                        <TableHead>Tahun</TableHead>
                        <TableHead>Target</TableHead>
                        <TableHead class="w-28 text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!indicators.length">
                        <TableCell colspan="5" class="text-center text-gray-400 py-8">Belum ada data.</TableCell>
                    </TableRow>
                    <TableRow v-for="indicator in indicators" :key="indicator.id">
                        <TableCell class="whitespace-normal">
                            <p class="leading-snug">{{ indicator.name }}</p>
                            <p v-if="indicator.sasaran" class="mt-0.5 text-xs leading-snug text-gray-500">Sasaran: {{ indicator.sasaran }}</p>
                        </TableCell>
                        <TableCell>{{ indicator.team?.name ?? '—' }}</TableCell>
                        <TableCell>{{ indicator.year }}</TableCell>
                        <TableCell>
                            <template v-if="indicator.target != null">
                                {{ indicator.target }} {{ indicator.target_unit }}
                            </template>
                            <template v-else>—</template>
                        </TableCell>
                        <TableCell class="text-right">
                            <RowActions :items="[
                                { label: 'Edit', onSelect: () => openForm(indicator), hidden: !indicator.can_update },
                                { label: 'Hapus', destructive: true, onSelect: () => confirmDelete(indicator.id, indicator.name), hidden: !indicator.can_delete },
                            ]" />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <IndicatorFormDialog v-model:open="formOpen" :item="editing" :teams="formTeams" :manager="manager" :default-year="props.year" />

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Hapus IKU"
            :description="`IKU &quot;${pendingName}&quot; akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.`"
            confirm-label="Hapus IKU"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>
