import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { RecapSegment, RecapRow, TeamOption, TeamRecapEvidence, WeeklyTeamNote, RecapLockState, MemberCompleteness } from '@/types';
import { useDateFormat } from '@/composables/useDateFormat';
import { groupAdjacent, textKey, textTarget, useRecapMerge } from '@/composables/useRecapMerge';

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
    canLock: boolean;
    lock: RecapLockState | null;
    currentEmployeeId: number | null;
    weeklyNote: WeeklyTeamNote | null;
    members: MemberCompleteness[];
}

type ParaForm = {
    uraian: string;
    solution: string;
    follow_up_plan: string;
    saving: boolean;
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
        return groupAdjacent([...base].sort((a, b) =>
            dir === 'asc'
                ? (a.achievement ?? 0) - (b.achievement ?? 0)
                : (b.achievement ?? 0) - (a.achievement ?? 0),
        ));
    }

    // ── Expand state ───────────────────────────────────────────────────────

    const expandedRows = ref<Record<string, boolean>>({});

    function toggleExpand(key: string) {
        expandedRows.value[key] = !expandedRows.value[key];
    }

    // ── Per-row paraphrase permission ──────────────────────────────────────

    function rowCanParaphrase(row: RecapRow): boolean {
        if (props.lock) return false;
        return props.canManage || (props.currentEmployeeId !== null && row.pic_employee_id === props.currentEmployeeId);
    }

    // ── Paraphrase forms (per planId) — Kendala / Solusi / RTL ────────────

    // Keyed by the text row, so every row of a merged group edits one form.
    const paraForms = ref<Record<string, ParaForm>>({});

    function getParaForm(row: RecapRow): ParaForm {
        if (!paraForms.value[textKey(row)]) {
            paraForms.value[textKey(row)] = {
                uraian: row.pj_uraian ?? '',
                solution: row.pj_solution ?? '',
                follow_up_plan: row.pj_follow_up_plan ?? '',
                saving: false,
            };
        }
        return paraForms.value[textKey(row)];
    }

    function saveParaphrase(row: RecapRow) {
        const f = getParaForm(row);
        f.saving = true;
        router.post(route('team-recap.override.store'), {
            team_id: props.selectedTeamId,
            ...textTarget(row),
            period_type: 'week',
            period_year: new Date(props.weekStart + 'T00:00:00').getFullYear(),
            week_start: props.weekStart,
            uraian: f.uraian,
            solution: f.solution,
            follow_up_plan: f.follow_up_plan,
        }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => { f.saving = false; },
        });
    }

    // ── Gabungkan / Pisahkan ───────────────────────────────────────────────

    const rowMerge = useRecapMerge(
        () => ({ team_id: props.selectedTeamId, period_type: 'week', period_year: Number(props.weekStart.slice(0, 4)), week_start: props.weekStart }),
        () => { paraForms.value = {}; },
    );

    // ── Single weekly PJ note (uraian + kendala + solusi + RTL) ───────────

    const weeklyNoteForm = ref({
        uraian: props.weeklyNote?.uraian ?? '',
        obstacle: props.weeklyNote?.obstacle ?? '',
        solution: props.weeklyNote?.solution ?? '',
        follow_up_plan: props.weeklyNote?.follow_up_plan ?? '',
        saving: false,
    });

    function prefillFromMembers() {
        const allRows = props.segments.flatMap(seg => seg.rows);

        // ── Uraian: grouped per RK, each kegiatan with who did it ──────────
        const lines: string[] = [];
        let n = 1;
        for (const row of allRows) {
            const items = (row.claims ?? []).filter(c => c.uraian);
            if (!items.length) continue;
            lines.push(`${n}. ${row.rk_description}`);
            for (const item of items) lines.push(`   - ${item.uraian} (${item.name})`);
            n++;
        }
        if (lines.length) weeklyNoteForm.value.uraian = lines.join('\n');

        // ── Kendala / Solusi / RTL: collect unique non-empty aggregated values ─
        function joinAgg(values: (string | null | undefined)[]): string {
            return [...new Set(values.filter((v): v is string => !!v && v.trim() !== ''))]
                .join('\n');
        }
        const obstacles = joinAgg(allRows.map(r => r.obstacle_aggregated));
        const solutions = joinAgg(allRows.map(r => r.solution_aggregated));
        const followUps = joinAgg(allRows.map(r => r.follow_up_aggregated));
        if (obstacles) weeklyNoteForm.value.obstacle = obstacles;
        if (solutions) weeklyNoteForm.value.solution = solutions;
        if (followUps) weeklyNoteForm.value.follow_up_plan = followUps;
    }

    function saveWeeklyNote() {
        weeklyNoteForm.value.saving = true;
        router.post(route('team-recap.weekly-note.store'), {
            team_id: props.selectedTeamId,
            week_start: props.weekStart,
            uraian: weeklyNoteForm.value.uraian,
            obstacle: weeklyNoteForm.value.obstacle,
            solution: weeklyNoteForm.value.solution,
            follow_up_plan: weeklyNoteForm.value.follow_up_plan,
        }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => { weeklyNoteForm.value.saving = false; },
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
        rowMerge,
        weeklyNoteForm,
        prefillFromMembers,
        saveWeeklyNote,
        evidenceTypeLabel,
        showEvidenceForm,
        evidenceForm,
        submitEvidence,
        deleteEvidence,
    };
}
