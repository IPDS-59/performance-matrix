<script setup lang="ts">
import { computed, ref } from 'vue';
import { Search } from 'lucide-vue-next';
import type { TeamWeekPlan } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { planStage, toneClass } from '@/composables/usePlanStage';
import { achievementPercent, withUnit } from '@/composables/useAchievement';
import { useDateFormat } from '@/composables/useDateFormat';

/** What the members plan this week, read only. Search, filter by person, click a row for every field. */
const props = defineProps<{ plans: TeamWeekPlan[] }>();

const { formatDate } = useDateFormat();
const query = ref('');
const person = ref('all');
const open = ref<TeamWeekPlan | null>(null);

const people = computed(() => [...new Map(props.plans.map(p => [p.employee_id, p.employee_name])).entries()]);
const shown = computed(() => props.plans.filter(p =>
    (person.value === 'all' || String(p.employee_id) === person.value)
    && (!query.value || `${p.employee_name} ${p.description} ${p.rk_name ?? ''}`.toLowerCase().includes(query.value.toLowerCase())),
));
const range = (p: TeamWeekPlan) => (p.date_end !== p.date_start ? `${formatDate(p.date_start)} – ${formatDate(p.date_end)}` : formatDate(p.date_start));
</script>

<template>
    <section class="mb-6 rounded-lg border bg-white" aria-labelledby="week-plans">
        <div class="flex flex-wrap items-center gap-3 border-b px-4 py-3">
            <h2 id="week-plans" class="text-sm font-semibold">Rencana minggu ini <span class="font-normal text-gray-600">({{ plans.length }} kegiatan dari anggota)</span></h2>
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-500" aria-hidden="true" />
                    <Input v-model="query" class="h-9 w-56 pl-9" placeholder="Cari anggota atau kegiatan" aria-label="Cari rencana" />
                </div>
                <Select v-if="people.length > 1" v-model="person">
                    <SelectTrigger class="h-9 w-48" aria-label="Pilih anggota"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua anggota</SelectItem>
                        <SelectItem v-for="[id, name] in people" :key="id" :value="String(id)">{{ name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
        <Table v-if="shown.length">
            <TableHeader><TableRow><TableHead>Anggota</TableHead><TableHead>Kegiatan</TableHead><TableHead>RK</TableHead><TableHead>Target</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
            <TableBody>
                <TableRow v-for="p in shown" :key="p.id" class="cursor-pointer" tabindex="0" :aria-label="`Lihat rencana ${p.employee_name}: ${p.description}`" @click="open = p" @keydown.enter="open = p">
                    <TableCell class="whitespace-nowrap font-medium">{{ p.employee_name }}</TableCell>
                    <TableCell class="max-w-xs truncate">{{ p.description }}</TableCell>
                    <TableCell class="max-w-[14rem] truncate text-gray-600">{{ p.rk_name ?? '–' }}</TableCell>
                    <TableCell class="tabular-nums">{{ withUnit(p.target, p.target_unit) }}</TableCell>
                    <TableCell><span :class="['rounded-full px-2 py-0.5 text-xs font-medium', toneClass[planStage(p).tone]]">{{ planStage(p).label }}</span></TableCell>
                </TableRow>
            </TableBody>
        </Table>
        <p v-else class="px-4 py-8 text-center text-sm text-gray-600">{{ plans.length ? 'Tidak ada rencana yang cocok.' : 'Belum ada rencana anggota untuk minggu ini.' }}</p>

        <Dialog :open="open !== null" @update:open="(v: boolean) => { if (!v) open = null; }">
            <DialogContent v-if="open" class="max-w-lg">
                <DialogHeader><DialogTitle>{{ open.description }}</DialogTitle><DialogDescription>{{ open.employee_name }} · {{ range(open) }}</DialogDescription></DialogHeader>
                <dl class="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-2 text-sm">
                    <dt class="text-gray-600">RK</dt><dd>{{ open.rk_name ?? '–' }}</dd>
                    <dt class="text-gray-600">Target</dt><dd class="tabular-nums">{{ withUnit(open.target, open.target_unit) }}</dd>
                    <dt class="text-gray-600">Capaian</dt>
                    <dd class="tabular-nums">{{ withUnit(open.capaian, open.target_unit) }}<template v-if="achievementPercent(open.capaian, open.target) !== null"> ({{ achievementPercent(open.capaian, open.target) }}%)</template></dd>
                    <dt class="text-gray-600">Uraian capaian</dt><dd class="whitespace-pre-line">{{ open.capaian_note || '–' }}</dd>
                    <dt class="text-gray-600">Bukti dukung</dt>
                    <dd><a v-if="open.evidence_url" :href="open.evidence_url" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">Buka tautan</a><template v-else>–</template></dd>
                    <dt class="text-gray-600">Status</dt><dd><span :class="['rounded-full px-2 py-0.5 text-xs font-medium', toneClass[planStage(open).tone]]">{{ planStage(open).label }}</span></dd>
                </dl>
                <p class="text-xs text-gray-600">Hanya baca. {{ open.employee_name.split(' ')[0] }} mengubahnya di halaman Rekap Mingguan sendiri.</p>
                <DialogFooter><Button variant="outline" @click="open = null">Tutup</Button></DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
