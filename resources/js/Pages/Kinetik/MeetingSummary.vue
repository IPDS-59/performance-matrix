<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ChevronLeft, ChevronRight, FileText, Lock } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

type Status = 'locked' | 'written' | 'none' | 'upcoming';
interface Cell { status: Status; written: number; evidence: number }
interface TeamRow { id: number; name: string; leader: string | null; weeks: Array<Cell & { start: string }>; month: Cell }

const props = defineProps<{ year: number; month: number; weeks: Array<{ start: string; label: string }>; teams: TeamRow[] }>();

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const title = computed(() => `${MONTHS[props.month - 1]} ${props.year}`);

function go(shift: number) {
    const d = new Date(props.year, props.month - 1 + shift, 1);
    router.get(route('team-recap.meetings'), { year: d.getFullYear(), month: d.getMonth() + 1 }, { preserveScroll: true });
}

const META: Record<Status, { label: string; chip: string }> = {
    locked: { label: 'Terkunci', chip: 'bg-green-100 text-green-800' },
    written: { label: 'Ditulis', chip: 'bg-amber-100 text-amber-800' },
    none: { label: 'Belum', chip: 'bg-red-100 text-red-800' },
    upcoming: { label: '—', chip: 'bg-gray-50 text-gray-400' },
};

// Counted over the weeks that already happened, so a week to come does not drag the numbers down.
const pastCells = computed(() => props.teams.flatMap((t) => t.weeks).filter((w) => w.status !== 'upcoming'));
const weeksDone = computed(() => pastCells.value.filter((w) => w.status === 'locked').length);
const monthDone = computed(() => props.teams.filter((t) => t.month.status === 'locked').length);
const withEvidence = computed(() => props.teams.filter((t) => t.month.evidence > 0 || t.weeks.some((w) => w.evidence > 0)).length);

const href = (team: TeamRow, type: 'week' | 'month', start?: string) =>
    type === 'week' ? route('team-recap.weekly', { team: team.id, week: start }) : route('team-recap.monthly', { team: team.id, year: props.year, month: props.month });
</script>

<template>
    <Head title="Ringkasan Rapat" />
    <AppLayout>
        <template #title>Ringkasan Rapat</template>

        <div class="mb-4 flex items-center justify-between gap-3">
            <div class="flex items-center rounded-md border bg-white">
                <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Bulan sebelumnya" @click="go(-1)"><ChevronLeft class="h-4 w-4" /></Button>
                <span class="min-w-40 px-2 text-center text-sm font-semibold tabular-nums">{{ title }}</span>
                <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Bulan berikutnya" @click="go(1)"><ChevronRight class="h-4 w-4" /></Button>
            </div>
            <p class="hidden text-xs text-gray-500 sm:block">Terkunci: rekap selesai. Ditulis: ada parafrase, belum dikunci.</p>
        </div>

        <dl class="mb-4 grid grid-cols-3 divide-x divide-gray-100 overflow-hidden rounded-lg border bg-white">
            <div class="px-4 py-3">
                <dd class="text-2xl font-semibold tabular-nums text-gray-900">{{ weeksDone }}<span class="text-sm font-normal text-gray-400"> / {{ pastCells.length }}</span></dd>
                <dt class="text-xs text-gray-500">Rekap mingguan terkunci</dt>
            </div>
            <div class="px-4 py-3">
                <dd class="text-2xl font-semibold tabular-nums text-gray-900">{{ monthDone }}<span class="text-sm font-normal text-gray-400"> / {{ teams.length }}</span></dd>
                <dt class="text-xs text-gray-500">Rekap bulanan terkunci</dt>
            </div>
            <div class="px-4 py-3">
                <dd class="text-2xl font-semibold tabular-nums text-gray-900">{{ withEvidence }}<span class="text-sm font-normal text-gray-400"> / {{ teams.length }}</span></dd>
                <dt class="text-xs text-gray-500">Tim punya bukti rapat</dt>
            </div>
        </dl>

        <div class="overflow-x-auto rounded-lg border bg-white">
            <Table class="text-sm">
                <TableHeader>
                    <TableRow class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <TableHead class="min-w-[12rem]">Tim</TableHead>
                        <TableHead v-for="w in weeks" :key="w.start" class="text-center">Minggu {{ w.label }}</TableHead>
                        <TableHead class="text-center">Rapat bulanan</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="divide-y divide-gray-100">
                    <TableRow v-for="team in teams" :key="team.id">
                        <TableCell class="whitespace-normal font-medium leading-snug">
                            {{ team.name }}
                            <span class="block text-xs font-normal text-gray-500">{{ team.leader ?? '—' }}</span>
                        </TableCell>
                        <TableCell v-for="w in team.weeks" :key="w.start" class="text-center">
                            <Link :href="href(team, 'week', w.start)" :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium', META[w.status].chip]" :aria-label="`${team.name}, minggu ${w.start}: ${META[w.status].label}, ${w.evidence} bukti`">
                                <Lock v-if="w.status === 'locked'" class="h-3 w-3" aria-hidden="true" />{{ META[w.status].label }}
                                <span v-if="w.evidence" class="inline-flex items-center gap-0.5 tabular-nums"><FileText class="h-3 w-3" aria-hidden="true" />{{ w.evidence }}</span>
                            </Link>
                        </TableCell>
                        <TableCell class="text-center">
                            <Link :href="href(team, 'month')" :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium', META[team.month.status].chip]" :aria-label="`${team.name}, rapat bulanan: ${META[team.month.status].label}, ${team.month.evidence} bukti`">
                                <Lock v-if="team.month.status === 'locked'" class="h-3 w-3" aria-hidden="true" />{{ META[team.month.status].label }}
                                <span v-if="team.month.evidence" class="inline-flex items-center gap-0.5 tabular-nums"><FileText class="h-3 w-3" aria-hidden="true" />{{ team.month.evidence }}</span>
                            </Link>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="!teams.length">
                        <TableCell :colspan="weeks.length + 2" class="py-10 text-center text-sm text-gray-500">Tidak ada tim yang dapat Anda lihat.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
