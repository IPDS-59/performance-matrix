<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import type { Employee, Team } from '@/types';
import FormDialog from '@/Components/FormDialog.vue';
import EmployeePicker from '@/Components/EmployeePicker.vue';
import InputError from '@/Components/InputError.vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Checkbox } from '@/Components/ui/checkbox';

/** Add or edit a team in a popup. */
const props = defineProps<{ open: boolean; item: Team | null; employees: Employee[] }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm({
    name: '',
    code: '',
    description: '',
    is_active: true,
    leader_id: null as number | null,
});

watch(() => [props.open, props.item] as const, ([open, item]) => {
    if (!open) return;
    form.clearErrors();
    form.name = item?.name ?? '';
    form.code = item?.code ?? '';
    form.description = item?.description ?? '';
    form.is_active = item?.is_active ?? true;
    form.leader_id = item?.leader_id ?? null;
}, { immediate: true });

function save() {
    const options = { preserveScroll: true, onSuccess: () => emit('update:open', false) };
    if (props.item) form.put(route('teams.update', props.item.id), options);
    else form.post(route('teams.store'), options);
}
</script>

<template>
    <FormDialog :open="open" :title="item ? 'Edit Tim Kerja' : 'Tambah Tim Kerja'" :processing="form.processing" @update:open="(v: boolean) => emit('update:open', v)" @submit="save">
        <div class="space-y-1.5"><Label for="team-name">Nama Tim</Label><Input id="team-name" v-model="form.name" required /><InputError :message="form.errors.name" /></div>
        <div class="space-y-1.5"><Label for="team-code">Kode Tim</Label><Input id="team-code" v-model="form.code" class="uppercase" maxlength="20" required /><InputError :message="form.errors.code" /></div>
        <div class="space-y-1.5"><Label for="team-desc">Deskripsi</Label><Textarea id="team-desc" v-model="form.description" :rows="3" /><InputError :message="form.errors.description" /></div>
        <div class="space-y-1.5">
            <Label>Ketua Tim</Label>
            <EmployeePicker v-model="form.leader_id" :employees="employees" empty-label="— Belum ditentukan —" />
            <InputError :message="form.errors.leader_id" />
        </div>
        <div class="flex items-center gap-2"><Checkbox id="team-active" v-model="form.is_active" /><Label for="team-active">Aktif</Label></div>
    </FormDialog>
</template>
