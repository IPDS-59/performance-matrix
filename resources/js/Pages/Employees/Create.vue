<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import InputError from '@/Components/InputError.vue';

defineProps<{ teams: Team[] }>();

const form = useForm({
    name: '',
    full_name: '',
    employee_number: '',
    team_id: null as number | null,
    position: '',
    office: '',
    is_active: true,
});

function submit() {
    form.post(route('employees.store'));
}
</script>

<template>
    <Head title="Tambah Pegawai" />
    <AppLayout>
        <template #title>Tambah Pegawai</template>

        <div class="max-w-xl bg-white rounded-md border p-6">
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <Label for="name">Nama Singkat</Label>
                    <Input id="name" v-model="form.name" class="mt-1" />
                    <InputError :message="form.errors.name" />
                </div>
                <div>
                    <Label for="full_name">Nama Lengkap</Label>
                    <Input id="full_name" v-model="form.full_name" class="mt-1" />
                    <InputError :message="form.errors.full_name" />
                </div>
                <div>
                    <Label for="employee_number">NIP</Label>
                    <Input id="employee_number" v-model="form.employee_number" class="mt-1" />
                    <InputError :message="form.errors.employee_number" />
                </div>
                <div>
                    <Label>Tim Kerja</Label>
                    <Select v-model="form.team_id">
                        <SelectTrigger class="mt-1">
                            <SelectValue placeholder="Pilih tim..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="team in teams" :key="team.id" :value="team.id">
                                {{ team.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.team_id" />
                </div>
                <div>
                    <Label for="position">Jabatan</Label>
                    <Input id="position" v-model="form.position" class="mt-1" />
                    <InputError :message="form.errors.position" />
                </div>
                <div>
                    <Label for="office">Kantor (untuk Kepala Satker Kab)</Label>
                    <Input id="office" v-model="form.office" placeholder="BPS Kab. Poso" class="mt-1" />
                    <InputError :message="form.errors.office" />
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_active" v-model="form.is_active" class="h-4 w-4" />
                    <Label for="is_active">Aktif</Label>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" as-child>
                        <a :href="route('employees.index')">Batal</a>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Simpan</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
