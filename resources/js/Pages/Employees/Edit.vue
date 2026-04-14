<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { Employee, Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Checkbox } from '@/Components/ui/checkbox';
import InputError from '@/Components/InputError.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ref } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Mutation {
    id: number;
    team_id: number;
    team: { id: number; name: string } | null;
    started_at: string;
    ended_at: string | null;
    notes: string | null;
}

interface EmployeeEducation {
    id: number;
    degree_front: string | null;
    degree_back: string | null;
    institution: string;
    field_of_study: string | null;
    graduated_year: number | null;
    is_highest: boolean;
}

const props = defineProps<{
    employee: Employee & { educations: EmployeeEducation[] };
    teams: Team[];
    users: User[];
    mutations: Mutation[];
}>();

const form = useForm({
    name: props.employee.name,
    full_name: props.employee.full_name ?? '',
    employee_number: props.employee.employee_number ?? '',
    team_id: props.employee.team_id ?? null as number | null,
    position: props.employee.position ?? '',
    office: props.employee.office ?? '',
    is_active: props.employee.is_active,
    user_id: props.employee.user_id ?? null as number | null,
});

const mutationForm = useForm({
    team_id: null as number | null,
    started_at: '',
    notes: '',
});

const showMutationForm = ref(false);

// ── Education ─────────────────────────────────────────────────────────────
const showEduForm = ref(false);
const editingEduId = ref<number | null>(null);
const eduForm = useForm({
    degree_front: '',
    degree_back: '',
    institution: '',
    field_of_study: '',
    graduated_year: '' as string | number,
    is_highest: false,
});

function openAddEdu() {
    editingEduId.value = null;
    eduForm.reset();
    showEduForm.value = true;
}

function openEditEdu(edu: EmployeeEducation) {
    editingEduId.value = edu.id;
    eduForm.degree_front = edu.degree_front ?? '';
    eduForm.degree_back = edu.degree_back ?? '';
    eduForm.institution = edu.institution;
    eduForm.field_of_study = edu.field_of_study ?? '';
    eduForm.graduated_year = edu.graduated_year ?? '';
    eduForm.is_highest = edu.is_highest;
    showEduForm.value = true;
}

function submitEdu() {
    if (editingEduId.value) {
        eduForm.put(route('employees.educations.update', { employee: props.employee.id, education: editingEduId.value }), {
            onSuccess: () => { showEduForm.value = false; eduForm.reset(); },
        });
    } else {
        eduForm.post(route('employees.educations.store', props.employee.id), {
            onSuccess: () => { showEduForm.value = false; eduForm.reset(); },
        });
    }
}

const deleteEduOpen = ref(false);
const pendingEduId = ref<number | null>(null);

function confirmDeleteEdu(eduId: number) {
    pendingEduId.value = eduId;
    deleteEduOpen.value = true;
}

function executeDeleteEdu() {
    if (pendingEduId.value !== null) {
        router.delete(route('employees.educations.destroy', { employee: props.employee.id, education: pendingEduId.value }), { preserveScroll: true });
    }
}

function submit() {
    form.put(route('employees.update', props.employee.id));
}

function submitMutation() {
    mutationForm.post(route('employees.mutasi', props.employee.id), {
        onSuccess: () => {
            mutationForm.reset();
            showMutationForm.value = false;
        },
    });
}

function formatDate(dateStr: string) {
    return new Date(dateStr).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' });
}
</script>

