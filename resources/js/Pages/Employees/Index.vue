<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { Employee } from '@/types';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ref } from 'vue';

defineProps<{ employees: Employee[] }>();

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
        router.delete(route('employees.destroy', pendingId.value));
    }
}
</script>

<template>
    <Head title="Pegawai" />
    <AppLayout>
        <template #title>Data Pegawai</template>

        <div class="mb-4 flex justify-end">
            <Button as-child>
                <Link :href="route('employees.create')">Tambah Pegawai</Link>
            </Button>
        </div>

        <div class="rounded-md border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nama</TableHead>
                        <TableHead>NIP</TableHead>
                        <TableHead>Tim Kerja</TableHead>
                        <TableHead>Jabatan</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="w-28 text-right">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!employees.length">
                        <TableCell colspan="6" class="text-center text-gray-400 py-8">Belum ada data.</TableCell>
                    </TableRow>
                    <TableRow v-for="emp in employees" :key="emp.id">
                        <TableCell>
                            <div class="font-medium">{{ emp.display_name || emp.name }}</div>
                            <div v-if="emp.office" class="text-xs text-gray-500">{{ emp.office }}</div>
                        </TableCell>
                        <TableCell class="font-mono text-sm">{{ emp.employee_number ?? '—' }}</TableCell>
                        <TableCell>{{ emp.team?.name ?? '—' }}</TableCell>
                        <TableCell>{{ emp.position ?? '—' }}</TableCell>
                        <TableCell>
                            <span :class="emp.is_active
                                ? 'inline-flex items-center rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700'
                                : 'inline-flex items-center rounded-full border border-gray-200 bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500'">
                                {{ emp.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="route('employees.edit', emp.id)">Edit</Link>
                                </Button>
                                <Button variant="destructive" size="sm" @click="confirmDelete(emp.id, emp.display_name || emp.name)">
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
            title="Hapus Pegawai"
            :description="`Pegawai &quot;${pendingName}&quot; akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.`"
            confirm-label="Hapus Pegawai"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>
