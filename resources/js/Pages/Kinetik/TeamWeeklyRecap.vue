<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { RecapSegment, RecapRow, TeamOption, TeamRecapEvidence } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';
import { ChevronLeft, ChevronRight, ChevronDown, ChevronUp, ExternalLink, Trash2, ChevronsUpDown } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { useDateFormat } from '@/composables/useDateFormat';

const { formatWeekRange } = useDateFormat();

const props = defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    segments: RecapSegment[];
    evidences: TeamRecapEvidence[];
    weekStart: string;
    weekEnd: string;
    prevWeek: string;
    nextWeek: string;
    canManage: boolean;
    currentEmployeeId: number | null;
}>();

// ── Navigation ─────────────────────────────────────────────────────────────

function navigate(params: Record<string, string | number>) {
    router.get(route('team-recap.weekly'), {
        team: props.selectedTeamId ?? undefined,
        week: props.weekStart,
        ...params,
    }, { preserveState: false });
}

// ── Achievement color ──────────────────────────────────────────────────────

function achievementColor(val: number | null): string {
    const n = Number(val ?? 0);
    if (n >= 80) return 'text-green-600 font-semibold';
    if (n >= 50) return 'text-yellow-600 font-semibold';
    return 'text-red-600 font-semibold';
}

// ── Per-segment sort ───────────────────────────────────────────────────────

const sortDirs = ref<Record<string, 'asc' | 'desc'>>({});

function sortDir(segKey: string): 'asc' | 'desc' {
    return sortDirs.value[segKey] ?? 'asc';
}

function toggleSort(segKey: string) {
    sortDirs.value[segKey] = sortDir(segKey) === 'asc' ? 'desc' : 'asc';
}

// ── "Perlu perhatian" filter ───────────────────────────────────────────────

const attentionOnly = ref(false);

function needsAttention(row: RecapRow): boolean {
    const hasObstacle = !!row.obstacle_aggregated && row.obstacle_aggregated !== '—' && row.obstacle_aggregated !== 'N/A';
    return (row.achievement ?? 0) < 100 || hasObstacle;
}

function attentionCount(seg: RecapSegment): number {
    return seg.rows.filter(needsAttention).length;
}

function filteredRows(seg: RecapSegment): RecapRow[] {
    const base = attentionOnly.value ? seg.rows.filter(needsAttention) : seg.rows;
    const key = String(seg.project_id ?? 'none');
    const dir = sortDir(key);
    return [...base].sort((a, b) =>
        dir === 'asc'
            ? (a.achievement ?? 0) - (b.achievement ?? 0)
            : (b.achievement ?? 0) - (a.achievement ?? 0),
    );
}

// ── Expand state ───────────────────────────────────────────────────────────

const expandedRows = ref<Record<number, boolean>>({});

function toggleExpand(planId: number) {
    expandedRows.value[planId] = !expandedRows.value[planId];
}

// ── Per-row paraphrase permission ──────────────────────────────────────────

function rowCanParaphrase(row: RecapRow): boolean {
    return props.canManage || (props.currentEmployeeId !== null && row.pic_employee_id === props.currentEmployeeId);
}

// ── Paraphrase forms (per planId) ──────────────────────────────────────────

type ParaForm = { obstacle: string; solution: string; follow_up_plan: string; saving: boolean; seededFromAgg: boolean };
const paraForms = ref<Record<number, ParaForm>>({});

function getParaForm(row: RecapRow): ParaForm {
    if (!paraForms.value[row.performance_plan_id]) {
        const hasPjObstacle = row.pj_obstacle !== null && row.pj_obstacle !== '';
        const seededFromAgg = !hasPjObstacle && !!row.obstacle_aggregated;
        paraForms.value[row.performance_plan_id] = {
            obstacle: row.pj_obstacle ?? row.obstacle_aggregated ?? '',
            solution: row.pj_solution ?? '',
            follow_up_plan: row.pj_follow_up_plan ?? '',
            saving: false,
            seededFromAgg,
        };
    }

    return paraForms.value[row.performance_plan_id];
}

function saveParaphrase(row: RecapRow) {
    const f = getParaForm(row);
    f.saving = true;
    router.post(route('team-recap.override.store'), {
        team_id: props.selectedTeamId,
        performance_plan_id: row.performance_plan_id,
        period_type: 'week',
        period_year: new Date(props.weekStart + 'T00:00:00').getFullYear(),
        week_start: props.weekStart,
        obstacle: f.obstacle,
        solution: f.solution,
        follow_up_plan: f.follow_up_plan,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { f.saving = false; },
    });
}

// ── Evidence ───────────────────────────────────────────────────────────────

const evidenceTypeLabel: Record<string, string> = {
    notula: 'Notula',
    photo: 'Foto',
    attendance: 'Daftar Hadir',
};

