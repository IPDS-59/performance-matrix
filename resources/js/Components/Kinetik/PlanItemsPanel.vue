<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { PlanItemRow, PlanRkOption } from '@/types';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import InputError from '@/Components/InputError.vue';
import { Plus } from 'lucide-vue-next';
import PlanItemCard from '@/Components/Kinetik/PlanItemCard.vue';
import FormDialog from '@/Components/FormDialog.vue';

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
    canSend?: boolean;
}>();

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

const field = 'w-full rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';
</script>

<template>
    <div>
        <div class="mb-2 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">Rencana minggu ini <span class="font-normal text-gray-500">({{ plans.length }})</span></h3>
            <Button v-if="canEdit && editing === null" variant="outline" size="sm" class="h-8" @click="openNew">
                <Plus class="mr-1 h-4 w-4" aria-hidden="true" /> Tambah rencana
            </Button>
        </div>

        <p v-if="!plans.length && editing === null" class="rounded-md border border-dashed bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
            Belum ada rencana untuk minggu ini.<template v-if="canEdit"> Tambahkan rencana agar bisa dikirim ke kipApp pada tanggal mulainya.</template>
        </p>

        <ul v-if="plans.length" class="divide-y rounded-md border">
            <PlanItemCard v-for="plan in plans" :key="plan.id" :plan="plan" :can-edit="canEdit" :can-evaluate="canEvaluate" :can-send="canSend" @edit="openEdit" />
        </ul>

        <FormDialog
            :open="editing !== null"
            :title="editing === 'new' ? 'Tambah rencana' : 'Ubah rencana'"
            description="Rencana dikirim ke kipApp pada tanggal mulainya."
            submit-label="Simpan rencana"
            :processing="form.processing"
            @update:open="(v: boolean) => { if (!v) editing = null; }"
            @submit="save"
        >
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
        </FormDialog>
    </div>
</template>
