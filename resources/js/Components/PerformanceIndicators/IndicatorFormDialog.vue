<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import type { PerformanceIndicator, Team } from '@/types';
import FormDialog from '@/Components/FormDialog.vue';
import InputError from '@/Components/InputError.vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

/** Add or edit one IKU in a popup. */
const props = defineProps<{ open: boolean; item: PerformanceIndicator | null; teams: Team[]; manager: boolean; defaultYear: number }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm({
    team_id: null as number | null,
    year: 0,
    name: '',
    target: '' as string | number,
    target_unit: '',
    description: '',
});

watch(() => [props.open, props.item] as const, ([open, item]) => {
    if (!open) return;
    form.clearErrors();
    form.team_id = item?.team_id ?? (props.teams.length === 1 ? props.teams[0].id : null);
    form.year = item?.year ?? props.defaultYear;
    form.name = item?.name ?? '';
    form.target = item?.target ?? '';
    form.target_unit = item?.target_unit ?? '';
    form.description = item?.description ?? '';
}, { immediate: true });

function save() {
    const options = { preserveScroll: true, onSuccess: () => emit('update:open', false) };
    if (props.item) form.put(route('performance-indicators.update', props.item.id), options);
    else form.post(route('performance-indicators.store'), options);
}
</script>

<template>
    <FormDialog :open="open" :title="item ? 'Edit IKU' : 'Tambah IKU'" :processing="form.processing" @update:open="(v: boolean) => emit('update:open', v)" @submit="save">
        <div v-if="manager || (!item && teams.length > 1)" class="space-y-1.5">
            <Label>Tim Kerja</Label>
            <Select :model-value="form.team_id ? String(form.team_id) : undefined" @update:model-value="(v) => (form.team_id = v ? Number(v) : null)">
                <SelectTrigger class="w-full"><SelectValue placeholder="Pilih tim..." /></SelectTrigger>
                <SelectContent><SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem></SelectContent>
            </Select>
            <InputError :message="form.errors.team_id" />
        </div>
        <div class="space-y-1.5"><Label for="ind-year">Tahun</Label><Input id="ind-year" v-model="form.year" type="number" min="2020" max="2099" /><InputError :message="form.errors.year" /></div>
        <div class="space-y-1.5"><Label for="ind-name">Nama IKU</Label><Input id="ind-name" v-model="form.name" required /><InputError :message="form.errors.name" /></div>
        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5"><Label for="ind-target">Target</Label><Input id="ind-target" v-model="form.target" type="number" step="any" placeholder="Opsional" /><InputError :message="form.errors.target" /></div>
            <div class="space-y-1.5"><Label for="ind-unit">Satuan</Label><Input id="ind-unit" v-model="form.target_unit" placeholder="Kegiatan, %, dll." /><InputError :message="form.errors.target_unit" /></div>
        </div>
        <div class="space-y-1.5"><Label for="ind-desc">Deskripsi</Label><Textarea id="ind-desc" v-model="form.description" :rows="3" /><InputError :message="form.errors.description" /></div>
    </FormDialog>
</template>
