<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { PlanEvaluation, PlanItemRow, PlanRkOption } from '@/types';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import InputError from '@/Components/InputError.vue';
import { Pencil, Plus, X } from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';
import { useDateFormat } from '@/composables/useDateFormat';

/** The planned tasks of one member this week, with the form to add or edit one. */
const props = defineProps<{
    teamId: number;
    employeeId: number;
    memberName: string;
    plans: PlanItemRow[];
    rkOptions: PlanRkOption[];
    projectOptions: Array<{ id: number; name: string }>;
    weekStart: string;
    weekEnd: string;
    /** The member themselves or the PJ. */
    canEdit: boolean;
    /** The PJ may correct the Friday evaluation. */
    canEvaluate?: boolean;
}>();

const { formatDate } = useDateFormat();
const editing = ref<number | 'new' | null>(null);

const form = useForm({
    team_id: props.teamId,
    employee_id: props.employeeId,
    performance_plan_id: null as number | null,
    project_id: null as number | null,
    description: '',
    date_start: props.weekStart,
    date_end: props.weekEnd,
    target: '' as string | number,
    target_unit: '',
});

const rk = computed(() => props.rkOptions.find(o => o.id === form.performance_plan_id) ?? null);
const projectChoices = computed(() => {
    if (!rk.value || rk.value.project_id) return [];
    const ids = rk.value.project_candidates;
    return props.projectOptions.filter(p => !ids.length || ids.includes(p.id));
});
const needsProject = computed(() => !!rk.value && !rk.value.project_id && !rk.value.project_optional);

function onRkChange() {
    form.project_id = null;
    if (rk.value && !form.target_unit) form.target_unit = rk.value.target_unit ?? '';
}

function openNew() {
    form.reset();
    form.clearErrors();
    form.date_start = props.weekStart;
    form.date_end = props.weekEnd;
    editing.value = 'new';
}

function openEdit(plan: PlanItemRow) {
    form.clearErrors();
    form.performance_plan_id = plan.performance_plan_id;
    form.project_id = plan.project_id;
    form.description = plan.description;
    form.date_start = plan.date_start;
    form.date_end = plan.date_end;
    form.target = plan.target ?? '';
    form.target_unit = plan.target_unit ?? '';
    editing.value = plan.id;
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (editing.value = null) };
    if (editing.value === 'new') form.post(route('plan-items.store'), options);
    else form.patch(route('plan-items.update', editing.value as number), options);
}

function cancel(plan: PlanItemRow) {
    if (confirm(`Batalkan rencana "${plan.description}"?`)) router.delete(route('plan-items.destroy', plan.id), { preserveScroll: true });
}

// Friday evaluation: result from kipApp, with the PJ's correction.
const evalLabel: Record<PlanEvaluation['state'], string> = { done: 'Selesai', in_progress: 'Berjalan', not_started: 'Belum dikerjakan' };
const evalClass: Record<PlanEvaluation['state'], string> = {
    done: 'bg-green-50 text-green-800',
    in_progress: 'bg-amber-50 text-amber-800',
    not_started: 'bg-gray-100 text-gray-700',
};
// Push to kipApp and mark complete (progres 100 goes to kipApp).
const completing = ref<number | null>(null);
const completion = useForm({ capaian: '', evidence_url: '' });

function pushNow(plan: PlanItemRow) {
    router.post(route('plan-items.push', plan.id), {}, { preserveScroll: true });
}

function openCompletion(plan: PlanItemRow) {
    completion.reset();
    completion.clearErrors();
    completing.value = plan.id;
}

function saveCompletion(plan: PlanItemRow) {
    completion.post(route('plan-items.complete', plan.id), { preserveScroll: true, onSuccess: () => (completing.value = null) });
}

const correcting = ref<number | null>(null);
const correction = useForm({ status: '' as string, reason: '' });

function openCorrection(plan: PlanItemRow) {
    correction.clearErrors();
    correction.status = plan.evaluation.overridden ? plan.evaluation.state : plan.evaluation.computed_state;
    correction.reason = plan.evaluation.override_reason ?? '';
    correcting.value = plan.id;
}

function saveCorrection(plan: PlanItemRow) {
    correction.patch(route('plan-items.evaluate', plan.id), { preserveScroll: true, onSuccess: () => (correcting.value = null) });
}

function clearCorrection(plan: PlanItemRow) {
    router.patch(route('plan-items.evaluate', plan.id), { status: '', reason: '' }, { preserveScroll: true, onSuccess: () => (correcting.value = null) });
}

