<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Download, MessageSquareText } from 'lucide-vue-next';
import type { FraIndicatorRow, FraProps } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import FraMonthlyTable from '@/Components/Kinetik/FraMonthlyTable.vue';
import FraAnalysisDialog from '@/Components/Kinetik/FraAnalysisDialog.vue';
import { downloadFraSheet, fmt } from '@/composables/useFra';

const props = defineProps<FraProps>();
const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const roman = ['I', 'II', 'III', 'IV'];
const sakip = ref(props.summary.sakip_score?.toString() ?? '');
const analysing = ref<FraIndicatorRow | null>(null);

const editable = computed(() => new Set(props.editableIds));
const teamName = (id: number | null) => props.teams.find(t => t.id === id)?.name ?? null;

/** Indicators grouped by sasaran, in sheet order. */
const groups = computed(() => {
    const map = new Map<string, { code: string; name: string | null; rows: FraIndicatorRow[] }>();
    for (const ind of props.indicators) {
        if (!map.has(ind.sasaran_code)) map.set(ind.sasaran_code, { code: ind.sasaran_code, name: ind.sasaran_name, rows: [] });
        map.get(ind.sasaran_code)!.rows.push(ind);
    }
    return [...map.values()];
});

function go(params: Record<string, string | number | undefined>) {
    router.get(route('fra.index'), { year: props.year, month: props.month, sakip: sakip.value || undefined, ...params }, { preserveState: true, preserveScroll: true });
}

function setOwner(ind: FraIndicatorRow, teamId: number | null) {
    router.patch(route('fra.owner', ind.id), { owner_team_id: teamId }, { preserveScroll: true });
}

const note = (ind: FraIndicatorRow) => {
    const m = ind.months[props.month];
    return [['Kendala', m?.obstacle], ['Solusi', m?.solution], ['Tindak lanjut', m?.follow_up]].filter(([, v]) => v) as Array<[string, string]>;
};
</script>

<template>
    <Head title="Kertas Kerja FRA" />
    <AppLayout>
        <template #title>Kertas Kerja FRA</template>

        <div class="mb-4 flex flex-col gap-3 rounded-lg border bg-white p-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between sm:p-4">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs text-gray-600" for="fra-month">Sampai bulan</label>
                    <Select :model-value="month" @update:model-value="(v) => go({ month: Number(v) })">
                        <SelectTrigger id="fra-month" class="w-44"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="(name, i) in MONTHS" :key="name" :value="i + 1">{{ name }} {{ year }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-600" for="fra-sakip">Nilai SAKIP {{ year }}</label>
                    <Input id="fra-sakip" v-model="sakip" type="number" step="any" class="w-32" placeholder="mis. 78,1" @change="go({})" />
                </div>
            </div>
            <Button variant="outline" size="sm" @click="downloadFraSheet(indicators, summary, year)">
                <Download class="mr-1.5 h-4 w-4" aria-hidden="true" /> Unduh Excel LK_Prov TW {{ roman[quarter - 1] }}
            </Button>
        </div>

        <div class="mb-6 grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Capaian IKU sampai {{ MONTHS[month - 1] }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ fmt(summary.iku_capaian_year) }}</p>
                <p class="text-xs text-gray-500">terhadap target tahunan</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">NKO rata-rata capaian PK</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ fmt(summary.nko) }}</p>
                <p class="text-xs text-gray-500">{{ summary.pko_predicate ?? '-' }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Predikat SAKIP</p>
                <p class="mt-1 text-lg font-semibold">{{ summary.sakip_predicate }}</p>
                <p class="text-xs text-gray-500">memengaruhi koreksi capaian</p>
            </div>
        </div>

        <div v-if="!indicators.length" class="rounded-md border border-dashed bg-gray-50 py-10 text-center text-sm text-gray-500">
            Belum ada indikator untuk tahun {{ year }}.
        </div>

        <section v-for="group in groups" :key="group.code" class="mb-6">
            <h2 class="mb-2 text-sm font-semibold text-gray-800">{{ group.code }} {{ group.name }}</h2>
            <article v-for="ind in group.rows" :key="ind.id" class="mb-3 rounded-md border bg-white">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b px-4 py-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-900">{{ ind.name }}</p>
                        <p class="text-xs text-gray-500">{{ ind.code }} · {{ ind.kind }} · target {{ fmt(ind.target_value) }} {{ ind.unit }} · capaian {{ fmt(ind.cum_capaian_year) }}%</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Select v-if="isAdmin" :model-value="ind.owner_team_id" @update:model-value="(v) => setOwner(ind, v === null ? null : Number(v))">
                            <SelectTrigger class="h-8 w-48 text-xs" :aria-label="`Tim pemilik ${ind.code}`"><SelectValue placeholder="Tim pemilik belum ditentukan" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">Belum ditentukan</SelectItem>
                                <SelectItem v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-else class="text-xs text-gray-600">{{ teamName(ind.owner_team_id) ?? 'Tim pemilik belum ditentukan' }}</span>
                        <Button v-if="editable.has(ind.id)" variant="outline" size="sm" class="h-8" @click="analysing = ind">
                            <MessageSquareText class="mr-1.5 h-4 w-4" aria-hidden="true" /> Analisis {{ MONTHS[month - 1] }}
                        </Button>
                    </div>
                </header>
                <FraMonthlyTable :indicator="ind" :year="year" :month="month" :editable="editable.has(ind.id)" />
                <dl v-if="note(ind).length" class="border-t px-4 py-2 text-xs text-gray-700">
                    <div v-for="[label, text] in note(ind)" :key="label" class="flex gap-2"><dt class="w-24 shrink-0 font-medium text-gray-500">{{ label }}</dt><dd>{{ text }}</dd></div>
                </dl>
            </article>
        </section>

        <Dialog :open="analysing !== null" @update:open="(open) => { if (!open) analysing = null; }">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ analysing?.code }} · {{ MONTHS[month - 1] }} {{ year }}</DialogTitle>
                    <DialogDescription>{{ analysing?.name }}</DialogDescription>
                </DialogHeader>
                <FraAnalysisDialog v-if="analysing" :key="`${analysing.id}-${month}`" :indicator="analysing" :month="month" @saved="analysing = null" />
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
