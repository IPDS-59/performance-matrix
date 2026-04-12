<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { Employee, Project } from '@/types';
import { ref, computed, reactive, watch } from 'vue';
import { Checkbox } from '@/Components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
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

interface ReportAttachment {
    id: number;
    type: 'file' | 'link';
    file_path: string | null;
    file_name: string | null;
    mime_type: string | null;
    url: string | null;
    title: string | null;
    status: 'pending' | 'approved' | 'rejected';
    reviewed_by: number | null;
    review_note: string | null;
    reviewed_at: string | null;
    reviewer: { id: number; name: string; display_name: string | null } | null;
    display_url: string | null;
}

interface WorkItemWithReports {
    id: number;
    number: number;
    description: string;
    target: number;
    target_unit: string;
    performance_reports: Array<{
        id: number;
        period_month: number;
        realization: number;
        achievement_percentage: number;
        issues: string | null;
        solutions: string | null;
        action_plan: string | null;
        approval_status: 'pending' | 'approved' | 'rejected';
        review_note: string | null;
        attachments: ReportAttachment[];
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
    period_month: number;
    realization: number;
    achievement_percentage: number;
    reported_by: number | null;
    reporter: { id: number; name: string; display_name: string | null } | null;
    attachments: ReportAttachment[];
    reviewer?: { id: number; name: string; display_name: string | null } | null;
    approval_status: 'pending' | 'approved' | 'rejected';
    review_note: string | null;
    reviewed_at: string | null;
    issues: string | null;
    solutions: string | null;
    action_plan: string | null;
}

interface WorkItemAssignment {
    employee_id: number;
    target: number;
    target_unit: string;
    employee?: { id: number; name: string; display_name: string | null };
}

interface TeamWorkItem {
    id: number;
    number: number;
    description: string;
    target: number;
    target_unit: string;
    assignments: WorkItemAssignment[];
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

const monthAbbr = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];

function selectMonth(m: number) {
    month.value = m;
    applyFilters();
}

function monthDotClass(wi: WorkItemWithReports, m: number): string {
    const report = wi.performance_reports.find(r => r.period_month === m);
    const isCurrent = m === props.filters.month;
    const ring = isCurrent ? ' ring-2 ring-offset-1 ring-primary scale-125' : '';
    if (!report) return `bg-gray-200${ring}`;
    const hasApproved = report.attachments.some(a => a.status === 'approved');
    if (hasApproved) return `bg-green-500${ring}`;
    if (report.attachments.length > 0) return `bg-blue-400${ring}`;
    return `bg-primary/60${ring}`;
}

// ── Personal projects: split by whether work items are assigned ───────────

const projectsWithWork = computed(() => props.projects.filter(p => p.work_items.length > 0));
const projectsWithoutWork = computed(() => props.projects.filter(p => p.work_items.length === 0));

function groupByTeam(projects: ProjectWithItems[]) {
    const groups: Record<number, { teamId: number; teamName: string; projects: ProjectWithItems[] }> = {};
    for (const p of projects) {
        const tid = p.team_id;
        const tname = p.team?.name ?? 'Tim Tidak Diketahui';
        if (!groups[tid]) groups[tid] = { teamId: tid, teamName: tname, projects: [] };
        groups[tid].projects.push(p);
    }
    return Object.values(groups).sort((a, b) => a.teamName.localeCompare(b.teamName));
}

const projectsByTeam = computed(() => groupByTeam(projectsWithWork.value));
const projectsByTeamNoWork = computed(() => groupByTeam(projectsWithoutWork.value));

// ── Performance report form (per-item) ────────────────────────────────────

type ItemState = {
    realization: number | string;
    issues: string;
    solutions: string;
    action_plan: string;
};

const itemStates = reactive(new Map<number, ItemState>());
const itemProcessing = reactive(new Set<number>());
const itemErrors = reactive(new Map<number, Partial<Record<string, string>>>());
const itemActiveMonth = reactive(new Map<number, number>());

function getActiveMonth(workItemId: number): number {
    return itemActiveMonth.get(workItemId) ?? props.filters.month;
}

function setItemMonth(workItemId: number, m: number) {
    itemActiveMonth.set(workItemId, m);
    itemStates.delete(workItemId); // re-seed from the new month's report
}

function getItemState(workItemId: number): ItemState {
    if (!itemStates.has(workItemId)) {
        const activeMonth = getActiveMonth(workItemId);
        let report: WorkItemWithReports['performance_reports'][number] | undefined;
        outer: for (const p of props.projects) {
            for (const wi of p.work_items) {
                if (wi.id === workItemId) {
                    report = wi.performance_reports.find(r => r.period_month === activeMonth);
                    break outer;
                }
            }
        }
        itemStates.set(workItemId, {
            realization: Number(report?.realization ?? 0),
            issues: report?.issues ?? '',
            solutions: report?.solutions ?? '',
            action_plan: report?.action_plan ?? '',
        });
    }
    return itemStates.get(workItemId)!;
}

function submitItem(workItemId: number) {
    const state = getItemState(workItemId);
    const activeMonth = getActiveMonth(workItemId);
    itemProcessing.add(workItemId);
    itemErrors.delete(workItemId);

    router.post(
        route('performance.batch'),
        {
            period_month: activeMonth,
            period_year: props.filters.year,
            items: [{
                work_item_id: workItemId,
                realization: state.realization,
                issues: state.issues || null,
                solutions: state.solutions || null,
                action_plan: state.action_plan || null,
            }],
        },
        {
            preserveScroll: true,
            onFinish: () => itemProcessing.delete(workItemId),
            onError: (errors) => {
                const errs: Partial<Record<string, string>> = {};
                for (const [key, msg] of Object.entries(errors)) {
                    const field = key.replace(/^items\.\d+\./, '');
                    errs[field] = msg as string;
                }
                itemErrors.set(workItemId, errs);
            },
        },
    );
}

function getWorkItem(workItemId: number): WorkItemWithReports | undefined {
    for (const p of props.projects) {
        const wi = p.work_items.find(w => w.id === workItemId);
        if (wi) return wi;
    }
}

function getReport(workItemId: number) {
    const activeMonth = getActiveMonth(workItemId);
    for (const p of props.projects) {
        const wi = p.work_items.find(w => w.id === workItemId);
        if (wi) return wi.performance_reports.find(r => r.period_month === activeMonth);
    }
    return undefined;
}

function computePct(workItemId: number): number {
    const wi = getWorkItem(workItemId);
    if (!wi || !wi.target || Number(wi.target) <= 0) return 0;

    const activeMonth = getActiveMonth(workItemId);
    // Sum all other months' saved realizations (cumulative progress)
    const prevTotal = wi.performance_reports
        .filter(r => r.period_month !== activeMonth)
        .reduce((sum, r) => sum + Number(r.realization), 0);

    const state = itemStates.get(workItemId);
    const savedRealization = wi.performance_reports
        .find(r => r.period_month === activeMonth)?.realization ?? 0;
    const currentRealization = state !== undefined
        ? Number(state.realization)
        : Number(savedRealization);

    return Math.min(100, ((prevTotal + currentRealization) / Number(wi.target)) * 100);
}

function computeTeamMemberPct(wi: TeamWorkItem, memberId: number): number {
    const reports = wi.performance_reports.filter(r => r.reported_by === memberId);
    if (!reports.length) return 0;
    const assignment = wi.assignments.find(a => a.employee_id === memberId);
    const target = assignment ? Number(assignment.target) : Number(wi.target);
    if (target <= 0) return 0;
    const totalRealization = reports.reduce((sum, r) => sum + Number(r.realization), 0);
    return Math.min(100, (totalRealization / target) * 100);
}

function computeTeamMemberTotalRealization(wi: TeamWorkItem, memberId: number): number {
    return wi.performance_reports
        .filter(r => r.reported_by === memberId)
        .reduce((sum, r) => sum + Number(r.realization), 0);
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

// When the month/year filter changes, clear per-item state so items re-seed
// from the new month's report data.
watch(
    () => [props.filters.month, props.filters.year] as const,
    () => {
        itemStates.clear();
        itemProcessing.clear();
        itemErrors.clear();
        itemActiveMonth.clear();
    },
);

// ── Work item management (project leader) ─────────────────────────────────

function canManageItems(leaderId: number | null | undefined): boolean {
    return leaderId === props.employee.id;
}

function nextNumber(items: Array<{ number: number }>): number {
    return items.length ? Math.max(...items.map(w => w.number)) + 1 : 1;
}

type FormAssignmentRow = {
    employee_id: number;
    target: number;
    target_unit: string;
    display_name: string;
};

const addMemberSearch = ref('');
const editMemberSearch = ref('');

// reactive(Set) lets Vue track .has()/.add()/.delete() calls — ref(Set) does NOT.
const addIncludedIds = reactive(new Set<number>());
const editIncludedIds = reactive(new Set<number>());

// Computed maps give Vue a guaranteed reactive property-access dependency path.
// Template uses !!addCheckedMap[id] instead of addIncludedIds.has(id) to avoid
// any edge-case where Set.has() isn't tracked as a template dependency.
const addCheckedMap = computed<Record<number, boolean>>(() => {
    const m: Record<number, boolean> = {};
    addIncludedIds.forEach(id => { m[id] = true; });
    return m;
});
const editCheckedMap = computed<Record<number, boolean>>(() => {
    const m: Record<number, boolean> = {};
    editIncludedIds.forEach(id => { m[id] = true; });
    return m;
});

// Add form
const addingProjectId = ref<number | null>(null);
const addAssignTo = ref<'all' | 'specific'>('all');
const addForm = useForm({
    number: 1,
    description: '',
    target: 1 as number,
    target_unit: 'Kegiatan',
    assignments: [] as FormAssignmentRow[],
});

function openAdd(project: { id: number; work_items: Array<{ number: number }>; members?: TeamMember[] }) {
    addForm.number = nextNumber(project.work_items);
    addForm.description = '';
    addForm.target = 1;
    addForm.target_unit = 'Kegiatan';
    addAssignTo.value = 'all';
    addForm.assignments = (project.members ?? []).map(m => ({
        employee_id: m.id,
        target: 1,
        target_unit: 'Kegiatan',
        display_name: m.display_name || m.name,
    }));
    addIncludedIds.clear();
    addForm.clearErrors();
    addMemberSearch.value = '';
    addingProjectId.value = project.id;
}

function toggleAddIncluded(employeeId: number) {
    if (addIncludedIds.has(employeeId)) addIncludedIds.delete(employeeId);
    else addIncludedIds.add(employeeId);
}

function submitAdd(projectId: number) {
    const assignTo = addAssignTo.value;
    const assignments = assignTo === 'specific'
        ? addForm.assignments
            .filter(a => addIncludedIds.has(a.employee_id))
            .map(a => ({ employee_id: a.employee_id, target: a.target, target_unit: a.target_unit }))
        : [];
    addForm
        .transform((data: Record<string, unknown>) => ({ ...data, assign_to: assignTo, assignments }))
        .post(route('work-items.store', projectId), {
            preserveScroll: true,
            onSuccess: () => { addingProjectId.value = null; },
        });
}

// Edit form
const editingItemId = ref<number | null>(null);
const editAssignTo = ref<'all' | 'specific'>('all');
const editForm = useForm({
    description: '',
    target: 1 as number,
    target_unit: 'Kegiatan',
    assignments: [] as FormAssignmentRow[],
});

function openEdit(
    wi: { id: number; description: string; target: number; target_unit: string; assignments?: WorkItemAssignment[] },
    members?: TeamMember[],
) {
    const hasSpecific = (wi.assignments?.length ?? 0) > 0;

    editForm.description = wi.description;
    editForm.target = Number(wi.target);
    editForm.target_unit = wi.target_unit;
    editAssignTo.value = hasSpecific ? 'specific' : 'all';
    editForm.assignments = (members ?? []).map(m => {
        const existing = wi.assignments?.find(a => a.employee_id === m.id);
        return {
            employee_id: m.id,
            target: existing?.target ?? Number(wi.target),
            target_unit: existing?.target_unit ?? wi.target_unit,
            display_name: m.display_name || m.name,
        };
    });
    editIncludedIds.clear();
    const editIds = !hasSpecific
        ? (members ?? []).map(m => m.id)
        : (wi.assignments ?? []).map(a => a.employee_id);
    for (const id of editIds) editIncludedIds.add(id);
    editForm.clearErrors();
    editMemberSearch.value = '';
    editingItemId.value = wi.id;
}

function toggleEditIncluded(employeeId: number) {
    if (editIncludedIds.has(employeeId)) editIncludedIds.delete(employeeId);
    else editIncludedIds.add(employeeId);
}

function submitEdit(itemId: number) {
    const assignTo = editAssignTo.value;
    const assignments = assignTo === 'specific'
        ? editForm.assignments
            .filter(a => editIncludedIds.has(a.employee_id))
            .map(a => ({ employee_id: a.employee_id, target: a.target, target_unit: a.target_unit }))
        : [];
    editForm
        .transform((data: any) => ({ ...data, assign_to: assignTo, assignments }))
        .put(route('work-items.update', itemId), {
            preserveScroll: true,
            onSuccess: () => { editingItemId.value = null; },
        });
}

function deleteItem(itemId: number) {
    router.delete(route('work-items.destroy', itemId), { preserveScroll: true });
}

const filteredAddIndices = computed(() => {
    const q = addMemberSearch.value.toLowerCase();
    return addForm.assignments.reduce<number[]>((acc, row, idx) => {
        if (!q || row.display_name.toLowerCase().includes(q)) acc.push(idx);
        return acc;
    }, []);
});
const filteredEditIndices = computed(() => {
    const q = editMemberSearch.value.toLowerCase();
    return editForm.assignments.reduce<number[]>((acc, row, idx) => {
        if (!q || row.display_name.toLowerCase().includes(q)) acc.push(idx);
        return acc;
    }, []);
});

// ── Chip scroll indicator (always visible when container is scrollable) ────

const teamChipScrollable = reactive<Record<number, boolean>>({});
const teamChipResizeObservers = new Map<number, ResizeObserver>();

function initTeamChipScrollable(el: HTMLElement | null, projectId: number) {
    teamChipResizeObservers.get(projectId)?.disconnect();
    if (!el) return;
    const update = () => { teamChipScrollable[projectId] = el.scrollWidth > el.clientWidth; };
    update();
    const ro = new ResizeObserver(update);
    ro.observe(el);
    teamChipResizeObservers.set(projectId, ro);
}

// ── Attachment forms ────────────────────────────────────────────────────────

const addingFileReportId = ref<number | null>(null);
const addingLinkReportId = ref<number | null>(null);

const fileForm = useForm({ type: 'file', title: '', file: null as File | null });
const linkForm = useForm({ type: 'link', title: '', url: '' });

function openAddFile(reportId: number) {
    addingLinkReportId.value = null;
    fileForm.reset();
    addingFileReportId.value = reportId;
}

function openAddLink(reportId: number) {
    addingFileReportId.value = null;
    linkForm.reset();
    addingLinkReportId.value = reportId;
}

function submitFile(reportId: number) {
    fileForm.post(route('report-attachments.store', reportId), {
        preserveScroll: true,
        onSuccess: () => { addingFileReportId.value = null; },
    });
}

function submitLink(reportId: number) {
    linkForm.post(route('report-attachments.store', reportId), {
        preserveScroll: true,
        onSuccess: () => { addingLinkReportId.value = null; },
    });
}

function deleteAttachment(attachmentId: number) {
    router.delete(route('report-attachments.destroy', attachmentId), { preserveScroll: true });
}

function reviewAttachment(attachmentId: number, status: 'approved' | 'rejected') {
    router.patch(route('report-attachments.review', attachmentId), { status }, { preserveScroll: true });
}

const rejectNoteMap = ref<Record<number, string>>({});
const showRejectForm = ref<Record<number, boolean>>({});

function getMemberReports(wi: TeamWorkItem, memberId: number): TeamWorkItemReport[] {
    return wi.performance_reports
        .filter(r => r.reported_by === memberId)
        .sort((a, b) => b.period_month - a.period_month);
}

function approveReport(reportId: number) {
    router.patch(route('performance.approve', reportId), {}, { preserveScroll: true });
}

function submitRejectReport(reportId: number) {
    const note = rejectNoteMap.value[reportId] ?? '';
    router.patch(route('performance.reject', reportId), { review_note: note }, { preserveScroll: true });
    showRejectForm.value[reportId] = false;
}

function reportStatusColor(status: string): string {
    if (status === 'approved') return 'bg-green-100 text-green-700 border-green-200';
    if (status === 'rejected') return 'bg-red-100 text-red-700 border-red-200';
    return 'bg-yellow-50 text-yellow-700 border-yellow-200';
}

function reportStatusLabel(status: string): string {
    if (status === 'approved') return 'Disetujui';
    if (status === 'rejected') return 'Ditolak';
    return 'Menunggu Persetujuan';
}

function attachmentStatusColor(status: string): string {
    if (status === 'approved') return 'bg-green-100 text-green-700 border-green-200';
    if (status === 'rejected') return 'bg-red-100 text-red-700 border-red-200';
    return 'bg-yellow-50 text-yellow-700 border-yellow-200';
}

function attachmentStatusLabel(status: string): string {
    if (status === 'approved') return 'Disetujui';
    if (status === 'rejected') return 'Ditolak';
    return 'Menunggu';
}

// ── Tim Saya: group by team ────────────────────────────────────────────────

const teamProjectSearch = ref('');

const teamProjectsByTeam = computed(() => {
    const groups: Record<number, { teamId: number; teamName: string; projects: TeamProjectWithMembers[] }> = {};
    for (const p of props.team_projects) {
        const tid = p.team?.id ?? 0;
        const tname = p.team?.name ?? 'Tim Tidak Diketahui';
        if (!groups[tid]) groups[tid] = { teamId: tid, teamName: tname, projects: [] };
        groups[tid].projects.push(p);
    }
    return Object.values(groups).sort((a, b) => a.teamName.localeCompare(b.teamName));
});

const filteredTeamProjectsByTeam = computed(() => {
    const q = teamProjectSearch.value.trim().toLowerCase();
    if (!q) return teamProjectsByTeam.value;
    return teamProjectsByTeam.value
        .map(group => ({
            ...group,
            projects: group.projects.filter(
                p => p.name.toLowerCase().includes(q) || group.teamName.toLowerCase().includes(q),
            ),
        }))
        .filter(group => group.projects.length > 0);
});

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

                <div v-else class="space-y-8">
                    <!-- Projects with assigned work items (main fillable section) -->
                    <div v-if="projectsByTeam.length">
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
                                            <Transition
                                                enter-from-class="opacity-0 -translate-y-1"
                                                enter-active-class="transition-all duration-200 ease-out"
                                                leave-active-class="transition-all duration-150 ease-in"
                                                leave-to-class="opacity-0 -translate-y-1"
                                            >
                                            <div v-if="editingItemId === wi.id" class="mb-4 rounded border border-blue-100 bg-blue-50 p-3">
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
                                            </Transition>

                                            <!-- Monthly timeline strip -->
                                            <div class="mb-3">
                                                <div class="flex items-center gap-1">
                                                    <button
                                                        v-for="m in 12"
                                                        :key="m"
                                                        type="button"
                                                        :title="`${months[m-1].label}: ${wi.performance_reports.find(r => r.period_month === m) ? Number(wi.performance_reports.find(r => r.period_month === m)!.realization).toLocaleString('id') + ' ' + wi.target_unit : 'Belum diinput'}`"
                                                        :class="['h-3.5 w-3.5 shrink-0 rounded-full transition-all', monthDotClass(wi, m)]"
                                                        @click.prevent="setItemMonth(wi.id, m)"
                                                    />
                                                </div>
                                                <div class="mt-1 flex items-center gap-1">
                                                    <span
                                                        v-for="m in 12"
                                                        :key="m"
                                                        :class="['w-3.5 shrink-0 text-center text-[9px]', m === getActiveMonth(wi.id) ? 'font-bold text-primary' : 'text-gray-400']"
                                                    >{{ monthAbbr[m-1] }}</span>
                                                </div>
                                            </div>

                                            <!-- Progress + realization input -->
                                            <div class="mb-4 rounded-md border border-gray-200 bg-white p-3">
                                                <div class="mb-2 flex items-center justify-between">
                                                    <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Progres Capaian</span>
                                                    <div class="flex items-center gap-2">
                                                        <span v-if="getReport(wi.id)" :class="['inline-flex rounded border px-1.5 py-0.5 text-[10px]', reportStatusColor(getReport(wi.id)!.approval_status)]">
                                                            {{ reportStatusLabel(getReport(wi.id)!.approval_status) }}
                                                        </span>
                                                        <span :class="['text-base font-bold', pctColor(computePct(wi.id))]">
                                                            {{ computePct(wi.id).toFixed(1) }}%
                                                        </span>
                                                    </div>
                                                </div>
                                                <p v-if="getReport(wi.id)?.review_note" class="mb-2 text-[10px] italic text-gray-500">Catatan reviewer: {{ getReport(wi.id)!.review_note }}</p>
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
                                                            <Input type="number" min="0" step="0.01" v-model="getItemState(wi.id).realization" class="w-28" />
                                                            <span class="text-xs text-gray-400">{{ wi.target_unit }}</span>
                                                        </div>
                                                        <InputError :message="itemErrors.get(wi.id)?.realization" />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="grid gap-3 sm:grid-cols-3">
                                                <div>
                                                    <Label class="text-xs text-gray-500">Kendala</Label>
                                                    <Textarea v-model="getItemState(wi.id).issues" rows="3" class="mt-1 text-sm" placeholder="(opsional)" />
                                                </div>
                                                <div>
                                                    <Label class="text-xs text-gray-500">Solusi</Label>
                                                    <Textarea v-model="getItemState(wi.id).solutions" rows="3" class="mt-1 text-sm" placeholder="(opsional)" />
                                                </div>
                                                <div>
                                                    <Label class="text-xs text-gray-500">Rencana Tindak Lanjut</Label>
                                                    <Textarea v-model="getItemState(wi.id).action_plan" rows="3" class="mt-1 text-sm" placeholder="(opsional)" />
                                                </div>
                                            </div>
                                            <div class="mt-2 flex justify-end">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    :disabled="itemProcessing.has(wi.id)"
                                                    @click="submitItem(wi.id)"
                                                >
                                                    {{ itemProcessing.has(wi.id) ? 'Menyimpan...' : 'Simpan' }}
                                                </Button>
                                            </div>

                                            <!-- Bukti dukung (only when a report exists for this month) -->
                                            <Transition
                                                enter-from-class="opacity-0 -translate-y-1"
                                                enter-active-class="transition-all duration-200 ease-out"
                                                leave-active-class="transition-all duration-150 ease-in"
                                                leave-to-class="opacity-0 -translate-y-1"
                                            >
                                            <div v-if="getReport(wi.id) || Number(getItemState(wi.id).realization) > 0" class="mt-3 rounded-md border border-gray-200 bg-white p-3">
                                                <div class="mb-2 flex items-center justify-between">
                                                    <span class="text-xs font-medium text-gray-500">Bukti Dukung</span>
                                                    <div v-if="getReport(wi.id)" class="flex gap-1.5">
                                                        <button
                                                            type="button"
                                                            class="flex items-center gap-1 rounded border px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
                                                            @click.prevent="openAddFile(getReport(wi.id)!.id)"
                                                        >
                                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                            File
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="flex items-center gap-1 rounded border px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
                                                            @click.prevent="openAddLink(getReport(wi.id)!.id)"
                                                        >
                                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                                            Tautan
                                                        </button>
                                                    </div>
                                                </div>

                                                <template v-if="getReport(wi.id)">
                                                    <!-- Existing attachments -->
                                                    <div v-if="getReport(wi.id)!.attachments.length" class="mb-2 space-y-1.5">
                                                        <div
                                                            v-for="att in getReport(wi.id)!.attachments"
                                                            :key="att.id"
                                                            class="flex items-center gap-2 rounded border border-gray-100 bg-gray-50 px-2.5 py-1.5"
                                                        >
                                                            <svg v-if="att.type === 'file'" class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                            <svg v-else class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                                            <a
                                                                v-if="att.display_url"
                                                                :href="att.display_url"
                                                                target="_blank"
                                                                rel="noopener"
                                                                class="min-w-0 flex-1 truncate text-xs text-blue-600 hover:underline"
                                                            >{{ att.title || att.file_name || att.url }}</a>
                                                            <span v-else class="min-w-0 flex-1 truncate text-xs text-gray-600">{{ att.title || att.file_name || att.url }}</span>
                                                            <span :class="['shrink-0 rounded border px-1.5 py-0.5 text-[10px]', attachmentStatusColor(att.status)]">
                                                                {{ attachmentStatusLabel(att.status) }}
                                                            </span>
                                                            <button
                                                                type="button"
                                                                class="shrink-0 text-gray-300 hover:text-red-500"
                                                                @click.prevent="deleteAttachment(att.id)"
                                                            >
                                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <p v-else-if="addingFileReportId !== getReport(wi.id)!.id && addingLinkReportId !== getReport(wi.id)!.id" class="mb-2 text-xs italic text-gray-400">Belum ada bukti dukung.</p>

                                                    <!-- Add file form -->
                                                    <Transition
                                                        enter-from-class="opacity-0 -translate-y-1"
                                                        enter-active-class="transition-all duration-200 ease-out"
                                                        leave-active-class="transition-all duration-150 ease-in"
                                                        leave-to-class="opacity-0 -translate-y-1"
                                                    >
                                                    <div v-if="addingFileReportId === getReport(wi.id)!.id" class="mt-2 space-y-1.5 rounded border border-dashed border-gray-200 p-2">
                                                        <p class="text-[10px] font-medium text-gray-500 uppercase">Unggah File</p>
                                                        <input
                                                            type="file"
                                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                                            class="block w-full text-xs text-gray-600 file:mr-2 file:rounded file:border file:border-gray-200 file:bg-gray-50 file:px-2 file:py-0.5 file:text-xs file:text-gray-600"
                                                            @change="e => fileForm.file = (e.target as HTMLInputElement).files?.[0] ?? null"
                                                        />
                                                        <Input v-model="fileForm.title" placeholder="Judul (opsional)" class="text-xs" />
                                                        <InputError :message="fileForm.errors.file" />
                                                        <div class="flex gap-1.5">
                                                            <button type="button" class="rounded px-3 py-1 text-xs text-gray-500 hover:bg-gray-100" @click.prevent="addingFileReportId = null">Batal</button>
                                                            <button type="button" class="rounded bg-primary px-3 py-1 text-xs text-white hover:bg-primary/90" :disabled="fileForm.processing || !fileForm.file" @click.prevent="submitFile(getReport(wi.id)!.id)">Unggah</button>
                                                        </div>
                                                    </div>
                                                    </Transition>

                                                    <!-- Add link form -->
                                                    <Transition
                                                        enter-from-class="opacity-0 -translate-y-1"
                                                        enter-active-class="transition-all duration-200 ease-out"
                                                        leave-active-class="transition-all duration-150 ease-in"
                                                        leave-to-class="opacity-0 -translate-y-1"
                                                    >
                                                    <div v-if="addingLinkReportId === getReport(wi.id)!.id" class="mt-2 space-y-1.5 rounded border border-dashed border-gray-200 p-2">
                                                        <p class="text-[10px] font-medium text-gray-500 uppercase">Tambah Tautan</p>
                                                        <Input v-model="linkForm.url" placeholder="https://..." class="text-xs" />
                                                        <Input v-model="linkForm.title" placeholder="Judul (opsional)" class="text-xs" />
                                                        <InputError :message="linkForm.errors.url" />
                                                        <div class="flex gap-1.5">
                                                            <button type="button" class="rounded px-3 py-1 text-xs text-gray-500 hover:bg-gray-100" @click.prevent="addingLinkReportId = null">Batal</button>
                                                            <button type="button" class="rounded bg-primary px-3 py-1 text-xs text-white hover:bg-primary/90" :disabled="linkForm.processing || !linkForm.url" @click.prevent="submitLink(getReport(wi.id)!.id)">Tambah</button>
                                                        </div>
                                                    </div>
                                                    </Transition>
                                                </template>
                                                <p v-else class="text-xs italic text-gray-400">Simpan laporan terlebih dahulu untuk menambahkan bukti dukung.</p>
                                            </div>
                                            </Transition>
                                        </div>

                                        <!-- Add item form (project leader only) -->
                                        <template v-if="canManageItems(project.leader_id)">
                                            <Transition
                                                enter-from-class="opacity-0 -translate-y-1"
                                                enter-active-class="transition-all duration-200 ease-out"
                                                leave-active-class="transition-all duration-150 ease-in"
                                                leave-to-class="opacity-0 -translate-y-1"
                                            >
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
                                                        <Input type="number" min="1" step="1" v-model="addForm.target" class="mt-1" />
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
                                            </Transition>
                                            <button
                                                v-if="addingProjectId !== project.id"
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
                    </div>

                    <!-- Projects without assigned work items (informational) -->
                    <template v-if="projectsByTeamNoWork.length">
                        <div class="flex items-center gap-3">
                            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Proyek Tanpa Penugasan</h2>
                            <span class="h-px flex-1 bg-gray-200"></span>
                            <span class="text-xs text-gray-400">Belum ada kegiatan yang ditugaskan kepada Anda</span>
                        </div>
                        <div class="space-y-2">
                            <div
                                v-for="group in projectsByTeamNoWork"
                                :key="group.teamId"
                            >
                                <p class="mb-1.5 text-xs font-medium text-gray-400">{{ group.teamName }}</p>
                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    <div
                                        v-for="project in group.projects"
                                        :key="project.id"
                                        class="flex items-center gap-3 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-3"
                                    >
                                        <svg class="h-4 w-4 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                        </svg>
                                        <span class="min-w-0 flex-1 truncate text-sm text-gray-500">{{ project.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                </div>
            </TabsContent>

            <!-- ── Tim Saya tab ────────────────────────────────────────── -->
            <TabsContent value="team">
                <div v-if="!team_projects.length" class="py-16 text-center text-gray-400">
                    <p class="font-medium">Tidak ada proyek tim untuk periode ini.</p>
                </div>

                <div v-else class="space-y-5">
                    <!-- Project search -->
                    <Input
                        v-model="teamProjectSearch"
                        placeholder="Cari proyek atau tim..."
                        class="max-w-sm"
                    />

                    <div v-if="!filteredTeamProjectsByTeam.length" class="py-12 text-center text-sm text-gray-400">
                        Tidak ada proyek yang cocok dengan pencarian.
                    </div>

                    <div v-for="group in filteredTeamProjectsByTeam" :key="group.teamId" class="space-y-4">
                        <!-- Team header -->
                        <div class="flex items-center gap-3">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-primary">{{ group.teamName }}</h2>
                            <span class="h-px flex-1 bg-primary/20"></span>
                            <Badge variant="outline" class="text-xs">{{ group.projects.length }} proyek</Badge>
                        </div>

                        <Card
                            v-for="teamProject in group.projects"
                            :key="teamProject.id"
                            class="overflow-hidden"
                        >
                            <CardHeader class="pb-4">
                                <div class="flex items-start justify-between gap-3">
                                    <CardTitle class="text-base font-semibold text-gray-800">{{ teamProject.name }}</CardTitle>
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
                                <!-- Member chips -->
                                <div class="mt-3 flex items-center gap-2">
                                    <!-- Pinned ketua chip(s) -->
                                    <template v-for="member in teamProject.members" :key="'lead-'+member.id">
                                        <div
                                            v-if="isMemberLeader(member)"
                                            class="flex shrink-0 items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs text-amber-800"
                                        >
                                            <span class="text-amber-500">&#9733;</span>
                                            <span>{{ member.display_name || member.name }}</span>
                                            <Badge class="ml-0.5 h-4 bg-amber-500 px-1.5 text-[10px] text-white hover:bg-amber-500">Ketua</Badge>
                                        </div>
                                    </template>

                                    <!-- Vertical separator if both leaders and non-leaders exist -->
                                    <span
                                        v-if="teamProject.members.some(m => isMemberLeader(m)) && teamProject.members.some(m => !isMemberLeader(m))"
                                        class="h-6 w-px shrink-0 bg-gray-200"
                                    />

                                    <!-- Scrollable non-leader chips with scroll indicator -->
                                    <div v-if="teamProject.members.some(m => !isMemberLeader(m))" class="relative min-w-0 flex-1">
                                        <div
                                            class="flex gap-2 overflow-x-auto [&::-webkit-scrollbar]:hidden [scrollbar-width:none]"
                                            :ref="(el) => initTeamChipScrollable(el as HTMLElement | null, teamProject.id)"
                                        >
                                            <template v-for="member in teamProject.members" :key="member.id">
                                                <div
                                                    v-if="!isMemberLeader(member)"
                                                    :class="['flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-xs',
                                                        memberHasAnyReport(teamProject, member.id) ? 'border-green-200 bg-green-50 text-green-700'
                                                        : 'border-gray-200 bg-gray-50 text-gray-500']"
                                                >
                                                    <span>{{ member.display_name || member.name }}</span>
                                                </div>
                                            </template>
                                        </div>
                                        <!-- Right-edge scroll hint — shown when container is scrollable -->
                                        <div v-if="teamChipScrollable[teamProject.id]" class="pointer-events-none absolute inset-y-0 right-0 flex items-center bg-gradient-to-l from-white via-white/70 to-transparent pl-6 pr-1">
                                            <svg class="h-4 w-4 animate-bounce-x text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </CardHeader>

                            <CardContent class="pt-0">
                                <div class="space-y-4">
                                    <p v-if="!teamProject.work_items.length" class="py-4 text-center text-sm text-gray-400">
                                        Belum ada rincian kegiatan.
                                    </p>

                                    <div
                                        v-for="wi in teamProject.work_items"
                                        :key="wi.id"
                                        class="rounded-md border border-gray-100 bg-gray-50 p-4"
                                    >
                                        <!-- Work item title -->
                                        <div class="mb-3 flex items-start gap-2">
                                            <p class="flex-1 text-sm font-semibold text-gray-700">{{ wi.number }}. {{ wi.description }}</p>
                                            <div v-if="canManageItems(teamProject.leader_id)" class="flex shrink-0 gap-1">
                                                <button type="button" class="rounded px-2 py-0.5 text-xs text-gray-400 hover:bg-gray-200 hover:text-gray-700" @click="openEdit(wi, teamProject.members)">Edit</button>
                                                <button type="button" class="rounded px-2 py-0.5 text-xs text-gray-400 hover:bg-red-50 hover:text-red-600" @click="deleteItem(wi.id)">Hapus</button>
                                            </div>
                                        </div>

                                        <!-- Inline edit form -->
                                        <Transition
                                            enter-from-class="opacity-0 -translate-y-1"
                                            enter-active-class="transition-all duration-200 ease-out"
                                            leave-active-class="transition-all duration-150 ease-in"
                                            leave-to-class="opacity-0 -translate-y-1"
                                        >
                                        <div v-if="editingItemId === wi.id" class="mb-3 rounded border border-blue-100 bg-blue-50 p-3">
                                            <div class="mb-2">
                                                <Label class="text-xs">Deskripsi</Label>
                                                <textarea v-model="editForm.description" rows="2" class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                            </div>
                                            <div class="mb-2 flex items-center gap-4">
                                                <Label class="text-xs shrink-0">Ditugaskan ke:</Label>
                                                <RadioGroup v-model="editAssignTo" class="flex gap-4" @update:modelValue="(v) => { if (v === 'specific') editIncludedIds.clear(); }">
                                                    <div class="flex items-center gap-1.5">
                                                        <RadioGroupItem id="edit-all" value="all" />
                                                        <Label for="edit-all" class="cursor-pointer text-xs font-normal">Semua</Label>
                                                    </div>
                                                    <div class="flex items-center gap-1.5">
                                                        <RadioGroupItem id="edit-specific" value="specific" />
                                                        <Label for="edit-specific" class="cursor-pointer text-xs font-normal">Tertentu</Label>
                                                    </div>
                                                </RadioGroup>
                                            </div>
                                            <Transition
                                                mode="out-in"
                                                enter-from-class="opacity-0 -translate-y-1"
                                                enter-active-class="transition-all duration-200 ease-out"
                                                leave-active-class="transition-all duration-150 ease-in"
                                                leave-to-class="opacity-0 -translate-y-1"
                                            >
                                                <div v-if="editAssignTo === 'all'" key="all" class="mb-2 grid grid-cols-2 gap-2">
                                                    <div><Label class="text-xs">Target</Label><Input type="number" min="0.01" step="0.01" v-model="editForm.target" class="mt-1" /><InputError :message="editForm.errors.target" /></div>
                                                    <div><Label class="text-xs">Satuan</Label><Input v-model="editForm.target_unit" class="mt-1" /><InputError :message="editForm.errors.target_unit" /></div>
                                                </div>
                                                <div v-else key="specific" class="mb-2 space-y-1.5">
                                                    <div class="relative">
                                                        <svg class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 pointer-events-none text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                                                        <input v-model="editMemberSearch" type="text" placeholder="Cari anggota..." class="w-full rounded-md border border-input bg-white py-1.5 pl-8 pr-3 text-xs focus:outline-none focus:ring-1 focus:ring-ring" />
                                                    </div>
                                                    <div class="relative">
                                                        <div class="max-h-44 overflow-y-auto rounded-md border [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 [&::-webkit-scrollbar-track]:bg-transparent">
                                                            <div
                                                                v-for="idx in filteredEditIndices"
                                                                :key="editForm.assignments[idx].employee_id"
                                                                :class="['flex items-center gap-2 border-b border-gray-50 px-3 py-2 last:border-b-0 transition-colors', editCheckedMap[editForm.assignments[idx].employee_id] ? 'bg-primary/5' : 'bg-white hover:bg-gray-50']"
                                                            >
                                                                <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2">
                                                                    <Checkbox
                                                                        :model-value="!!editCheckedMap[editForm.assignments[idx].employee_id]"
                                                                        @update:model-value="() => toggleEditIncluded(editForm.assignments[idx].employee_id)"
                                                                        @click.stop
                                                                        class="h-3.5 w-3.5 shrink-0"
                                                                    />
                                                                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary">
                                                                        {{ editForm.assignments[idx].display_name.charAt(0).toUpperCase() }}
                                                                    </div>
                                                                    <span class="min-w-0 flex-1 truncate text-xs text-gray-700">{{ editForm.assignments[idx].display_name }}</span>
                                                                </label>
                                                                <div :class="['flex shrink-0 gap-1', !editCheckedMap[editForm.assignments[idx].employee_id] && 'pointer-events-none opacity-40']">
                                                                    <Input type="number" min="0.01" step="0.01" v-model="editForm.assignments[idx].target" class="w-20 text-xs" />
                                                                    <Input v-model="editForm.assignments[idx].target_unit" class="w-24 text-xs" />
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
                                                <button type="button" class="rounded px-3 py-1 text-xs text-gray-500 hover:bg-gray-100" @click="editingItemId = null">Batal</button>
                                                <button type="button" class="rounded bg-primary px-3 py-1 text-xs text-white hover:bg-primary/90" :disabled="editForm.processing" @click="submitEdit(wi.id)">Simpan</button>
                                            </div>
                                        </div>
                                        </Transition>

                                        <!-- Per-member progress: only assigned members -->
                                        <div class="space-y-2.5">
                                            <template v-for="member in teamProject.members" :key="member.id">
                                                <div
                                                    v-if="wi.assignments.length === 0 || wi.assignments.some(a => a.employee_id === member.id)"
                                                    class="rounded-md border bg-white p-3"
                                                >
                                                    <div class="mb-2 flex items-center gap-2">
                                                        <div :class="['flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold', isMemberLeader(member) ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600']">
                                                            {{ (member.display_name || member.name).charAt(0).toUpperCase() }}
                                                        </div>
                                                        <span :class="['text-xs font-medium', isMemberLeader(member) ? 'text-amber-800' : 'text-gray-700']">{{ member.display_name || member.name }}</span>
                                                        <Badge v-if="isMemberLeader(member)" class="h-4 bg-amber-500 px-1.5 text-[10px] text-white hover:bg-amber-500">Ketua</Badge>
                                                        <!-- Per-member target badge -->
                                                        <span v-if="wi.assignments.find(a => a.employee_id === member.id)" class="ml-auto text-xs text-gray-400">
                                                            Target: {{ wi.assignments.find(a => a.employee_id === member.id)!.target }} {{ wi.assignments.find(a => a.employee_id === member.id)!.target_unit }}
                                                        </span>
                                                    </div>
                                                    <template v-if="wi.performance_reports.some(r => r.reported_by === member.id)">
                                                        <div class="flex items-center justify-between gap-3">
                                                            <Progress
                                                                :model-value="computeTeamMemberPct(wi, member.id)"
                                                                :class="['h-2 flex-1', memberProgressColor(computeTeamMemberPct(wi, member.id))]"
                                                            />
                                                            <div class="flex shrink-0 items-center gap-2 text-xs">
                                                                <span class="text-gray-500">{{ computeTeamMemberTotalRealization(wi, member.id).toLocaleString('id') }}</span>
                                                                <span :class="['font-bold', memberPctColor(computeTeamMemberPct(wi, member.id))]">
                                                                    {{ computeTeamMemberPct(wi, member.id).toFixed(1) }}%
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <!-- All months' reports (newest first) -->
                                                        <template v-if="getMemberReports(wi, member.id).length">
                                                            <div
                                                                v-for="report in getMemberReports(wi, member.id)"
                                                                :key="report.id"
                                                                class="mt-2 rounded border border-gray-100 bg-gray-50 p-2"
                                                            >
                                                                <!-- Month label + status -->
                                                                <div class="mb-1.5 flex items-center gap-2">
                                                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                                                        {{ months[report.period_month - 1].label }} {{ filters.year }}
                                                                    </span>
                                                                    <span class="text-[10px] text-gray-400">·</span>
                                                                    <span class="text-xs font-medium text-gray-700">{{ Number(report.realization).toLocaleString('id') }} {{ wi.target_unit }}</span>
                                                                    <span :class="['ml-auto inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[10px]', reportStatusColor(report.approval_status)]">
                                                                        {{ reportStatusLabel(report.approval_status) }}
                                                                    </span>
                                                                </div>

                                                                <!-- Kendala / Solusi / RTL -->
                                                                <div v-if="report.issues || report.solutions || report.action_plan" class="mb-1.5 grid gap-1.5 sm:grid-cols-3">
                                                                    <div v-if="report.issues">
                                                                        <p class="text-[10px] font-medium uppercase text-gray-400">Kendala</p>
                                                                        <p class="mt-0.5 text-xs text-gray-700 whitespace-pre-line">{{ report.issues }}</p>
                                                                    </div>
                                                                    <div v-if="report.solutions">
                                                                        <p class="text-[10px] font-medium uppercase text-gray-400">Solusi</p>
                                                                        <p class="mt-0.5 text-xs text-gray-700 whitespace-pre-line">{{ report.solutions }}</p>
                                                                    </div>
                                                                    <div v-if="report.action_plan">
                                                                        <p class="text-[10px] font-medium uppercase text-gray-400">Rencana Tindak Lanjut</p>
                                                                        <p class="mt-0.5 text-xs text-gray-700 whitespace-pre-line">{{ report.action_plan }}</p>
                                                                    </div>
                                                                </div>

                                                                <!-- Attachments -->
                                                                <div v-if="report.attachments?.length" class="mb-1.5 space-y-1">
                                                                    <div
                                                                        v-for="att in report.attachments"
                                                                        :key="att.id"
                                                                        class="flex items-center gap-2 rounded border border-gray-100 bg-white px-2.5 py-1.5"
                                                                    >
                                                                        <svg v-if="att.type === 'file'" class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                                        <svg v-else class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                                                        <a v-if="att.display_url" :href="att.display_url" target="_blank" rel="noopener" class="min-w-0 flex-1 truncate text-xs text-blue-600 hover:underline">{{ att.title || att.file_name || att.url }}</a>
                                                                        <span v-else class="min-w-0 flex-1 truncate text-xs text-gray-600">{{ att.title || att.file_name || att.url }}</span>
                                                                        <span :class="['shrink-0 rounded border px-1.5 py-0.5 text-[10px]', attachmentStatusColor(att.status)]">{{ attachmentStatusLabel(att.status) }}</span>
                                                                        <template v-if="att.status === 'pending' && canManageItems(teamProject.leader_id)">
                                                                            <button type="button" class="shrink-0 rounded bg-green-500 px-2 py-0.5 text-[10px] text-white hover:bg-green-600" @click="reviewAttachment(att.id, 'approved')">&#10003;</button>
                                                                            <button type="button" class="shrink-0 rounded bg-red-400 px-2 py-0.5 text-[10px] text-white hover:bg-red-500" @click="reviewAttachment(att.id, 'rejected')">&#10007;</button>
                                                                        </template>
                                                                    </div>
                                                                </div>

                                                                <!-- Approval actions (pending only) -->
                                                                <div v-if="canManageItems(teamProject.leader_id) && report.approval_status === 'pending'" class="flex items-center gap-1.5">
                                                                    <button type="button" class="rounded bg-green-500 px-2 py-0.5 text-[10px] text-white hover:bg-green-600" @click="approveReport(report.id)">Setujui</button>
                                                                    <button type="button" class="rounded bg-red-400 px-2 py-0.5 text-[10px] text-white hover:bg-red-500" @click="showRejectForm[report.id] = !showRejectForm[report.id]">Tolak</button>
                                                                </div>
                                                                <p v-if="report.review_note" class="mt-1 text-[10px] italic text-gray-500">Catatan: {{ report.review_note }}</p>
                                                                <div v-if="showRejectForm[report.id]" class="mt-1.5 flex gap-1.5">
                                                                    <input
                                                                        v-model="rejectNoteMap[report.id]"
                                                                        type="text"
                                                                        placeholder="Alasan penolakan..."
                                                                        class="flex-1 rounded border border-gray-300 px-2 py-0.5 text-[10px] focus:border-primary focus:outline-none"
                                                                    />
                                                                    <button type="button" class="rounded bg-red-500 px-2 py-0.5 text-[10px] text-white hover:bg-red-600" @click="submitRejectReport(report.id)">Kirim</button>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </template>
                                                    <p v-else class="text-xs text-gray-400 italic">Belum diinput</p>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Add item (project leader only) -->
                                    <template v-if="canManageItems(teamProject.leader_id)">
                                        <Transition
                                            enter-from-class="opacity-0 -translate-y-1"
                                            enter-active-class="transition-all duration-200 ease-out"
                                            leave-active-class="transition-all duration-150 ease-in"
                                            leave-to-class="opacity-0 -translate-y-1"
                                        >
                                        <div v-if="addingProjectId === teamProject.id" class="rounded-md border border-blue-100 bg-blue-50 p-3">
                                            <p class="mb-2 text-xs font-medium text-blue-800">Tambah Rincian Kegiatan</p>
                                            <div class="mb-2 grid grid-cols-4 gap-2">
                                                <div><Label class="text-xs">No.</Label><Input type="number" min="1" v-model="addForm.number" class="mt-1" /></div>
                                                <div class="col-span-3">
                                                    <Label class="text-xs">Deskripsi <span class="text-red-500">*</span></Label>
                                                    <textarea v-model="addForm.description" rows="2" placeholder="Deskripsi kegiatan..." class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                                    <InputError :message="addForm.errors.description" />
                                                </div>
                                            </div>
                                            <div class="mb-2 flex items-center gap-4">
                                                <Label class="text-xs shrink-0">Ditugaskan ke:</Label>
                                                <RadioGroup v-model="addAssignTo" class="flex gap-4" @update:modelValue="(v) => { if (v === 'specific') addIncludedIds.clear(); }">
                                                    <div class="flex items-center gap-1.5">
                                                        <RadioGroupItem id="add-all" value="all" />
                                                        <Label for="add-all" class="cursor-pointer text-xs font-normal">Semua</Label>
                                                    </div>
                                                    <div class="flex items-center gap-1.5">
                                                        <RadioGroupItem id="add-specific" value="specific" />
                                                        <Label for="add-specific" class="cursor-pointer text-xs font-normal">Tertentu</Label>
                                                    </div>
                                                </RadioGroup>
                                            </div>
                                            <Transition
                                                mode="out-in"
                                                enter-from-class="opacity-0 -translate-y-1"
                                                enter-active-class="transition-all duration-200 ease-out"
                                                leave-active-class="transition-all duration-150 ease-in"
                                                leave-to-class="opacity-0 -translate-y-1"
                                            >
                                                <div v-if="addAssignTo === 'all'" key="all" class="mb-2 grid grid-cols-2 gap-2">
                                                    <div><Label class="text-xs">Target <span class="text-red-500">*</span></Label><Input type="number" min="1" step="1" v-model="addForm.target" class="mt-1" /><InputError :message="addForm.errors.target" /></div>
                                                    <div><Label class="text-xs">Satuan</Label><Input v-model="addForm.target_unit" placeholder="Kegiatan" class="mt-1" /></div>
                                                </div>
                                                <div v-else key="specific" class="mb-2 space-y-1.5">
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
                                                                        {{ addForm.assignments[idx].display_name.charAt(0).toUpperCase() }}
                                                                    </div>
                                                                    <span class="min-w-0 flex-1 truncate text-xs text-gray-700">{{ addForm.assignments[idx].display_name }}</span>
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
                                                </div>
                                            </Transition>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="rounded px-3 py-1 text-xs text-gray-500 hover:bg-gray-100" @click="addingProjectId = null">Batal</button>
                                                <button type="button" class="rounded bg-primary px-3 py-1 text-xs text-white hover:bg-primary/90" :disabled="addForm.processing" @click="submitAdd(teamProject.id)">Tambah</button>
                                            </div>
                                        </div>
                                        </Transition>
                                        <button v-if="addingProjectId !== teamProject.id" type="button" class="w-full rounded-md border border-dashed border-gray-300 py-2 text-xs text-gray-400 hover:border-primary hover:text-primary transition-colors" @click="openAdd(teamProject)">
                                            + Tambah Kegiatan
                                        </button>
                                    </template>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </TabsContent>
        </Tabs>

        <!-- Non-lead: no tabs, show personal form directly ─────────────── -->
        <template v-else>
            <div v-if="!projects.length" class="py-16 text-center text-gray-400">
                <p class="font-medium">Tidak ada proyek untuk periode ini.</p>
                <p class="mt-1 text-sm">Anda belum ditugaskan ke proyek aktif tahun {{ filters.year }}.</p>
            </div>

            <div v-else class="space-y-8">
                <!-- Projects with assigned work items (main fillable section) -->
                <div v-if="projectsByTeam.length">
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

                                        <Transition
                                            enter-from-class="opacity-0 -translate-y-1"
                                            enter-active-class="transition-all duration-200 ease-out"
                                            leave-active-class="transition-all duration-150 ease-in"
                                            leave-to-class="opacity-0 -translate-y-1"
                                        >
                                        <div v-if="editingItemId === wi.id" class="mb-4 rounded border border-blue-100 bg-blue-50 p-3">
                                            <div class="mb-2">
                                                <Label class="text-xs">Deskripsi</Label>
                                                <textarea v-model="editForm.description" rows="2" class="mt-1 w-full rounded-md border border-input bg-white px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                                <InputError :message="editForm.errors.description" />
                                            </div>
                                            <div class="mb-2 grid grid-cols-2 gap-2">
                                                <div>
                                                    <Label class="text-xs">Target</Label>
                                                    <Input type="number" min="1" step="1" v-model="editForm.target" class="mt-1" />
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
                                        </Transition>

                                        <!-- Monthly timeline strip -->
                                        <div class="mb-3">
                                            <div class="flex items-center gap-1">
                                                <button
                                                    v-for="m in 12"
                                                    :key="m"
                                                    type="button"
                                                    :title="`${months[m-1].label}: ${wi.performance_reports.find(r => r.period_month === m) ? Number(wi.performance_reports.find(r => r.period_month === m)!.realization).toLocaleString('id') + ' ' + wi.target_unit : 'Belum diinput'}`"
                                                    :class="['h-3.5 w-3.5 shrink-0 rounded-full transition-all', monthDotClass(wi, m)]"
                                                    @click.prevent="selectMonth(m)"
                                                />
                                            </div>
                                            <div class="mt-1 flex items-center gap-1">
                                                <span
                                                    v-for="m in 12"
                                                    :key="m"
                                                    :class="['w-3.5 shrink-0 text-center text-[9px]', m === props.filters.month ? 'font-bold text-primary' : 'text-gray-400']"
                                                >{{ monthAbbr[m-1] }}</span>
                                            </div>
                                        </div>

                                        <div class="mb-4 rounded-md border border-gray-200 bg-white p-3">
                                            <div class="mb-2 flex items-center justify-between">
                                                <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Progres Capaian</span>
                                                <div class="flex items-center gap-2">
                                                    <span v-if="getReport(wi.id)" :class="['inline-flex rounded border px-1.5 py-0.5 text-[10px]', reportStatusColor(getReport(wi.id)!.approval_status)]">
                                                        {{ reportStatusLabel(getReport(wi.id)!.approval_status) }}
                                                    </span>
                                                    <span :class="['text-base font-bold', pctColor(computePct(wi.id))]">
                                                        {{ computePct(wi.id).toFixed(1) }}%
                                                    </span>
                                                </div>
                                            </div>
                                            <p v-if="getReport(wi.id)?.review_note" class="mb-2 text-[10px] italic text-gray-500">Catatan reviewer: {{ getReport(wi.id)!.review_note }}</p>
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
                                                        <Input type="number" min="0" step="0.01" v-model="getItemState(wi.id).realization" class="w-28" />
                                                        <span class="text-xs text-gray-400">{{ wi.target_unit }}</span>
                                                    </div>
                                                    <InputError :message="itemErrors.get(wi.id)?.realization" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid gap-3 sm:grid-cols-3">
                                            <div>
                                                <Label class="text-xs text-gray-500">Kendala</Label>
                                                <Textarea v-model="getItemState(wi.id).issues" rows="3" class="mt-1 text-sm" placeholder="(opsional)" />
                                            </div>
                                            <div>
                                                <Label class="text-xs text-gray-500">Solusi</Label>
                                                <Textarea v-model="getItemState(wi.id).solutions" rows="3" class="mt-1 text-sm" placeholder="(opsional)" />
                                            </div>
                                            <div>
                                                <Label class="text-xs text-gray-500">Rencana Tindak Lanjut</Label>
                                                <Textarea v-model="getItemState(wi.id).action_plan" rows="3" class="mt-1 text-sm" placeholder="(opsional)" />
                                            </div>
                                        </div>
                                        <div class="mt-2 flex justify-end">
                                            <Button
                                                type="button"
                                                size="sm"
                                                :disabled="itemProcessing.has(wi.id)"
                                                @click="submitItem(wi.id)"
                                            >
                                                {{ itemProcessing.has(wi.id) ? 'Menyimpan...' : 'Simpan' }}
                                            </Button>
                                        </div>

                                        <!-- Bukti dukung (only when a report exists for this month) -->
                                        <Transition
                                            enter-from-class="opacity-0 -translate-y-1"
                                            enter-active-class="transition-all duration-200 ease-out"
                                            leave-active-class="transition-all duration-150 ease-in"
                                            leave-to-class="opacity-0 -translate-y-1"
                                        >
                                        <div v-if="getReport(wi.id) || Number(getItemState(wi.id).realization) > 0" class="mt-3 rounded-md border border-gray-200 bg-white p-3">
                                            <div class="mb-2 flex items-center justify-between">
                                                <span class="text-xs font-medium text-gray-500">Bukti Dukung</span>
                                                <div v-if="getReport(wi.id)" class="flex gap-1.5">
                                                    <button
                                                        type="button"
                                                        class="flex items-center gap-1 rounded border px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
                                                        @click.prevent="openAddFile(getReport(wi.id)!.id)"
                                                    >
                                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                        File
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="flex items-center gap-1 rounded border px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
                                                        @click.prevent="openAddLink(getReport(wi.id)!.id)"
                                                    >
                                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                                        Tautan
                                                    </button>
                                                </div>
                                            </div>

                                            <template v-if="getReport(wi.id)">
                                                <!-- Existing attachments -->
                                                <div v-if="getReport(wi.id)!.attachments.length" class="mb-2 space-y-1.5">
                                                    <div
                                                        v-for="att in getReport(wi.id)!.attachments"
                                                        :key="att.id"
                                                        class="flex items-center gap-2 rounded border border-gray-100 bg-gray-50 px-2.5 py-1.5"
                                                    >
                                                        <svg v-if="att.type === 'file'" class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                        <svg v-else class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                                        <a
                                                            v-if="att.display_url"
                                                            :href="att.display_url"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="min-w-0 flex-1 truncate text-xs text-blue-600 hover:underline"
                                                        >{{ att.title || att.file_name || att.url }}</a>
                                                        <span v-else class="min-w-0 flex-1 truncate text-xs text-gray-600">{{ att.title || att.file_name || att.url }}</span>
                                                        <span :class="['shrink-0 rounded border px-1.5 py-0.5 text-[10px]', attachmentStatusColor(att.status)]">
                                                            {{ attachmentStatusLabel(att.status) }}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            class="shrink-0 text-gray-300 hover:text-red-500"
                                                            @click.prevent="deleteAttachment(att.id)"
                                                        >
                                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                                <p v-else-if="addingFileReportId !== getReport(wi.id)!.id && addingLinkReportId !== getReport(wi.id)!.id" class="mb-2 text-xs italic text-gray-400">Belum ada bukti dukung.</p>

                                                <!-- Add file form -->
                                                <Transition
                                                    enter-from-class="opacity-0 -translate-y-1"
                                                    enter-active-class="transition-all duration-200 ease-out"
                                                    leave-active-class="transition-all duration-150 ease-in"
                                                    leave-to-class="opacity-0 -translate-y-1"
                                                >
                                                <div v-if="addingFileReportId === getReport(wi.id)!.id" class="mt-2 space-y-1.5 rounded border border-dashed border-gray-200 p-2">
                                                    <p class="text-[10px] font-medium text-gray-500 uppercase">Unggah File</p>
                                                    <input
                                                        type="file"
                                                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                                                        class="block w-full text-xs text-gray-600 file:mr-2 file:rounded file:border file:border-gray-200 file:bg-gray-50 file:px-2 file:py-0.5 file:text-xs file:text-gray-600"
                                                        @change="e => fileForm.file = (e.target as HTMLInputElement).files?.[0] ?? null"
                                                    />
                                                    <Input v-model="fileForm.title" placeholder="Judul (opsional)" class="text-xs" />
                                                    <InputError :message="fileForm.errors.file" />
                                                    <div class="flex gap-1.5">
                                                        <button type="button" class="rounded px-3 py-1 text-xs text-gray-500 hover:bg-gray-100" @click.prevent="addingFileReportId = null">Batal</button>
                                                        <button type="button" class="rounded bg-primary px-3 py-1 text-xs text-white hover:bg-primary/90" :disabled="fileForm.processing || !fileForm.file" @click.prevent="submitFile(getReport(wi.id)!.id)">Unggah</button>
                                                    </div>
                                                </div>
                                                </Transition>

                                                <!-- Add link form -->
                                                <Transition
                                                    enter-from-class="opacity-0 -translate-y-1"
                                                    enter-active-class="transition-all duration-200 ease-out"
                                                    leave-active-class="transition-all duration-150 ease-in"
                                                    leave-to-class="opacity-0 -translate-y-1"
                                                >
                                                <div v-if="addingLinkReportId === getReport(wi.id)!.id" class="mt-2 space-y-1.5 rounded border border-dashed border-gray-200 p-2">
                                                    <p class="text-[10px] font-medium text-gray-500 uppercase">Tambah Tautan</p>
                                                    <Input v-model="linkForm.url" placeholder="https://..." class="text-xs" />
                                                    <Input v-model="linkForm.title" placeholder="Judul (opsional)" class="text-xs" />
                                                    <InputError :message="linkForm.errors.url" />
                                                    <div class="flex gap-1.5">
                                                        <button type="button" class="rounded px-3 py-1 text-xs text-gray-500 hover:bg-gray-100" @click.prevent="addingLinkReportId = null">Batal</button>
                                                        <button type="button" class="rounded bg-primary px-3 py-1 text-xs text-white hover:bg-primary/90" :disabled="linkForm.processing || !linkForm.url" @click.prevent="submitLink(getReport(wi.id)!.id)">Tambah</button>
                                                    </div>
                                                </div>
                                                </Transition>
                                            </template>
                                            <p v-else class="text-xs italic text-gray-400">Simpan laporan terlebih dahulu untuk menambahkan bukti dukung.</p>
                                        </div>
                                        </Transition>
                                    </div>

                                    <template v-if="canManageItems(project.leader_id)">
                                        <Transition
                                            enter-from-class="opacity-0 -translate-y-1"
                                            enter-active-class="transition-all duration-200 ease-out"
                                            leave-active-class="transition-all duration-150 ease-in"
                                            leave-to-class="opacity-0 -translate-y-1"
                                        >
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
                                                    <Input type="number" min="1" step="1" v-model="addForm.target" class="mt-1" />
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
                                        </Transition>
                                        <button
                                            v-if="addingProjectId !== project.id"
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
                </div>

                <!-- Projects without assigned work items (informational) -->
                <template v-if="projectsByTeamNoWork.length">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Proyek Tanpa Penugasan</h2>
                        <span class="h-px flex-1 bg-gray-200"></span>
                        <span class="text-xs text-gray-400">Belum ada kegiatan yang ditugaskan kepada Anda</span>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="group in projectsByTeamNoWork"
                            :key="group.teamId"
                        >
                            <p class="mb-1.5 text-xs font-medium text-gray-400">{{ group.teamName }}</p>
                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                <div
                                    v-for="project in group.projects"
                                    :key="project.id"
                                    class="flex items-center gap-3 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-3"
                                >
                                    <svg class="h-4 w-4 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                    </svg>
                                    <span class="min-w-0 flex-1 truncate text-sm text-gray-500">{{ project.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

            </div>
        </template>
    </AppLayout>
</template>

