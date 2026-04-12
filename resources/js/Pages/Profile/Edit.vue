<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import { Head } from '@inertiajs/vue3';
import type { Employee } from '@/types';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
    employee?: Employee | null;
}>();
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

            <!-- Profile information -->
            <div class="rounded-lg border bg-white p-6">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
            </div>

            <!-- Password -->
            <div class="rounded-lg border bg-white p-6">
                <UpdatePasswordForm />
            </div>

            <!-- Danger zone -->
            <div class="rounded-lg border bg-white p-6">
                <DeleteUserForm />
            </div>
        </div>
    </AppLayout>
</template>
