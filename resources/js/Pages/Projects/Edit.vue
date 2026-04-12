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

interface WorkItem {
    id: number;
    number: number;
    description: string;
    target: number;
    target_unit: string;
}

const props = defineProps<{
    project: Project & { work_items?: WorkItem[] };
    teams: Team[];
    employees: Employee[];
    isLeader?: boolean;
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

// ── Work items ────────────────────────────────────────────────────────────

const workItems = computed(() => (props.project.work_items ?? []).slice().sort((a, b) => a.number - b.number));

const nextNumber = computed(() =>
    workItems.value.length ? Math.max(...workItems.value.map(w => w.number)) + 1 : 1
);

const showAddForm = ref(false);
const addForm = useForm({
    number: nextNumber.value,
    description: '',
    target: 1,
    target_unit: 'Kegiatan',
});

function openAddForm() {
    addForm.number = nextNumber.value;
    addForm.description = '';
    addForm.target = 1;
    addForm.target_unit = 'Kegiatan';
    showAddForm.value = true;
}

function storeItem() {
    addForm.post(route('work-items.store', props.project.id), {
        preserveScroll: true,
        onSuccess: () => { showAddForm.value = false; },
    });
}

// Inline edit state per item
const editingId = ref<number | null>(null);
const editForms = ref<Record<number, ReturnType<typeof useForm>>>({});

function startEdit(item: WorkItem) {
    editForms.value[item.id] = useForm({
        description: item.description,
        target: item.target,
        target_unit: item.target_unit,
    });
    editingId.value = item.id;
}

function cancelEdit() {
    editingId.value = null;
}

function saveEdit(itemId: number) {
    editForms.value[itemId].put(route('work-items.update', itemId), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
}

function deleteItem(itemId: number) {
    router.delete(route('work-items.destroy', itemId), { preserveScroll: true });
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

            <!-- Work items management -->
            <div class="bg-white rounded-md border p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-800">Rincian Kegiatan</h2>
                    <Button v-if="!showAddForm" size="sm" @click="openAddForm">+ Tambah</Button>
                </div>

                <!-- Empty state -->
                <p v-if="!workItems.length && !showAddForm" class="text-sm text-gray-400">
                    Belum ada rincian kegiatan. Klik "+ Tambah" untuk menambahkan.
                </p>

                <!-- Existing items -->
                <div class="space-y-3">
                    <div
                        v-for="item in workItems"
                        :key="item.id"
                        class="rounded-md border border-gray-100 bg-gray-50 p-3"
                    >
                        <!-- View mode -->
                        <template v-if="editingId !== item.id">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 shrink-0 text-xs font-semibold text-gray-400">{{ item.number }}.</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-gray-700">{{ item.description }}</p>
                                    <p class="mt-0.5 text-xs text-gray-400">
                                        Target: {{ item.target }} {{ item.target_unit }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <Button size="sm" variant="outline" class="h-7 px-2 text-xs" @click="startEdit(item)">
                                        Edit
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="h-7 px-2 text-xs text-red-600 hover:bg-red-50 hover:text-red-700"
                                        @click="deleteItem(item.id)"
                                    >
                                        Hapus
                                    </Button>
                                </div>
                            </div>
                        </template>

                        <!-- Edit mode -->
                        <template v-else>
                            <div class="space-y-3">
                                <div>
                                    <Label class="text-xs">Deskripsi</Label>
                                    <textarea
                                        v-model="editForms[item.id].description"
                                        rows="2"
                                        class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                    />
                                    <InputError :message="editForms[item.id].errors.description" />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <Label class="text-xs">Target</Label>
                                        <Input type="number" min="0.01" step="0.01" v-model="editForms[item.id].target" class="mt-1" />
                                        <InputError :message="editForms[item.id].errors.target" />
                                    </div>
                                    <div>
                                        <Label class="text-xs">Satuan</Label>
                                        <Input v-model="editForms[item.id].target_unit" class="mt-1" placeholder="Kegiatan" />
                                        <InputError :message="editForms[item.id].errors.target_unit" />
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" class="h-7 px-3 text-xs" @click="cancelEdit">Batal</Button>
                                    <Button
                                        size="sm"
                                        class="h-7 px-3 text-xs"
                                        :disabled="editForms[item.id].processing"
                                        @click="saveEdit(item.id)"
                                    >
                                        Simpan
                                    </Button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Add new item form -->
                <div v-if="showAddForm" class="mt-3 rounded-md border border-blue-100 bg-blue-50 p-4">
                    <p class="mb-3 text-sm font-medium text-blue-800">Tambah Rincian Kegiatan</p>
                    <div class="space-y-3">
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
                                    class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                    placeholder="Deskripsi kegiatan..."
                                />
                                <InputError :message="addForm.errors.description" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label class="text-xs">Target <span class="text-red-500">*</span></Label>
                                <Input type="number" min="0.01" step="0.01" v-model="addForm.target" class="mt-1" />
                                <InputError :message="addForm.errors.target" />
                            </div>
                            <div>
                                <Label class="text-xs">Satuan</Label>
                                <Input v-model="addForm.target_unit" class="mt-1" placeholder="Kegiatan" />
                                <InputError :message="addForm.errors.target_unit" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button size="sm" variant="outline" class="h-7 px-3 text-xs" @click="showAddForm = false">Batal</Button>
                            <Button size="sm" class="h-7 px-3 text-xs" :disabled="addForm.processing" @click="storeItem">
                                Tambah
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
