<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { PlanItemRow, PlanRkOption } from '@/types';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import InputError from '@/Components/InputError.vue';
import { Pencil, Plus, X } from 'lucide-vue-next';
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
                </div>
                <div v-if="canEdit && plan.status === 'planned'" class="flex shrink-0 gap-1">
                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-primary focus-visible:ring-2 focus-visible:ring-primary" :aria-label="`Ubah rencana ${plan.description}`" @click="openEdit(plan)"><Pencil class="h-3.5 w-3.5" /></button>
                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-red-600 focus-visible:ring-2 focus-visible:ring-primary" :aria-label="`Batalkan rencana ${plan.description}`" @click="cancel(plan)"><X class="h-3.5 w-3.5" /></button>
                </div>
            </li>
        </ul>

        <form v-if="editing !== null" class="mt-2 space-y-2 rounded border border-primary/30 bg-primary/5 p-3" @submit.prevent="save">
            <label class="block text-xs text-gray-600">Rencana Kinerja (RK)
                <select v-model="form.performance_plan_id" :class="[field, 'mt-0.5']" required @change="onRkChange">
                    <option :value="null" disabled>Pilih RK</option>
                    <option v-for="o in rkOptions" :key="o.id" :value="o.id">{{ o.description }}</option>
                </select>
                <InputError :message="form.errors.performance_plan_id" />
            </label>
            <label v-if="rk && !rk.project_id && (needsProject || projectChoices.length)" class="block text-xs text-gray-600">Projek
                <select v-model="form.project_id" :class="[field, 'mt-0.5']" :required="needsProject">
                    <option :value="null">{{ needsProject ? 'Pilih projek' : '— Tanpa projek —' }}</option>
                    <option v-for="p in projectChoices" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
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
