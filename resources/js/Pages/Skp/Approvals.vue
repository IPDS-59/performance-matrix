<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { CircleAlert, ExternalLink } from 'lucide-vue-next';
import type { SkpApprovalRow } from '@/types';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';

defineProps<{ rows: SkpApprovalRow[]; loadError: string | null; canActAsAtasan: boolean }>();

const { formatDate } = useDateFormat();
const processing = ref(false);

type Ask = { title: string; description: string; run: () => void } | null;
const ask = ref<Ask>(null);
const askOpen = computed({ get: () => ask.value !== null, set: (v) => { if (!v) ask.value = null; } });

function post(name: string, id: number) {
    processing.value = true;
    router.post(route(name, id), {}, { preserveScroll: true, onFinish: () => (processing.value = false) });
}

function approve(row: SkpApprovalRow) {
    ask.value = { title: `Tetapkan ${row.label}`, description: `Anda yakin menetapkan ${row.label} milik ${row.employee}? Setelah ditetapkan, RK dan IKI-nya tidak bisa diubah.`, run: () => post('skp.approve', row.id) };
}

function sendBack(row: SkpApprovalRow) {
    ask.value = { title: `Kembalikan ${row.label}`, description: `${row.label} milik ${row.employee} kembali ke Sedang dibuat dan bisa diubah lagi oleh pegawai.`, run: () => post('skp.sendback', row.id) };
}
</script>

<template>
    <Head title="Persetujuan SKP" />
    <AppLayout>
        <template #title>Persetujuan SKP</template>

        <div class="mx-auto max-w-5xl space-y-4">
            <p v-if="loadError" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
                <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <span>Akun kipApp Anda belum bisa dipakai: {{ loadError }}</span>
            </p>

            <section class="overflow-hidden rounded-lg border bg-white" aria-labelledby="menunggu-title">
                <h2 id="menunggu-title" class="border-b bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-900">SKP yang menunggu Anda</h2>
                <Table class="text-sm">
                    <TableHeader>
                        <TableRow class="text-xs uppercase tracking-wide text-gray-500">
                            <TableHead>Pegawai</TableHead>
                            <TableHead>SKP</TableHead>
                            <TableHead class="text-right">RK</TableHead>
                            <TableHead>Menunggu sejak</TableHead>
                            <TableHead class="text-right">Tindakan</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody class="divide-y divide-gray-100">
                        <TableRow v-for="row in rows" :key="row.id">
                            <TableCell class="font-medium text-gray-900">{{ row.employee }}</TableCell>
                            <TableCell class="text-gray-700">
                                {{ row.label }}
                                <span class="block text-xs text-gray-500">{{ row.kind === 'yearly' ? 'Menunggu ditetapkan' : 'Menunggu dinilai' }}</span>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{ row.jml_rk }}</TableCell>
                            <TableCell class="text-gray-700">{{ row.since ? formatDate(row.since) : '—' }}</TableCell>
                            <TableCell class="whitespace-normal text-right">
                                <span v-if="!row.mine" class="text-xs text-gray-500">Atasan: {{ row.atasan || 'lain' }}</span>
                                <div v-else class="flex flex-wrap justify-end gap-2">
                                        <Button v-if="row.kind === 'yearly'" size="sm" :disabled="processing" @click="approve(row)">Tetapkan</Button>
                                        <Button v-if="row.kind === 'quarter'" as="a" href="https://kipapp.bps.go.id/" target="_blank" rel="noopener" size="sm">Nilai di kipApp <ExternalLink class="ml-1 h-3.5 w-3.5" aria-hidden="true" /></Button>
                                        <Button size="sm" variant="outline" :disabled="processing" @click="sendBack(row)">Kembalikan</Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!rows.length">
                            <TableCell colspan="5" class="py-10 text-center text-sm text-gray-500">Tidak ada SKP yang menunggu Anda.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </section>

            <p class="text-xs leading-relaxed text-gray-500">
                Tindakan dilakukan dengan akun kipApp Anda sendiri. Kinetik menetapkan SKP Tahunan dan mengembalikan SKP. Menilai SKP triwulan tetap di kipApp karena butuh nilai tiap RK. Daftar ini diperbarui tiap pagi dan saat pegawai mengirim SKP lewat Kinetik. SKP yang dikirim langsung dari kipApp baru tampil pada pembaruan berikutnya.
            </p>
        </div>

        <ConfirmDialog v-model:open="askOpen" :title="ask?.title" :description="ask?.description" confirm-label="Ya" @confirm="ask?.run()" />
    </AppLayout>
</template>
