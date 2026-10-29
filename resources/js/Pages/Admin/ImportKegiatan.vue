<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { CircleAlert, CircleCheck, FileUp } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { readKegiatanWorkbook } from '@/composables/useKegiatanWorkbook';
import { useDateFormat } from '@/composables/useDateFormat';

interface PreviewRow {
    index: number;
    sheet: string | null;
    member: string | null;
    employee_id: number | null;
    employee_name: string | null;
    project_name: string | null;
    project_id: number | null;
    performance_plan_id: number | null;
    rk_options: Array<{ id: number; description: string }>;
    date_start: string;
    date_end: string;
    description: string;
    target: number | null;
    capaian: number | null;
    progress: number;
    notes: string[];
    blocked: boolean;
    duplicate: boolean;
    include: boolean;
}

const props = defineProps<{ defaultWeek: string; tahun: number }>();
const { formatDate } = useDateFormat();

const week = ref(props.defaultWeek);
const file = ref<File | null>(null);
const rows = ref<PreviewRow[]>([]);
const busy = ref(false);
const error = ref('');
const result = ref<{ created: number; skipped: Array<{ description: string; reason: string }> } | null>(null);

const needsRk = (r: PreviewRow) => !r.blocked && r.performance_plan_id === null;
const ready = computed(() => rows.value.filter((r) => r.include && !r.blocked && r.performance_plan_id !== null));
const counts = computed(() => ({
    total: rows.value.length,
    ready: rows.value.filter((r) => !r.blocked && !r.duplicate && r.performance_plan_id !== null).length,
    pickRk: rows.value.filter(needsRk).length,
    blocked: rows.value.filter((r) => r.blocked).length,
    duplicate: rows.value.filter((r) => r.duplicate).length,
}));

async function read() {
    if (!file.value) return;
    busy.value = true;
    error.value = '';
    result.value = null;
    try {
        const parsed = await readKegiatanWorkbook(file.value, props.tahun);
        if (!parsed.length) throw new Error('Tidak ada baris kegiatan yang terbaca. Pastikan sheet punya kolom "Nama Anggota" dan "Uraian".');
        const { data } = await axios.post(route('admin.import-kegiatan.preview'), { week_start: week.value, rows: parsed });
        rows.value = (data.rows as PreviewRow[]).map((r) => ({ ...r, include: !r.blocked && !r.duplicate && r.performance_plan_id !== null }));
    } catch (e) {
        error.value = axios.isAxiosError(e) ? (e.response?.data?.message ?? 'Server menolak file ini.') : (e as Error).message;
    } finally {
        busy.value = false;
    }
}

async function save() {
    busy.value = true;
    error.value = '';
    try {
        const payload = ready.value.map((r) => ({
            employee_id: r.employee_id, performance_plan_id: r.performance_plan_id, project_id: r.project_id, description: r.description,
            date_start: r.date_start, date_end: r.date_end, target: r.target, capaian: r.capaian, progress: r.progress,
        }));
        const { data } = await axios.post(route('admin.import-kegiatan.store'), { rows: payload });
        result.value = data;
        rows.value = [];
    } catch (e) {
        error.value = axios.isAxiosError(e) ? (e.response?.data?.message ?? 'Gagal menyimpan.') : (e as Error).message;
    } finally {
        busy.value = false;
    }
}

const num = (v: number | null) => (v === null ? '-' : v.toLocaleString('id-ID'));
</script>

