<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import InputError from '@/Components/InputError.vue';
import type { Employee } from '@/types';
import { ref } from 'vue';

interface EmployeeEducation {
    id: number;
    degree_front: string | null;
    degree_back: string | null;
    institution: string;
    field_of_study: string | null;
    graduated_year: number | null;
    is_highest: boolean;
}

interface EmployeeWithEducations extends Employee {
    educations: EmployeeEducation[];
}

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
    employee?: EmployeeWithEducations | null;
}>();

// ── Education ──────────────────────────────────────────────────────────────
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

function submitEdu(employeeId: number) {
    if (editingEduId.value) {
        eduForm.put(route('employees.educations.update', { employee: employeeId, education: editingEduId.value }), {
            onSuccess: () => { showEduForm.value = false; eduForm.reset(); },
        });
    } else {
        eduForm.post(route('employees.educations.store', employeeId), {
            onSuccess: () => { showEduForm.value = false; eduForm.reset(); },
        });
    }
}

function deleteEdu(employeeId: number, eduId: number) {
    if (!confirm('Hapus riwayat pendidikan ini?')) return;
    router.delete(route('employees.educations.destroy', { employee: employeeId, education: eduId }), { preserveScroll: true });
}
</script>

<template>
    <Head title="Profil Saya" />
    <AppLayout>
        <template #title>Profil Saya</template>

        <div class="max-w-2xl space-y-5">
            <!-- Linked employee card -->
            <div v-if="employee" class="rounded-lg border bg-white p-6">
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wider text-gray-400">Data Kepegawaian</h2>
                <div class="grid grid-cols-2 gap-x-6 gap-y-3">
                    <div>
                        <p class="text-xs text-gray-400">Nama</p>
                        <p class="text-sm font-medium text-gray-900">{{ employee.display_name ?? employee.name }}</p>
                    </div>
                    <div v-if="employee.employee_number">
                        <p class="text-xs text-gray-400">NIP</p>
                        <p class="font-mono text-sm font-medium text-gray-900">{{ employee.employee_number }}</p>
                    </div>
                    <div v-if="employee.position">
                        <p class="text-xs text-gray-400">Jabatan</p>
                        <p class="text-sm font-medium text-gray-900">{{ employee.position }}</p>
                    </div>
                    <div v-if="employee.team">
                        <p class="text-xs text-gray-400">Tim Kerja</p>
                        <p class="text-sm font-medium text-gray-900">{{ employee.team.name }}</p>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-lg border border-dashed bg-white p-6 text-center text-sm text-gray-400">
                Akun ini belum dihubungkan ke data pegawai.
            </div>

            <!-- Education history (only when employee is linked) -->
            <div v-if="employee" class="rounded-lg border bg-white p-6">
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
                        <Checkbox id="is_highest_profile" :model-value="eduForm.is_highest" @update:model-value="eduForm.is_highest = !!$event" />
                        <Label for="is_highest_profile" class="cursor-pointer text-sm">Ini adalah pendidikan tertinggi</Label>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <Button variant="outline" size="sm" @click="showEduForm = false; eduForm.reset()">Batal</Button>
                        <Button size="sm" :disabled="eduForm.processing" @click="submitEdu(employee.id)">{{ editingEduId ? 'Perbarui' : 'Simpan' }}</Button>
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
                        <div class="inline-flex shrink-0 gap-1.5">
                            <Button size="sm" variant="ghost" class="h-7 px-2 text-xs" @click="openEditEdu(edu)">Edit</Button>
                            <Button size="sm" variant="ghost" class="h-7 px-2 text-xs text-red-500 hover:text-red-600" @click="deleteEdu(employee.id, edu.id)">Hapus</Button>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">Belum ada riwayat pendidikan.</p>
            </div>

            <!-- Profile information -->
            <div class="rounded-lg border bg-white p-6">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    :employee="employee"
                />
            </div>

            <!-- Password -->
            <div class="rounded-lg border bg-white p-6">
                <UpdatePasswordForm />
            </div>

            <!-- Danger zone -->
            <div class="rounded-lg border border-red-200 bg-white p-6">
                <DeleteUserForm />
            </div>
        </div>
    </AppLayout>
</template>
