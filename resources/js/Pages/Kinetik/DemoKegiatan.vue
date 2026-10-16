<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { Plus, Search } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

/** Demo only: sample data in the browser, nothing is saved. Shows the planned one-page flow. */
type Stage = 'plan' | 'kip' | 'done' | 'sent';
interface Item { id: number; text: string; rk: string; period: string; target: number; capaian: number | null; unit: string; stage: Stage }

const stageLabel: Record<Stage, string> = { plan: 'Direncanakan', kip: 'Ada di kipApp', done: 'Selesai', sent: 'Terkirim ke atasan' };
const stageClass: Record<Stage, string> = { plan: 'bg-gray-100 text-gray-700', kip: 'bg-blue-50 text-blue-800', done: 'bg-green-50 text-green-800', sent: 'bg-emerald-100 text-emerald-900' };
const nextLabel: Partial<Record<Stage, string>> = { plan: 'Kirim ke kipApp', kip: 'Tandai selesai', done: 'Kirim ke atasan' };
const nextStage: Partial<Record<Stage, Stage>> = { plan: 'kip', kip: 'done', done: 'sent' };

const rks = ['Membuat Kinetik', 'Pengolahan SE2026'];
const last = ref<Item[]>([
    { id: 1, text: 'Menyusun skema tabel Kinetik', rk: 'Membuat Kinetik', period: '5 – 9 Okt', target: 1, capaian: 1, unit: 'skema', stage: 'sent' },
    { id: 2, text: 'Entri data SE2026 blok 1–11', rk: 'Pengolahan SE2026', period: '6 – 10 Okt', target: 11, capaian: 9, unit: 'blok', stage: 'done' },
]);
const current = ref<Item[]>([
    { id: 3, text: 'Entri data SE2026 blok 12–20', rk: 'Pengolahan SE2026', period: '12 – 16 Okt', target: 9, capaian: null, unit: 'blok', stage: 'kip' },
    { id: 4, text: 'Susun laporan triwulan', rk: 'Membuat Kinetik', period: '14 – 16 Okt', target: 1, capaian: null, unit: 'laporan', stage: 'plan' },
]);

const query = ref('');
const match = (i: Item) => !query.value || `${i.text} ${i.rk}`.toLowerCase().includes(query.value.toLowerCase());
const lastRows = computed(() => last.value.filter(match));
const currentRows = computed(() => current.value.filter(match));

const adding = ref(false);
const draft = reactive({ rk: rks[0], text: '', target: 1, unit: 'laporan' });
function add() {
    current.value.push({ id: Date.now(), text: draft.text.trim() || 'Kegiatan baru', rk: draft.rk, period: '14 – 16 Okt', target: Number(draft.target), capaian: null, unit: draft.unit, stage: 'plan' });
    draft.text = '';
    adding.value = false;
}

const open = ref<Item | null>(null);
const edit = reactive({ target: 0, capaian: '' as string | number, unit: '' });
function show(item: Item) {
    open.value = item;
    edit.target = item.target;
    edit.capaian = item.capaian ?? '';
    edit.unit = item.unit;
}
function applyEdits() {
    if (!open.value) return;
    open.value.target = Number(edit.target);
    open.value.unit = edit.unit;
    open.value.capaian = edit.capaian === '' ? null : Number(edit.capaian);
}
function save() { applyEdits(); open.value = null; }
function advance() {
    if (!open.value) return;
    applyEdits();
    const next = nextStage[open.value.stage];
    if (next) open.value.stage = next;
    open.value = null;
}
const percent = (i: Item) => (i.capaian === null || !i.target ? null : Math.round((i.capaian / i.target) * 100));
</script>

