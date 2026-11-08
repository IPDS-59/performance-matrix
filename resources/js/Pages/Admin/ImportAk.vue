<script setup lang="ts">
import { formatNumber } from '@/composables/useNumber';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { CircleAlert, CircleCheck, FileUp } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { readAkWorkbook, sheetNames, type AkRow } from '@/composables/useAkWorkbook';

interface PreviewRow {
    index: number;
    nip: string;
    name: string | null;
    employee_id: number | null;
    employee_name: string | null;
    integrasi: number | null;
    y2023: number | null;
    y2024: number | null;
    y2025: number | null;
    proposed: number | null;
    current_base: number | null;
    current_source: string | null;
    note: string | null;
    notes: string[];
    blocked: boolean;
    include: boolean;
}

interface YearCheck {
    official: number | null;
    kinetik: number | null;
    diff: number | null;
    months: number;
    official_share: number | null;
    predikat: string;
    verdict: 'same' | 'predikat' | 'months_missing' | 'no_kip' | 'no_official';
}
interface CompareRow { nip: string; name: string | null; employee_name: string | null; matched: boolean; years: Record<string, YearCheck> }

const VERDICT: Record<YearCheck['verdict'], { label: string; cls: string }> = {
    same: { label: 'Sama', cls: 'text-green-700' },
    predikat: { label: 'Beda predikat', cls: 'text-amber-700' },
    months_missing: { label: 'Bulan kosong di kipApp', cls: 'text-red-700' },
    no_kip: { label: 'Tidak ada data kipApp', cls: 'text-gray-500' },
    no_official: { label: 'Tidak ada angka kantor', cls: 'text-gray-400' },
};

const props = defineProps<{ defaultBaseDate: string }>();

const file = ref<File | null>(null);
const sheets = ref<string[]>([]);
const sheet = ref('');
const baseDate = ref(props.defaultBaseDate);
const rows = ref<PreviewRow[]>([]);
const busy = ref(false);
const error = ref('');
const saved = ref<number | null>(null);
const parsedRows = ref<AkRow[]>([]);
const compareRows = ref<CompareRow[]>([]);
const YEARS = ['2023', '2024', '2025'];
const matchCount = computed(() => compareRows.value.filter((r) => YEARS.every((y) => r.years[y].verdict === 'same' || r.years[y].verdict === 'no_official')).length);

const selected = computed(() => rows.value.filter((r) => r.include && !r.blocked && r.proposed !== null));
const fmt = (v: number | null) => (v === null ? '-' : v.toLocaleString('id-ID', { maximumFractionDigits: 3 }));

async function pick(e: Event) {
    file.value = (e.target as HTMLInputElement).files?.[0] ?? null;
    rows.value = [];
    saved.value = null;
    sheets.value = file.value ? await sheetNames(file.value) : [];
    sheet.value = sheets.value.find((s) => s.trim() === '7200') ?? sheets.value[0] ?? '';
}

async function read() {
    if (!file.value || !sheet.value) return;
    busy.value = true;
    error.value = '';
    saved.value = null;
    try {
        const parsed = await readAkWorkbook(file.value, sheet.value);
        if (!parsed.length) throw new Error('Tidak ada baris dengan NIP di sheet ini.');
        const { data } = await axios.post(route('admin.import-ak.preview'), { rows: parsed });
        rows.value = data.rows;
        parsedRows.value = parsed;
        compareRows.value = [];
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'File tidak bisa dibaca.';
    } finally {
        busy.value = false;
    }
}

async function compare() {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await axios.post(route('admin.import-ak.compare'), { rows: parsedRows.value });
        compareRows.value = data.rows;
    } catch {
        error.value = 'Perbandingan gagal. Coba lagi.';
    } finally {
        busy.value = false;
    }
}

