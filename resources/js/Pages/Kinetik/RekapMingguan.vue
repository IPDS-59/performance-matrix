<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ChevronDown, ChevronLeft, ChevronRight, Plus, RefreshCw, Search } from 'lucide-vue-next';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import type { MemberWeekProps, PlanItemRow } from '@/types';
import RecapPeriodTable from '@/Components/Kinetik/RecapPeriodTable.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import SyncMyActivitiesButton from '@/Components/Kinetik/SyncMyActivitiesButton.vue';
import PlanItemTable from '@/Components/Kinetik/PlanItemTable.vue';
import PlanItemFormDialog from '@/Components/Kinetik/PlanItemFormDialog.vue';
import PlanItemDetailDialog from '@/Components/Kinetik/PlanItemDetailDialog.vue';
import { useDateFormat } from '@/composables/useDateFormat';
import { planRowMatches } from '@/composables/usePlanSearch';
import { useRowSelection } from '@/composables/useRowSelection';
import { bulkCopy, useBulkSend } from '@/composables/usePlanBulk';

const props = defineProps<MemberWeekProps>();
const { formatDate } = useDateFormat();
const range = (a?: string, b?: string) => (a && b ? `${formatDate(a, { day: 'numeric', month: 'long' })} – ${formatDate(b, { day: 'numeric', month: 'long', year: 'numeric' })}` : '');

const query = ref('');
const matches = (row: PlanItemRow) => planRowMatches(row, query.value, d => formatDate(d, { day: 'numeric', month: 'long', year: 'numeric' }));
// Coming from the Beranda card "Isi realisasi": only the kegiatan of last week that still lack their capaian.
const onlyRealisasi = ref(typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('perlu') === 'realisasi');
const needsRealisasi = (row: PlanItemRow) => row.capaian === null || row.capaian === undefined;
const lastRows = computed(() => (props.lastWeek ?? []).filter(matches).filter(r => !onlyRealisasi.value || needsRealisasi(r)));
const lackCount = computed(() => (props.lastWeek ?? []).filter(needsRealisasi).length);
const thisRows = computed(() => (props.thisWeek ?? []).filter(matches));

const removing = ref<PlanItemRow | null>(null);
function removeConfirmed() {
    if (!removing.value) return;
    router.delete(route('plan-items.destroy', removing.value.id), { preserveScroll: true, onFinish: () => (removing.value = null) });
}
const refresh = () => router.reload({ preserveScroll: true });

const detailId = ref<number | null>(null);
// The detail follows the fresh props, so a saved capaian shows at once.
const detail = computed(() => [...(props.lastWeek ?? []), ...(props.thisWeek ?? [])].find(r => r.id === detailId.value) ?? null);
const formOpen = ref(false);
const editing = ref<PlanItemRow | null>(null);
const copying = ref<PlanItemRow | null>(null);

const selection = useRowSelection(() => [...(props.lastWeek ?? []), ...(props.thisWeek ?? [])]);
const bulk = useBulkSend(() => ({ send: selection.toSend.value.map(r => r.id), cancel: selection.toCancel.value.map(r => r.id), push: selection.toPush.value.map(r => r.id) }), selection.clear);

function add() { editing.value = null; copying.value = null; formOpen.value = true; }
function edit(item: PlanItemRow) { detailId.value = null; editing.value = item; copying.value = null; formOpen.value = true; }
function copy(item: PlanItemRow) { detailId.value = null; editing.value = null; copying.value = item; formOpen.value = true; }
function go(week?: string) { if (week) router.get(route('weekly.index'), { week }, { preserveState: false }); }

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const monthName = computed(() => `${MONTHS[(props.month ?? 1) - 1]} ${props.year}`);
const to = (query: Record<string, string | number>) => router.get(route('weekly.index'), query, { preserveState: false });
const openMonth = (m: number | string) => to({ year: props.year, month: m });
const toMonths = () => to({ year: props.year });
const toWeeks = () => to({ year: props.year, month: props.month ?? 1 });
const shiftYear = (delta: number) => to({ year: props.year + delta });
</script>

