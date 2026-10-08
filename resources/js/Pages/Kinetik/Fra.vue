<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Download, Pencil } from 'lucide-vue-next';
import type { FraIndicatorRow, FraProps } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import FraIndicatorForm from '@/Components/Kinetik/FraIndicatorForm.vue';
import { downloadFraSheet, fmt } from '@/composables/useFra';

const props = defineProps<FraProps>();
const roman = ['I', 'II', 'III', 'IV'];
const sakip = ref(props.summary.sakip_score?.toString() ?? '');
const editing = ref<FraIndicatorRow | null>(null);

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
    router.get(route('fra.index'), { year: props.year, quarter: props.quarter, sakip: sakip.value || undefined, ...params }, { preserveState: true, preserveScroll: true });
}

function setOwner(ind: FraIndicatorRow, teamId: number | null) {
    router.patch(route('fra.owner', ind.id), { owner_team_id: teamId }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Kertas Kerja FRA" />
    <AppLayout>
        <template #title>Kertas Kerja FRA (LK_Prov)</template>

        <div class="mb-4 flex flex-col gap-3 rounded-lg border bg-white p-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between sm:p-4">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs text-gray-600" for="fra-quarter">Triwulan</label>
                    <Select :model-value="quarter" @update:model-value="(v) => go({ quarter: Number(v) })">
                        <SelectTrigger id="fra-quarter" class="w-36"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="n in 4" :key="n" :value="n">TW {{ roman[n - 1] }} {{ year }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-600" for="fra-sakip">Nilai SAKIP {{ year }}</label>
                    <Input id="fra-sakip" v-model="sakip" type="number" step="any" class="w-32" placeholder="mis. 78,1" @change="go({})" />
                </div>
            </div>
            <Button variant="outline" size="sm" @click="downloadFraSheet(indicators, summary, year)">
                <Download class="mr-1.5 h-4 w-4" aria-hidden="true" /> Unduh Excel
            </Button>
        </div>

        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Capaian IKU TW {{ roman[quarter - 1] }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ fmt(summary.iku_capaian_quarter) }}</p>
                <p class="text-xs text-gray-500">terhadap target triwulanan</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Capaian IKU setahun</p>
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

        <section v-for="group in groups" :key="group.code" class="mb-5 overflow-x-auto rounded-md border bg-white">
            <h2 class="border-b bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-800">{{ group.code }} {{ group.name }}</h2>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="text-left">Indikator</TableHead>
                        <TableHead class="text-right">Target</TableHead>
                        <TableHead class="text-right">Alokasi</TableHead>
                        <TableHead class="text-right">Realisasi</TableHead>
                        <TableHead class="text-right">Capaian TW</TableHead>
                        <TableHead class="text-right">Capaian tahun</TableHead>
                        <TableHead class="text-left">Tim pemilik</TableHead>
                        <TableHead><span class="sr-only">Aksi</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="ind in group.rows" :key="ind.id">
                        <TableCell class="w-[20rem] max-w-[20rem] whitespace-normal align-top">
                            <p class="text-sm text-gray-900">{{ ind.name }}</p>
                            <p class="text-xs text-gray-500">{{ ind.code }} · {{ ind.kind }} · {{ ind.period_type }}</p>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{ fmt(ind.target_value) }} <span class="text-xs text-gray-400">{{ ind.unit }}</span></TableCell>
                        <TableCell class="text-right tabular-nums">{{ fmt(ind.quarters[quarter].allocation_value) }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ fmt(ind.quarters[quarter].realization_value) }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ fmt(ind.quarters[quarter].capaian_quarter) }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ fmt(ind.quarters[quarter].capaian_year) }}</TableCell>
                        <TableCell class="min-w-[10rem] whitespace-normal text-sm">
                            <Select v-if="isAdmin" :model-value="ind.owner_team_id" @update:model-value="(v) => setOwner(ind, v === null ? null : Number(v))">
                                <SelectTrigger class="h-8 text-xs" :aria-label="`Tim pemilik ${ind.code}`"><SelectValue placeholder="Belum ditentukan" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="null">Belum ditentukan</SelectItem>
                                    <SelectItem v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <span v-else :class="teamName(ind.owner_team_id) ? 'text-gray-700' : 'text-gray-400'">{{ teamName(ind.owner_team_id) ?? 'Belum ditentukan' }}</span>
                        </TableCell>
                        <TableCell class="text-right">
                            <Button v-if="editable.has(ind.id)" variant="ghost" size="sm" :aria-label="`Isi ${ind.code}`" @click="editing = ind">
                                <Pencil class="h-4 w-4" aria-hidden="true" />
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>

        <Dialog :open="editing !== null" @update:open="(open) => { if (!open) editing = null; }">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ editing?.code }} · TW {{ roman[quarter - 1] }} {{ year }}</DialogTitle>
                    <DialogDescription>{{ editing?.name }}</DialogDescription>
                </DialogHeader>
                <FraIndicatorForm v-if="editing" :key="`${editing.id}-${quarter}`" :indicator="editing" :quarter="quarter" @saved="editing = null" />
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
