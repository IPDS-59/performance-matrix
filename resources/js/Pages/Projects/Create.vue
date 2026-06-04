<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { Employee, Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Check, ChevronsUpDown, X } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { ref, computed, watch } from 'vue';

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
    isAdmin: boolean;
}>();

const leaderOpen = ref(false);
const memberSearch = ref('');
const memberPickerOpen = ref(false);

const initialTeamId = props.isAdmin ? null as number | null : (props.teams[0]?.id ?? null) as number | null;

const form = useForm({
    team_id: initialTeamId,
    leader_id: null as number | null,
    name: '',
    description: '',
    objective: '',
    kpi: '',
    status: 'active',
    year: new Date().getFullYear(),
    members: [] as Array<{ employee_id: number; role: 'leader' | 'member' }>,
});

// Employees currently added as members
const memberEmployees = computed(() =>
    form.members
        .map(m => props.employees.find(e => e.id === m.employee_id))
        .filter((e): e is Employee => e !== undefined)
);

// Employees not yet added (for the picker)
const memberAddableEmployees = computed(() => {
    const addedIds = new Set(form.members.map(m => m.employee_id));
    const q = memberSearch.value.trim().toLowerCase();
    return props.employees.filter(e => {
        if (addedIds.has(e.id)) return false;
        if (!q) return true;
        return (e.display_name || e.name).toLowerCase().includes(q);
    });
});

const selectedLeaderLabel = computed(() => {
    if (form.leader_id === null) return '— Belum ditentukan —';
    const emp = memberEmployees.value.find(e => e.id === form.leader_id);
    return emp ? (emp.display_name || emp.name) : '— Belum ditentukan —';
});

function addMember(emp: Employee) {
    if (!form.members.find(m => m.employee_id === emp.id)) {
        form.members.push({ employee_id: emp.id, role: 'member' });
    }
    memberSearch.value = '';
}

function removeMember(employeeId: number) {
    form.members = form.members.filter(m => m.employee_id !== employeeId);
    if (form.leader_id === employeeId) {
        form.leader_id = null;
    }
}

function memberLabel(employeeId: number): string {
    const emp = props.employees.find(e => e.id === employeeId);
    return emp ? (emp.display_name || emp.name) : `#${employeeId}`;
}

// For non-admin: when selected team changes, ensure the team's leader is auto-added
function applyTeamLeader(teamId: number | null) {
    if (props.isAdmin || teamId === null) return;
    const team = props.teams.find(t => t.id === teamId);
    if (!team?.leader_id) return;
    const leaderId = team.leader_id;
    // Auto-add the team leader as a member with role leader
    if (!form.members.find(m => m.employee_id === leaderId)) {
        form.members.push({ employee_id: leaderId, role: 'leader' });
    } else {
        // Update role if already present
        const existing = form.members.find(m => m.employee_id === leaderId);
        if (existing) existing.role = 'leader';
    }
    form.leader_id = leaderId;
}

// Initialize for non-admin on mount
if (!props.isAdmin) {
    applyTeamLeader(form.team_id);
}

watch(() => form.team_id, (newTeamId) => {
    if (!props.isAdmin) {
        // Remove the previous team's leader auto-membership before applying new one
        form.members = [];
        form.leader_id = null;
        applyTeamLeader(newTeamId);
    }
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
                        <!-- Admin: team picker; Lead: select from led teams -->
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
                    <!-- Members picker (all users, cross-team allowed) -->
                    <div>
                        <Label>Anggota Proyek</Label>
                        <Popover v-model:open="memberPickerOpen">
                            <PopoverTrigger as-child>
                                <Button variant="outline" role="combobox" class="mt-1 w-full justify-between font-normal">
                                    {{ form.members.length ? `${form.members.length} anggota dipilih` : 'Tambah anggota...' }}
                                    <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-[--radix-popover-trigger-width] p-0">
                                <Command>
                                    <CommandInput v-model="memberSearch" placeholder="Cari pegawai..." />
                                    <CommandList>
                                        <CommandEmpty>Tidak ada hasil.</CommandEmpty>
                                        <CommandGroup>
                                            <CommandItem
                                                v-for="emp in memberAddableEmployees"
                                                :key="emp.id"
                                                :value="emp.display_name || emp.name"
                                                @select="() => { addMember(emp); memberPickerOpen = false }"
                                            >
                                                {{ emp.display_name || emp.name }}
                                            </CommandItem>
                                        </CommandGroup>
                                    </CommandList>
                                </Command>
                            </PopoverContent>
                        </Popover>
                        <InputError :message="form.errors.members" />
                        <!-- Selected members list -->
                        <div v-if="form.members.length" class="mt-2 space-y-1">
                            <div
                                v-for="m in form.members"
                                :key="m.employee_id"
                                class="flex items-center justify-between rounded-md border border-gray-100 bg-gray-50 px-3 py-1.5 text-sm"
                            >
                                <span class="truncate">{{ memberLabel(m.employee_id) }}</span>
                                <button
                                    type="button"
                                    class="ml-2 shrink-0 text-gray-400 hover:text-red-500"
                                    @click="removeMember(m.employee_id)"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Ketua Proyek picker — options restricted to selected members -->
                    <div>
                        <Label>Ketua Proyek</Label>
                        <Popover v-model:open="leaderOpen">
                            <PopoverTrigger as-child>
                                <Button variant="outline" role="combobox" class="mt-1 w-full justify-between font-normal">
                                    {{ selectedLeaderLabel }}
                                    <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-[--radix-popover-trigger-width] p-0">
                                <Command>
                                    <CommandInput placeholder="Cari anggota..." />
                                    <CommandList>
                                        <CommandEmpty>Tidak ada hasil.</CommandEmpty>
                                        <CommandGroup>
                                            <CommandItem value="__none__" @select="() => { form.leader_id = null; leaderOpen = false }">
                                                — Belum ditentukan —
                                                <Check v-if="form.leader_id === null" class="ml-auto h-4 w-4" />
                                            </CommandItem>
                                            <CommandItem
                                                v-for="emp in memberEmployees"
                                                :key="emp.id"
                                                :value="emp.display_name || emp.name"
                                                @select="() => { form.leader_id = emp.id; leaderOpen = false }"
                                            >
                                                {{ emp.display_name || emp.name }}
                                                <Check v-if="form.leader_id === emp.id" class="ml-auto h-4 w-4" />
                                            </CommandItem>
                                        </CommandGroup>
                                    </CommandList>
                                </Command>
                            </PopoverContent>
                        </Popover>
                        <InputError :message="form.errors.leader_id" />
                    </div>
                    <div>
                        <Label for="kpi">IKU (Indikator Kinerja Utama)</Label>
                        <Textarea id="kpi" v-model="form.kpi" rows="2" class="mt-1" />
                        <InputError :message="form.errors.kpi" />
                    </div>
                    <div>
                        <Label for="objective">Tujuan</Label>
                        <Textarea id="objective" v-model="form.objective" rows="2" class="mt-1" />
                        <InputError :message="form.errors.objective" />
                    </div>
                    <div v-if="isAdmin">
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
                    :placeholder="isAdmin ? 'Cari nama proyek atau tim...' : 'Cari nama proyek...'"
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
