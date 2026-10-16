<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import type { PlanItemRow, PlanRkOption } from '@/types';
import FormDialog from '@/Components/FormDialog.vue';
import InputError from '@/Components/InputError.vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

/** Add or edit one kegiatan, with the same fields as kipApp plus target, satuan and capaian. */
const props = defineProps<{
    open: boolean;
    /** The kegiatan to edit, or null to add one. */
    item: PlanItemRow | null;
    rkOptions: PlanRkOption[];
    projectOptions: Array<{ id: number; name: string }>;
    /** Default start date of a new kegiatan. */
    defaultDate: string;
}>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm({
    team_id: null as number | null,
    performance_plan_id: null as number | null,
    project_id: null as number | null,
    description: '',
    mode: 'period' as 'period' | 'time',
    date_start: '',
    date_end: '',
    time_start: '',
    time_end: '',
    target: '' as string | number,
    target_unit: '',
    capaian: '' as string | number,
    progress: 0 as string | number,
    evidence_url: '',
    capaian_note: '',
});

watch(() => [props.open, props.item] as const, ([open, item]) => {
    if (!open) return;
    form.clearErrors();
    form.team_id = item?.team_id ?? null;
    form.performance_plan_id = item?.performance_plan_id ?? null;
    form.project_id = item?.project_id ?? null;
    form.description = item?.description ?? '';
    form.mode = item?.time_start ? 'time' : 'period';
    form.date_start = item?.date_start ?? props.defaultDate;
    form.date_end = item?.date_end ?? props.defaultDate;
    form.time_start = item?.time_start ?? '08:00';
    form.time_end = item?.time_end ?? '16:00';
    form.target = item?.target ?? '';
    form.target_unit = item?.target_unit ?? '';
    form.capaian = item?.capaian ?? '';
    form.progress = item?.progress ?? 0;
    form.evidence_url = item?.evidence_url ?? '';
    form.capaian_note = item?.capaian_note ?? '';
}, { immediate: true });

const rk = computed(() => props.rkOptions.find(o => o.id === form.performance_plan_id) ?? null);
const needsProject = computed(() => !!rk.value && !rk.value.project_id && !rk.value.project_optional);
const projectChoices = computed(() => {
    if (!rk.value || rk.value.project_id) return [];
    const ids = rk.value.project_candidates;
    return props.projectOptions.filter(p => !ids.length || ids.includes(p.id));
});

function pickRk(value: unknown) {
    form.performance_plan_id = Number(value);
    form.project_id = null;
    form.team_id = rk.value?.team_id ?? null;
    if (rk.value && !form.target_unit) form.target_unit = rk.value.target_unit ?? '';
}

function save() {
    const timed = form.mode === 'time';
    form.transform((data: Record<string, any>) => ({
        team_id: data.team_id,
        performance_plan_id: data.performance_plan_id,
        project_id: data.project_id,
        description: data.description,
        date_start: data.date_start,
        date_end: timed ? data.date_start : data.date_end,
        time_start: timed ? data.time_start : null,
        time_end: timed ? data.time_end : null,
        target: data.target === '' ? null : data.target,
        target_unit: data.target_unit || null,
        capaian: data.capaian === '' ? null : data.capaian,
        progress: data.progress === '' ? 0 : data.progress,
        evidence_url: data.evidence_url || null,
        capaian_note: data.capaian_note || null,
    }));
    const options = { preserveScroll: true, onSuccess: () => emit('update:open', false) };
    if (props.item) form.patch(route('plan-items.update', props.item.id), options);
    else form.post(route('plan-items.store'), options);
}
</script>

