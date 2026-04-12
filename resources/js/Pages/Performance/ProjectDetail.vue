<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import InputError from '@/Components/InputError.vue';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog';

// ── Types ──────────────────────────────────────────────────────────────────

interface ProjectData {
    id: number;
    name: string;
    year: number;
    leader_id: number | null;
    team: { id: number; name: string } | null;
    leader: { id: number; name: string } | null;
}

interface Member {
    id: number;
    name: string;
}

interface EmployeeWorkItem {
    id: number;
    number: number;
    description: string;
    target: number;
    target_unit: string;
    year_realization: number;
    year_pct: number;
    report_count: number;
    has_pending: boolean;
    has_rejected: boolean;
    all_approved: boolean;
}

interface AssignedMember {
    employee_id: number;
    name: string;
    target: number;
    target_unit: string;
}

interface LeadWorkItem {
    id: number;
    number: number;
    description: string;
    target: number;
    target_unit: string;
    assigned_members: AssignedMember[];
    pending_count: number;
    approved_count: number;
    rejected_count: number;
    total_report_count: number;
}

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    project: ProjectData;
    work_items: EmployeeWorkItem[] | LeadWorkItem[];
    is_lead: boolean;
    year: number;
    members: Member[];
    next_number: number | null;
}>();

// ── Helpers ────────────────────────────────────────────────────────────────

function progressBarColor(pct: number): string {
    if (pct >= 80) return 'bg-green-500';
    if (pct >= 50) return 'bg-yellow-400';
    return 'bg-red-400';
}

function pctTextColor(pct: number): string {
    if (pct >= 80) return 'text-green-600';
    if (pct >= 50) return 'text-yellow-600';
    return 'text-red-500';
}

const employeeItems = () => props.work_items as EmployeeWorkItem[];
const leadItems = () => props.work_items as LeadWorkItem[];

function memberName(id: number): string {
    return props.members.find(m => m.id === id)?.name ?? '—';
}

// ── Add work item dialog ───────────────────────────────────────────────────

type AssignmentRow = { employee_id: number; target: number; target_unit: string };

const showAddDialog = ref(false);
const addMemberSearch = ref('');
const addAssignTo = ref<'all' | 'specific'>('all');
const addIncludedIds = reactive(new Set<number>());

const addForm = useForm({
    number: props.next_number ?? 1,
    description: '',
    target: 1 as number,
    target_unit: 'Kegiatan',
    assignments: [] as AssignmentRow[],
});

const addCheckedMap = computed<Record<number, boolean>>(() => {
    const m: Record<number, boolean> = {};
    addIncludedIds.forEach(id => { m[id] = true; });
    return m;
});

const filteredAddIndices = computed(() => {
    const q = addMemberSearch.value.toLowerCase();
    return addForm.assignments.reduce<number[]>((acc, row, idx) => {
        if (!q || memberName(row.employee_id).toLowerCase().includes(q)) acc.push(idx);
        return acc;
    }, []);
});

function openAddDialog() {
    addForm.reset();
    addForm.number = props.next_number ?? 1;
    addForm.target = 1;
    addForm.target_unit = 'Kegiatan';
    addAssignTo.value = 'all';
    addForm.assignments = props.members.map(m => ({
        employee_id: m.id,
        target: 1,
        target_unit: 'Kegiatan',
    }));
    addIncludedIds.clear();
    addMemberSearch.value = '';
    showAddDialog.value = true;
}

function toggleAddIncluded(employeeId: number) {
    if (addIncludedIds.has(employeeId)) addIncludedIds.delete(employeeId);
    else addIncludedIds.add(employeeId);
}

function submitAdd() {
    const assignments = addAssignTo.value === 'specific'
        ? addForm.assignments
            .filter(a => addIncludedIds.has(a.employee_id))
            .map(a => ({ employee_id: a.employee_id, target: a.target, target_unit: a.target_unit }))
        : [];
    addForm
        .transform((data: Record<string, unknown>) => ({ ...data, assign_to: addAssignTo.value, assignments }))
        .post(route('work-items.store', props.project.id), {
            preserveScroll: true,
            onSuccess: () => { showAddDialog.value = false; },
        });
}
</script>

