<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Search } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

type Level = 'pimpinan' | 'pj' | 'anggota';
interface Row { id: number; name: string; email: string; level: Level }

const props = defineProps<{ users: Row[] }>();

const LEVELS: Array<{ key: Level | 'all'; label: string }> = [
    { key: 'all', label: 'Semua' },
    { key: 'pimpinan', label: 'Pimpinan' },
    { key: 'pj', label: 'PJ' },
    { key: 'anggota', label: 'Anggota' },
];
const levelLabel: Record<Level, string> = { pimpinan: 'Pimpinan', pj: 'PJ', anggota: 'Anggota' };
const tone: Record<Level, string> = { pimpinan: 'bg-purple-100 text-purple-900', pj: 'bg-blue-100 text-blue-900', anggota: 'bg-gray-100 text-gray-800' };

const level = ref<Level | 'all'>('all');
const query = ref('');
const shown = computed(() => props.users.filter(u =>
    (level.value === 'all' || u.level === level.value)
    && (!query.value || `${u.name} ${u.email}`.toLowerCase().includes(query.value.toLowerCase())),
));
const count = (key: Level | 'all') => (key === 'all' ? props.users.length : props.users.filter(u => u.level === key).length);

function start(user: Row) {
    router.post(route('admin.impersonate.start', user.id));
}
</script>

<template>
    <Head title="Lihat sebagai" />
    <AppLayout>
        <template #title>Lihat sebagai pengguna</template>

        <p class="mb-4 max-w-3xl rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
            Pilih satu pengguna untuk bertindak sebagai dia: melihat aplikasi seperti yang ia lihat, dan menyimpan, mengirim ke kipApp, atau menghapus atas namanya.
            <strong>Setiap perubahan tercatat</strong> dengan nama Anda. Kata sandi, profil, dan login kipApp tidak bisa diubah dari sini. Akun admin tidak dapat dipilih.
            Setiap awal dan akhir tercatat.
        </p>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="inline-flex rounded-md border bg-white p-0.5" role="tablist" aria-label="Tingkat pengguna">
                <button v-for="l in LEVELS" :key="l.key" type="button" role="tab" :aria-selected="level === l.key"
                    :class="['rounded px-3 py-1.5 text-sm', level === l.key ? 'bg-primary text-primary-foreground' : 'text-gray-700']" @click="level = l.key">
                    {{ l.label }} <span class="tabular-nums opacity-70">{{ count(l.key) }}</span>
                </button>
            </div>
            <div class="relative w-full max-w-xs">
                <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-500" aria-hidden="true" />
                <Input v-model="query" class="pl-9" placeholder="Cari nama atau email" aria-label="Cari pengguna" />
            </div>
        </div>

        <div class="rounded-lg border bg-white">
            <Table>
                <TableHeader><TableRow><TableHead>Nama</TableHead><TableHead>Email</TableHead><TableHead>Tingkat</TableHead><TableHead class="text-right">Aksi</TableHead></TableRow></TableHeader>
                <TableBody>
                    <TableRow v-for="u in shown" :key="u.id">
                        <TableCell class="font-medium">{{ u.name }}</TableCell>
                        <TableCell class="text-gray-600">{{ u.email }}</TableCell>
                        <TableCell><span :class="['rounded-full px-2 py-0.5 text-xs font-medium', tone[u.level]]">{{ levelLabel[u.level] }}</span></TableCell>
                        <TableCell class="text-right"><Button size="sm" variant="outline" @click="start(u)">Lihat sebagai</Button></TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <p v-if="!shown.length" class="px-4 py-8 text-center text-sm text-gray-600">Tidak ada pengguna yang cocok.</p>
        </div>
    </AppLayout>
</template>
