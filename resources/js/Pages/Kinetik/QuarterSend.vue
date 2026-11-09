<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Info, Search } from 'lucide-vue-next';
import type { PlanItemRow } from '@/types';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import PlanItemTable from '@/Components/Kinetik/PlanItemTable.vue';
import PlanItemDetailDialog from '@/Components/Kinetik/PlanItemDetailDialog.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { planRowMatches } from '@/composables/usePlanSearch';
import { useRowSelection } from '@/composables/useRowSelection';
import { bulkCopy, useBulkSend } from '@/composables/usePlanBulk';
import { formatNumber } from '@/composables/useNumber';
import { useDateFormat } from '@/composables/useDateFormat';

/**
 * "Kirim ke Atasan" for a whole quarter, like kipApp's SKP triwulan page: the quarter's kegiatan by RK, check the ones to send, one
 * button. It sends and takes back through the same endpoints as the weekly table.
 */
const props = defineProps<{ year: number; quarter: number; rows: PlanItemRow[]; canSendToAtasan: boolean; skpStatus: string | null }>();

const ROMAN = ['I', 'II', 'III', 'IV'];
type Filter = 'unsent' | 'sent' | 'blocked' | 'all';
const filter = ref<Filter>('unsent');
const query = ref('');
const detailId = ref<number | null>(null);
const { formatDate } = useDateFormat();

const isUnsent = (r: PlanItemRow) => r.can_send && !r.sent;
const isSent = (r: PlanItemRow) => r.sent;
const isBlocked = (r: PlanItemRow) => !r.can_send && !r.sent;
const counts = computed(() => ({
    all: props.rows.length,
    unsent: props.rows.filter(isUnsent).length,
    sent: props.rows.filter(isSent).length,
    blocked: props.rows.filter(isBlocked).length,
}));

const shown = computed(() => props.rows
    .filter((r) => ({ unsent: isUnsent(r), sent: isSent(r), blocked: isBlocked(r), all: true })[filter.value])
    .filter((r) => planRowMatches(r, query.value, (d) => formatDate(d, { day: 'numeric', month: 'short' }))));

/** The shown kegiatan by RK, in the order the RK first appear. */
const groups = computed(() => {
    const map = new Map<string, { name: string; rows: PlanItemRow[] }>();
    for (const r of shown.value) {
        const key = String(r.performance_plan_id);
        if (!map.has(key)) map.set(key, { name: r.rk_name ?? 'Tanpa RK', rows: [] });
        map.get(key)!.rows.push(r);
    }
    return [...map.values()];
});

const selection = useRowSelection(() => props.rows);
const bulk = useBulkSend(() => ({ send: selection.toSend.value.map((r) => r.id), cancel: selection.toCancel.value.map((r) => r.id), push: selection.toPush.value.map((r) => r.id) }), selection.clear);
const detail = computed(() => props.rows.find((r) => r.id === detailId.value) ?? null);

// A check before sending, as the spv asked: what is still missing on the kegiatan that are about to go to the atasan. A sent kegiatan
// cannot be changed, and people forget they sent it. Sending stays possible; this only says what is lacking.
const lacking = computed(() => selection.toSend.value
    .map((r) => ({ row: r, missing: [r.capaian === null || r.capaian === undefined ? 'capaian' : null, !r.evidence_url ? 'bukti dukung' : null, !(r.capaian_note ?? '').trim() ? 'uraian capaian' : null, r.progress < 100 ? 'progres belum 100' : null].filter((m): m is string => m !== null) }))
    .filter((x) => x.missing.length));

const selectAllUnsent = () => selection.toggleAll(props.rows.filter(isUnsent), true);
const openWeek = (item: PlanItemRow) => router.get(route('weekly.index', { week: item.date_start }));
const go = (quarter: number) => router.get(route('quarter-send.index'), { quarter }, { preserveScroll: true });
</script>

