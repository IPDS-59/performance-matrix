<script setup lang="ts">
import RowActions from '@/Components/RowActions.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import type { Employee, Team } from '@/types';
import TeamFormDialog from '@/Components/Teams/TeamFormDialog.vue';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ref } from 'vue';

const props = defineProps<{
    teams: Team[];
    manageableTeamIds: number[];
    employees: Employee[];
}>();

const formOpen = ref(false);
const editing = ref<Team | null>(null);
function openForm(team: Team | null) { editing.value = team; formOpen.value = true; }

const manageableSet = new Set(props.manageableTeamIds);

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
        router.delete(route('teams.destroy', pendingId.value));
    }
}
</script>

<template>
    <Head title="Tim Kerja" />
    <AppLayout>
        <template #title>Tim Kerja</template>

        <div class="mb-4 flex justify-end">
            <Button @click="openForm(null)">Tambah Tim</Button>
        </div>

        <div class="rounded-md border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Kode</TableHead>
                        <TableHead>Nama Tim</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="w-28 text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!teams.length">
                        <TableCell colspan="4" class="py-8 text-center text-gray-400">Belum ada data.</TableCell>
                    </TableRow>
                    <TableRow v-for="team in teams" :key="team.id">
                        <TableCell class="font-mono text-sm">{{ team.code }}</TableCell>
                        <TableCell>{{ team.name }}</TableCell>
                        <TableCell>
                            <span :class="team.is_active
                                ? 'inline-flex items-center rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700'
                                : 'inline-flex items-center rounded-full border border-gray-200 bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500'">
                                {{ team.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right">
                            <RowActions :items="[
                                { label: 'Kelola Anggota', href: route('teams.members.edit', team.id), hidden: !manageableSet.has(team.id) },
                                { label: 'Edit', onSelect: () => openForm(team) },
                                { label: 'Hapus', destructive: true, onSelect: () => confirmDelete(team.id, team.name) },
                            ]" />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <TeamFormDialog v-model:open="formOpen" :item="editing" :employees="employees" />

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Hapus Tim"
            :description="`Tim &quot;${pendingName}&quot; akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.`"
            confirm-label="Hapus Tim"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>