async function save() {
    busy.value = true;
    error.value = '';
    try {
        const { data } = await axios.post(route('admin.import-ak.store'), {
            base_date: baseDate.value,
            rows: selected.value.map((r) => ({ employee_id: r.employee_id, ak: r.proposed })),
        });
        saved.value = data.saved;
    } catch {
        error.value = 'Penyimpanan gagal. Coba lagi.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head title="Impor AK" />
    <AppLayout>
        <template #title>Impor angka kredit dari rekapan</template>

        <div class="mb-5 rounded-xl border bg-white p-5">
            <p class="text-sm text-gray-700">
                Unggah file <b>Rekapan SKP</b> kantor (kolom INTEGRASI sampai 2022 dan AK tahun 2023, 2024, 2025). Angka kumulatifnya menjadi <b>AK dasar</b> pegawai pada tanggal yang dipilih,
                menggantikan estimasi dari penilaian bulanan kipApp. Kinetik menghitung lagi mulai hari berikutnya.
            </p>
            <div class="mt-4 flex flex-wrap items-end gap-4">
                <div class="space-y-1.5">
                    <Label for="ak-file">File Excel (.xlsx)</Label>
                    <Input id="ak-file" type="file" accept=".xlsx" class="w-72" @change="pick" />
                </div>
                <div v-if="sheets.length" class="space-y-1.5">
                    <Label for="ak-sheet">Sheet</Label>
                    <select id="ak-sheet" v-model="sheet" class="h-9 rounded-md border px-3 text-sm"><option v-for="s in sheets" :key="s" :value="s">{{ s }}</option></select>
                </div>
                <div class="space-y-1.5">
                    <Label for="ak-date">Angka berlaku sampai</Label>
                    <Input id="ak-date" v-model="baseDate" type="date" class="w-44" />
                </div>
                <Button :disabled="!file || busy" @click="read"><FileUp class="mr-1.5 h-4 w-4" aria-hidden="true" /> {{ busy && !rows.length ? 'Membaca…' : 'Baca file' }}</Button>
            </div>
            <p class="mt-3 text-xs text-gray-600">Jumlah dipakai bila ada. Tanpa kolom jumlah, angkanya integrasi ditambah AK tiga tahun. Baris yang keterangannya menyebut AK awal di luar kolom (PJL, inpassing) tidak dicentang: periksa dokumennya dulu.</p>
        </div>

        <p v-if="error" class="mb-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><CircleAlert class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" /> {{ error }}</p>
        <p v-if="saved !== null" class="mb-4 flex items-start gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status"><CircleCheck class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" /> {{ formatNumber(saved) }} pegawai disimpan.</p>

        <div v-if="rows.length" class="rounded-xl border bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                <p class="text-sm text-gray-700">{{ formatNumber(rows.length) }} baris dibaca, {{ formatNumber(selected.length) }} akan disimpan.</p>
                <div class="flex gap-2">
                    <Button variant="outline" :disabled="busy" @click="compare">Bandingkan dengan hitungan Kinetik</Button>
                    <Button :disabled="!selected.length || busy" @click="save">{{ busy ? 'Menyimpan…' : `Simpan ${formatNumber(selected.length)} pegawai` }}</Button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-10"></TableHead>
                            <TableHead>Pegawai</TableHead>
                            <TableHead class="text-right">Integrasi</TableHead>
                            <TableHead class="text-right">2023</TableHead>
                            <TableHead class="text-right">2024</TableHead>
                            <TableHead class="text-right">2025</TableHead>
                            <TableHead class="text-right">AK dasar baru</TableHead>
                            <TableHead class="text-right">Sekarang</TableHead>
                            <TableHead>Catatan</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="r in rows" :key="r.index" :class="r.blocked ? 'opacity-60' : ''">
                            <TableCell><input v-model="r.include" type="checkbox" :disabled="r.blocked" :aria-label="`Simpan ${r.name}`" /></TableCell>
                            <TableCell class="text-sm">{{ r.employee_name ?? r.name }}<span class="block text-xs text-gray-500">{{ r.nip }}</span></TableCell>
                            <TableCell class="text-right tabular-nums">{{ fmt(r.integrasi) }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ fmt(r.y2023) }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ fmt(r.y2024) }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ fmt(r.y2025) }}</TableCell>
                            <TableCell class="text-right font-semibold tabular-nums">{{ fmt(r.proposed) }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{ fmt(r.current_base) }}</TableCell>
                            <TableCell class="text-xs text-gray-700"><span v-for="n in r.notes" :key="n" class="block">{{ n }}</span></TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <div v-if="compareRows.length" class="mt-5 rounded-xl border bg-white">
            <div class="border-b px-4 py-3">
                <h2 class="text-sm font-semibold">Perbandingan AK per tahun</h2>
                <p class="mt-1 text-xs text-gray-600">
                    Angka kantor (satu predikat setahun) di samping hitungan Kinetik dari penilaian bulanan kipApp. {{ formatNumber(matchCount) }} dari {{ formatNumber(compareRows.length) }} pegawai sama di semua tahun.
                    "Beda predikat" berarti jabatan dan bulannya cocok tetapi predikatnya berbeda, misalnya kantor memakai Sangat Baik setahun penuh. "Bulan kosong" berarti kipApp tidak punya penilaian untuk sebagian bulan.
                </p>
            </div>
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Pegawai</TableHead>
                            <template v-for="y in YEARS" :key="y"><TableHead class="text-right">{{ y }} kantor</TableHead><TableHead class="text-right">{{ y }} Kinetik</TableHead><TableHead>{{ y }} keterangan</TableHead></template>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="r in compareRows" :key="r.nip" :class="r.matched ? '' : 'opacity-50'">
                            <TableCell class="text-sm">{{ r.employee_name ?? r.name }}<span class="block text-xs text-gray-500">{{ r.nip }}</span></TableCell>
                            <template v-for="y in YEARS" :key="y">
                                <TableCell class="text-right tabular-nums">{{ fmt(r.years[y].official) }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ fmt(r.years[y].kinetik) }}</TableCell>
                                <TableCell class="text-xs">
                                    <span :class="VERDICT[r.years[y].verdict].cls" class="font-medium">{{ VERDICT[r.years[y].verdict].label }}</span>
                                    <span v-if="r.years[y].months" class="block text-gray-500">{{ r.years[y].months }} bulan: {{ r.years[y].predikat }}</span>
                                    <span v-if="r.years[y].official_share !== null && r.years[y].verdict !== 'same'" class="block text-gray-500">kantor setara {{ Math.round((r.years[y].official_share ?? 0) * 100) }}%</span>
                                </TableCell>
                            </template>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
