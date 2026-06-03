<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { PerformanceIndicator, Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ref } from 'vue';

const props = defineProps<{
    indicators: PerformanceIndicator[];
    teams: Team[];
    year: number;
    teamId: number;
    canCreate: boolean;
}>();

const year = ref(String(props.year));
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
                <Button as-child>
                    <Link :href="route('performance-indicators.create')">Tambah IKU</Link>
                </Button>
            </div>
        </div>

        <div class="rounded-md border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Kode</TableHead>
                        <TableHead>Nama IKU</TableHead>
                        <TableHead>Tim</TableHead>
                        <TableHead>Tahun</TableHead>
                        <TableHead>Target</TableHead>
                        <TableHead class="w-28 text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!indicators.length">
                        <TableCell colspan="6" class="text-center text-gray-400 py-8">Belum ada data.</TableCell>
                    </TableRow>
                    <TableRow v-for="indicator in indicators" :key="indicator.id">
                        <TableCell class="font-mono text-sm">{{ indicator.code ?? '—' }}</TableCell>
                        <TableCell>{{ indicator.name }}</TableCell>
                        <TableCell>{{ indicator.team?.name ?? '—' }}</TableCell>
                        <TableCell>{{ indicator.year }}</TableCell>
                        <TableCell>
                            <template v-if="indicator.target != null">
                                {{ indicator.target }} {{ indicator.target_unit }}
                            </template>
                            <template v-else>—</template>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="inline-flex gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="route('performance-indicators.edit', indicator.id)">Edit</Link>
                                </Button>
                                <Button variant="destructive" size="sm" @click="confirmDelete(indicator.id, indicator.name)">
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
            title="Hapus IKU"
            :description="`IKU &quot;${pendingName}&quot; akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.`"
            confirm-label="Hapus IKU"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>
