<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import type { Team } from '@/types';
import FormDialog from '@/Components/FormDialog.vue';
import InputError from '@/Components/InputError.vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

/** Add an employee in a popup. Editing stays on its own page (education, mutation, account link). */
const props = defineProps<{ open: boolean; teams: Team[] }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm({
    name: '',
    full_name: '',
    employee_number: '',
    nip_lama: '',
    nip_baru: '',
    team_id: null as number | null,
    position: '',
    office: '',
    is_active: true,
});

watch(() => props.open, (open) => {
    if (!open) return;
    form.reset();
    form.clearErrors();
}, { immediate: true });

function save() {
    form.post(route('employees.store'), { preserveScroll: true, onSuccess: () => emit('update:open', false) });
}
</script>

<template>
    <FormDialog :open="open" title="Tambah Pegawai" :processing="form.processing" @update:open="(v: boolean) => emit('update:open', v)" @submit="save">
        <div class="space-y-1.5"><Label for="emp-name">Nama Singkat</Label><Input id="emp-name" v-model="form.name" required /><InputError :message="form.errors.name" /></div>
        <div class="space-y-1.5"><Label for="emp-full">Nama Lengkap</Label><Input id="emp-full" v-model="form.full_name" /><InputError :message="form.errors.full_name" /></div>
        <div class="space-y-1.5"><Label for="emp-number">NIP</Label><Input id="emp-number" v-model="form.employee_number" /><InputError :message="form.errors.employee_number" /></div>
        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5"><Label for="emp-lama">NIP Lama (kipApp)</Label><Input id="emp-lama" v-model="form.nip_lama" placeholder="9 digit" /><InputError :message="form.errors.nip_lama" /></div>
            <div class="space-y-1.5"><Label for="emp-baru">NIP Baru</Label><Input id="emp-baru" v-model="form.nip_baru" /><InputError :message="form.errors.nip_baru" /></div>
        </div>
        <div class="space-y-1.5">
            <Label>Tim Kerja</Label>
            <Select :model-value="form.team_id ?? undefined" @update:model-value="(v) => (form.team_id = v ? Number(v) : null)">
                <SelectTrigger class="w-full"><SelectValue placeholder="Pilih tim..." /></SelectTrigger>
                <SelectContent><SelectItem v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</SelectItem></SelectContent>
            </Select>
            <InputError :message="form.errors.team_id" />
        </div>
        <div class="space-y-1.5"><Label for="emp-pos">Jabatan</Label><Input id="emp-pos" v-model="form.position" /><InputError :message="form.errors.position" /></div>
        <div class="space-y-1.5"><Label for="emp-office">Kantor (untuk Kepala Satker Kab)</Label><Input id="emp-office" v-model="form.office" placeholder="BPS Kab. Poso" /><InputError :message="form.errors.office" /></div>
        <div class="flex items-center gap-2"><Checkbox id="emp-active" v-model="form.is_active" /><Label for="emp-active">Aktif</Label></div>
    </FormDialog>
</template>