<template>
    <Head title="Edit Pegawai" />
    <AppLayout>
        <template #title>Edit Pegawai</template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
            <!-- Left: Edit form -->
            <div class="rounded-lg border bg-white p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Data Pegawai</h2>
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
                        <Label for="office">Kantor (Kepala Satker Kab)</Label>
                        <Input id="office" v-model="form.office" placeholder="BPS Kab. Poso" class="mt-1" />
                        <InputError :message="form.errors.office" />
                    </div>
                    <div>
                        <Label>Hubungkan ke Akun</Label>
                        <Select v-model="form.user_id">
                            <SelectTrigger class="mt-1">
                                <SelectValue placeholder="Pilih akun pengguna..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">— Tidak dihubungkan —</SelectItem>
                                <SelectItem v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }} ({{ user.email }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.user_id" />
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="is_active" v-model="form.is_active" class="h-4 w-4" />
                        <Label for="is_active">Aktif</Label>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="outline" as-child>
                            <a :href="route('employees.index')">Batal</a>
                        </Button>
                        <Button type="submit" :disabled="form.processing">Perbarui</Button>
                    </div>
                </form>
            </div>

            <!-- Right: Mutation history -->
            <div class="rounded-lg border bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Riwayat Mutasi</h2>
                    <Button size="sm" @click="showMutationForm = !showMutationForm">
                        {{ showMutationForm ? 'Tutup' : '+ Mutasi Baru' }}
                    </Button>
                </div>

                <!-- New mutation form -->
                <div v-if="showMutationForm" class="mb-5 rounded-md border border-blue-100 bg-blue-50 p-4 space-y-3">
                    <h3 class="text-sm font-semibold text-blue-900">Catat Mutasi / Pindah Tim</h3>
                    <div>
                        <Label>Tim Tujuan</Label>
                        <Select v-model="mutationForm.team_id">
                            <SelectTrigger class="mt-1 bg-white">
                                <SelectValue placeholder="Pilih tim..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="team in teams" :key="team.id" :value="team.id">
                                    {{ team.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="mutationForm.errors.team_id" />
                    </div>
                    <div>
                        <Label for="started_at">Tanggal Efektif</Label>
                        <Input id="started_at" type="date" v-model="mutationForm.started_at" class="mt-1 bg-white" />
                        <InputError :message="mutationForm.errors.started_at" />
                    </div>
                    <div>
                        <Label for="mutation_notes">Keterangan</Label>
                        <Input id="mutation_notes" v-model="mutationForm.notes" placeholder="Opsional" class="mt-1 bg-white" />
                        <InputError :message="mutationForm.errors.notes" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" size="sm" @click="showMutationForm = false">Batal</Button>
                        <Button size="sm" :disabled="mutationForm.processing" @click="submitMutation">Simpan Mutasi</Button>
                    </div>
                </div>

                <!-- Timeline -->
                <div v-if="mutations.length" class="space-y-0">
                    <div
                        v-for="(mut, idx) in mutations"
                        :key="mut.id"
                        class="relative flex gap-4"
                    >
                        <!-- Timeline line -->
                        <div class="flex flex-col items-center">
                            <div
                                :class="mut.ended_at === null ? 'bg-primary' : 'bg-gray-300'"
                                class="mt-1 h-3 w-3 shrink-0 rounded-full"
                            />
                            <div v-if="idx < mutations.length - 1" class="w-px flex-1 bg-gray-200" />
                        </div>
                        <!-- Content -->
                        <div class="pb-5">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-900">
                                    {{ mut.team?.name ?? 'Tim tidak diketahui' }}
                                </span>
                                <span
                                    v-if="mut.ended_at === null"
                                    class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700"
                                >
                                    Saat ini
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-gray-400">
                                {{ formatDate(mut.started_at) }}
                                <template v-if="mut.ended_at"> — {{ formatDate(mut.ended_at) }}</template>
                                <template v-else> — Sekarang</template>
                            </p>
                            <p v-if="mut.notes" class="mt-1 text-xs text-gray-500 italic">{{ mut.notes }}</p>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">Belum ada riwayat mutasi.</p>
            </div>
        </div>

        <!-- Education history (full width) -->
        <div class="mt-6 rounded-lg border bg-white p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">Riwayat Pendidikan</h2>
                <Button size="sm" @click="openAddEdu">+ Tambah Pendidikan</Button>
            </div>

            <!-- Add/Edit form -->
            <div v-if="showEduForm" class="mb-5 rounded-md border border-blue-100 bg-blue-50 p-4">
                <h3 class="mb-3 text-sm font-semibold text-blue-900">{{ editingEduId ? 'Edit Pendidikan' : 'Tambah Pendidikan' }}</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <Label>Gelar Depan</Label>
                        <Input v-model="eduForm.degree_front" placeholder="Dr., Prof., dll." class="mt-1 bg-white" />
                        <InputError :message="eduForm.errors.degree_front" />
                    </div>
                    <div>
                        <Label>Gelar Belakang</Label>
                        <Input v-model="eduForm.degree_back" placeholder="S.T., M.T., dll." class="mt-1 bg-white" />
                        <InputError :message="eduForm.errors.degree_back" />
                    </div>
                    <div>
                        <Label>Tahun Lulus</Label>
                        <Input v-model="eduForm.graduated_year" type="number" placeholder="2020" class="mt-1 bg-white" />
                        <InputError :message="eduForm.errors.graduated_year" />
                    </div>
                    <div class="sm:col-span-2">
                        <Label>Institusi <span class="text-red-500">*</span></Label>
                        <Input v-model="eduForm.institution" placeholder="Nama universitas/perguruan tinggi" class="mt-1 bg-white" />
                        <InputError :message="eduForm.errors.institution" />
                    </div>
                    <div>
                        <Label>Bidang Studi</Label>
                        <Input v-model="eduForm.field_of_study" placeholder="Statistika, Matematika, dll." class="mt-1 bg-white" />
                        <InputError :message="eduForm.errors.field_of_study" />
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <Checkbox id="is_highest" :model-value="eduForm.is_highest" @update:model-value="eduForm.is_highest = !!$event" />
                    <Label for="is_highest" class="cursor-pointer text-sm">Ini adalah pendidikan tertinggi</Label>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" size="sm" @click="showEduForm = false; eduForm.reset()">Batal</Button>
                    <Button size="sm" :disabled="eduForm.processing" @click="submitEdu">{{ editingEduId ? 'Perbarui' : 'Simpan' }}</Button>
                </div>
            </div>

            <!-- Education list -->
            <div v-if="employee.educations.length" class="divide-y">
                <div v-for="edu in employee.educations" :key="edu.id" class="flex items-start justify-between gap-4 py-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-gray-900 text-sm">{{ edu.institution }}</span>
                            <span v-if="edu.is_highest" class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">Tertinggi</span>
                        </div>
                        <p class="mt-0.5 text-sm text-gray-600">
                            <template v-if="edu.field_of_study">{{ edu.field_of_study }}</template>
                            <template v-if="edu.field_of_study && (edu.degree_front || edu.degree_back)"> · </template>
                            <template v-if="edu.degree_front || edu.degree_back">
                                <span class="text-gray-500">{{ [edu.degree_front, edu.degree_back].filter(Boolean).join(', ') }}</span>
                            </template>
                        </p>
                        <p v-if="edu.graduated_year" class="mt-0.5 text-xs text-gray-400">Lulus {{ edu.graduated_year }}</p>
                    </div>
                    <div class="flex shrink-0 gap-1.5">
                        <Button size="sm" variant="ghost" class="h-7 px-2 text-xs" @click="openEditEdu(edu)">Edit</Button>
                        <Button size="sm" variant="ghost" class="h-7 px-2 text-xs text-red-500 hover:text-red-600" @click="confirmDeleteEdu(edu.id)">Hapus</Button>
                    </div>
                </div>
            </div>
            <p v-else class="text-sm text-gray-400">Belum ada riwayat pendidikan.</p>
        </div>
        <ConfirmDialog
            v-model:open="deleteEduOpen"
            title="Hapus Pendidikan"
            description="Riwayat pendidikan ini akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan."
            confirm-label="Hapus"
            @confirm="executeDeleteEdu"
        />
    </AppLayout>
</template>
