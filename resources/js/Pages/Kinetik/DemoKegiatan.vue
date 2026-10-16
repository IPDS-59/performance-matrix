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
interface Item { id: number; text: string; rk: string; period: string; target: number; capaian: number | null; unit: string; stage: Stage; progres?: number; uraian?: string; bukti?: string }

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
const draft = reactive({ rk: rks[0], text: '', mode: 'periode' as 'periode' | 'jam', start: '2026-10-14', end: '2026-10-16', jamMulai: '08:00', jamSelesai: '16:00', progres: 0, uraian: '', bukti: '', target: 1, unit: 'laporan' });
function add() {
    current.value.push({ id: Date.now(), text: draft.text.trim() || 'Kegiatan baru', rk: draft.rk, period: draft.mode === 'periode' ? `${draft.start} s.d. ${draft.end}` : `${draft.start} · ${draft.jamMulai}–${draft.jamSelesai}`, target: Number(draft.target), capaian: null, unit: draft.unit, stage: 'plan', progres: Number(draft.progres), uraian: draft.uraian, bukti: draft.bukti });
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
const role = ref<'me' | 'pj'>('me');
const names = ['Bayu (DB)', 'Sukma (FE)', 'Ical (Scraping/BE)', 'Andi Pratama', 'Dewi Lestari', 'Rudi Hartono', 'Maya Sari', 'Fajar Nugroho', 'Intan Permata', 'Hasan Basri', 'Lina Marlina', 'Yusuf Rahman', 'Nadia Putri', 'Eko Prasetyo', 'Rina Wulandari', 'Dedi Kurniawan'];
const acts = ['Menyusun skema tabel dan migrasi untuk rekap mingguan.', 'Membuat halaman rekap dan popup isian.', 'Sinkron kegiatan dan RK dari kipApp per anggota.', 'Menguji alur login dan tombol sinkron.', 'Menulis dokumentasi pengguna.', 'Merapikan tabel agar kolom ringkas.'];
const people = reactive(names.map((who, i) => {
    const t = 2 + (i % 4);
    return { who, lines: [acts[i % acts.length], acts[(i + 2) % acts.length]], target: t, capaian: Math.max(0, t - (i % 3)), unit: 'tugas', read: false, open: false };
}));
const peopleQuery = ref('');
const onlyUnread = ref(false);
const shownPeople = computed(() => people.filter(p => (!onlyUnread.value || !p.read) && (!peopleQuery.value || `${p.who} ${p.lines.join(' ')}`.toLowerCase().includes(peopleQuery.value.toLowerCase()))));
const readCount = computed(() => people.filter(p => p.read).length);
const para = ref('');
const rkTarget = ref<number | ''>('');
const rkUnit = ref('');
const rkCapaian = ref<number | ''>('');
const locked = ref(false);
function toggle(p: (typeof people)[number]) { p.open = !p.open; p.read = true; }
function insert(line: string) { if (!locked.value) para.value = para.value ? `${para.value} ${line}` : line; }
const personFilter = ref('all');

const percent = (i: Item) => (i.capaian === null || !i.target ? null : Math.round((i.capaian / i.target) * 100));
</script>

<template>
    <Head title="Kegiatan Saya (demo)" />
    <AppLayout>
        <template #title>Kegiatan Saya (demo)</template>

        <div class="mb-4 inline-flex rounded-lg border bg-white p-1" role="tablist" aria-label="Sudut pandang">
            <button type="button" role="tab" :aria-selected="role === 'me'" :class="['rounded-md px-4 py-1.5 text-sm', role === 'me' ? 'bg-primary text-primary-foreground' : 'text-gray-700']" @click="role = 'me'">Anggota</button>
            <button type="button" role="tab" :aria-selected="role === 'pj'" :class="['rounded-md px-4 py-1.5 text-sm', role === 'pj' ? 'bg-primary text-primary-foreground' : 'text-gray-700']" @click="role = 'pj'">PJ (rekap tim)</button>
        </div>

        <p class="mb-4 max-w-3xl rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
            Demo desain. Data contoh di browser, tidak ada yang disimpan. Satu halaman untuk merencanakan dan memantau, tanpa klaim kegiatan.
        </p>

        <template v-if="role === 'me'">
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

        </template>

        <section v-else class="rounded-lg border bg-white" aria-labelledby="h-pj">
            <div class="flex flex-wrap items-center gap-3 border-b px-4 py-3">
                <h2 id="h-pj" class="text-sm font-semibold">Membuat Kinetik <span class="font-normal text-gray-600">RK Tim MTI · {{ people.length }} anggota</span></h2>
                <div class="ml-auto flex items-center gap-2">
                    <Label for="pf" class="text-xs text-gray-600">Lihat</Label>
                    <select id="pf" v-model="personFilter" class="h-9 rounded-md border bg-white px-2 text-sm"><option value="all">Semua anggota</option><option v-for="p in people" :key="p.who" :value="p.who">{{ p.who }}</option></select>
                </div>
            </div>
            <div class="grid lg:grid-cols-[1.05fr_1fr]">
                <div class="border-b bg-gray-50/60 p-4 lg:border-b-0 lg:border-r">
                    <div class="mb-2 flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-gray-600"><span>Kontribusi anggota</span><span class="tabular-nums">Dibaca {{ readCount }}/{{ people.length }}</span></div>
                    <div class="relative mb-2"><Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-500" aria-hidden="true" /><Input v-model="peopleQuery" class="pl-9" placeholder="Cari nama atau isi kegiatan" aria-label="Cari anggota" /></div>
                    <div class="mb-2 flex items-center justify-between text-xs text-gray-600">
                        <label class="flex items-center gap-1.5"><input v-model="onlyUnread" type="checkbox" class="h-4 w-4" /> Belum dibaca saja</label>
                        <span>Menampilkan {{ shownPeople.filter(p => personFilter === 'all' || p.who === personFilter).length }} dari {{ people.length }}</span>
                    </div>
                    <ul class="max-h-96 space-y-2 overflow-y-auto pr-1">
                        <li v-for="p in shownPeople.filter(x => personFilter === 'all' || x.who === personFilter)" :key="p.who" class="rounded-lg border bg-white">
                            <button type="button" class="flex w-full items-baseline gap-2 px-3 py-2 text-left text-sm" :aria-expanded="p.open" @click="toggle(p)">
                                <span class="whitespace-nowrap font-semibold">{{ p.who }}<span v-if="p.read" class="ml-1 text-green-700">✓</span></span>
                                <span v-if="!p.open" class="min-w-0 flex-1 truncate text-gray-600">{{ p.lines[0] }}</span>
                            </button>
                            <div v-if="p.open" class="space-y-1.5 px-3 pb-3 text-sm">
                                <div v-for="line in p.lines" :key="line" class="flex items-start gap-2"><span class="flex-1">{{ line }}</span><Button size="sm" variant="outline" class="h-7 text-xs" :disabled="locked" @click="insert(line)">Sisipkan</Button></div>
                                <p class="tabular-nums text-xs text-gray-700">Target {{ p.target }} {{ p.unit }} · Capaian {{ p.capaian }} {{ p.unit }} ({{ Math.round((p.capaian / p.target) * 100) }}%)</p>
                                <p class="text-xs text-gray-600">Diisi {{ p.who.split(' ')[0] }} sendiri. PJ tidak mengubahnya.</p>
                            </div>
                        </li>
                    </ul>
                    <p v-if="!shownPeople.length" class="py-6 text-center text-sm text-gray-600">Tidak ada anggota yang cocok.</p>
                </div>
                <div class="space-y-3 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-600">Parafrase PJ</div>
                    <Textarea v-model="para" :rows="4" :disabled="locked" placeholder="Tulis parafrase sendiri. Pakai “Sisipkan” di kiri untuk mengambil satu kalimat." />
                    <p class="text-xs text-gray-600">Tidak ada isi otomatis dari semua anggota. Anda memilih kalimat mana yang dipakai.</p>
                    <div class="grid grid-cols-[1fr_1fr_1fr] gap-3">
                        <div class="space-y-1.5"><Label for="rk-t">Target RK</Label><Input id="rk-t" v-model="rkTarget" type="number" min="0" :disabled="locked" /></div>
                        <div class="space-y-1.5"><Label for="rk-u">Satuan</Label><Input id="rk-u" v-model="rkUnit" placeholder="aplikasi" :disabled="locked" /></div>
                        <div class="space-y-1.5"><Label for="rk-c">Capaian RK</Label><Input id="rk-c" v-model="rkCapaian" type="number" min="0" step="any" :disabled="locked" /></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button :disabled="locked">Simpan</Button>
                        <Button variant="outline" @click="locked = !locked">{{ locked ? 'Buka kunci' : 'Kunci' }}</Button>
                        <span class="ml-auto text-sm text-gray-600">{{ locked ? 'Terkunci oleh PJ' : 'Belum dikunci' }}</span>
                    </div>
                </div>
            </div>
        </section>

        <Dialog :open="adding" @update:open="(v: boolean) => (adding = v)">
            <DialogContent class="max-w-lg">
                <DialogHeader><DialogTitle>Tambah kegiatan</DialogTitle><DialogDescription>Rencana ini dikirim ke kipApp pada tanggal mulainya.</DialogDescription></DialogHeader>
                <div class="space-y-3">
                    <div class="space-y-1.5"><Label>Rencana Kinerja (RK)</Label>
                        <Select v-model="draft.rk"><SelectTrigger class="w-full"><SelectValue /></SelectTrigger><SelectContent><SelectItem v-for="r in rks" :key="r" :value="r">{{ r }}</SelectItem></SelectContent></Select>
                    </div>
                    <div class="space-y-1.5"><Label for="d-text">Kegiatan</Label><Textarea id="d-text" v-model="draft.text" :rows="2" placeholder="Contoh: Menyusun halaman rekap" /></div>
                    <div class="flex gap-4 text-sm">
                        <label class="flex items-center gap-1.5"><input v-model="draft.mode" type="radio" value="periode" /> Gunakan periode tanggal</label>
                        <label class="flex items-center gap-1.5"><input v-model="draft.mode" type="radio" value="jam" /> Gunakan jam</label>
                    </div>
                    <div v-if="draft.mode === 'periode'" class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5"><Label for="d-start">Tanggal mulai</Label><Input id="d-start" v-model="draft.start" type="date" /></div>
                        <div class="space-y-1.5"><Label for="d-end">Tanggal selesai</Label><Input id="d-end" v-model="draft.end" type="date" /></div>
                    </div>
                    <div v-else class="grid grid-cols-3 gap-3">
                        <div class="space-y-1.5"><Label for="d-day">Tanggal</Label><Input id="d-day" v-model="draft.start" type="date" /></div>
                        <div class="space-y-1.5"><Label for="d-jm">Jam mulai</Label><Input id="d-jm" v-model="draft.jamMulai" type="time" /></div>
                        <div class="space-y-1.5"><Label for="d-js">Jam selesai</Label><Input id="d-js" v-model="draft.jamSelesai" type="time" /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5"><Label for="d-target">Target (angka)</Label><Input id="d-target" v-model="draft.target" type="number" min="0" /></div>
                        <div class="space-y-1.5"><Label for="d-unit">Satuan</Label><Input id="d-unit" v-model="draft.unit" /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5"><Label for="d-prog">Progres (%)</Label><Input id="d-prog" v-model="draft.progres" type="number" min="0" max="100" /></div>
                        <div class="space-y-1.5"><Label for="d-bukti">Bukti dukung (tautan)</Label><Input id="d-bukti" v-model="draft.bukti" type="url" placeholder="https://…" /></div>
                    </div>
                    <div class="space-y-1.5"><Label for="d-ur">Uraian capaian (teks ke kipApp)</Label><Textarea id="d-ur" v-model="draft.uraian" :rows="2" placeholder="Apa yang sudah dicapai, dalam kalimat" /></div>
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
