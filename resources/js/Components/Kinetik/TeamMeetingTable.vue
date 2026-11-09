<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Search } from 'lucide-vue-next';
import type { TeamWeekPlan } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import InputError from '@/Components/InputError.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { achievementPercent, withUnit } from '@/composables/useAchievement';
import { formatNumber } from '@/composables/useNumber';
import { useDateFormat } from '@/composables/useDateFormat';

/**
 * The table of the weekly team meeting, shaped like the Excel the teams used before Kinetik: one row per kegiatan with target,
 * realisasi, percent, progress and permasalahan, grouped by person. A person with no kegiatan in the week is listed too, so the
 * meeting sees who did not plan. The PJ can fill the realisasi and the permasalahan of a row, as in the meeting.
 */
const props = defineProps<{
    title: string;
    range: string;
    plans: TeamWeekPlan[];
    noPlan: Array<{ employee_id: number; name: string }>;
    canEdit: boolean;
    /** Monday of the week of this table: a kegiatan that runs for several weeks records its realisasi for it. */
    week: string;
}>();

const { formatDate } = useDateFormat();
const query = ref('');

interface Group { employee_id: number; name: string; rows: TeamWeekPlan[] }
const groups = computed<Group[]>(() => {
    const q = query.value.trim().toLowerCase();
    const map = new Map<number, Group>();
    for (const p of props.plans) {
        if (!map.has(p.employee_id)) map.set(p.employee_id, { employee_id: p.employee_id, name: p.employee_name, rows: [] });
        map.get(p.employee_id)!.rows.push(p);
    }
    for (const m of props.noPlan) if (!map.has(m.employee_id)) map.set(m.employee_id, { employee_id: m.employee_id, name: m.name, rows: [] });

    return [...map.values()]
        .filter(g => !q || g.name.toLowerCase().includes(q) || g.rows.some(r => `${r.description} ${r.rk_name ?? ''}`.toLowerCase().includes(q)))
        .sort((a, b) => a.name.localeCompare(b.name, 'id'));
});
const rowCount = computed(() => props.plans.length);
const without = computed(() => props.noPlan.length);

const dates = (p: TeamWeekPlan) => (p.date_end !== p.date_start
    ? `${formatDate(p.date_start, { day: 'numeric', month: 'short' })} – ${formatDate(p.date_end, { day: 'numeric', month: 'short' })}`
    : formatDate(p.date_start, { day: 'numeric', month: 'short' }));

// Fill the realisasi and the permasalahan of one row (the PJ, in the meeting).
const editing = ref<TeamWeekPlan | null>(null);
const form = useForm({ capaian: '' as string | number, progress: 0 as string | number, kendala: '' });
function edit(p: TeamWeekPlan) {
    if (!props.canEdit) return;
    form.clearErrors();
    form.capaian = p.capaian ?? '';
    form.progress = p.progress;
    form.kendala = p.kendala ?? '';
    editing.value = p;
}
function save() {
    if (!editing.value) return;
    form.transform((d: { capaian: string | number; progress: string | number; kendala: string }) => ({ capaian: d.capaian === '' ? null : d.capaian, progress: d.progress === '' ? null : d.progress, kendala: d.kendala || null, week: props.week }))
        .patch(route('plan-items.result', editing.value.id), { preserveScroll: true, onSuccess: () => { editing.value = null; router.reload({ only: ['plans', 'lastPlans', 'members'] }); } });
}
const pct = (p: TeamWeekPlan) => achievementPercent(p.capaian, p.target);
</script>