<template>
    <Head title="Kegiatan Saya (demo)" />
    <AppLayout>
        <template #title>Kegiatan Saya (demo)</template>

        <p class="mb-4 max-w-3xl rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
            Demo desain. Data contoh di browser, tidak ada yang disimpan. Satu halaman untuk merencanakan dan memantau, tanpa klaim kegiatan.
        </p>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="relative w-full max-w-xs">
                <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-500" aria-hidden="true" />
                <Input v-model="query" class="pl-9" placeholder="Cari kegiatan atau RK" aria-label="Cari kegiatan" />
            </div>
            <span class="rounded-md border bg-white px-3 py-2 text-sm font-medium tabular-nums">12 – 18 Oktober 2026</span>
            <Button class="ml-auto" @click="adding = true"><Plus class="mr-1 h-4 w-4" aria-hidden="true" /> Tambah</Button>
        </div>

        <section class="mb-6 rounded-lg border bg-white" aria-labelledby="h-last">
            <h2 id="h-last" class="border-b px-4 py-3 text-sm font-semibold">Capaian minggu lalu <span class="font-normal text-gray-600">5 – 11 Oktober</span></h2>
            <Table>
                <TableHeader><TableRow><TableHead>Kegiatan</TableHead><TableHead>RK</TableHead><TableHead>Target</TableHead><TableHead>Capaian</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
                <TableBody>
                    <TableRow v-for="i in lastRows" :key="i.id" class="cursor-pointer" tabindex="0" @click="show(i)" @keydown.enter="show(i)">
                        <TableCell class="max-w-xs truncate font-medium">{{ i.text }}</TableCell>
                        <TableCell class="text-gray-600">{{ i.rk }}</TableCell>
                        <TableCell class="tabular-nums">{{ i.target }} {{ i.unit }}</TableCell>
                        <TableCell class="tabular-nums">{{ i.capaian ?? '–' }} {{ i.capaian !== null ? i.unit : '' }}<span v-if="percent(i) !== null" class="ml-1 text-gray-600">({{ percent(i) }}%)</span></TableCell>
                        <TableCell><span :class="['rounded-full px-2 py-0.5 text-xs font-medium', stageClass[i.stage]]">{{ stageLabel[i.stage] }}</span></TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>

        <section class="rounded-lg border bg-white" aria-labelledby="h-now">
            <h2 id="h-now" class="border-b px-4 py-3 text-sm font-semibold">Rencana minggu ini <span class="font-normal text-gray-600">12 – 18 Oktober</span></h2>
            <Table>
                <TableHeader><TableRow><TableHead>Kegiatan</TableHead><TableHead>RK</TableHead><TableHead>Tanggal</TableHead><TableHead>Target</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
                <TableBody>
                    <TableRow v-for="i in currentRows" :key="i.id" class="cursor-pointer" tabindex="0" @click="show(i)" @keydown.enter="show(i)">
                        <TableCell class="max-w-xs truncate font-medium">{{ i.text }}</TableCell>
                        <TableCell class="text-gray-600">{{ i.rk }}</TableCell>
                        <TableCell class="tabular-nums">{{ i.period }}</TableCell>
                        <TableCell class="tabular-nums">{{ i.target }} {{ i.unit }}</TableCell>
                        <TableCell><span :class="['rounded-full px-2 py-0.5 text-xs font-medium', stageClass[i.stage]]">{{ stageLabel[i.stage] }}</span></TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <p v-if="!currentRows.length" class="px-4 py-8 text-center text-sm text-gray-600">Tidak ada rencana. Tekan “Tambah”.</p>
        </section>

        <Dialog :open="adding" @update:open="(v: boolean) => (adding = v)">
            <DialogContent class="max-w-lg">
                <DialogHeader><DialogTitle>Tambah kegiatan</DialogTitle><DialogDescription>Rencana ini dikirim ke kipApp pada tanggal mulainya.</DialogDescription></DialogHeader>
                <div class="space-y-3">
                    <div class="space-y-1.5"><Label>Rencana Kinerja (RK)</Label>
                        <Select v-model="draft.rk"><SelectTrigger class="w-full"><SelectValue /></SelectTrigger><SelectContent><SelectItem v-for="r in rks" :key="r" :value="r">{{ r }}</SelectItem></SelectContent></Select>
                    </div>
                    <div class="space-y-1.5"><Label for="d-text">Apa yang dikerjakan</Label><Textarea id="d-text" v-model="draft.text" :rows="2" placeholder="Contoh: Menyusun halaman rekap" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5"><Label for="d-target">Target (angka)</Label><Input id="d-target" v-model="draft.target" type="number" min="0" /></div>
                        <div class="space-y-1.5"><Label for="d-unit">Satuan</Label><Input id="d-unit" v-model="draft.unit" /></div>
                    </div>
                </div>
                <DialogFooter><Button variant="ghost" @click="adding = false">Batal</Button><Button @click="add">Simpan</Button></DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="open !== null" @update:open="(v: boolean) => { if (!v) open = null; }">
            <DialogContent v-if="open" class="max-w-lg">
                <DialogHeader><DialogTitle>{{ open.text }}</DialogTitle><DialogDescription>{{ open.rk }} · {{ open.period }}</DialogDescription></DialogHeader>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5"><Label for="e-target">Target (angka)</Label><Input id="e-target" v-model="edit.target" type="number" min="0" /></div>
                        <div class="space-y-1.5"><Label for="e-unit">Satuan</Label><Input id="e-unit" v-model="edit.unit" /></div>
                    </div>
                    <div class="space-y-1.5"><Label for="e-cap">Capaian (angka, satuan {{ edit.unit }})</Label><Input id="e-cap" v-model="edit.capaian" type="number" min="0" step="any" placeholder="0" /></div>
                    <p class="text-sm text-gray-600">Status: <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', stageClass[open.stage]]">{{ stageLabel[open.stage] }}</span></p>
                </div>
                <DialogFooter>
                    <Button variant="ghost" @click="open = null">Tutup</Button>
                    <Button variant="outline" @click="save">Simpan</Button>
                    <Button v-if="nextLabel[open.stage]" @click="advance">{{ nextLabel[open.stage] }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
