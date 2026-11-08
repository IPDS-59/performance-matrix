<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Info } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

interface Month { value: number | null; items: number }
interface Aggregate { months: Record<string, Month>; year_value: number | null; items: number }
interface IkuRow extends Aggregate { id: number; code: string; name: string; sasaran_code: string; linked: boolean; projects: Array<{ id: number; name: string; team: string | null }>; teams: string[] }
interface TeamRow extends Aggregate { id: number; name: string | null; ikus: Array<{ code: string; items: number }> }

const props = defineProps<{ year: number; indicators: IkuRow[]; teams: TeamRow[] }>();
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const tab = ref<'iku' | 'team'>('iku');
const fmt = (v: number | null) => (v === null ? '-' : v.toLocaleString('id-ID', { maximumFractionDigits: 1 }));
const tone = (v: number | null) => (v === null ? 'text-gray-400' : v >= 100 ? 'bg-green-50 text-green-700' : v >= 70 ? 'bg-amber-50 text-amber-800' : 'bg-red-50 text-red-700');
const withWork = computed(() => props.indicators.filter((i) => i.items > 0).length);
const years = computed(() => [props.year - 1, props.year, props.year + 1]);
const go = (year: number) => router.get(route('iku-progress.index'), { year }, { preserveScroll: true });
</script>

<template>
    <Head title="Progres per IKU" />
    <AppLayout>
        <template #title>Progres per IKU</template>

        <div class="mb-4 flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900" role="note">
            <Info class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
            <p>
                Ini <b>progres pekerjaan</b>, bukan realisasi IKU. Tiap kegiatan dihitung capaian dibagi targetnya (paling tinggi 100%), lalu dirata-rata per bulan lewat kegiatan, RK, dan projek yang terhubung ke IKU.
                Rumus IKU sendiri berbeda (misalnya jumlah publikasi terbit dibanding rencana), jadi realisasi resminya tetap di Kertas Kerja FRA.
            </p>
        </div>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex rounded-lg border bg-white p-1" role="tablist" aria-label="Tampilan">
                <button type="button" role="tab" :aria-selected="tab === 'iku'" :class="['rounded-md px-3 py-1.5 text-sm font-medium', tab === 'iku' ? 'bg-primary text-primary-foreground' : 'text-gray-700']" @click="tab = 'iku'">Per IKU</button>
                <button type="button" role="tab" :aria-selected="tab === 'team'" :class="['rounded-md px-3 py-1.5 text-sm font-medium', tab === 'team' ? 'bg-primary text-primary-foreground' : 'text-gray-700']" @click="tab = 'team'">Per tim</button>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <span>{{ withWork }} dari {{ indicators.length }} IKU punya kegiatan dengan target dan capaian.</span>
                <Button v-for="y in years" :key="y" size="sm" :variant="y === year ? 'default' : 'outline'" @click="go(y)">{{ y }}</Button>
            </div>
        </div>

        <div v-if="tab === 'iku'" class="overflow-x-auto rounded-xl border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="min-w-72">IKU</TableHead>
                        <TableHead class="min-w-48">Tim terkait</TableHead>
                        <TableHead v-for="m in MONTHS" :key="m" class="text-right">{{ m }}</TableHead>
                        <TableHead class="text-right">Setahun</TableHead>
                        <TableHead class="text-right">Kegiatan</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="i in indicators" :key="i.id">
                        <TableCell class="whitespace-normal text-sm">
                            <span class="font-mono text-xs text-gray-500">{{ i.code }}</span> {{ i.name }}
                            <span v-if="!i.linked" class="block text-xs text-red-700">Belum terhubung ke IKU di data master.</span>
                            <span v-else-if="!i.projects.length" class="block text-xs text-amber-700">Belum ada projek di bawah IKU ini.</span>
                        </TableCell>
                        <TableCell class="whitespace-normal text-xs text-gray-700">{{ i.teams.join(', ') || '-' }}</TableCell>
                        <TableCell v-for="(m, n) in MONTHS" :key="m" class="px-1 text-right">
                            <span :class="['block rounded px-1.5 py-1 text-right text-xs tabular-nums', tone(i.months[n + 1].value)]" :title="`${i.months[n + 1].items} kegiatan`">{{ fmt(i.months[n + 1].value) }}</span>
                        </TableCell>
                        <TableCell class="text-right font-semibold tabular-nums">{{ fmt(i.year_value) }}</TableCell>
                        <TableCell class="text-right tabular-nums text-gray-600">{{ i.items }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="min-w-56">Tim</TableHead>
                        <TableHead class="min-w-48">IKU yang dikerjakan</TableHead>
                        <TableHead v-for="m in MONTHS" :key="m" class="text-right">{{ m }}</TableHead>
                        <TableHead class="text-right">Setahun</TableHead>
                        <TableHead class="text-right">Kegiatan</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!teams.length"><TableCell colspan="16" class="py-8 text-center text-gray-400">Belum ada kegiatan dengan target dan capaian.</TableCell></TableRow>
                    <TableRow v-for="t in teams" :key="t.id">
                        <TableCell class="whitespace-normal text-sm font-medium">{{ t.name }}</TableCell>
                        <TableCell class="whitespace-normal text-xs text-gray-700">{{ t.ikus.map((i) => `${i.code} (${i.items})`).join(', ') }}</TableCell>
                        <TableCell v-for="(m, n) in MONTHS" :key="m" class="px-1 text-right">
                            <span :class="['block rounded px-1.5 py-1 text-right text-xs tabular-nums', tone(t.months[n + 1].value)]" :title="`${t.months[n + 1].items} kegiatan`">{{ fmt(t.months[n + 1].value) }}</span>
                        </TableCell>
                        <TableCell class="text-right font-semibold tabular-nums">{{ fmt(t.year_value) }}</TableCell>
                        <TableCell class="text-right tabular-nums text-gray-600">{{ t.items }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
