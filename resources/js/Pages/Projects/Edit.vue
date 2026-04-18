<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import type { Employee, Project, Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { computed, reactive, ref } from 'vue';
import { Checkbox } from '@/Components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';

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

// ── Leader combobox ───────────────────────────────────────────────────────

const leaderOpen = ref(false);

// ── Project form ─────────────────────────────────────────────────────────

const initialMembers = computed(() =>
    (props.project.members ?? []).map((m) => ({
        employee_id: m.id,
        role: m.pivot?.role ?? 'member',
    }))
);

const selectedLeaderLabel = computed(() => {
    if (form.leader_id === null) return '— Belum ditentukan —';
    const emp = props.employees.find(e => e.id === form.leader_id);
    return emp ? (emp.display_name || emp.name) : '— Belum ditentukan —';
});

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

type AssignmentRow = { employee_id: number; target: number; target_unit: string };

const addMemberSearch = ref('');
const editMemberSearch = ref('');

// reactive(Set) lets Vue track .has()/.add()/.delete() — ref(Set) does NOT.
const addIncludedIds = reactive(new Set<number>());
// Outer record + each inner Set are both reactive.
const editIncludedIdsMap = reactive<Record<number, Set<number>>>({});

// Computed maps for template bindings — avoids Set.has() tracking edge-cases.
const addCheckedMap = computed<Record<number, boolean>>(() => {
    const m: Record<number, boolean> = {};
    addIncludedIds.forEach(id => { m[id] = true; });
    return m;
});
const editCheckedMap = computed<Record<number, Record<number, boolean>>>(() => {
    const out: Record<number, Record<number, boolean>> = {};
    for (const [itemId, set] of Object.entries(editIncludedIdsMap)) {
        const m: Record<number, boolean> = {};
        (set as Set<number>).forEach((id: number) => { m[id] = true; });
        out[Number(itemId)] = m;
    }
    return out;
});

const addAssignTo = ref<'all' | 'specific'>('all');
const editAssignToMap = ref<Record<number, 'all' | 'specific'>>({});

const addForm = useForm({
    number: nextNumber.value,
    description: '',
    target: 1 as number,
    target_unit: 'Kegiatan',
    assignments: [] as AssignmentRow[],
});

const filteredAddIndices = computed(() => {
    const q = addMemberSearch.value.toLowerCase();
    return addForm.assignments.reduce<number[]>((acc, row, idx) => {
        if (!q || memberName(row.employee_id).toLowerCase().includes(q)) acc.push(idx);
        return acc;
    }, []);
});
const filteredEditIndices = computed(() => {
    const q = editMemberSearch.value.toLowerCase();
    const form = editingId.value !== null ? editForms.value[editingId.value] : null;
    if (!form) return [];
    return (form.assignments as AssignmentRow[]).reduce<number[]>((acc, row, idx) => {
        if (!q || memberName(row.employee_id).toLowerCase().includes(q)) acc.push(idx);
        return acc;
    }, []);
});

function openAddForm() {
    addForm.number = nextNumber.value;
    addForm.description = '';
    addForm.target = 1;
    addForm.target_unit = 'Kegiatan';
    addAssignTo.value = 'all';
    addForm.assignments = projectMembers.value.map(m => ({
        employee_id: m.id,
        target: 1,
        target_unit: 'Kegiatan',
    }));
    addIncludedIds.clear();
    for (const m of projectMembers.value) addIncludedIds.add(m.id);
    addForm.clearErrors();
    addMemberSearch.value = '';
    showAddForm.value = true;
}

function toggleAddIncluded(employeeId: number) {
    if (addIncludedIds.has(employeeId)) addIncludedIds.delete(employeeId);
    else addIncludedIds.add(employeeId);
}

function storeItem() {
    const assignTo = addAssignTo.value;
    const assignments = assignTo === 'specific'
        ? addForm.assignments
            .filter(a => addIncludedIds.has(a.employee_id))
            .map(a => ({ employee_id: a.employee_id, target: a.target, target_unit: a.target_unit }))
        : [];
    addForm
        .transform((data: Record<string, unknown>) => ({ ...data, assign_to: assignTo, assignments }))
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

    const assignments: AssignmentRow[] = projectMembers.value.map(m => {
        const existing = item.assignments.find(a => a.employee_id === m.id);
        return {
            employee_id: m.id,
            target: existing?.target ?? item.target,
            target_unit: existing?.target_unit ?? item.target_unit,
        };
    });

    const includedIds = !hasSpecific ? projectMembers.value.map(m => m.id) : item.assignments.map(a => a.employee_id);
    editIncludedIdsMap[item.id] = reactive(new Set(includedIds));

    editAssignToMap.value = {
        ...editAssignToMap.value,
        [item.id]: hasSpecific ? 'specific' : 'all',
    };

    editForms.value[item.id] = useForm({
        description: item.description,
        target: item.target,
        target_unit: item.target_unit,
        assignments,
    });
    editMemberSearch.value = '';
    editingId.value = item.id;
}

function cancelEdit() {
    editingId.value = null;
}

function toggleEditIncluded(itemId: number, employeeId: number) {
    const set = editIncludedIdsMap[itemId];
    if (!set) return;
    if (set.has(employeeId)) set.delete(employeeId);
    else set.add(employeeId);
}

function saveEdit(itemId: number) {
    const f = editForms.value[itemId];
    const assignTo = editAssignToMap.value[itemId] ?? 'all';
    const includedSet = editIncludedIdsMap[itemId] ?? new Set<number>();
    const assignments = assignTo === 'specific'
        ? (f.assignments as AssignmentRow[])
            .filter(a => includedSet.has(a.employee_id))
            .map(a => ({ employee_id: a.employee_id, target: a.target, target_unit: a.target_unit }))
        : [];
    f.transform((data: any) => ({ ...data, assign_to: assignTo, assignments }))
        .put(route('work-items.update', itemId), {
            preserveScroll: true,
            onSuccess: () => { editingId.value = null; },
        });
}

function deleteItem(itemId: number) {
    router.delete(route('work-items.destroy', itemId), { preserveScroll: true });
}

const memberNamesMap = computed<Record<number, string>>(() => {
    const map: Record<number, string> = {};
    for (const m of projectMembers.value) map[m.id] = m.display_name || m.name;
    return map;
});

function memberName(employeeId: number): string {
    return memberNamesMap.value[employeeId] ?? `#${employeeId}`;
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
                                    <CommandInput placeholder="Cari pegawai..." />
                                    <CommandList>
                                        <CommandEmpty>Tidak ada hasil.</CommandEmpty>
                                        <CommandGroup>
                                            <CommandItem value="__none__" @select="() => { form.leader_id = null; leaderOpen = false }">
                                                — Belum ditentukan —
                                                <Check v-if="form.leader_id === null" class="ml-auto h-4 w-4" />
                                            </CommandItem>
                                            <CommandItem
                                                v-for="emp in employees"
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
                                    <RadioGroup v-model="editAssignToMap[item.id]" class="flex gap-4">
                                        <div class="flex items-center gap-1.5">
                                            <RadioGroupItem :id="`edit-all-${item.id}`" value="all" />
                                            <Label :for="`edit-all-${item.id}`" class="cursor-pointer text-xs font-normal">Semua anggota</Label>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <RadioGroupItem :id="`edit-specific-${item.id}`" value="specific" />
                                            <Label :for="`edit-specific-${item.id}`" class="cursor-pointer text-xs font-normal">Anggota tertentu</Label>
                                        </div>
                                    </RadioGroup>
                                </div>

                                <!-- Assignment panel: single target or per-member -->
                                <Transition
                                    mode="out-in"
                                    enter-from-class="opacity-0 -translate-y-1"
                                    enter-active-class="transition-all duration-200 ease-out"
                                    leave-active-class="transition-all duration-150 ease-in"
                                    leave-to-class="opacity-0 -translate-y-1"
                                >
                                    <div v-if="editAssignToMap[item.id] === 'all'" key="all" class="grid grid-cols-2 gap-3">
                                        <div>
                                            <Label class="text-xs">Target (semua)</Label>
                                            <Input type="number" min="1" step="1" v-model="editForms[item.id].target" class="mt-1" />
                                        </div>
                                        <div>
                                            <Label class="text-xs">Satuan</Label>
                                            <Input v-model="editForms[item.id].target_unit" class="mt-1" />
                                        </div>
                                    </div>
                                    <div v-else key="specific" class="space-y-2">
                                        <div class="relative">
                                            <svg class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 pointer-events-none text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                                            <input v-model="editMemberSearch" type="text" placeholder="Cari anggota..." class="w-full rounded-md border border-input bg-white py-1.5 pl-8 pr-3 text-xs focus:outline-none focus:ring-1 focus:ring-ring" />
                                        </div>
                                        <div class="relative">
                                            <div class="max-h-44 overflow-y-auto rounded-md border [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                                                <div
                                                    v-for="idx in filteredEditIndices"
                                                    :key="editForms[item.id].assignments[idx].employee_id"
                                                    :class="['flex items-center gap-2 border-b border-gray-50 px-3 py-2 last:border-b-0 transition-colors', editCheckedMap[item.id]?.[editForms[item.id].assignments[idx].employee_id] ? 'bg-primary/5' : 'bg-white hover:bg-gray-50']"
                                                >
                                                    <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2">
                                                        <Checkbox
                                                            :model-value="!!editCheckedMap[item.id]?.[editForms[item.id].assignments[idx].employee_id]"
                                                            @update:model-value="() => toggleEditIncluded(item.id, editForms[item.id].assignments[idx].employee_id)"
                                                            @click.stop
                                                            class="h-3.5 w-3.5 shrink-0"
                                                        />
                                                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary">
                                                            {{ memberName(editForms[item.id].assignments[idx].employee_id).charAt(0).toUpperCase() }}
                                                        </div>
                                                        <span class="min-w-0 flex-1 truncate text-xs text-gray-700">{{ memberName(editForms[item.id].assignments[idx].employee_id) }}</span>
                                                    </label>
                                                    <div :class="['flex shrink-0 gap-1', !editCheckedMap[item.id]?.[editForms[item.id].assignments[idx].employee_id] && 'pointer-events-none opacity-40']">
                                                        <Input type="number" min="1" step="1" v-model="editForms[item.id].assignments[idx].target" class="w-20 text-xs" />
                                                        <Input v-model="editForms[item.id].assignments[idx].target_unit" class="w-24 text-xs" />
                                                    </div>
                                                </div>
                                                <p v-if="filteredEditIndices.length === 0" class="px-3 py-4 text-center text-xs text-gray-400">Tidak ada anggota ditemukan.</p>
                                            </div>
                                            <div class="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center rounded-b-md bg-gradient-to-t from-white via-white/60 to-transparent py-1">
                                                <svg class="h-3.5 w-3.5 animate-bounce text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </Transition>

                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" class="h-7 px-3 text-xs" @click="cancelEdit">Batal</Button>
                                    <Button size="sm" class="h-7 px-3 text-xs" :disabled="editForms[item.id].processing" @click="saveEdit(item.id)">Simpan</Button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Add form -->
                <Transition
                    enter-from-class="opacity-0 -translate-y-1"
                    enter-active-class="transition-all duration-200 ease-out"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-to-class="opacity-0 -translate-y-1"
                >
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
                            <RadioGroup v-model="addAssignTo" class="flex gap-4">
                                <div class="flex items-center gap-1.5">
                                    <RadioGroupItem id="add-all" value="all" />
                                    <Label for="add-all" class="cursor-pointer text-xs font-normal">Semua anggota</Label>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <RadioGroupItem id="add-specific" value="specific" />
                                    <Label for="add-specific" class="cursor-pointer text-xs font-normal">Anggota tertentu</Label>
                                </div>
                            </RadioGroup>
                        </div>

                        <!-- Assignment panel: single target or per-member -->
                        <Transition
                            mode="out-in"
                            enter-from-class="opacity-0 -translate-y-1"
                            enter-active-class="transition-all duration-200 ease-out"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-to-class="opacity-0 -translate-y-1"
                        >
                            <div v-if="addAssignTo === 'all'" key="all" class="grid grid-cols-2 gap-3">
                                <div>
                                    <Label class="text-xs">Target (semua) <span class="text-red-500">*</span></Label>
                                    <Input type="number" min="1" step="1" v-model="addForm.target" class="mt-1" />
                                    <InputError :message="addForm.errors.target" />
                                </div>
                                <div>
                                    <Label class="text-xs">Satuan</Label>
                                    <Input v-model="addForm.target_unit" class="mt-1" placeholder="Kegiatan" />
                                </div>
                            </div>
                            <div v-else key="specific" class="space-y-2">
                                <p v-if="!projectMembers.length" class="text-xs text-gray-400">Tambahkan anggota proyek terlebih dahulu.</p>
                                <template v-else>
                                    <div class="relative">
                                        <svg class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 pointer-events-none text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                                        <input v-model="addMemberSearch" type="text" placeholder="Cari anggota..." class="w-full rounded-md border border-input bg-white py-1.5 pl-8 pr-3 text-xs focus:outline-none focus:ring-1 focus:ring-ring" />
                                    </div>
                                    <div class="relative">
                                        <div class="max-h-44 overflow-y-auto rounded-md border [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                                            <div
                                                v-for="idx in filteredAddIndices"
                                                :key="addForm.assignments[idx].employee_id"
                                                :class="['flex items-center gap-2 border-b border-gray-50 px-3 py-2 last:border-b-0 transition-colors', addCheckedMap[addForm.assignments[idx].employee_id] ? 'bg-primary/5' : 'bg-white hover:bg-gray-50']"
                                            >
                                                <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2">
                                                    <Checkbox
                                                        :model-value="!!addCheckedMap[addForm.assignments[idx].employee_id]"
                                                        @update:model-value="() => toggleAddIncluded(addForm.assignments[idx].employee_id)"
                                                        @click.stop
                                                        class="h-3.5 w-3.5 shrink-0"
                                                    />
                                                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary">
                                                        {{ memberName(addForm.assignments[idx].employee_id).charAt(0).toUpperCase() }}
                                                    </div>
                                                    <span class="min-w-0 flex-1 truncate text-xs text-gray-700">{{ memberName(addForm.assignments[idx].employee_id) }}</span>
                                                </label>
                                                <div :class="['flex shrink-0 gap-1', !addCheckedMap[addForm.assignments[idx].employee_id] && 'pointer-events-none opacity-40']">
                                                    <Input type="number" min="1" step="1" v-model="addForm.assignments[idx].target" class="w-20 text-xs" />
                                                    <Input v-model="addForm.assignments[idx].target_unit" class="w-24 text-xs" />
                                                </div>
                                            </div>
                                            <p v-if="filteredAddIndices.length === 0" class="px-3 py-4 text-center text-xs text-gray-400">Tidak ada anggota ditemukan.</p>
                                        </div>
                                        <div class="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center rounded-b-md bg-gradient-to-t from-white via-white/60 to-transparent py-1">
                                            <svg class="h-3.5 w-3.5 animate-bounce text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </Transition>

                        <div class="flex justify-end gap-2">
                            <Button size="sm" variant="outline" class="h-7 px-3 text-xs" @click="showAddForm = false">Batal</Button>
                            <Button size="sm" class="h-7 px-3 text-xs" :disabled="addForm.processing" @click="storeItem">Tambah</Button>
                        </div>
                    </div>
                </div>
                </Transition>
            </div>
        </div>
    </AppLayout>
</template>

