<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { Employee, Project } from '@/types';
import { ref, computed } from 'vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/Components/ui/accordion';
import { Progress } from '@/Components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import InputError from '@/Components/InputError.vue';

// ── Types ──────────────────────────────────────────────────────────────────

interface WorkItemWithReports {
    id: number;
    number: number;
    description: string;
    target: number;
    target_unit: string;
    performance_reports: Array<{
        id: number;
        realization: number;
        achievement_percentage: number;
        issues: string | null;
        solutions: string | null;
        action_plan: string | null;
    }>;
}

interface ProjectWithItems extends Project {
    work_items: WorkItemWithReports[];
}

interface TeamMember extends Employee {
    pivot: { role: string };
}

interface TeamWorkItemReport {
    id: number;
    realization: number;
    achievement_percentage: number;
    reported_by: number | null;
    reporter: { id: number; name: string; display_name: string | null } | null;
}

interface TeamWorkItem {
    id: number;
    number: number;
    description: string;
    target: number;
    target_unit: string;
    performance_reports: TeamWorkItemReport[];
}

interface TeamProjectWithMembers {
    id: number;
    name: string;
    leader_id?: number | null;
    team: { id: number; name: string } | null;
    members: TeamMember[];
    work_items: TeamWorkItem[];
}

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    employee: Pick<Employee, 'id' | 'name' | 'display_name'>;
    projects: ProjectWithItems[];
    is_team_lead: boolean;
    team_projects: TeamProjectWithMembers[];
    filters: { year: number; month: number };
}>();

// ── Filters ────────────────────────────────────────────────────────────────

const year = ref(props.filters.year);
const month = ref(props.filters.month);

function applyFilters() {
    router.get(route('performance.index'), { year: year.value, month: month.value }, { preserveState: true });
}

