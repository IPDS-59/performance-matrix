<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import type { PlanComplianceProps } from '@/types';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { planningRate, rateClass, usePlanCompliance } from '@/composables/usePlanCompliance';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps<PlanComplianceProps>();
const { formatDate } = useDateFormat();
const { thisWeekRate, quarterCompletion } = usePlanCompliance(() => props.teams);

const pct = (value: number | null) => (value === null ? '—' : `${value}%`);
</script>

<template>
    <Head title="Kepatuhan Rencana" />
    <AppLayout>
        <template #title>Kepatuhan Rencana</template>

        <p class="mb-4 text-sm text-gray-600">
            Berapa persen anggota yang sudah membuat rencana untuk setiap minggu di triwulan ini. Batas pengisian: Senin pukul 09.00.
        </p>

        <div class="mb-6 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Minggu ini</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ pct(thisWeekRate) }}</p>
                <p class="text-sm text-gray-600">anggota yang sudah membuat rencana</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Triwulan ini</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ pct(quarterCompletion) }}</p>
                <p class="text-sm text-gray-600">rencana minggu yang sudah lewat berakhir selesai (menurut kipApp dan koreksi PJ)</p>
            </div>
        </div>

        <div v-if="!teams.length" class="rounded-md border border-dashed bg-gray-50 py-10 text-center text-sm text-gray-500">Belum ada tim.</div>

        <div v-else class="overflow-x-auto rounded-md border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="text-left">Tim</TableHead>
                        <TableHead v-for="w in weeks" :key="w" class="whitespace-nowrap text-center">{{ formatDate(w) }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="team in teams" :key="team.team_id">
                        <TableCell class="min-w-[12rem] font-medium text-gray-800">{{ team.name }}</TableCell>
                        <TableCell v-for="w in team.weeks" :key="w.week_start" class="p-1 text-center">
                            <div :class="['rounded px-2 py-1.5 text-sm tabular-nums', rateClass(planningRate(w))]" :title="`${w.planners} dari ${w.members} anggota punya rencana${w.done !== null ? `; selesai ${w.done}, berjalan ${w.in_progress}, belum ${w.not_started}` : ''}`">
                                {{ pct(planningRate(w)) }}
                                <span class="block text-[11px] opacity-75">{{ w.planners }}/{{ w.members }}</span>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <section v-if="teams.some(t => t.missing_this_week.length)" class="mt-6" aria-labelledby="belum-title">
            <h2 id="belum-title" class="mb-2 text-sm font-semibold text-gray-800">Belum membuat rencana minggu ini</h2>
            <ul class="space-y-1 text-sm text-gray-700">
                <li v-for="team in teams.filter(t => t.missing_this_week.length)" :key="team.team_id">
                    <span class="font-medium">{{ team.name }}:</span> {{ team.missing_this_week.join(', ') }}
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