const showEvidenceForm = ref(false);
const evidenceForm = ref({
    team_id: props.selectedTeamId,
    project_id: null as number | null,
    week_start: props.weekStart,
    type: 'notula',
    title: '',
    url: '',
    errors: {} as Record<string, string>,
    processing: false,
});

function submitEvidence() {
    evidenceForm.value.processing = true;
    router.post(route('team-recap.evidence.store'), {
        team_id: props.selectedTeamId,
        project_id: evidenceForm.value.project_id,
        week_start: props.weekStart,
        type: evidenceForm.value.type,
        title: evidenceForm.value.title,
        url: evidenceForm.value.url,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            evidenceForm.value.title = '';
            evidenceForm.value.url = '';
            showEvidenceForm.value = false;
        },
        onError: (errors: Record<string, string>) => { evidenceForm.value.errors = errors; },
        onFinish: () => { evidenceForm.value.processing = false; },
    });
}

function deleteEvidence(id: number) {
    router.delete(route('team-recap.evidence.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Rekap Tim" />
    <AppLayout>
        <template #title>Rekap Tim (Mingguan)</template>

        <div v-if="!teams.length" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Anda belum tergabung dalam tim mana pun.
        </div>

        <template v-else>
            <!-- Controls -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-md border bg-white px-4 py-3">
                <Select
                    :model-value="selectedTeamId != null ? String(selectedTeamId) : undefined"
                    @update:model-value="(v) => navigate({ team: Number(v) })"
                >
                    <SelectTrigger class="w-auto min-w-[16rem]">
                        <SelectValue placeholder="Pilih tim" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                    </SelectContent>
                </Select>

                <div class="flex items-center gap-3">
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Minggu sebelumnya" @click="navigate({ week: prevWeek })">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="text-sm font-medium text-gray-700">{{ formatWeekRange(weekStart, weekEnd) }}</span>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Minggu berikutnya" @click="navigate({ week: nextWeek })">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        :class="['inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors', attentionOnly ? 'border-orange-400 bg-orange-50 text-orange-700' : 'border-gray-200 bg-white text-gray-600 hover:border-orange-300 hover:text-orange-600']"
                        @click="attentionOnly = !attentionOnly"
                    >
                        Perlu perhatian
                        <span :class="['rounded-full px-1.5 py-0.5 text-xs', attentionOnly ? 'bg-orange-400 text-white' : 'bg-gray-200 text-gray-600']">
                            {{ segments.reduce((sum, seg) => sum + attentionCount(seg), 0) }}
                        </span>
                    </button>

                </div>
            </div>

            <!-- Segments by project -->
            <div v-if="!segments.length" class="mb-6 rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                Belum ada rekap tersimpan untuk tim ini pada minggu ini.
            </div>

            <div v-else class="mb-8 space-y-6">
                <div v-for="seg in segments" :key="seg.project_id ?? 'none'" class="overflow-hidden rounded-md border bg-white">
                    <div class="flex items-center justify-between border-b bg-gray-50 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-800">{{ seg.project_name }}</h3>
                        <span v-if="attentionCount(seg) > 0" class="inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">
                            {{ attentionCount(seg) }} perlu perhatian
                        </span>
                    </div>

                    <Table class="w-full text-sm">
                        <TableHeader>
                            <TableRow class="border-b bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                                <TableHead class="text-left">Rencana Kinerja</TableHead>
                                <TableHead class="text-left text-xs">Kontributor</TableHead>
                                <TableHead class="text-right">Target</TableHead>
                                <TableHead class="text-right">Realisasi</TableHead>
                                <TableHead class="cursor-pointer select-none text-right" @click="toggleSort(String(seg.project_id ?? 'none'))">
                                    <span class="inline-flex items-center gap-1">
                                        Capaian
                                        <ChevronsUpDown class="h-3 w-3" :class="{ 'rotate-180': sortDir(String(seg.project_id ?? 'none')) === 'desc' }" />
                                    </span>
                                </TableHead>
                                <TableHead class="w-8" />
                            </TableRow>
                        </TableHeader>
                        <TableBody class="divide-y divide-gray-100">
                            <template v-for="row in filteredRows(seg)" :key="row.performance_plan_id">
                                <TableRow class="hover:bg-gray-50">
                                    <TableCell class="align-top">
                                        <p class="font-medium text-gray-800">{{ row.rk_description }}</p>
                                        <p v-if="row.rk_code" class="text-xs text-gray-500">{{ row.rk_code }}</p>
                                        <p v-if="row.is_overridden" class="mt-0.5 text-xs italic text-blue-500">Telah diparafrase</p>
                                    </TableCell>
                                    <TableCell class="align-top text-xs text-gray-600">{{ row.contributors.join(', ') || '—' }}</TableCell>
                                    <TableCell class="text-right align-top text-gray-700">{{ row.target }} {{ row.target_unit ?? '' }}</TableCell>
                                    <TableCell class="text-right align-top text-gray-700">{{ row.realization }}</TableCell>
                                    <TableCell class="text-right align-top">
                                        <span v-if="row.achievement != null" :class="achievementColor(row.achievement)">{{ row.achievement.toFixed(2) }}%</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </TableCell>
                                    <TableCell class="text-center align-top">
                                        <button
                                            type="button"
                                            class="flex h-6 w-6 items-center justify-center rounded hover:bg-gray-100"
                                            :title="expandedRows[row.performance_plan_id] ? 'Tutup panel' : 'Buka panel parafrase'"
                                            @click="toggleExpand(row.performance_plan_id)"
                                        >
                                            <ChevronDown v-if="!expandedRows[row.performance_plan_id]" class="h-4 w-4 text-gray-500" />
                                            <ChevronUp v-else class="h-4 w-4 text-gray-500" />
                                        </button>
                                    </TableCell>
                                </TableRow>

                                <!-- Expand panel -->
                                <TableRow v-if="expandedRows[row.performance_plan_id]" :key="`${row.performance_plan_id}-panel`" class="bg-gray-50">
                                    <TableCell colspan="7" class="px-6 py-4">
                                        <div class="space-y-4">
                                            <!-- Member kendala (read-only) -->
                                            <div>
                                                <p class="mb-1 text-xs font-medium text-gray-500">Kendala (anggota)</p>
                                                <p class="rounded bg-white px-3 py-2 text-sm text-gray-700 ring-1 ring-gray-200">{{ row.obstacle_aggregated || '—' }}</p>
                                            </div>

                                            <!-- Paraphrase inputs (PJ or this row's PIC) -->
                                            <template v-if="rowCanParaphrase(row)">
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <Label class="text-xs">Kendala (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).obstacle" :rows="2" class="mt-1 text-sm" />
                                                        <p v-if="getParaForm(row).seededFromAgg" class="mt-0.5 text-xs italic text-gray-400">Prafilled dari kendala anggota</p>
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">Solusi (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).solution" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">RTL (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).follow_up_plan" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                </div>
                                                <div class="flex justify-end">
                                                    <Button size="sm" :disabled="getParaForm(row).saving" @click="saveParaphrase(row)">
                                                        Simpan parafrase
                                                    </Button>
                                                </div>
                                            </template>

                                            <!-- Read-only paraphrase (no paraphrase permission) -->
                                            <template v-else>
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">Kendala (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_obstacle || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">Solusi (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_solution || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">RTL (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_follow_up_plan || '—' }}</p>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- Evidence (notula / foto / DH) — unchanged -->
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">Bukti Dukung Rapat</h2>
                    <Button v-if="canManage" size="sm" variant="outline" @click="showEvidenceForm = !showEvidenceForm">
                        {{ showEvidenceForm ? 'Tutup' : 'Tambah Bukti' }}
                    </Button>
                    <span v-else class="text-xs text-gray-400">Hanya PJ yang dapat menambah bukti</span>
                </div>

                <form v-if="canManage && showEvidenceForm" class="mb-4 space-y-3 rounded-md border bg-white p-4" @submit.prevent="submitEvidence">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label for="ev-type">Jenis</Label>
                            <Select v-model="evidenceForm.type">
                                <SelectTrigger id="ev-type" class="mt-1 w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="notula">Notula</SelectItem>
                                    <SelectItem value="photo">Foto</SelectItem>
                                    <SelectItem value="attendance">Daftar Hadir</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label for="ev-title">Judul</Label>
                            <Input id="ev-title" v-model="evidenceForm.title" class="mt-1" placeholder="Rapat koordinasi..." />
                        </div>
                    </div>
                    <div>
                        <Label for="ev-url">URL Bukti Dukung <span class="text-red-500">*</span></Label>
                        <Input id="ev-url" v-model="evidenceForm.url" type="url" class="mt-1" placeholder="https://..." />
                        <InputError :message="evidenceForm.errors.url" />
                    </div>
                    <div class="flex justify-end">
                        <Button type="submit" size="sm" :disabled="evidenceForm.processing">Simpan Bukti</Button>
                    </div>
                </form>

                <div v-if="!evidences.length" class="rounded-md border border-dashed border-gray-200 bg-gray-50 py-8 text-center text-sm text-gray-400">
                    Belum ada bukti dukung untuk minggu ini.
                </div>
                <ul v-else class="divide-y divide-gray-100 overflow-hidden rounded-md border bg-white">
                    <li v-for="ev in evidences" :key="ev.id" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ evidenceTypeLabel[ev.type] ?? ev.type }}</span>
                            <span class="ml-2 text-sm text-gray-800">{{ ev.title || '—' }}</span>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <a :href="ev.url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                                Buka <ExternalLink class="h-3 w-3" />
                            </a>
                            <button v-if="canManage" type="button" class="text-gray-400 hover:text-red-600" title="Hapus" @click="deleteEvidence(ev.id)">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </template>
    </AppLayout>
</template>