<template>
    <Head :title="`${project.name} — Kinerja`" />
    <AppLayout>
        <template #title>
            <nav class="flex items-center gap-1.5 text-sm text-gray-500">
                <a :href="route('performance.index')" class="hover:text-gray-800">Kinerja</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-800 font-medium">{{ project.name }}</span>
            </nav>
        </template>

        <!-- Project header -->
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <Badge variant="outline" class="text-xs font-normal text-gray-500">
                        {{ project.team?.name ?? '—' }}
                    </Badge>
                    <span class="text-xs text-gray-400">·</span>
                    <span class="text-xs text-gray-500">{{ project.year }}</span>
                    <span v-if="is_lead" class="text-xs text-gray-400">·</span>
                    <Badge v-if="is_lead" class="border-blue-200 bg-blue-50 text-blue-700 text-xs font-normal">
                        Ketua Tim
                    </Badge>
                </div>
                <h2 class="mt-1 text-lg font-semibold text-gray-800">{{ project.name }}</h2>
                <p v-if="project.leader && !is_lead" class="mt-0.5 text-xs text-gray-500">
                    Ketua: {{ project.leader.name }}
                </p>
            </div>
            <Button v-if="is_lead" size="sm" @click="openAddDialog">+ Tambah Rincian</Button>
        </div>

        <!-- Empty state -->
        <div v-if="!work_items.length" class="py-16 text-center text-gray-400">
            <p class="font-medium">Belum ada rincian kegiatan.</p>
            <p v-if="is_lead" class="mt-1 text-sm">
                Klik <button class="text-blue-500 hover:underline" @click="openAddDialog">+ Tambah Rincian</button> untuk menambahkan.
            </p>
            <p v-else class="mt-1 text-sm">Anda belum ditugaskan ke rincian kegiatan manapun di proyek ini.</p>
        </div>

        <!-- ── Employee view ──────────────────────────────────────────────── -->
        <template v-else-if="!is_lead">
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left w-10">#</th>
                            <th class="px-4 py-3 text-left">Rincian Kegiatan</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Target</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap hidden sm:table-cell">Realisasi</th>
                            <th class="px-4 py-3 text-center whitespace-nowrap hidden md:table-cell">Progress</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="wi in employeeItems()" :key="wi.id" class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ wi.number }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800 leading-snug">{{ wi.description }}</p>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">
                                {{ Number(wi.target).toLocaleString('id') }} {{ wi.target_unit }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap hidden sm:table-cell">
                                {{ Number(wi.year_realization).toLocaleString('id') }} {{ wi.target_unit }}
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-1.5 rounded-full bg-gray-200 overflow-hidden">
                                        <div :class="['h-full rounded-full transition-all', progressBarColor(wi.year_pct)]" :style="`width: ${wi.year_pct}%`" />
                                    </div>
                                    <span :class="['text-xs font-medium w-10 text-right shrink-0', pctTextColor(wi.year_pct)]">{{ wi.year_pct }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="wi.all_approved" class="inline-flex items-center rounded border border-green-200 bg-green-50 px-2 py-0.5 text-[10px] font-medium text-green-700">Disetujui</span>
                                <span v-else-if="wi.has_rejected" class="inline-flex items-center rounded border border-red-200 bg-red-50 px-2 py-0.5 text-[10px] font-medium text-red-700">Ditolak</span>
                                <span v-else-if="wi.has_pending" class="inline-flex items-center rounded border border-yellow-200 bg-yellow-50 px-2 py-0.5 text-[10px] font-medium text-yellow-700">Menunggu</span>
                                <span v-else-if="wi.report_count === 0" class="inline-flex items-center rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] font-medium text-gray-500">Belum lapor</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a :href="route('performance.work-items.show', wi.id)" class="inline-flex items-center rounded bg-blue-600 px-2.5 py-1 text-[11px] font-medium text-white hover:bg-blue-700 transition">Laporan</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- ── Lead view ─────────────────────────────────────────────────── -->
        <template v-else>
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left w-10">#</th>
                            <th class="px-4 py-3 text-left">Rincian Kegiatan</th>
                            <th class="px-4 py-3 text-left hidden lg:table-cell">Anggota</th>
                            <th class="px-4 py-3 text-center whitespace-nowrap">Laporan</th>
                            <th class="px-4 py-3 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="wi in leadItems()" :key="wi.id" class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ wi.number }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800 leading-snug">{{ wi.description }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">Target: {{ Number(wi.target).toLocaleString('id') }} {{ wi.target_unit }}</p>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="m in wi.assigned_members" :key="m.employee_id" class="inline-flex items-center rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[10px] text-gray-600">{{ m.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-center gap-1">
                                    <span v-if="wi.pending_count > 0" class="inline-flex items-center rounded border border-yellow-200 bg-yellow-50 px-1.5 py-0.5 text-[10px] font-medium text-yellow-700">{{ wi.pending_count }} menunggu</span>
                                    <span v-if="wi.rejected_count > 0" class="inline-flex items-center rounded border border-red-200 bg-red-50 px-1.5 py-0.5 text-[10px] font-medium text-red-700">{{ wi.rejected_count }} ditolak</span>
                                    <span v-if="wi.approved_count > 0" class="inline-flex items-center rounded border border-green-200 bg-green-50 px-1.5 py-0.5 text-[10px] font-medium text-green-700">{{ wi.approved_count }} disetujui</span>
                                    <span v-if="wi.total_report_count === 0" class="inline-flex items-center rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[10px] text-gray-400">Belum ada laporan</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a :href="route('performance.work-items.show', wi.id)" class="inline-flex items-center rounded bg-blue-600 px-2.5 py-1 text-[11px] font-medium text-white hover:bg-blue-700 transition">Tinjau</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </AppLayout>

    <!-- Add work item dialog -->
    <Dialog :open="showAddDialog" @update:open="showAddDialog = $event">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Tambah Rincian Kegiatan</DialogTitle>
            </DialogHeader>

            <div class="space-y-3 pt-1">
                <!-- Number + description -->
                <div class="grid grid-cols-4 gap-3">
                    <div class="col-span-1">
                        <Label class="text-xs">No.</Label>
                        <Input type="number" min="1" v-model="addForm.number" class="mt-1" />
                        <InputError :message="addForm.errors.number" />
                    </div>
                    <div class="col-span-3">
                        <Label class="text-xs">Deskripsi <span class="text-red-500">*</span></Label>
                        <textarea
                            v-model="addForm.description"
                            rows="2"
                            class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                            placeholder="Deskripsi kegiatan..."
                        />
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

                <!-- Assignment panel -->
                <Transition
                    mode="out-in"
                    enter-from-class="opacity-0 -translate-y-1"
                    enter-active-class="transition-all duration-200 ease-out"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <!-- All: single shared target -->
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

                    <!-- Specific: per-member rows -->
                    <div v-else key="specific" class="space-y-2">
                        <p v-if="!members.length" class="text-xs text-gray-400">Belum ada anggota di proyek ini.</p>
                        <template v-else>
                            <div class="relative">
                                <svg class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 pointer-events-none text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                                </svg>
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

                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" size="sm" @click="showAddDialog = false">Batal</Button>
                    <Button size="sm" :disabled="addForm.processing" @click="submitAdd">
                        {{ addForm.processing ? 'Menyimpan...' : 'Simpan' }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
