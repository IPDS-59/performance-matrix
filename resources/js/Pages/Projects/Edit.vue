<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { Employee, Project, Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import InputError from '@/Components/InputError.vue';
import { computed } from 'vue';

const props = defineProps<{
    project: Project;
    teams: Team[];
    employees: Employee[];
}>();

const initialMembers = computed(() =>
    (props.project.members ?? []).map((m) => ({
        employee_id: m.id,
        role: m.pivot?.role ?? 'member',
    }))
);

const form = useForm({
    team_id: props.project.team_id,
    leader_id: props.project.leader_id ?? null as number | null,
    name: props.project.name,
    description: props.project.description ?? '',
    objective: props.project.objective ?? '',
    kpi: props.project.kpi ?? '',
    status: props.project.status,
    year: props.project.year,
    members: initialMembers.value,
});

function submit() {
    form.put(route('projects.update', props.project.id));
}
</script>

<template>
    <Head title="Edit Proyek" />
    <AppLayout>
        <template #title>Edit Proyek</template>

        <div class="max-w-2xl bg-white rounded-md border p-6">
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label>Tim Kerja</Label>
                        <Select v-model="form.team_id">
                            <SelectTrigger class="mt-1">
                                <SelectValue placeholder="Pilih tim..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.team_id" />
                    </div>
                    <div>
                        <Label for="year">Tahun</Label>
                        <Input id="year" type="number" v-model="form.year" class="mt-1" />
                        <InputError :message="form.errors.year" />
                    </div>
                </div>
                <div>
                    <Label for="name">Nama Proyek</Label>
                    <Input id="name" v-model="form.name" class="mt-1" />
                    <InputError :message="form.errors.name" />
                </div>
                <div>
                    <Label>Ketua Tim</Label>
                    <Select v-model="form.leader_id">
                        <SelectTrigger class="mt-1">
                            <SelectValue placeholder="Pilih ketua..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="null">— Belum ditentukan —</SelectItem>
                            <SelectItem v-for="emp in employees" :key="emp.id" :value="emp.id">
                                {{ emp.display_name || emp.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.leader_id" />
                </div>
                <div>
                    <Label for="kpi">IKU</Label>
                    <textarea id="kpi" v-model="form.kpi" rows="2" class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                    <InputError :message="form.errors.kpi" />
                </div>
                <div>
                    <Label for="objective">Tujuan</Label>
                    <textarea id="objective" v-model="form.objective" rows="2" class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                    <InputError :message="form.errors.objective" />
                </div>
                <div>
                    <Label>Status</Label>
                    <Select v-model="form.status">
                        <SelectTrigger class="mt-1">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Aktif</SelectItem>
                            <SelectItem value="completed">Selesai</SelectItem>
                            <SelectItem value="cancelled">Dibatalkan</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" as-child>
                        <a :href="route('projects.index')">Batal</a>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Perbarui</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
