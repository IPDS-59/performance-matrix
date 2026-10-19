<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Plus, Search } from 'lucide-vue-next';
import type { PresensiRow } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { filterPresensi, loadPresensi } from '@/composables/usePresensi';
import { useDateFormat } from '@/composables/useDateFormat';

/** "List Aktivitas": the member's kipApp presensi of the quarter. One row fills one kegiatan. */
const props = defineProps<{ open: boolean; date: string }>();
const emit = defineEmits<{ 'update:open': [value: boolean]; pick: [row: PresensiRow] }>();

const { formatDate } = useDateFormat();
const rows = ref<PresensiRow[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const query = ref('');
const shown = computed(() => filterPresensi(rows.value, query.value));

watch(() => props.open, async (open) => {
    if (!open) return;
    query.value = '';
    error.value = null;
    loading.value = true;
    try {
        rows.value = await loadPresensi(props.date);
    } catch (e) {
        rows.value = [];
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
});

function pick(row: PresensiRow) {
    emit('pick', row);
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(v: boolean) => emit('update:open', v)">
        <DialogContent class="max-h-[85vh] max-w-xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>List Aktivitas</DialogTitle>
                <DialogDescription>Klik tombol tambah atau double klik pada baris untuk menggunakan aktivitas</DialogDescription>
            </DialogHeader>
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-500" aria-hidden="true" />
                <Input v-model="query" class="pl-9" placeholder="Cari aktivitas" aria-label="Cari aktivitas" />
            </div>
            <p v-if="loading" class="py-6 text-center text-sm text-gray-600">Mengambil presensi dari kipApp…</p>
            <p v-else-if="error" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert">{{ error }}</p>
            <template v-else>
                <Table>
                    <TableHeader>
                        <TableRow><TableHead>Tanggal</TableHead><TableHead>Deskripsi</TableHead><TableHead class="w-12"><span class="sr-only">Gunakan</span></TableHead></TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in shown" :key="row.id" class="cursor-pointer" @dblclick="pick(row)">
                            <TableCell class="whitespace-nowrap tabular-nums">{{ formatDate(row.date, { day: 'numeric', month: 'short', year: 'numeric' }) }}</TableCell>
                            <TableCell>{{ row.description }}</TableCell>
                            <TableCell><Button type="button" size="icon" class="h-7 w-7 rounded-full" :aria-label="`Gunakan aktivitas ${row.description}`" @click="pick(row)"><Plus class="h-4 w-4" /></Button></TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p v-if="!shown.length" class="py-6 text-center text-sm text-gray-600">Tidak ada aktivitas presensi.</p>
            </template>
        </DialogContent>
    </Dialog>
</template>