const statusLabel: Record<PlanItemRow['status'], string> = {
    planned: 'Direncanakan', pushed: 'Dikirim ke kipApp', in_progress: 'Berjalan', done: 'Selesai', cancelled: 'Dibatalkan',
};
const field = 'w-full rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';
</script>

<template>
    <div>
        <p class="mb-1 flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-gray-500">
            <span>Rencana minggu ini ({{ plans.length }})</span>
            <Button v-if="canEdit && editing === null" variant="ghost" size="sm" class="h-6 px-2 text-xs normal-case tracking-normal text-primary" @click="openNew">
                <Plus class="mr-1 h-3.5 w-3.5" aria-hidden="true" /> Tambah rencana
            </Button>
        </p>

        <p v-if="!plans.length && editing === null" class="text-sm text-gray-400">Belum ada rencana untuk minggu ini.</p>

        <ul v-if="plans.length" class="divide-y rounded border">
            <li v-for="plan in plans" :key="plan.id" class="flex items-start justify-between gap-3 px-3 py-2 text-sm">
                <div class="min-w-0">
                    <p class="leading-snug text-gray-800">{{ plan.description }}</p>
                    <p class="text-xs text-gray-500">
                        {{ formatDate(plan.date_start) }}<template v-if="plan.date_end !== plan.date_start"> – {{ formatDate(plan.date_end) }}</template>
                        <template v-if="plan.rk_name"> · {{ plan.rk_name }}</template>
                        <template v-if="plan.target"> · target {{ plan.target }} {{ plan.target_unit ?? '' }}</template>
                    </p>
                    <p class="mt-0.5 flex gap-1.5 text-[11px]">
                        <span class="rounded-full bg-gray-100 px-1.5 text-gray-600">{{ statusLabel[plan.status] }}</span>
                        <span v-if="plan.source === 'pj'" class="rounded-full bg-primary/10 px-1.5 text-primary">Dari PJ</span>
                        <span v-if="plan.source === 'rtl'" class="rounded-full bg-orange-100 px-1.5 text-orange-700">Dari RTL</span>
                    </p>
                    <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                        <span :class="['rounded-full px-2 py-0.5 font-medium', evalClass[plan.evaluation.state]]">{{ evalLabel[plan.evaluation.state] }}</span>
                        <span class="text-gray-500">
                            <template v-if="plan.evaluation.overridden">Dikoreksi PJ: {{ plan.evaluation.override_reason }}</template>
                            <template v-else-if="plan.evaluation.activity_count">Dari kipApp: {{ plan.evaluation.activity_count }} kegiatan, progres rata-rata {{ Math.round(plan.evaluation.progress ?? 0) }}%</template>
                            <template v-else>Belum ada kegiatan kipApp yang cocok</template>
                        </span>
                        <button v-if="canEvaluate && correcting !== plan.id" type="button" class="text-primary underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-primary" @click="openCorrection(plan)">Koreksi</button>
                    </p>
                    <p v-if="plan.push_error" class="mt-1 text-xs text-red-700">Belum terkirim ke kipApp: {{ plan.push_error }}</p>
                    <p v-if="canEdit && plan.status !== 'done' && plan.status !== 'cancelled' && completing !== plan.id" class="mt-1.5 flex flex-wrap gap-1.5">
                        <Button v-if="plan.status === 'planned'" type="button" variant="outline" size="sm" class="h-7 text-xs" @click="pushNow(plan)">Kirim ke kipApp</Button>
                        <Button type="button" variant="outline" size="sm" class="h-7 text-xs" @click="openCompletion(plan)">Tandai selesai</Button>
                    </p>
                    <form v-if="completing === plan.id" class="mt-2 space-y-2 rounded border border-primary/30 bg-primary/5 p-2" @submit.prevent="saveCompletion(plan)">
                        <Textarea v-model="completion.capaian" :rows="2" class="text-xs" placeholder="Capaian: apa yang sudah selesai (opsional)" aria-label="Capaian" />
                        <Input v-model="completion.evidence_url" type="url" class="h-8 text-xs" placeholder="Link bukti dukung (opsional)" aria-label="Link bukti dukung" />
                        <InputError :message="completion.errors.evidence_url || completion.errors.capaian" />
                        <p class="text-[11px] text-gray-500">Progres 100% dikirim ke kipApp atas nama Anda.</p>
                        <div class="flex justify-end gap-1.5">
                            <Button type="button" variant="ghost" size="sm" class="h-7 text-xs" @click="completing = null">Batal</Button>
                            <Button type="submit" size="sm" class="h-7 text-xs" :disabled="completion.processing">{{ completion.processing ? 'Mengirim…' : 'Simpan dan kirim' }}</Button>
                        </div>
                    </form>
                    <form v-if="correcting === plan.id" class="mt-2 space-y-2 rounded border border-primary/30 bg-primary/5 p-2" @submit.prevent="saveCorrection(plan)">
                        <Select v-model="correction.status">
                            <SelectTrigger class="h-8 w-full text-xs" aria-label="Hasil rencana"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="(label, key) in evalLabel" :key="key" :value="key">{{ label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Input v-model="correction.reason" class="h-8 text-xs" placeholder="Alasan koreksi (wajib)" aria-label="Alasan koreksi" required maxlength="500" />
                        <InputError :message="correction.errors.reason || correction.errors.status" />
                        <div class="flex justify-end gap-1.5">
                            <Button v-if="plan.evaluation.overridden" type="button" variant="ghost" size="sm" class="h-7 text-xs" @click="clearCorrection(plan)">Hapus koreksi</Button>
                            <Button type="button" variant="ghost" size="sm" class="h-7 text-xs" @click="correcting = null">Batal</Button>
                            <Button type="submit" size="sm" class="h-7 text-xs" :disabled="correction.processing">Simpan</Button>
                        </div>
                    </form>
                </div>
                <div v-if="canEdit && plan.status === 'planned'" class="flex shrink-0 gap-1">
                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-primary focus-visible:ring-2 focus-visible:ring-primary" :aria-label="`Ubah rencana ${plan.description}`" @click="openEdit(plan)"><Pencil class="h-3.5 w-3.5" /></button>
                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-red-600 focus-visible:ring-2 focus-visible:ring-primary" :aria-label="`Batalkan rencana ${plan.description}`" @click="cancel(plan)"><X class="h-3.5 w-3.5" /></button>
                </div>
            </li>
        </ul>

        <form v-if="editing !== null" class="mt-2 space-y-2 rounded border border-primary/30 bg-primary/5 p-3" @submit.prevent="save">
            <label class="block text-xs text-gray-600">Rencana Kinerja (RK)
                <Select :model-value="form.performance_plan_id" @update:model-value="(v) => { form.performance_plan_id = Number(v); onRkChange(); }">
                    <SelectTrigger class="mt-0.5 h-auto min-h-9 w-full whitespace-normal text-left"><SelectValue placeholder="Pilih RK" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="o in rkOptions" :key="o.id" :value="o.id">{{ o.description }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.performance_plan_id" />
            </label>
            <label v-if="rk && !rk.project_id && (needsProject || projectChoices.length)" class="block text-xs text-gray-600">Projek
                <Select v-model="form.project_id">
                    <SelectTrigger class="mt-0.5 h-auto min-h-9 w-full whitespace-normal text-left"><SelectValue :placeholder="needsProject ? 'Pilih projek' : '— Tanpa projek —'" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-if="!needsProject" :value="null">— Tanpa projek —</SelectItem>
                        <SelectItem v-for="p in projectChoices" :key="p.id" :value="p.id">{{ p.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.project_id" />
            </label>
            <label class="block text-xs text-gray-600">Apa yang akan dikerjakan
                <Textarea v-model="form.description" :rows="2" class="mt-0.5 text-sm" required />
                <InputError :message="form.errors.description" />
            </label>
            <div class="grid grid-cols-2 gap-2">
                <label class="text-xs text-gray-600">Mulai <input v-model="form.date_start" type="date" :class="[field, 'mt-0.5']" required /></label>
                <label class="text-xs text-gray-600">Selesai <input v-model="form.date_end" type="date" :min="form.date_start" :class="[field, 'mt-0.5']" required /></label>
            </div>
            <InputError :message="form.errors.date_end" />
            <div class="grid grid-cols-2 gap-2">
                <label class="text-xs text-gray-600">Target (opsional) <input v-model="form.target" type="number" min="0" step="any" :class="[field, 'mt-0.5']" /></label>
                <label class="text-xs text-gray-600">Satuan <input v-model="form.target_unit" type="text" :class="[field, 'mt-0.5']" /></label>
            </div>
            <div class="flex justify-end gap-2">
                <Button type="button" variant="ghost" size="sm" @click="editing = null">Batal</Button>
                <Button type="submit" size="sm" :disabled="form.processing">{{ form.processing ? 'Menyimpan…' : 'Simpan rencana' }}</Button>
            </div>
        </form>
    </div>
</template>
