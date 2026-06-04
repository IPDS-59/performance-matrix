<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { Employee } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { computed, ref } from 'vue';

interface ProjectOption {
    id: number;
    name: string;
    year: number;
    team_id: number;
    team?: { id: number; name: string } | null;
}

const props = defineProps<{
    projects: ProjectOption[];
    employees: Employee[];
    isAdmin: boolean;
}>();

const form = useForm({
    project_id: null as number | null,
    code: '',
    description: '',
    target: '' as string | number,
    target_unit: '',
    period_type: 'year' as 'year' | 'quarter',
    period: null as number | null,
    pic_employee_id: null as number | null,
});

const picOpen = ref(false);

const selectedPicLabel = computed(() => {
    if (form.pic_employee_id === null) return '— Tidak ada —';
    const emp = props.employees.find(e => e.id === form.pic_employee_id);
    return emp ? (emp.display_name || emp.name) : '— Tidak ada —';
});

const teamEmployees = computed(() => {
    if (!form.project_id) return props.employees;
    const project = props.projects.find(p => p.id === form.project_id);
    if (!project) return props.employees;
    return props.employees.filter(e => e.team_id === project.team_id);
});

function submit() {
    form.post(route('performance-plans.store'));
}
</script>

<template>
    <Head title="Tambah Rencana Kinerja" />
    <AppLayout>
        <template #title>Tambah Rencana Kinerja (RK)</template>

        <div class="max-w-lg bg-white rounded-md border p-6">
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <Label for="project_id">Proyek</Label>
                    <Select
                        :model-value="form.project_id ? String(form.project_id) : ''"
                        @update:model-value="(v) => { form.project_id = v ? Number(v) : null; form.pic_employee_id = null; }"
                    >
                        <SelectTrigger class="mt-1 w-full">
                            <SelectValue placeholder="Pilih proyek..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="p in projects" :key="p.id" :value="String(p.id)">
                                {{ p.name }} ({{ p.year }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.project_id" />
                </div>
                <div>
                    <Label for="code">Kode RK</Label>
                    <Input id="code" v-model="form.code" class="mt-1" placeholder="Opsional" />
                    <InputError :message="form.errors.code" />
                </div>
                <div>
                    <Label for="description">Deskripsi</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label for="target">Target</Label>
                        <Input id="target" type="number" step="any" v-model="form.target" class="mt-1" placeholder="Opsional" />
                        <InputError :message="form.errors.target" />
                    </div>
                    <div>
                        <Label for="target_unit">Satuan</Label>
                        <Input id="target_unit" v-model="form.target_unit" class="mt-1" placeholder="Kegiatan, %, dll." />
                        <InputError :message="form.errors.target_unit" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label>Tipe Periode</Label>
                        <Select v-model="form.period_type">
                            <SelectTrigger class="mt-1 w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="year">Tahunan</SelectItem>
                                <SelectItem value="quarter">Triwulan</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.period_type" />
                    </div>
                    <div v-if="form.period_type === 'quarter'">
                        <Label>Triwulan</Label>
                        <Select
                            :model-value="form.period ? String(form.period) : ''"
                            @update:model-value="(v) => (form.period = v ? Number(v) : null)"
                        >
                            <SelectTrigger class="mt-1 w-full">
                                <SelectValue placeholder="Pilih..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">TW 1</SelectItem>
                                <SelectItem value="2">TW 2</SelectItem>
                                <SelectItem value="3">TW 3</SelectItem>
                                <SelectItem value="4">TW 4</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.period" />
                    </div>
                </div>
                <div>
                    <Label>PIC</Label>
                    <Popover v-model:open="picOpen">
                        <PopoverTrigger as-child>
                            <Button variant="outline" role="combobox" class="mt-1 w-full justify-between font-normal">
                                {{ selectedPicLabel }}
                                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-[--radix-popover-trigger-width] p-0">
                            <Command>
                                <CommandInput placeholder="Cari pegawai..." />
                                <CommandList>
                                    <CommandEmpty>Tidak ada hasil.</CommandEmpty>
                                    <CommandGroup>
                                        <CommandItem value="__none__" @select="() => { form.pic_employee_id = null; picOpen = false }">
                                            — Tidak ada —
                                            <Check v-if="form.pic_employee_id === null" class="ml-auto h-4 w-4" />
                                        </CommandItem>
                                        <CommandItem
                                            v-for="emp in teamEmployees"
                                            :key="emp.id"
                                            :value="emp.display_name || emp.name"
                                            @select="() => { form.pic_employee_id = emp.id; picOpen = false }"
                                        >
                                            {{ emp.display_name || emp.name }}
                                            <Check v-if="form.pic_employee_id === emp.id" class="ml-auto h-4 w-4" />
                                        </CommandItem>
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                    <InputError :message="form.errors.pic_employee_id" />
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" as-child>
                        <a :href="route('performance-plans.index')">Batal</a>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Simpan</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
