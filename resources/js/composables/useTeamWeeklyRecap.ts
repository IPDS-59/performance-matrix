import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { RecapSegment, RecapRow, TeamOption, TeamRecapEvidence } from '@/types';
import { useDateFormat } from '@/composables/useDateFormat';

export interface TeamWeeklyRecapProps {
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
}

type ParaForm = {
    obstacle: string;
    solution: string;
    follow_up_plan: string;
    saving: boolean;
    seededFromAgg: boolean;
};

export function useTeamWeeklyRecap(props: TeamWeeklyRecapProps) {
    const { formatWeekRange } = useDateFormat();

    // ── Navigation ─────────────────────────────────────────────────────────

    function navigate(params: Record<string, string | number>) {
        router.get(route('team-recap.weekly'), {
            team: props.selectedTeamId ?? undefined,
            week: props.weekStart,
            ...params,
        }, { preserveState: false });
    }

    // ── Achievement color ──────────────────────────────────────────────────

    function achievementColor(val: number | null): string {
        const n = Number(val ?? 0);
        if (n >= 80) return 'text-green-600 font-semibold';
        if (n >= 50) return 'text-yellow-600 font-semibold';
        return 'text-red-600 font-semibold';
    }

    // ── Per-segment sort ───────────────────────────────────────────────────

    const sortDirs = ref<Record<string, 'asc' | 'desc'>>({});

    function sortDir(segKey: string): 'asc' | 'desc' {
        return sortDirs.value[segKey] ?? 'asc';
    }

    function toggleSort(segKey: string) {
        sortDirs.value[segKey] = sortDir(segKey) === 'asc' ? 'desc' : 'asc';
    }

    // ── "Perlu perhatian" filter ───────────────────────────────────────────

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

    // ── Expand state ───────────────────────────────────────────────────────

    const expandedRows = ref<Record<number, boolean>>({});

    function toggleExpand(planId: number) {
        expandedRows.value[planId] = !expandedRows.value[planId];
    }

    // ── Per-row paraphrase permission ──────────────────────────────────────

    function rowCanParaphrase(row: RecapRow): boolean {
        return props.canManage || (props.currentEmployeeId !== null && row.pic_employee_id === props.currentEmployeeId);
    }

    // ── Paraphrase forms (per planId) ──────────────────────────────────────

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

    // ── Evidence ───────────────────────────────────────────────────────────

    const evidenceTypeLabel: Record<string, string> = {
        notula: 'Notula',
        photo: 'Foto',
        attendance: 'Daftar Hadir',
    };

    const showEvidenceForm = ref(false);
    const evidenceForm = ref({
        project_id: null as number | null,
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

    return {
        formatWeekRange,
        navigate,
        achievementColor,
        sortDir,
        toggleSort,
        attentionOnly,
        attentionCount,
        filteredRows,
        expandedRows,
        toggleExpand,
        rowCanParaphrase,
        getParaForm,
        saveParaphrase,
        evidenceTypeLabel,
        showEvidenceForm,
        evidenceForm,
        submitEvidence,
        deleteEvidence,
    };
}