<template>
    <Head title="Impor Kegiatan" />
    <AppLayout>
        <template #title>Impor Kegiatan dari Excel</template>

        <div class="mb-5 rounded-xl border bg-white p-5">
            <p class="text-sm text-gray-700">
                Unggah file <b>Kegiatan Mingguan Anggota</b> (satu sheet per tim). Kinetik membacanya di browser, mencocokkan pegawai, projek, dan RK, lalu Anda memeriksa hasilnya sebelum menyimpan.
                Kegiatan masuk sebagai <b>rencana</b>. Kegiatan yang tanggalnya tiba dikirim ke kipApp otomatis, seperti rencana biasa.
            </p>
            <div class="mt-4 flex flex-wrap items-end gap-4">
                <div class="space-y-1.5">
                    <Label for="imp-week">Minggu untuk baris tanpa tanggal</Label>
                    <Input id="imp-week" v-model="week" type="date" class="w-48" />
                </div>
                <div class="space-y-1.5">
                    <Label for="imp-file">File Excel (.xlsx)</Label>
                    <Input id="imp-file" type="file" accept=".xlsx" class="w-72" @change="(e: Event) => (file = (e.target as HTMLInputElement).files?.[0] ?? null)" />
                </div>
                <Button :disabled="!file || busy" @click="read"><FileUp class="mr-1.5 h-4 w-4" aria-hidden="true" /> {{ busy && !rows.length ? 'Membaca…' : 'Baca file' }}</Button>
            </div>
            <p class="mt-3 text-xs text-gray-600">Baris tanpa tanggal menjadi rencana Senin sampai Jumat minggu yang dipilih. Kolom Permasalahan, Solusi, dan Rencana Tindak Lanjut tidak diimpor.</p>
        </div>

        <p v-if="error" class="mb-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><CircleAlert class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" /> {{ error }}</p>

        <div v-if="result" class="mb-4 rounded-xl border border-green-200 bg-green-50 p-5 text-sm text-green-900">
            <p class="flex items-center gap-2 font-semibold"><CircleCheck class="h-4 w-4" aria-hidden="true" /> {{ result.created }} kegiatan diimpor.</p>
            <ul v-if="result.skipped.length" class="mt-2 list-disc pl-5">
                <li v-for="(s, i) in result.skipped" :key="i">{{ s.description }}: {{ s.reason }}</li>
            </ul>
            <Link :href="route('weekly.index')" class="mt-3 inline-block font-medium text-primary hover:underline">Buka Rekap Mingguan</Link>
        </div>

        <template v-if="rows.length">
            <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                <span class="rounded-full bg-gray-100 px-3 py-1">{{ counts.total }} baris</span>
                <span class="rounded-full bg-green-50 px-3 py-1 text-green-800">{{ counts.ready }} siap</span>
                <span v-if="counts.pickRk" class="rounded-full bg-amber-50 px-3 py-1 text-amber-800">{{ counts.pickRk }} perlu pilih RK</span>
                <span v-if="counts.duplicate" class="rounded-full bg-gray-100 px-3 py-1 text-gray-700">{{ counts.duplicate }} sudah ada</span>
                <span v-if="counts.blocked" class="rounded-full bg-red-50 px-3 py-1 text-red-700">{{ counts.blocked }} tidak bisa diimpor</span>
                <Button class="ml-auto" :disabled="!ready.length || busy" @click="save">{{ busy ? 'Menyimpan…' : `Impor ${ready.length} kegiatan` }}</Button>
            </div>

            <div class="overflow-x-auto rounded-xl border bg-white">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-10"><span class="sr-only">Impor</span></TableHead>
                            <TableHead>Pegawai</TableHead>
                            <TableHead>Uraian</TableHead>
                            <TableHead>Tanggal</TableHead>
                            <TableHead class="text-right">Target</TableHead>
                            <TableHead class="text-right">Capaian</TableHead>
                            <TableHead class="min-w-[16rem]">RK</TableHead>
                            <TableHead>Catatan</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="r in rows" :key="r.index" :class="r.blocked || r.duplicate ? 'bg-gray-50 text-gray-500' : ''">
                            <TableCell><input v-model="r.include" type="checkbox" :disabled="r.blocked || r.performance_plan_id === null" :aria-label="`Impor baris ${r.index + 1}`"></TableCell>
                            <TableCell class="whitespace-normal">
                                <p :class="r.employee_id ? 'text-gray-900' : 'text-red-700'">{{ r.employee_name ?? r.member }}</p>
                                <p class="text-xs text-gray-500">{{ r.sheet }}<template v-if="r.project_name"> · {{ r.project_name }}</template></p>
                            </TableCell>
                            <TableCell class="max-w-[20rem] whitespace-normal text-sm">{{ r.description }}</TableCell>
                            <TableCell class="whitespace-nowrap tabular-nums text-sm">{{ formatDate(r.date_start) }}<template v-if="r.date_end !== r.date_start"> – {{ formatDate(r.date_end) }}</template></TableCell>
                            <TableCell class="text-right tabular-nums">{{ num(r.target) }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ num(r.capaian) }}</TableCell>
                            <TableCell>
                                <SearchableSelect
                                    v-if="r.rk_options.length && !r.blocked"
                                    v-model="r.performance_plan_id"
                                    :options="r.rk_options.map((o) => ({ value: o.id, label: o.description }))"
                                    placeholder="Pilih RK" search-placeholder="Cari RK…"
                                    @update:model-value="r.include = r.performance_plan_id !== null"
                                />
                                <span v-else class="text-xs text-gray-500">-</span>
                            </TableCell>
                            <TableCell class="whitespace-normal text-xs text-red-700">{{ r.notes.join(' ') }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </template>
    </AppLayout>
</template>