<template>
    <section class="rounded-lg border bg-white" :aria-label="title">
        <div class="flex flex-wrap items-center gap-3 border-b px-4 py-3">
            <h2 class="text-sm font-semibold">
                {{ title }}
                <span class="font-normal text-gray-600">{{ range }} · {{ formatNumber(rowCount) }} kegiatan<template v-if="without">, {{ formatNumber(without) }} anggota belum membuat rencana</template></span>
            </h2>
            <div class="relative ml-auto">
                <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-500" aria-hidden="true" />
                <Input v-model="query" class="h-9 w-56 pl-9" placeholder="Cari anggota atau kegiatan" :aria-label="`Cari di ${title}`" />
            </div>
        </div>
        <div v-if="groups.length" class="max-h-[32rem] overflow-auto">
            <table class="w-full min-w-[56rem] border-collapse text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="w-10 border-b px-3 py-2">No</th>
                        <th class="border-b px-3 py-2">Anggota</th>
                        <th class="border-b px-3 py-2">Kegiatan</th>
                        <th class="border-b px-3 py-2">Tanggal</th>
                        <th class="border-b px-3 py-2 text-right">Target</th>
                        <th class="border-b px-3 py-2 text-right">Realisasi</th>
                        <th class="border-b px-3 py-2 text-right">%</th>
                        <th class="border-b px-3 py-2 text-right">Progres</th>
                        <th class="border-b px-3 py-2">Permasalahan</th>
                        <th v-if="canEdit" class="w-10 border-b px-2 py-2"><span class="sr-only">Isi</span></th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(g, gi) in groups" :key="g.employee_id">
                        <tr v-if="!g.rows.length" class="border-b bg-amber-50/50">
                            <td class="px-3 py-2 tabular-nums text-gray-600">{{ gi + 1 }}</td>
                            <td class="px-3 py-2 font-medium">{{ g.name }}</td>
                            <td :colspan="canEdit ? 8 : 7" class="px-3 py-2 italic text-amber-800">Belum membuat rencana</td>
                        </tr>
                        <tr v-for="(p, i) in g.rows" v-else :key="p.id" :class="['border-b align-top', canEdit ? 'cursor-pointer hover:bg-gray-50' : '']" @click="edit(p)">
                            <td class="px-3 py-2 tabular-nums text-gray-600">{{ i === 0 ? gi + 1 : '' }}</td>
                            <td class="px-3 py-2 font-medium">{{ i === 0 ? g.name : '' }}</td>
                            <td class="max-w-sm px-3 py-2">
                                {{ p.description }}
                                <span v-if="p.continued" class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700" title="Dimulai di minggu sebelumnya dan masih berjalan">Lanjutan</span>
                                <span v-if="p.rk_name" class="block truncate text-xs text-gray-600">{{ p.rk_name }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 tabular-nums">{{ dates(p) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ withUnit(p.target, p.target_unit) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ withUnit(p.capaian, p.target_unit) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ pct(p) !== null ? `${pct(p)}%` : '–' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ formatNumber(p.progress) }}%</td>
                            <td class="max-w-xs px-3 py-2 text-gray-800">{{ p.kendala || '–' }}</td>
                            <td v-if="canEdit" class="px-2 py-2"><Pencil class="h-4 w-4 text-gray-500" aria-hidden="true" /><span class="sr-only">Isi realisasi dan permasalahan</span></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p v-else class="px-4 py-8 text-center text-sm text-gray-600">{{ plans.length || noPlan.length ? 'Tidak ada yang cocok.' : 'Belum ada kegiatan anggota pada minggu ini.' }}</p>

        <Dialog :open="editing !== null" @update:open="(v: boolean) => { if (!v) editing = null; }">
            <DialogContent v-if="editing" class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editing.description }}</DialogTitle>
                    <DialogDescription>{{ editing.employee_name }} · target {{ withUnit(editing.target, editing.target_unit) }}</DialogDescription>
                </DialogHeader>
                <form class="space-y-3" @submit.prevent="save">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5"><Label for="tm-capaian">Realisasi{{ editing.target_unit ? ` (${editing.target_unit})` : '' }}</Label><Input id="tm-capaian" v-model="form.capaian" type="number" min="0" step="any" /></div>
                        <div class="space-y-1.5"><Label for="tm-progress">Progres (%)</Label><Input id="tm-progress" v-model="form.progress" type="number" min="0" max="100" step="1" /></div>
                    </div>
                    <InputError :message="form.errors.capaian || form.errors.progress" />
                    <div class="space-y-1.5">
                        <Label for="tm-kendala">Permasalahan</Label>
                        <Textarea id="tm-kendala" v-model="form.kendala" :rows="3" placeholder="Kendala yang dibahas di rapat" />
                        <InputError :message="form.errors.kendala" />
                    </div>
                    <p class="text-xs text-gray-600">Disimpan di kegiatan {{ editing.employee_name.split(' ')[0] }}<template v-if="editing.multi_week">, untuk minggu ini. Kegiatannya berjalan beberapa minggu, jadi tiap minggu punya realisasinya sendiri</template>. Ke kipApp hanya terkirim bila kegiatannya sudah lengkap.</p>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="editing = null">Batal</Button>
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </section>
</template>
