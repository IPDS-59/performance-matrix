<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import type { Employee, Project, Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import InputError from '@/Components/InputError.vue';
import { computed, ref } from 'vue';

interface WorkItemAssignment {
    employee_id: number;
    target: number;
    target_unit: string;
}

interface WorkItem {
    id: number;
    number: number;
    description: string;
    target: number;
    target_unit: string;
    assignments: WorkItemAssignment[];
}

const props = defineProps<{
    project: Project & { work_items?: WorkItem[]; members?: Employee[] };
    teams: Team[];
    employees: Employee[];
    isLeader?: boolean;
}>();

// ── Project form ─────────────────────────────────────────────────────────

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

// ── Project members convenience list ─────────────────────────────────────

const projectMembers = computed(() => props.project.members ?? []);

// ── Work items ────────────────────────────────────────────────────────────

const workItems = computed(() =>
    (props.project.work_items ?? []).slice().sort((a, b) => a.number - b.number)
);

const nextNumber = computed(() =>
    workItems.value.length ? Math.max(...workItems.value.map(w => w.number)) + 1 : 1
);

// Add form

const showAddForm = ref(false);

type AssignmentRow = { employee_id: number; target: number; target_unit: string; _included: boolean };

const addForm = useForm({
    number: nextNumber.value,
    description: '',
    target: 1 as number,
    target_unit: 'Kegiatan',
    assign_to: 'all' as 'all' | 'specific',
    assignments: [] as AssignmentRow[],
});

function openAddForm() {
    addForm.number = nextNumber.value;
    addForm.description = '';
    addForm.target = 1;
    addForm.target_unit = 'Kegiatan';
    addForm.assign_to = 'all';
    addForm.assignments = projectMembers.value.map(m => ({
        employee_id: m.id,
        target: 1,
        target_unit: 'Kegiatan',
        _included: true,
    }));
    addForm.clearErrors();
    showAddForm.value = true;
}

function storeItem() {
    addForm
        .transform(data => ({
            ...data,
            assignments: data.assign_to === 'specific'
                ? data.assignments
                    .filter(a => a._included)
                    .map(a => ({ employee_id: a.employee_id, target: a.target, target_unit: a.target_unit }))
                : [],
        }))
        .post(route('work-items.store', props.project.id), {
            preserveScroll: true,
            onSuccess: () => { showAddForm.value = false; },
        });
}

// Edit form

const editingId = ref<number | null>(null);
const editForms = ref<Record<number, ReturnType<typeof useForm>>>({});

function startEdit(item: WorkItem) {
    const hasSpecific = item.assignments.length > 0;
    const allSameTarget = !hasSpecific ||
        (item.assignments.length === projectMembers.value.length &&
            item.assignments.every(a => a.target == item.target && a.target_unit === item.target_unit));

    const assignments: AssignmentRow[] = projectMembers.value.map(m => {
        const existing = item.assignments.find(a => a.employee_id === m.id);
        return {
            employee_id: m.id,
            target: existing?.target ?? item.target,
            target_unit: existing?.target_unit ?? item.target_unit,
            _included: !hasSpecific || !!existing,
        };
    });

    editForms.value[item.id] = useForm({
        description: item.description,
        target: item.target,
        target_unit: item.target_unit,
        assign_to: (!hasSpecific || allSameTarget) ? 'all' : 'specific' as 'all' | 'specific',
        assignments,
    });
    editingId.value = item.id;
}

function cancelEdit() {
    editingId.value = null;
}

function saveEdit(itemId: number) {
    editForms.value[itemId]
        .transform((data: any) => ({
            ...data,
            assignments: data.assign_to === 'specific'
                ? data.assignments
                    .filter((a: AssignmentRow) => a._included)
                    .map((a: AssignmentRow) => ({ employee_id: a.employee_id, target: a.target, target_unit: a.target_unit }))
                : [],
        }))
        .put(route('work-items.update', itemId), {
            preserveScroll: true,
            onSuccess: () => { editingId.value = null; },
        });
}

function deleteItem(itemId: number) {
    router.delete(route('work-items.destroy', itemId), { preserveScroll: true });
}

function memberName(employeeId: number): string {
    const m = projectMembers.value.find(m => m.id === employeeId);
    return m ? (m.display_name || m.name) : `#${employeeId}`;
}
</script>

<template>
    <Head title="Edit Proyek" />
    <AppLayout>
        <template #title>Edit Proyek</template>

        <div class="space-y-6 max-w-2xl">
            <!-- Project form -->
            <div class="bg-white rounded-md border p-6">
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

            <!-- Work items -->
            <div class="bg-white rounded-md border p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-800">Rincian Kegiatan</h2>
                    <Button v-if="!showAddForm" size="sm" @click="openAddForm">+ Tambah</Button>
                </div>

                <p v-if="!workItems.length && !showAddForm" class="text-sm text-gray-400">
                    Belum ada rincian kegiatan. Klik "+ Tambah" untuk menambahkan.
                </p>

                <div class="space-y-3">
                    <div v-for="item in workItems" :key="item.id" class="rounded-md border border-gray-100 bg-gray-50 p-3">

                        <!-- View mode -->
                        <template v-if="editingId !== item.id">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 shrink-0 text-xs font-semibold text-gray-400">{{ item.number }}.</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-gray-700">{{ item.description }}</p>
                                    <!-- Assignment summary -->
                                    <div v-if="item.assignments.length" class="mt-1.5 space-y-0.5">
                                        <div v-for="a in item.assignments" :key="a.employee_id" class="flex items-center gap-2 text-xs text-gray-500">
                                            <span class="font-medium">{{ memberName(a.employee_id) }}</span>
                                            <span>— {{ a.target }} {{ a.target_unit }}</span>
                                        </div>
                                    </div>
                                    <p v-else class="mt-1 text-xs text-gray-400">Belum ada penugasan</p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <Button size="sm" variant="outline" class="h-7 px-2 text-xs" @click="startEdit(item)">Edit</Button>
                                    <Button size="sm" variant="outline" class="h-7 px-2 text-xs text-red-600 hover:bg-red-50 hover:text-red-700" @click="deleteItem(item.id)">Hapus</Button>
                                </div>
                            </div>
                        </template>

                        <!-- Edit mode -->
                        <template v-else>
                            <div class="space-y-3">
                                <div>
                                    <Label class="text-xs">Deskripsi</Label>
                                    <textarea v-model="editForms[item.id].description" rows="2" class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                    <InputError :message="editForms[item.id].errors.description" />
                                </div>

                                <!-- Assignment mode toggle -->
                                <div class="flex items-center gap-4 text-sm">
                                    <Label class="text-xs">Ditugaskan ke:</Label>
                                    <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                                        <input type="radio" v-model="editForms[item.id].assign_to" value="all" class="accent-primary" />
                                        Semua anggota
                                    </label>
                                    <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                                        <input type="radio" v-model="editForms[item.id].assign_to" value="specific" class="accent-primary" />
                                        Anggota tertentu
                                    </label>
                                </div>

                                <!-- All members: single target -->
                                <div v-if="editForms[item.id].assign_to === 'all'" class="grid grid-cols-2 gap-3">
                                    <div>
                                        <Label class="text-xs">Target (semua)</Label>
                                        <Input type="number" min="0.01" step="0.01" v-model="editForms[item.id].target" class="mt-1" />
                                    </div>
                                    <div>
                                        <Label class="text-xs">Satuan</Label>
                                        <Input v-model="editForms[item.id].target_unit" class="mt-1" />
                                    </div>
                                </div>

                                <!-- Specific members: per-member targets -->
                                <div v-else class="space-y-2">
                                    <div v-for="(row, idx) in editForms[item.id].assignments" :key="row.employee_id" class="flex items-center gap-2">
                                        <label class="flex items-center gap-1.5 w-40 shrink-0">
                                            <input type="checkbox"
                                                v-model="editForms[item.id].assignments[idx]._included"
                                                class="accent-primary"
                                            />
                                            <span class="truncate text-xs">{{ memberName(row.employee_id) }}</span>
                                        </label>
                                        <Input type="number" min="0.01" step="0.01" v-model="editForms[item.id].assignments[idx].target" class="w-24 text-xs" />
                                        <Input v-model="editForms[item.id].assignments[idx].target_unit" class="w-28 text-xs" placeholder="Satuan" />
                                    </div>
                                </div>

                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" class="h-7 px-3 text-xs" @click="cancelEdit">Batal</Button>
                                    <Button size="sm" class="h-7 px-3 text-xs" :disabled="editForms[item.id].processing" @click="saveEdit(item.id)">Simpan</Button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Add form -->
                <div v-if="showAddForm" class="mt-3 rounded-md border border-blue-100 bg-blue-50 p-4">
                    <p class="mb-3 text-sm font-medium text-blue-800">Tambah Rincian Kegiatan</p>
                    <div class="space-y-3">
                        <div class="grid grid-cols-4 gap-3">
                            <div class="col-span-1">
                                <Label class="text-xs">No.</Label>
                                <Input type="number" min="1" v-model="addForm.number" class="mt-1" />
                            </div>
                            <div class="col-span-3">
                                <Label class="text-xs">Deskripsi <span class="text-red-500">*</span></Label>
                                <textarea v-model="addForm.description" rows="2" class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring" placeholder="Deskripsi kegiatan..." />
                                <InputError :message="addForm.errors.description" />
                            </div>
                        </div>

                        <!-- Assignment mode -->
                        <div class="flex items-center gap-4">
                            <Label class="text-xs">Ditugaskan ke:</Label>
                            <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                                <input type="radio" v-model="addForm.assign_to" value="all" class="accent-primary" />
                                Semua anggota
                            </label>
                            <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                                <input type="radio" v-model="addForm.assign_to" value="specific" class="accent-primary" />
                                Anggota tertentu
                            </label>
                        </div>

                        <!-- All: single target -->
                        <div v-if="addForm.assign_to === 'all'" class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-xs">Target (semua) <span class="text-red-500">*</span></Label>
                                <Input type="number" min="0.01" step="0.01" v-model="addForm.target" class="mt-1" />
                                <InputError :message="addForm.errors.target" />
                            </div>
                            <div>
                                <Label class="text-xs">Satuan</Label>
                                <Input v-model="addForm.target_unit" class="mt-1" placeholder="Kegiatan" />
                            </div>
                        </div>

                        <!-- Specific: per-member -->
                        <div v-else class="space-y-2">
                            <p v-if="!projectMembers.length" class="text-xs text-gray-400">Tambahkan anggota proyek terlebih dahulu.</p>
                            <div v-for="(row, idx) in addForm.assignments" :key="row.employee_id" class="flex items-center gap-2">
                                <label class="flex items-center gap-1.5 w-40 shrink-0">
                                    <input type="checkbox" v-model="addForm.assignments[idx]._included" class="accent-primary" />
                                    <span class="truncate text-xs">{{ memberName(row.employee_id) }}</span>
                                </label>
                                <Input type="number" min="0.01" step="0.01" v-model="addForm.assignments[idx].target" class="w-24 text-xs" />
                                <Input v-model="addForm.assignments[idx].target_unit" class="w-28 text-xs" placeholder="Satuan" />
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <Button size="sm" variant="outline" class="h-7 px-3 text-xs" @click="showAddForm = false">Batal</Button>
                            <Button size="sm" class="h-7 px-3 text-xs" :disabled="addForm.processing" @click="storeItem">Tambah</Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