<template>
    <FormDialog
        :open="open"
        :title="item ? 'Ubah kegiatan' : 'Tambah kegiatan'"
        description="Kegiatan dikirim ke kipApp pada tanggal mulainya, atau saat Anda menekan Kirim ke kipApp."
        :processing="form.processing"
        @update:open="(v: boolean) => emit('update:open', v)"
        @submit="save"
    >
        <div class="space-y-1.5">
            <Label>Rencana Kinerja (RK)</Label>
            <Select :model-value="form.performance_plan_id ?? undefined" :disabled="!!item" @update:model-value="pickRk">
                <SelectTrigger class="h-auto min-h-9 w-full whitespace-normal text-left"><SelectValue placeholder="Pilih RK" /></SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="o in rkOptions" :key="o.id" :value="o.id">{{ o.description }}</SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="form.errors.performance_plan_id" />
        </div>
        <div v-if="rk && !rk.project_id && (needsProject || projectChoices.length)" class="space-y-1.5">
            <Label>Projek</Label>
            <Select v-model="form.project_id">
                <SelectTrigger class="h-auto min-h-9 w-full whitespace-normal text-left"><SelectValue :placeholder="needsProject ? 'Pilih projek' : '— Tanpa projek —'" /></SelectTrigger>
                <SelectContent>
                    <SelectItem v-if="!needsProject" :value="null">— Tanpa projek —</SelectItem>
                    <SelectItem v-for="p in projectChoices" :key="p.id" :value="p.id">{{ p.name }}</SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="form.errors.project_id" />
        </div>

        <div class="space-y-1.5">
            <Label for="pi-desc">Kegiatan</Label>
            <Textarea id="pi-desc" v-model="form.description" :rows="2" required />
            <InputError :message="form.errors.description" />
        </div>

        <div class="flex gap-4 text-sm">
            <label class="flex items-center gap-1.5"><input v-model="form.mode" type="radio" value="period" /> Gunakan periode tanggal</label>
            <label class="flex items-center gap-1.5"><input v-model="form.mode" type="radio" value="time" /> Gunakan jam</label>
        </div>
        <div v-if="form.mode === 'period'" class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5"><Label for="pi-start">Tanggal mulai</Label><Input id="pi-start" v-model="form.date_start" type="date" required /></div>
            <div class="space-y-1.5"><Label for="pi-end">Tanggal selesai</Label><Input id="pi-end" v-model="form.date_end" type="date" :min="form.date_start" required /></div>
        </div>
        <div v-else class="grid grid-cols-3 gap-3">
            <div class="space-y-1.5"><Label for="pi-day">Tanggal</Label><Input id="pi-day" v-model="form.date_start" type="date" required /></div>
            <div class="space-y-1.5"><Label for="pi-ts">Jam mulai</Label><Input id="pi-ts" v-model="form.time_start" type="time" required /></div>
            <div class="space-y-1.5"><Label for="pi-te">Jam selesai</Label><Input id="pi-te" v-model="form.time_end" type="time" required /></div>
        </div>
        <InputError :message="form.errors.date_end || form.errors.time_end || form.errors.date_start" />

        <div class="grid grid-cols-3 gap-3">
            <div class="space-y-1.5"><Label for="pi-target">Target (angka)</Label><Input id="pi-target" v-model="form.target" type="number" min="0" step="any" /></div>
            <div class="space-y-1.5"><Label for="pi-unit">Satuan</Label><Input id="pi-unit" v-model="form.target_unit" placeholder="blok" /></div>
            <div class="space-y-1.5"><Label for="pi-cap">Capaian (angka)</Label><Input id="pi-cap" v-model="form.capaian" type="number" min="0" step="any" /></div>
        </div>
        <InputError :message="form.errors.target || form.errors.capaian" />

        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5"><Label for="pi-prog">Progres (%)</Label><Input id="pi-prog" v-model="form.progress" type="number" min="0" max="100" /><InputError :message="form.errors.progress" /></div>
            <div class="space-y-1.5"><Label for="pi-url">Bukti dukung (tautan)</Label><Input id="pi-url" v-model="form.evidence_url" type="url" placeholder="https://…" /><InputError :message="form.errors.evidence_url" /></div>
        </div>
        <div class="space-y-1.5">
            <Label for="pi-note">Uraian capaian (teks ke kipApp)</Label>
            <Textarea id="pi-note" v-model="form.capaian_note" :rows="2" placeholder="Apa yang sudah dicapai, dalam kalimat" />
            <InputError :message="form.errors.capaian_note" />
        </div>
    </FormDialog>
</template>