<template>
    <Head title="Rekap Mingguan" />
    <AppLayout>
        <template #title>Rekap Mingguan</template>

        <div v-if="!employee" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Akun Anda belum terhubung ke data pegawai.
        </div>

        <template v-else-if="view !== 'detail'">
            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border bg-white p-3">
                <template v-if="view === 'months'">
                    <div class="flex items-center rounded-md border">
                        <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Tahun sebelumnya" @click="shiftYear(-1)"><ChevronLeft class="h-4 w-4" /></Button>
                        <span class="min-w-24 px-2 text-center text-sm font-semibold tabular-nums">{{ year }}</span>
                        <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Tahun berikutnya" @click="shiftYear(1)"><ChevronRight class="h-4 w-4" /></Button>
                    </div>
                    <p class="text-sm text-gray-600">Pilih bulan, lalu pilih minggunya.</p>
                </template>
                <template v-else>
                    <Button variant="ghost" size="sm" @click="toMonths"><ChevronLeft class="mr-1 h-4 w-4" aria-hidden="true" /> Semua bulan {{ year }}</Button>
                    <h2 class="text-lg font-semibold">{{ monthName }}</h2>
                </template>
            </div>
            <RecapPeriodTable :rows="periods" :period-label="view === 'months' ? 'Bulan' : 'Minggu'" written-label="Capaian terisi" locked-label="Terkirim ke atasan" done-status="Terkirim" @open="(k) => (view === 'months' ? openMonth(k) : go(String(k)))" />
        </template>

        <template v-else>
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <Button variant="ghost" size="sm" class="-ml-2" @click="toWeeks"><ChevronLeft class="mr-1 h-4 w-4" aria-hidden="true" /> Minggu di {{ monthName }}</Button>
                <h2 class="text-lg font-semibold tabular-nums">{{ range(weekStart, weekEnd) }}</h2>
            </div>
            <!-- Like kipApp's Pelaksanaan: a toolbar (Refresh, search, bulk action, Tambah) over one table. -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <Button variant="outline" @click="refresh"><RefreshCw class="mr-1.5 h-4 w-4" aria-hidden="true" /> Refresh</Button>
                <div class="relative w-full max-w-xs">
                    <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-500" aria-hidden="true" />
                    <Input v-model="query" class="pl-9" placeholder="Pencarian" aria-label="Cari kegiatan" />
                </div>
                <span data-tour="sync-me" class="inline-flex"><SyncMyActivitiesButton /></span>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" :disabled="!selection.selected.value.length">Aksi kegiatan terpilih ({{ selection.selected.value.length }}) <ChevronDown class="ml-1.5 h-4 w-4" aria-hidden="true" /></Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem :disabled="!selection.toPush.value.length" @select="bulk.ask('push')">Kirim ke kipApp ({{ selection.toPush.value.length }})</DropdownMenuItem>
                        <template v-if="canSendToAtasan">
                            <DropdownMenuItem :disabled="!selection.toSend.value.length" @select="bulk.ask('send')">Kirim ke atasan ({{ selection.toSend.value.length }})</DropdownMenuItem>
                            <DropdownMenuItem :disabled="!selection.toCancel.value.length" @select="bulk.ask('cancel')">Batalkan kirim ({{ selection.toCancel.value.length }})</DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button class="ml-auto" data-tour="add-item" @click="add"><Plus class="mr-1 h-4 w-4" aria-hidden="true" /> Tambah</Button>
            </div>

            <p v-if="!rkSynced" class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                Daftar RK Anda belum tersinkron. Tekan “Sinkronkan data saya” untuk menarik RK dan kegiatan dari kipApp.
            </p>
            <p v-if="focus" class="mb-4 rounded-md border bg-white px-4 py-2 text-sm"><span class="font-semibold">Fokus dari PJ:</span> {{ focus }}</p>

            <!-- Two tables in one page, in the order of the weekly meeting: first what last week turned out, then what this week plans. -->
            <section class="mb-6 rounded-lg border bg-white" aria-labelledby="h-last">
                <h2 id="h-last" class="flex flex-wrap items-center gap-2 border-b px-4 py-3 text-sm font-semibold">
                    Realisasi minggu lalu <span class="font-normal text-gray-600">{{ range(lastWeekStart, lastWeekEnd) }}</span>
                    <span v-if="lackCount" class="rounded-full bg-amber-400 px-2 py-0.5 text-xs font-semibold tabular-nums text-gray-900">{{ lackCount }} belum diisi</span>
                </h2>
                <p v-if="onlyRealisasi" class="flex flex-wrap items-center gap-3 border-b bg-amber-50 px-4 py-2 text-sm text-amber-900">
                    <span>Menampilkan {{ lackCount }} kegiatan yang belum diisi. Klik barisnya, lalu isi capaian, uraian, dan bukti dukung.</span>
                    <Button variant="outline" size="sm" @click="onlyRealisasi = false">Tampilkan semua</Button>
                </p>
                <p v-else-if="lackCount" class="border-b bg-amber-50 px-4 py-2 text-sm text-amber-900">
                    Klik kegiatan yang belum diisi, lalu isi capaian, uraian, dan bukti dukung.
                    <button type="button" class="ml-1 underline" @click="onlyRealisasi = true">Tampilkan hanya yang belum diisi</button>
                </p>
                <PlanItemTable :rows="lastRows" :can-send-to-atasan="canSendToAtasan" :selected="selection.selected.value" :empty-text="onlyRealisasi ? 'Semua kegiatan minggu lalu sudah terisi realisasinya.' : 'Tidak ada kegiatan minggu lalu.'" @open="detailId = $event.id" @edit="edit" @copy="copy" @remove="removing = $event" @toggle="selection.toggle" @toggle-all="selection.toggleAll" />
            </section>

            <section v-if="!onlyRealisasi" class="rounded-lg border bg-white" aria-labelledby="h-now">
                <h2 id="h-now" class="border-b px-4 py-3 text-sm font-semibold">Rencana minggu ini <span class="font-normal text-gray-600">{{ range(weekStart, weekEnd) }}</span></h2>
                <PlanItemTable :rows="thisRows" :can-send-to-atasan="canSendToAtasan" :selected="selection.selected.value" empty-text="Belum ada rencana. Tekan “Tambah”." @open="detailId = $event.id" @edit="edit" @copy="copy" @remove="removing = $event" @toggle="selection.toggle" @toggle-all="selection.toggleAll" />
            </section>

            <PlanItemFormDialog v-model:open="formOpen" :item="editing" :copy-of="copying" :skp-year="skpYear" :rk-options="rkOptions ?? []" :project-options="projectOptions ?? []" :default-date="weekStart ?? ''" />
            <PlanItemDetailDialog :item="detail" :can-send-to-atasan="canSendToAtasan" @close="detailId = null" @edit="edit" @copy="copy" />
            <ConfirmDialog
                :open="removing !== null"
                :title="removing?.in_kipapp ? 'Hapus kegiatan' : 'Batalkan kegiatan'"
                :description="removing?.in_kipapp ? 'Apakah akan menghapus kegiatan ini? Kegiatan di kipApp ikut terhapus.' : 'Batalkan kegiatan ini?'"
                confirm-label="Ya"
                cancel-label="Tidak"
                variant="warning"
                @update:open="(v: boolean) => { if (!v) removing = null; }"
                @confirm="removeConfirmed"
            />
            <ConfirmDialog
                :open="bulk.pending.value !== null"
                :title="bulk.pending.value ? bulkCopy[bulk.pending.value].title : ''"
                :description="bulk.pending.value ? bulkCopy[bulk.pending.value].description : ''"
                :confirm-label="bulk.pending.value ? bulkCopy[bulk.pending.value].confirm : ''"
                cancel-label="Tidak"
                variant="warning"
                @update:open="(v: boolean) => { if (!v) bulk.pending.value = null; }"
                @confirm="bulk.run"
            />
        </template>
    </AppLayout>
</template>