<template>
    <Head title="Kirim ke Atasan" />
    <AppLayout>
        <template #title>Kirim ke Atasan</template>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex rounded-lg border bg-white p-1" role="tablist" aria-label="Triwulan">
                <button v-for="q in 4" :key="q" type="button" role="tab" :aria-selected="q === quarter" :class="['rounded-md px-3 py-1.5 text-sm font-medium', q === quarter ? 'bg-primary text-primary-foreground' : 'text-gray-700']" @click="go(q)">TW {{ ROMAN[q - 1] }} {{ year }}</button>
            </div>
            <p v-if="skpStatus" class="text-sm text-gray-700">Status SKP triwulan: <span class="rounded-full bg-gray-100 px-2 py-0.5 font-medium">{{ skpStatus }}</span></p>
        </div>

        <div v-if="!canSendToAtasan" class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">
            <Info class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" /> Kirim ke atasan belum diaktifkan oleh admin.
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-4">
            <button v-for="f in ([['unsent', 'Belum dikirim', counts.unsent], ['sent', 'Sudah dikirim', counts.sent], ['blocked', 'Belum bisa dikirim', counts.blocked], ['all', 'Semua', counts.all]] as const)" :key="f[0]" type="button"
                :class="['rounded-lg border bg-white p-3 text-left', filter === f[0] ? 'ring-2 ring-primary' : '']" @click="filter = f[0]">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ f[1] }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ formatNumber(f[2]) }}</p>
            </button>
        </div>
        <p v-if="filter === 'blocked' || counts.blocked" class="mb-3 text-xs text-gray-600">"Belum bisa dikirim": kegiatannya belum ada di kipApp (kirim dulu dari Rekap Mingguan), atau SKP triwulannya sudah dikunci.</p>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="relative w-full max-w-xs">
                <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-500" aria-hidden="true" />
                <Input v-model="query" class="pl-9" placeholder="Cari kegiatan atau RK" aria-label="Cari kegiatan" />
            </div>
            <template v-if="canSendToAtasan">
                <Button variant="outline" :disabled="!counts.unsent" @click="selectAllUnsent">Pilih semua yang belum dikirim ({{ formatNumber(counts.unsent) }})</Button>
                <Button v-if="selection.toPush.value.length" variant="outline" @click="bulk.ask('push')">Kirim ke kipApp ({{ formatNumber(selection.toPush.value.length) }})</Button>
                <Button :disabled="!selection.toSend.value.length" @click="bulk.ask('send')">Kirim ke atasan ({{ formatNumber(selection.toSend.value.length) }})</Button>
                <Button variant="outline" :disabled="!selection.toCancel.value.length" @click="bulk.ask('cancel')">Batalkan kirim ({{ formatNumber(selection.toCancel.value.length) }})</Button>
            </template>
        </div>

        <div v-if="lacking.length" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            <p class="font-medium">{{ formatNumber(lacking.length) }} dari {{ formatNumber(selection.toSend.value.length) }} kegiatan yang dipilih belum lengkap:</p>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="x in lacking.slice(0, 5)" :key="x.row.id">{{ x.row.description }} <span class="text-amber-800">(belum ada {{ x.missing.join(', ') }})</span></li>
            </ul>
            <p v-if="lacking.length > 5" class="mt-1 text-xs">Dan {{ formatNumber(lacking.length - 5) }} lainnya. Kegiatan yang sudah dikirim tidak bisa diubah, jadi lengkapi dulu atau batalkan pilihannya.</p>
            <p v-else class="mt-1 text-xs">Kegiatan yang sudah dikirim tidak bisa diubah, jadi lengkapi dulu atau batalkan pilihannya.</p>
        </div>

        <p v-if="!groups.length" class="rounded-md border border-dashed bg-gray-50 py-10 text-center text-sm text-gray-500">Tidak ada kegiatan pada tampilan ini.</p>
        <section v-for="g in groups" :key="g.name" class="mb-4 rounded-lg border bg-white">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">{{ g.name }} <span class="font-normal text-gray-600">{{ formatNumber(g.rows.length) }} kegiatan</span></h2>
            <PlanItemTable :rows="g.rows" :actions="false" :can-send-to-atasan="canSendToAtasan" :selected="selection.selected.value" empty-text="" @open="detailId = $event.id" @toggle="selection.toggle" @toggle-all="selection.toggleAll" />
        </section>

        <PlanItemDetailDialog :item="detail" :can-send-to-atasan="canSendToAtasan" @close="detailId = null" @edit="openWeek" @copy="openWeek" />
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
    </AppLayout>
</template>