const months = [
    { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' }, { value: 4, label: 'April' },
    { value: 5, label: 'Mei' }, { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' }, { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' }, { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' }, { value: 12, label: 'Desember' },
];

const monthLabel = computed(() => months.find(m => m.value === props.filters.month)?.label ?? '');

// ── Personal projects: group by team ──────────────────────────────────────

const projectsByTeam = computed(() => {
    const groups: Record<number, { teamId: number; teamName: string; projects: ProjectWithItems[] }> = {};
    for (const p of props.projects) {
        const tid = p.team_id;
        const tname = p.team?.name ?? 'Tim Tidak Diketahui';
        if (!groups[tid]) groups[tid] = { teamId: tid, teamName: tname, projects: [] };
        groups[tid].projects.push(p);
    }
    return Object.values(groups).sort((a, b) => a.teamName.localeCompare(b.teamName));
});

// ── Performance report form ────────────────────────────────────────────────

type ItemForm = {
    work_item_id: number;
    realization: number;
    issues: string;
    solutions: string;
    action_plan: string;
};

const form = useForm<{
    period_month: number;
    period_year: number;
    items: ItemForm[];
}>({
    period_month: props.filters.month,
    period_year: props.filters.year,
    items: [],
});

const seededIds = new Set<number>();

function getItem(workItemId: number): ItemForm {
    const existing = form.items.find(i => i.work_item_id === workItemId);
    if (existing) return existing;

    if (!seededIds.has(workItemId)) {
        seededIds.add(workItemId);
        let report: WorkItemWithReports['performance_reports'][number] | undefined;
        outer: for (const p of props.projects) {
            for (const wi of p.work_items) {
                if (wi.id === workItemId) {
                    report = wi.performance_reports[0];
                    break outer;
                }
            }
        }
        form.items.push({
            work_item_id: workItemId,
            realization: report?.realization ?? 0,
            issues: report?.issues ?? '',
            solutions: report?.solutions ?? '',
            action_plan: report?.action_plan ?? '',
        });
    }
    return form.items.find(i => i.work_item_id === workItemId)!;
}

function getWorkItem(workItemId: number): WorkItemWithReports | undefined {
    for (const p of props.projects) {
        const wi = p.work_items.find(w => w.id === workItemId);
        if (wi) return wi;
    }
}

function computePct(workItemId: number): number {
    const wi = getWorkItem(workItemId);
    if (!wi || !wi.target || Number(wi.target) <= 0) return 0;
    const item = getItem(workItemId);
    return Math.min(100, (Number(item.realization) / Number(wi.target)) * 100);
}

function progressColor(pct: number): string {
    if (pct >= 80) return '[&>div]:bg-green-500';
    if (pct >= 50) return '[&>div]:bg-yellow-500';
    return '[&>div]:bg-red-500';
}

function pctColor(pct: number): string {
    if (pct >= 80) return 'text-green-600';
    if (pct >= 50) return 'text-yellow-500';
    return 'text-red-500';
}

function projectAvgPct(project: ProjectWithItems): number {
    if (!project.work_items.length) return 0;
    const pcts = project.work_items.map(wi => computePct(wi.id));
    return pcts.reduce((s, v) => s + v, 0) / pcts.length;
}

function submit() {
    form.post(route('performance.batch'), { preserveScroll: true });
}

// ── Work item management (project leader) ─────────────────────────────────

function canManageItems(leaderId: number | null | undefined): boolean {
    return leaderId === props.employee.id;
}

function nextNumber(items: Array<{ number: number }>): number {
    return items.length ? Math.max(...items.map(w => w.number)) + 1 : 1;
}

// Add form
const addingProjectId = ref<number | null>(null);
const addForm = useForm({ number: 1, description: '', target: 1 as number, target_unit: 'Kegiatan' });

function openAdd(project: { id: number; work_items: Array<{ number: number }> }) {
    addForm.number = nextNumber(project.work_items);
    addForm.description = '';
    addForm.target = 1;
    addForm.target_unit = 'Kegiatan';
    addForm.clearErrors();
    addingProjectId.value = project.id;
}

function submitAdd(projectId: number) {
    addForm.post(route('work-items.store', projectId), {
        preserveScroll: true,
        onSuccess: () => { addingProjectId.value = null; },
    });
}

// Edit form
const editingItemId = ref<number | null>(null);
const editForm = useForm({ description: '', target: 1 as number, target_unit: 'Kegiatan' });

function openEdit(wi: { id: number; description: string; target: number; target_unit: string }) {
    editForm.description = wi.description;
    editForm.target = Number(wi.target);
    editForm.target_unit = wi.target_unit;
    editForm.clearErrors();
    editingItemId.value = wi.id;
}

function submitEdit(itemId: number) {
    editForm.put(route('work-items.update', itemId), {
        preserveScroll: true,
        onSuccess: () => { editingItemId.value = null; },
    });
}

function deleteItem(itemId: number) {
    router.delete(route('work-items.destroy', itemId), { preserveScroll: true });
}

// ── Team view helpers ──────────────────────────────────────────────────────

function isMemberLeader(member: TeamMember): boolean {
    return member.pivot.role === 'leader' || member.pivot.role === 'ketua';
}

function memberHasAnyReport(project: TeamProjectWithMembers, memberId: number): boolean {
    return project.work_items.some(wi =>
        wi.performance_reports.some(r => r.reported_by === memberId),
    );
}

function memberProgressColor(pct: number): string {
    if (pct >= 80) return '[&>div]:bg-green-500';
    if (pct >= 50) return '[&>div]:bg-yellow-500';
    return '[&>div]:bg-red-500';
}

function memberPctColor(pct: number): string {
    if (pct >= 80) return 'text-green-600';
    if (pct >= 50) return 'text-yellow-500';
    return 'text-red-500';
}

function projectSubmittedCount(project: TeamProjectWithMembers): number {
    const reportedBySet = new Set<number>();
    for (const wi of project.work_items) {
        for (const r of wi.performance_reports) {
            if (r.reported_by !== null) {
                reportedBySet.add(r.reported_by);
            }
        }
    }
    return reportedBySet.size;
}
</script>

<template>
    <Head title="Input Kinerja" />
    <AppLayout>
        <template #title>
            Input Kinerja — {{ employee.display_name || employee.name }}
        </template>

        <!-- Period filters -->
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <Select v-model="month" @update:modelValue="applyFilters">
                <SelectTrigger class="w-40">
                    <SelectValue placeholder="Bulan" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="year" @update:modelValue="applyFilters">
                <SelectTrigger class="w-28">
                    <SelectValue placeholder="Tahun" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</SelectItem>
                </SelectContent>
            </Select>
            <span class="ml-auto text-sm text-gray-500">
                Periode: <strong>{{ monthLabel }} {{ filters.year }}</strong>
            </span>
        </div>

        <!-- Tab toggle: only shown for team leads -->
        <Tabs v-if="is_team_lead" default-value="personal" class="w-full">
            <TabsList class="mb-6">
                <TabsTrigger value="personal">Kinerja Saya</TabsTrigger>
                <TabsTrigger value="team">
                    Tim Saya
                    <Badge variant="secondary" class="ml-2 text-xs">
                        {{ team_projects.length }}
                    </Badge>
                </TabsTrigger>
            </TabsList>

            <!-- ── Kinerja Saya tab ─────────────────────────────────────── -->
            <TabsContent value="personal">
                <div v-if="!projects.length" class="py-16 text-center text-gray-400">
                    <p class="font-medium">Tidak ada proyek untuk periode ini.</p>
                    <p class="mt-1 text-sm">Anda belum ditugaskan ke proyek aktif tahun {{ filters.year }}.</p>
                </div>

                <form v-else @submit.prevent="submit" class="space-y-8">
                    <div v-for="group in projectsByTeam" :key="group.teamId" class="space-y-3">
                        <div class="flex items-center gap-3">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-primary">
                                {{ group.teamName }}
                            </h2>
                            <span class="h-px flex-1 bg-primary/20"></span>
                            <Badge variant="outline" class="text-xs">{{ group.projects.length }} proyek</Badge>
                        </div>

                        <Accordion type="multiple" class="space-y-2">
                            <AccordionItem
                                v-for="project in group.projects"
                                :key="project.id"
                                :value="String(project.id)"
                                class="rounded-lg border bg-white shadow-sm"
                            >
                                <AccordionTrigger class="px-4 py-3 hover:no-underline">
                                    <div class="flex min-w-0 flex-1 items-center gap-3 pr-2">
                                        <span class="min-w-0 flex-1 truncate text-left font-medium text-gray-800">{{ project.name }}</span>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <div class="hidden w-24 sm:block">
                                                <Progress :model-value="projectAvgPct(project)" :class="['h-1.5', progressColor(projectAvgPct(project))]" />
                                            </div>
                                            <span :class="['text-sm font-bold', pctColor(projectAvgPct(project))]">
                                                {{ projectAvgPct(project).toFixed(0) }}%
                                            </span>
                                        </div>
                                    </div>
                                </AccordionTrigger>
                                <AccordionContent class="px-4 pb-4">
                                    <div class="space-y-4">
                                        <!-- Empty state -->
                                        <p v-if="!project.work_items.length" class="py-4 text-center text-sm text-gray-400">
                                            Belum ada rincian kegiatan.
                                        </p>

                                        <div
                                            v-for="wi in project.work_items"
                                            :key="wi.id"
                                            class="rounded-md border border-gray-100 bg-gray-50 p-4"
                                        >
                                            <!-- Work item title row with edit/delete -->
                                            <div v-if="editingItemId !== wi.id" class="mb-4 flex items-start gap-2">
                                                <p class="flex-1 text-sm font-semibold text-gray-700">
                                                    {{ wi.number }}. {{ wi.description }}
                                                </p>
                                                <div v-if="canManageItems(project.leader_id)" class="flex shrink-0 gap-1">
                                                    <button
                                                        type="button"
                                                        class="rounded px-2 py-0.5 text-xs text-gray-400 hover:bg-gray-200 hover:text-gray-700"
                                                        @click="openEdit(wi)"
                                                    >Edit</button>
                                                    <button
                                                        type="button"
                                                        class="rounded px-2 py-0.5 text-xs text-gray-400 hover:bg-red-50 hover:text-red-600"
                                                        @click="deleteItem(wi.id)"
                                                    >Hapus</button>
                                                </div>
                                            </div>

                                            <!-- Inline edit form -->
                                            <div v-else class="mb-4 rounded border border-blue-100 bg-blue-50 p-3">
                                                <div class="mb-2">
                                                    <Label class="text-xs">Deskripsi</Label>
                                                    <textarea v-model="editForm.description" rows="2" class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                                    <InputError :message="editForm.errors.description" />
                                                </div>
                                                <div class="mb-2 grid grid-cols-2 gap-2">
                                                    <div>
                                                        <Label class="text-xs">Target</Label>
                                                        <Input type="number" min="0.01" step="0.01" v-model="editForm.target" class="mt-1" />
                                                        <InputError :message="editForm.errors.target" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Satuan</Label>
                                                        <Input v-model="editForm.target_unit" class="mt-1" />
                                                        <InputError :message="editForm.errors.target_unit" />
                                                    </div>
                                                </div>
                                                <div class="flex justify-end gap-2">
                                                    <button type="button" class="rounded px-3 py-1 text-xs text-gray-500 hover:bg-gray-100" @click="editingItemId = null">Batal</button>
                                                    <button type="button" class="rounded bg-primary px-3 py-1 text-xs text-white hover:bg-primary/90" :disabled="editForm.processing" @click="submitEdit(wi.id)">Simpan</button>
                                                </div>
                                            </div>

                                            <!-- Progress + realization input -->
                                            <div class="mb-4 rounded-md border border-gray-200 bg-white p-3">
                                                <div class="mb-2 flex items-center justify-between">
                                                    <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Progres Capaian</span>
                                                    <span :class="['text-base font-bold', pctColor(computePct(wi.id))]">
                                                        {{ computePct(wi.id).toFixed(1) }}%
                                                    </span>
                                                </div>
                                                <Progress :model-value="computePct(wi.id)" :class="['mb-3 h-2', progressColor(computePct(wi.id))]" />
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <Label class="text-xs text-gray-500">Target ({{ wi.target_unit }})</Label>
                                                        <div class="mt-1 flex items-center gap-2">
                                                            <span class="rounded bg-gray-100 px-2.5 py-1.5 text-sm font-semibold text-gray-700">
                                                                {{ Number(wi.target).toLocaleString('id') }}
                                                            </span>
                                                            <span class="text-xs text-gray-400">{{ wi.target_unit }}</span>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs text-gray-500">Realisasi ({{ wi.target_unit }})</Label>
                                                        <div class="mt-1 flex items-center gap-2">
                                                            <Input type="number" min="0" step="0.01" v-model="getItem(wi.id).realization" class="w-28" />
                                                            <span class="text-xs text-gray-400">{{ wi.target_unit }}</span>
                                                        </div>
                                                        <InputError :message="form.errors[`items.${form.items.findIndex(i => i.work_item_id === wi.id)}.realization`]" />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="grid gap-3 sm:grid-cols-3">
                                                <div>
                                                    <Label class="text-xs text-gray-500">Kendala</Label>
                                                    <Textarea v-model="getItem(wi.id).issues" rows="3" class="mt-1 text-sm" placeholder="(opsional)" />
                                                </div>
                                                <div>
                                                    <Label class="text-xs text-gray-500">Solusi</Label>
                                                    <Textarea v-model="getItem(wi.id).solutions" rows="3" class="mt-1 text-sm" placeholder="(opsional)" />
                                                </div>
                                                <div>
                                                    <Label class="text-xs text-gray-500">Rencana Tindak Lanjut</Label>
                                                    <Textarea v-model="getItem(wi.id).action_plan" rows="3" class="mt-1 text-sm" placeholder="(opsional)" />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Add item form (project leader only) -->
                                        <template v-if="canManageItems(project.leader_id)">
                                            <div v-if="addingProjectId === project.id" class="rounded-md border border-blue-100 bg-blue-50 p-3">
                                                <p class="mb-2 text-xs font-medium text-blue-800">Tambah Rincian Kegiatan</p>
                                                <div class="mb-2 grid grid-cols-4 gap-2">
                                                    <div>
                                                        <Label class="text-xs">No.</Label>
                                                        <Input type="number" min="1" v-model="addForm.number" class="mt-1" />
                                                    </div>
                                                    <div class="col-span-3">
                                                        <Label class="text-xs">Deskripsi <span class="text-red-500">*</span></Label>
                                                        <textarea v-model="addForm.description" rows="2" placeholder="Deskripsi kegiatan..." class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                                        <InputError :message="addForm.errors.description" />
                                                    </div>
                                                </div>
                                                <div class="mb-2 grid grid-cols-2 gap-2">
                                                    <div>
                                                        <Label class="text-xs">Target <span class="text-red-500">*</span></Label>
                                                        <Input type="number" min="0.01" step="0.01" v-model="addForm.target" class="mt-1" />
                                                        <InputError :message="addForm.errors.target" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Satuan</Label>
                                                        <Input v-model="addForm.target_unit" placeholder="Kegiatan" class="mt-1" />
                                                    </div>
                                                </div>
                                                <div class="flex justify-end gap-2">
                                                    <button type="button" class="rounded px-3 py-1 text-xs text-gray-500 hover:bg-gray-100" @click="addingProjectId = null">Batal</button>
                                                    <button type="button" class="rounded bg-primary px-3 py-1 text-xs text-white hover:bg-primary/90" :disabled="addForm.processing" @click="submitAdd(project.id)">Tambah</button>
                                                </div>
                                            </div>
                                            <button
                                                v-else
                                                type="button"
                                                class="w-full rounded-md border border-dashed border-gray-300 py-2 text-xs text-gray-400 hover:border-primary hover:text-primary transition-colors"
                                                @click="openAdd(project)"
                                            >
                                                + Tambah Kegiatan
                                            </button>
                                        </template>
                                    </div>
                                </AccordionContent>
                            </AccordionItem>
                        </Accordion>
                    </div>

                    <div class="sticky bottom-4 flex justify-end">
                        <Button type="submit" :disabled="form.processing" class="px-8 shadow-lg">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Laporan Kinerja' }}
                        </Button>
                    </div>
                </form>
            </TabsContent>

            <!-- ── Tim Saya tab ────────────────────────────────────────── -->
            <TabsContent value="team">
                <div v-if="!team_projects.length" class="py-16 text-center text-gray-400">
                    <p class="font-medium">Tidak ada proyek tim untuk periode ini.</p>
                </div>

                <div v-else class="space-y-6">
                    <Card
                        v-for="teamProject in team_projects"
                        :key="teamProject.id"
                        class="overflow-hidden"
                    >
                        <CardHeader class="pb-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <CardTitle class="text-base font-semibold text-gray-800">{{ teamProject.name }}</CardTitle>
                                    <p v-if="teamProject.team" class="mt-0.5 text-sm text-gray-500">{{ teamProject.team.name }}</p>
                                </div>
                                <div class="shrink-0 rounded-lg border bg-gray-50 px-3 py-2 text-right">
                                    <p class="text-xs text-gray-500">Sudah input</p>
                                    <p class="text-lg font-bold text-gray-800 leading-tight">
                                        <span :class="projectSubmittedCount(teamProject) === teamProject.members.length ? 'text-green-600' : 'text-gray-800'">
                                            {{ projectSubmittedCount(teamProject) }}
                                        </span>
                                        <span class="text-sm font-normal text-gray-400"> / {{ teamProject.members.length }}</span>
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <div
                                    v-for="member in teamProject.members"
                                    :key="member.id"
                                    :class="[
                                        'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors',
                                        isMemberLeader(member)
                                            ? 'border-amber-300 bg-amber-50 text-amber-800'
                                            : memberHasAnyReport(teamProject, member.id)
                                                ? 'border-green-200 bg-green-50 text-green-700'
                                                : 'border-gray-200 bg-gray-50 text-gray-500'
                                    ]"
                                >
                                    <span v-if="isMemberLeader(member)" class="text-amber-500" aria-label="Ketua Proyek">&#9733;</span>
                                    <span>{{ member.display_name || member.name }}</span>
                                    <Badge v-if="isMemberLeader(member)" class="ml-0.5 h-4 bg-amber-500 px-1.5 text-[10px] text-white hover:bg-amber-500">Ketua</Badge>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent class="pt-0">
                            <div class="space-y-4">
                                <!-- Empty state -->
                                <p v-if="!teamProject.work_items.length" class="py-4 text-center text-sm text-gray-400">
                                    Belum ada rincian kegiatan.
                                </p>

                                <div
                                    v-for="wi in teamProject.work_items"
                                    :key="wi.id"
                                    class="rounded-md border border-gray-100 bg-gray-50 p-4"
                                >
                                    <!-- Work item header with edit/delete -->
                                    <div v-if="editingItemId !== wi.id" class="mb-3 flex items-start gap-2">
                                        <p class="flex-1 text-sm font-semibold text-gray-700">
                                            {{ wi.number }}. {{ wi.description }}
                                        </p>
                                        <span class="shrink-0 rounded bg-gray-200 px-2 py-0.5 text-xs text-gray-600">
                                            Target: {{ Number(wi.target).toLocaleString('id') }} {{ wi.target_unit }}
                                        </span>
                                        <div v-if="canManageItems(teamProject.leader_id)" class="flex shrink-0 gap-1">
                                            <button type="button" class="rounded px-2 py-0.5 text-xs text-gray-400 hover:bg-gray-200 hover:text-gray-700" @click="openEdit(wi)">Edit</button>
                                            <button type="button" class="rounded px-2 py-0.5 text-xs text-gray-400 hover:bg-red-50 hover:text-red-600" @click="deleteItem(wi.id)">Hapus</button>
                                        </div>
                                    </div>

                                    <!-- Inline edit form -->
                                    <div v-else class="mb-3 rounded border border-blue-100 bg-blue-50 p-3">
                                        <div class="mb-2">
                                            <Label class="text-xs">Deskripsi</Label>
                                            <textarea v-model="editForm.description" rows="2" class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                            <InputError :message="editForm.errors.description" />
                                        </div>
                                        <div class="mb-2 grid grid-cols-2 gap-2">
                                            <div>
                                                <Label class="text-xs">Target</Label>
                                                <Input type="number" min="0.01" step="0.01" v-model="editForm.target" class="mt-1" />
                                            </div>
                                            <div>
                                                <Label class="text-xs">Satuan</Label>
                                                <Input v-model="editForm.target_unit" class="mt-1" />
                                            </div>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="rounded px-3 py-1 text-xs text-gray-500 hover:bg-gray-100" @click="editingItemId = null">Batal</button>
                                            <button type="button" class="rounded bg-primary px-3 py-1 text-xs text-white hover:bg-primary/90" :disabled="editForm.processing" @click="submitEdit(wi.id)">Simpan</button>
                                        </div>
                                    </div>

                                    <!-- Per-member progress rows -->
                                    <div class="space-y-2.5">
                                        <div v-for="member in teamProject.members" :key="member.id" class="rounded-md border bg-white p-3">
                                            <div class="mb-2 flex items-center gap-2">
                                                <div
                                                    :class="['flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold', isMemberLeader(member) ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600']"
                                                >
                                                    {{ (member.display_name || member.name).charAt(0).toUpperCase() }}
                                                </div>
                                                <span :class="['text-xs font-medium', isMemberLeader(member) ? 'text-amber-800' : 'text-gray-700']">
                                                    {{ member.display_name || member.name }}
                                                </span>
                                                <Badge v-if="isMemberLeader(member)" class="h-4 bg-amber-500 px-1.5 text-[10px] text-white hover:bg-amber-500">Ketua</Badge>
                                            </div>

                                            <template v-if="wi.performance_reports.find(r => r.reported_by === member.id)">
                                                <div class="flex items-center justify-between gap-3">
                                                    <div class="min-w-0 flex-1">
                                                        <Progress
                                                            :model-value="wi.performance_reports.find(r => r.reported_by === member.id)!.achievement_percentage"
                                                            :class="['h-2', memberProgressColor(wi.performance_reports.find(r => r.reported_by === member.id)!.achievement_percentage)]"
                                                        />
                                                    </div>
                                                    <div class="flex shrink-0 items-center gap-2 text-xs">
                                                        <span class="text-gray-500">
                                                            {{ Number(wi.performance_reports.find(r => r.reported_by === member.id)!.realization).toLocaleString('id') }}
                                                            <span class="text-gray-400">{{ wi.target_unit }}</span>
                                                        </span>
                                                        <span :class="['font-bold', memberPctColor(wi.performance_reports.find(r => r.reported_by === member.id)!.achievement_percentage)]">
                                                            {{ wi.performance_reports.find(r => r.reported_by === member.id)!.achievement_percentage.toFixed(1) }}%
                                                        </span>
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <p class="text-xs text-gray-400 italic">Belum diinput</p>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add item (project leader only) -->
                                <template v-if="canManageItems(teamProject.leader_id)">
                                    <div v-if="addingProjectId === teamProject.id" class="rounded-md border border-blue-100 bg-blue-50 p-3">
                                        <p class="mb-2 text-xs font-medium text-blue-800">Tambah Rincian Kegiatan</p>
                                        <div class="mb-2 grid grid-cols-4 gap-2">
                                            <div>
                                                <Label class="text-xs">No.</Label>
                                                <Input type="number" min="1" v-model="addForm.number" class="mt-1" />
                                            </div>
                                            <div class="col-span-3">
                                                <Label class="text-xs">Deskripsi <span class="text-red-500">*</span></Label>
                                                <textarea v-model="addForm.description" rows="2" placeholder="Deskripsi kegiatan..." class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                                <InputError :message="addForm.errors.description" />
                                            </div>
                                        </div>
                                        <div class="mb-2 grid grid-cols-2 gap-2">
                                            <div>
                                                <Label class="text-xs">Target <span class="text-red-500">*</span></Label>
                                                <Input type="number" min="0.01" step="0.01" v-model="addForm.target" class="mt-1" />
                                                <InputError :message="addForm.errors.target" />
                                            </div>
                                            <div>
                                                <Label class="text-xs">Satuan</Label>
                                                <Input v-model="addForm.target_unit" placeholder="Kegiatan" class="mt-1" />
                                            </div>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="rounded px-3 py-1 text-xs text-gray-500 hover:bg-gray-100" @click="addingProjectId = null">Batal</button>
                                            <button type="button" class="rounded bg-primary px-3 py-1 text-xs text-white hover:bg-primary/90" :disabled="addForm.processing" @click="submitAdd(teamProject.id)">Tambah</button>
                                        </div>
                                    </div>
                                    <button
                                        v-else
                                        type="button"
                                        class="w-full rounded-md border border-dashed border-gray-300 py-2 text-xs text-gray-400 hover:border-primary hover:text-primary transition-colors"
                                        @click="openAdd(teamProject)"
                                    >
                                        + Tambah Kegiatan
                                    </button>
                                </template>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </TabsContent>
        </Tabs>

        <!-- Non-lead: no tabs, show personal form directly ─────────────── -->
        <template v-else>
            <div v-if="!projects.length" class="py-16 text-center text-gray-400">
                <p class="font-medium">Tidak ada proyek untuk periode ini.</p>
                <p class="mt-1 text-sm">Anda belum ditugaskan ke proyek aktif tahun {{ filters.year }}.</p>
            </div>

            <form v-else @submit.prevent="submit" class="space-y-8">
                <div v-for="group in projectsByTeam" :key="group.teamId" class="space-y-3">
                    <div class="flex items-center gap-3">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-primary">{{ group.teamName }}</h2>
                        <span class="h-px flex-1 bg-primary/20"></span>
                        <Badge variant="outline" class="text-xs">{{ group.projects.length }} proyek</Badge>
                    </div>

                    <Accordion type="multiple" class="space-y-2">
                        <AccordionItem
                            v-for="project in group.projects"
                            :key="project.id"
                            :value="String(project.id)"
                            class="rounded-lg border bg-white shadow-sm"
                        >
                            <AccordionTrigger class="px-4 py-3 hover:no-underline">
                                <div class="flex min-w-0 flex-1 items-center gap-3 pr-2">
                                    <span class="min-w-0 flex-1 truncate text-left font-medium text-gray-800">{{ project.name }}</span>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <div class="hidden w-24 sm:block">
                                            <Progress :model-value="projectAvgPct(project)" :class="['h-1.5', progressColor(projectAvgPct(project))]" />
                                        </div>
                                        <span :class="['text-sm font-bold', pctColor(projectAvgPct(project))]">
                                            {{ projectAvgPct(project).toFixed(0) }}%
                                        </span>
                                    </div>
                                </div>
                            </AccordionTrigger>
                            <AccordionContent class="px-4 pb-4">
                                <div class="space-y-4">
                                    <p v-if="!project.work_items.length" class="py-4 text-center text-sm text-gray-400">
                                        Belum ada rincian kegiatan.
                                    </p>

                                    <div
                                        v-for="wi in project.work_items"
                                        :key="wi.id"
                                        class="rounded-md border border-gray-100 bg-gray-50 p-4"
                                    >
                                        <div v-if="editingItemId !== wi.id" class="mb-4 flex items-start gap-2">
                                            <p class="flex-1 text-sm font-semibold text-gray-700">
                                                {{ wi.number }}. {{ wi.description }}
                                            </p>
                                            <div v-if="canManageItems(project.leader_id)" class="flex shrink-0 gap-1">
                                                <button type="button" class="rounded px-2 py-0.5 text-xs text-gray-400 hover:bg-gray-200 hover:text-gray-700" @click="openEdit(wi)">Edit</button>
                                                <button type="button" class="rounded px-2 py-0.5 text-xs text-gray-400 hover:bg-red-50 hover:text-red-600" @click="deleteItem(wi.id)">Hapus</button>
                                            </div>
                                        </div>

                                        <div v-else class="mb-4 rounded border border-blue-100 bg-blue-50 p-3">
                                            <div class="mb-2">
                                                <Label class="text-xs">Deskripsi</Label>
                                                <textarea v-model="editForm.description" rows="2" class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                                <InputError :message="editForm.errors.description" />
                                            </div>
                                            <div class="mb-2 grid grid-cols-2 gap-2">
                                                <div>
                                                    <Label class="text-xs">Target</Label>
                                                    <Input type="number" min="0.01" step="0.01" v-model="editForm.target" class="mt-1" />
                                                </div>
                                                <div>
                                                    <Label class="text-xs">Satuan</Label>
                                                    <Input v-model="editForm.target_unit" class="mt-1" />
                                                </div>
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="rounded px-3 py-1 text-xs text-gray-500 hover:bg-gray-100" @click="editingItemId = null">Batal</button>
                                                <button type="button" class="rounded bg-primary px-3 py-1 text-xs text-white hover:bg-primary/90" :disabled="editForm.processing" @click="submitEdit(wi.id)">Simpan</button>
                                            </div>
                                        </div>

                                        <div class="mb-4 rounded-md border border-gray-200 bg-white p-3">
                                            <div class="mb-2 flex items-center justify-between">
                                                <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Progres Capaian</span>
                                                <span :class="['text-base font-bold', pctColor(computePct(wi.id))]">
                                                    {{ computePct(wi.id).toFixed(1) }}%
                                                </span>
                                            </div>
                                            <Progress :model-value="computePct(wi.id)" :class="['mb-3 h-2', progressColor(computePct(wi.id))]" />
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label class="text-xs text-gray-500">Target ({{ wi.target_unit }})</Label>
                                                    <div class="mt-1 flex items-center gap-2">
                                                        <span class="rounded bg-gray-100 px-2.5 py-1.5 text-sm font-semibold text-gray-700">
                                                            {{ Number(wi.target).toLocaleString('id') }}
                                                        </span>
                                                        <span class="text-xs text-gray-400">{{ wi.target_unit }}</span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <Label class="text-xs text-gray-500">Realisasi ({{ wi.target_unit }})</Label>
                                                    <div class="mt-1 flex items-center gap-2">
                                                        <Input type="number" min="0" step="0.01" v-model="getItem(wi.id).realization" class="w-28" />
                                                        <span class="text-xs text-gray-400">{{ wi.target_unit }}</span>
                                                    </div>
                                                    <InputError :message="form.errors[`items.${form.items.findIndex(i => i.work_item_id === wi.id)}.realization`]" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid gap-3 sm:grid-cols-3">
                                            <div>
                                                <Label class="text-xs text-gray-500">Kendala</Label>
                                                <Textarea v-model="getItem(wi.id).issues" rows="3" class="mt-1 text-sm" placeholder="(opsional)" />
                                            </div>
                                            <div>
                                                <Label class="text-xs text-gray-500">Solusi</Label>
                                                <Textarea v-model="getItem(wi.id).solutions" rows="3" class="mt-1 text-sm" placeholder="(opsional)" />
                                            </div>
                                            <div>
                                                <Label class="text-xs text-gray-500">Rencana Tindak Lanjut</Label>
                                                <Textarea v-model="getItem(wi.id).action_plan" rows="3" class="mt-1 text-sm" placeholder="(opsional)" />
                                            </div>
                                        </div>
                                    </div>

                                    <template v-if="canManageItems(project.leader_id)">
                                        <div v-if="addingProjectId === project.id" class="rounded-md border border-blue-100 bg-blue-50 p-3">
                                            <p class="mb-2 text-xs font-medium text-blue-800">Tambah Rincian Kegiatan</p>
                                            <div class="mb-2 grid grid-cols-4 gap-2">
                                                <div>
                                                    <Label class="text-xs">No.</Label>
                                                    <Input type="number" min="1" v-model="addForm.number" class="mt-1" />
                                                </div>
                                                <div class="col-span-3">
                                                    <Label class="text-xs">Deskripsi <span class="text-red-500">*</span></Label>
                                                    <textarea v-model="addForm.description" rows="2" placeholder="Deskripsi kegiatan..." class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                                    <InputError :message="addForm.errors.description" />
                                                </div>
                                            </div>
                                            <div class="mb-2 grid grid-cols-2 gap-2">
                                                <div>
                                                    <Label class="text-xs">Target <span class="text-red-500">*</span></Label>
                                                    <Input type="number" min="0.01" step="0.01" v-model="addForm.target" class="mt-1" />
                                                    <InputError :message="addForm.errors.target" />
                                                </div>
                                                <div>
                                                    <Label class="text-xs">Satuan</Label>
                                                    <Input v-model="addForm.target_unit" placeholder="Kegiatan" class="mt-1" />
                                                </div>
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="rounded px-3 py-1 text-xs text-gray-500 hover:bg-gray-100" @click="addingProjectId = null">Batal</button>
                                                <button type="button" class="rounded bg-primary px-3 py-1 text-xs text-white hover:bg-primary/90" :disabled="addForm.processing" @click="submitAdd(project.id)">Tambah</button>
                                            </div>
                                        </div>
                                        <button
                                            v-else
                                            type="button"
                                            class="w-full rounded-md border border-dashed border-gray-300 py-2 text-xs text-gray-400 hover:border-primary hover:text-primary transition-colors"
                                            @click="openAdd(project)"
                                        >
                                            + Tambah Kegiatan
                                        </button>
                                    </template>
                                </div>
                            </AccordionContent>
                        </AccordionItem>
                    </Accordion>
                </div>

                <div class="sticky bottom-4 flex justify-end">
                    <Button type="submit" :disabled="form.processing" class="px-8 shadow-lg">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Laporan Kinerja' }}
                    </Button>
                </div>
            </form>
        </template>
    </AppLayout>
</template>
