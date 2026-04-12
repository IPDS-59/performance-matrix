<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { Employee, Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import InputError from '@/Components/InputError.vue';
import { ref, computed } from 'vue';

interface PreviousProject {
    id: number;
    name: string;
    team_id: number;
    year: number;
    team: { id: number; name: string } | null;
}

const props = defineProps<{
    teams: Team[];
    employees: Employee[];
    previousProjects: PreviousProject[];
    copyYear: number;
}>();

const form = useForm({
    team_id: null as number | null,
    leader_id: null as number | null,
    name: '',
    description: '',
    objective: '',
    kpi: '',
    status: 'active',
    year: new Date().getFullYear(),
    members: [] as Array<{ employee_id: number; role: string }>,
});

function submit() {
    form.post(route('projects.store'));
}

// ── Copy from previous year ────────────────────────────────────────────────
const copySearch = ref('');
const copyingId = ref<number | null>(null);

const filteredPreviousProjects = computed(() => {
    const q = copySearch.value.trim().toLowerCase();
    if (!q) return props.previousProjects;
    return props.previousProjects.filter(
        (p) => p.name.toLowerCase().includes(q) || p.team?.name.toLowerCase().includes(q),
    );
});

function copyProject(project: PreviousProject) {
    if (copyingId.value !== null) return;
    copyingId.value = project.id;
    router.post(
        route('projects.copy', project.id),
        { year: form.year, copy_members: true, copy_work_items: true },
        { onFinish: () => { copyingId.value = null; } },
    );
}
</script>

<template>
    <Head title="Tambah Proyek" />
    <AppLayout>
        <template #title>Tambah Proyek</template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
            <!-- Left: create form -->
            <div class="lg:col-span-2 bg-white rounded-md border p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-900">Proyek Baru</h2>
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
                        <Label for="kpi">IKU (Indikator Kinerja Utama)</Label>
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
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>

            <!-- Right: copy from previous year -->
            <div class="bg-white rounded-md border p-6">
                <h2 class="mb-1 text-base font-semibold text-gray-900">Salin dari {{ copyYear }}</h2>
                <p class="mb-3 text-xs text-gray-500">
                    Klik proyek di bawah untuk menyalinnya ke tahun <strong>{{ form.year }}</strong> beserta anggota dan rincian kegiatannya.
                </p>

                <Input
                    v-model="copySearch"
                    placeholder="Cari nama proyek atau tim..."
                    class="mb-3"
                />

                <div v-if="filteredPreviousProjects.length" class="max-h-[460px] overflow-y-auto divide-y">
                    <div
                        v-for="project in filteredPreviousProjects"
                        :key="project.id"
                        class="flex items-start justify-between gap-2 py-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 leading-snug">{{ project.name }}</p>
                            <p v-if="project.team" class="mt-0.5 text-xs text-gray-400">{{ project.team.name }}</p>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            class="shrink-0 text-xs"
                            :disabled="copyingId !== null"
                            @click="copyProject(project)"
                        >
                            <span v-if="copyingId === project.id">Menyalin…</span>
                            <span v-else>Salin</span>
                        </Button>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">Tidak ada proyek ditemukan.</p>
            </div>
        </div>
    </AppLayout>
</template>
