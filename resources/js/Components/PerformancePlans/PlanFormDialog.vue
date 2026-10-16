<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import type { Employee, PerformancePlan } from '@/types';
import type { ProjectChoice } from '@/types/crud';
import FormDialog from '@/Components/FormDialog.vue';
import EmployeePicker from '@/Components/EmployeePicker.vue';
import InputError from '@/Components/InputError.vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

/** Add or edit one RK in a popup. */
const props = defineProps<{ open: boolean; item: PerformancePlan | null; projects: ProjectChoice[]; employees: Employee[]; manager: boolean }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm({
    project_id: null as number | null,
    description: '',
    target: '' as string | number,
    target_unit: '',
    period_type: 'year' as 'year' | 'quarter',
    period: null as number | null,
    pic_employee_id: null as number | null,
});

watch(() => [props.open, props.item] as const, ([open, item]) => {
    if (!open) return;
    form.clearErrors();
    form.project_id = item?.project_id ?? null;
    form.description = item?.description ?? '';
    form.target = item?.target ?? '';
    form.target_unit = item?.target_unit ?? '';
    form.period_type = item?.period_type ?? 'year';
    form.period = item?.period ?? null;
    form.pic_employee_id = item?.pic_employee_id ?? null;
}, { immediate: true });

// The PIC comes from the team of the RK (its project, or the team of a team-scoped RK).
const teamEmployees = computed(() => {
    const teamId = props.projects.find(p => p.id === form.project_id)?.team_id ?? props.item?.project?.team_id ?? props.item?.team?.id;
    return teamId ? props.employees.filter(e => e.team_id === teamId) : props.employees;
});

function save() {
    const options = { preserveScroll: true, onSuccess: () => emit('update:open', false) };
    if (props.item) form.put(route('performance-plans.update', props.item.id), options);
    else form.post(route('performance-plans.store'), options);
}
</script>

<template>
    <FormDialog :open="open" :title="item ? 'Edit Rencana Kinerja (RK)' : 'Tambah Rencana Kinerja (RK)'" :processing="form.processing" @update:open="(v: boolean) => emit('update:open', v)" @submit="save">
        <div v-if="!item || manager" class="space-y-1.5">
            <Label>Proyek</Label>
            <Select :model-value="form.project_id ? String(form.project_id) : undefined" @update:model-value="(v) => { form.project_id = v ? Number(v) : null; form.pic_employee_id = null; }">
                <SelectTrigger class="w-full"><SelectValue placeholder="Pilih proyek..." /></SelectTrigger>
                <SelectContent><SelectItem v-for="p in projects" :key="p.id" :value="String(p.id)">{{ p.name }} ({{ p.year }})</SelectItem></SelectContent>
            </Select>
            <InputError :message="form.errors.project_id" />
        </div>
        <p v-else class="text-sm text-gray-500">Proyek: <span class="font-medium text-gray-800">{{ item.project?.name ?? '—' }}</span></p>
        <div class="space-y-1.5"><Label for="rk-desc">Deskripsi</Label><Textarea id="rk-desc" v-model="form.description" :rows="3" required /><InputError :message="form.errors.description" /></div>
        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5"><Label for="rk-target">Target</Label><Input id="rk-target" v-model="form.target" type="number" step="any" placeholder="Opsional" /><InputError :message="form.errors.target" /></div>
            <div class="space-y-1.5"><Label for="rk-unit">Satuan</Label><Input id="rk-unit" v-model="form.target_unit" placeholder="Kegiatan, %, dll." /><InputError :message="form.errors.target_unit" /></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
                <Label>Tipe Periode</Label>
                <Select v-model="form.period_type">
                    <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent><SelectItem value="year">Tahunan</SelectItem><SelectItem value="quarter">Triwulan</SelectItem></SelectContent>
                </Select>
                <InputError :message="form.errors.period_type" />
            </div>
            <div v-if="form.period_type === 'quarter'" class="space-y-1.5">
                <Label>Triwulan</Label>
                <Select :model-value="form.period ? String(form.period) : undefined" @update:model-value="(v) => (form.period = v ? Number(v) : null)">
                    <SelectTrigger class="w-full"><SelectValue placeholder="Pilih..." /></SelectTrigger>
                    <SelectContent><SelectItem v-for="q in [1, 2, 3, 4]" :key="q" :value="String(q)">TW {{ q }}</SelectItem></SelectContent>
                </Select>
                <InputError :message="form.errors.period" />
            </div>
        </div>
        <div class="space-y-1.5">
            <Label>PIC</Label>
            <EmployeePicker v-model="form.pic_employee_id" :employees="teamEmployees" empty-label="— Tidak ada —" />
            <InputError :message="form.errors.pic_employee_id" />
        </div>
    </FormDialog>
</template>
