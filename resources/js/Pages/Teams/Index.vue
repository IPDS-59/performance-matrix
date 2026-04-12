<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ref } from 'vue';

defineProps<{ teams: Team[] }>();

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
            <Button as-child>
                <Link :href="route('teams.create')">Tambah Tim</Link>
            </Button>
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
                        <TableCell colspan="4" class="text-center text-gray-400 py-8">Belum ada data.</TableCell>
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
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="route('teams.edit', team.id)">Edit</Link>
                                </Button>
                                <Button variant="destructive" size="sm" @click="confirmDelete(team.id, team.name)">
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
            title="Hapus Tim"
            :description="`Tim &quot;${pendingName}&quot; akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.`"
            confirm-label="Hapus Tim"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>
