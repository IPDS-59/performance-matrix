<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { ChevronLeft, ChevronRight, Lock } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';

interface Item {
    id: number;
    date: string;
    description: string;
    rk: string | null;
    project: string | null;
    target: number | string | null;
    target_unit: string | null;
    capaian: number | string | null;
    capaian_note: string | null;
    evidence_url: string | null;
    progress: number;
    in_kip: boolean;
    sent: boolean;
    lock: string | null;
}

interface Row extends Omit<Item, 'target' | 'target_unit' | 'capaian' | 'capaian_note' | 'evidence_url'> {
    target: string | number;
    target_unit: string;
    capaian: string | number;
    capaian_note: string;
    evidence_url: string;
    to_kip: boolean;
    touched: boolean;
}

const props = defineProps<{ month: string; monthLabel: string; items: Item[] }>();
const { formatDate } = useDateFormat();

const rows = ref<Row[]>([]);
const saving = ref(false);

watch(() => props.items, (items) => {
    rows.value = items.map((i) => ({
        ...i,
        target: i.target ?? '',
        target_unit: i.target_unit ?? '',
        capaian: i.capaian ?? '',
        capaian_note: i.capaian_note ?? '',
        evidence_url: i.evidence_url ?? '',
        to_kip: false,
        touched: false,
    }));
}, { immediate: true });

const touchedRows = computed(() => rows.value.filter((r) => r.touched || r.to_kip));
const toKipCount = computed(() => rows.value.filter((r) => r.to_kip).length);

function shift(delta: number) {
    const [y, m] = props.month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    router.get(route('realisasi.monthly'), { month: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` }, { preserveState: false });
}

/** Capaian is what was done against the target, so it cannot be more than it. */
const over = (r: Row) => r.target !== '' && r.capaian !== '' && Number(r.capaian) > Number(r.target);
const anyOver = computed(() => touchedRows.value.some(over));

function markDone(r: Row) {
    r.touched = true;
    if (r.to_kip && r.progress < 100) r.progress = 100;
}

function save() {
    saving.value = true;
    router.post(route('realisasi.monthly.store'), {
        rows: touchedRows.value.map((r) => ({
            id: r.id,
            target: r.target === '' ? null : r.target,
            target_unit: r.target_unit || null,
            capaian: r.capaian === '' ? null : r.capaian,
            capaian_note: r.capaian_note || null,
            evidence_url: r.evidence_url || null,
            progress: r.progress,
            to_kip: r.to_kip,
        })),
    }, { preserveScroll: true, onFinish: () => (saving.value = false) });
}
</script>

<template>
    <Head title="Isi Capaian Bulanan" />
    <AppLayout>
        <template #title>Isi Capaian Bulanan</template>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Button variant="outline" size="icon" aria-label="Bulan sebelumnya" @click="shift(-1)"><ChevronLeft class="h-4 w-4" aria-hidden="true" /></Button>
                <h2 class="min-w-36 text-center text-lg font-semibold capitalize text-gray-900">{{ monthLabel }}</h2>
                <Button variant="outline" size="icon" aria-label="Bulan berikutnya" @click="shift(1)"><ChevronRight class="h-4 w-4" aria-hidden="true" /></Button>
            </div>
            <Button :disabled="!touchedRows.length || saving || anyOver" @click="save">{{ saving ? 'Menyimpan…' : touchedRows.length ? `Simpan ${touchedRows.length} kegiatan` : 'Simpan' }}</Button>
        </div>

        <p class="mb-4 text-sm text-gray-700">
            Isi target dan capaian semua kegiatan bulan ini sekaligus. Semua yang diubah disimpan di Kinetik. Centang <b>Selesai di kipApp</b> pada kegiatan yang capaiannya sudah final:
            progresnya menjadi 100, lalu uraian dan bukti dikirim ke kipApp saat itu juga, jadi tidak perlu membuka kipApp lagi.
            <span v-if="toKipCount" class="font-medium"> {{ toKipCount }} kegiatan akan dikirim.</span>
        </p>

        <p v-if="!rows.length" class="rounded-xl border bg-white p-6 text-sm text-gray-600">Tidak ada kegiatan di bulan ini. Kegiatan dari kipApp masuk lewat tombol Sinkron di Rekap Mingguan.</p>

        <div v-else class="overflow-x-auto rounded-xl border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="min-w-64">Kegiatan</TableHead>
                        <TableHead class="w-28">Target</TableHead>
                        <TableHead class="w-24">Satuan</TableHead>
                        <TableHead class="w-28">Capaian</TableHead>
                        <TableHead class="w-24">Progres %</TableHead>
                        <TableHead class="min-w-56">Uraian capaian</TableHead>
                        <TableHead class="min-w-48">Bukti (tautan)</TableHead>
                        <TableHead class="w-32">Selesai di kipApp</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="r in rows" :key="r.id" class="align-top">
                        <TableCell class="text-sm">
                            <span class="font-medium text-gray-900">{{ r.description }}</span>
                            <span class="block text-xs text-gray-600">{{ formatDate(r.date) }}<template v-if="r.rk"> · {{ r.rk }}</template></span>
                            <span v-if="r.lock" class="mt-1 flex items-center gap-1 text-xs text-amber-700"><Lock class="h-3 w-3" aria-hidden="true" /> {{ r.lock }}</span>
                        </TableCell>
                        <TableCell><Input v-model="r.target" type="number" min="0" step="any" class="h-8" :aria-label="`Target ${r.description}`" @input="r.touched = true" /></TableCell>
                        <TableCell><Input v-model="r.target_unit" class="h-8" placeholder="dokumen" :aria-label="`Satuan ${r.description}`" @input="r.touched = true" /></TableCell>
                        <TableCell>
                            <Input v-model="r.capaian" type="number" min="0" step="any" class="h-8" :class="over(r) ? 'border-red-500' : ''" :aria-invalid="over(r)" :aria-label="`Capaian ${r.description}`" @input="r.touched = true" />
                            <p v-if="over(r)" class="mt-1 text-xs text-red-600" role="alert">Melebihi target. Naikkan targetnya dulu.</p>
                        </TableCell>
                        <TableCell><Input v-model.number="r.progress" type="number" min="0" max="100" class="h-8" :aria-label="`Progres ${r.description}`" @input="r.touched = true" /></TableCell>
                        <TableCell><Textarea v-model="r.capaian_note" rows="2" class="min-h-0 text-sm" :aria-label="`Uraian capaian ${r.description}`" @input="r.touched = true" /></TableCell>
                        <TableCell><Input v-model="r.evidence_url" class="h-8" placeholder="Tautan bukti" :aria-label="`Bukti ${r.description}`" @input="r.touched = true" /></TableCell>
                        <TableCell>
                            <label class="flex items-center gap-2 text-sm" :class="r.lock ? 'opacity-50' : ''">
                                <input v-model="r.to_kip" type="checkbox" :disabled="Boolean(r.lock)" @change="() => markDone(r)" />
                                <span>{{ r.in_kip ? 'Perbarui' : 'Kirim' }}</span>
                            </label>
                            <span v-if="r.sent" class="text-xs text-gray-500">sudah dikirim ke atasan</span>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
