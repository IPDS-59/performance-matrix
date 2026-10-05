import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { RecapSection, RecapSegment, RecapRow, TeamOption, TeamRecapEvidence, WeeklyTeamNote, RecapLockState, MemberCompleteness } from '@/types';
import { useDateFormat } from '@/composables/useDateFormat';
import { groupAdjacent, useRecapMerge } from '@/composables/useRecapMerge';

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
    previousWeeks: RecapSection[];
}

/** Members write "-" (or "—", "N/A") when there is no obstacle. */
export function hasObstacle(text: string | null | undefined): boolean {
    return !['', '-', '—', 'n/a'].includes((text ?? '').trim().toLowerCase());
}

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
        return (row.achievement ?? 0) < 100 || hasObstacle(row.obstacle_aggregated);
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

    // ── Per-row paraphrase permission ──────────────────────────────────────

    function rowCanParaphrase(row: RecapRow): boolean {
        if (props.lock) return false;
        return props.canManage || (props.currentEmployeeId !== null && row.pic_employee_id === props.currentEmployeeId);
    }

    // ── Gabungkan / Pisahkan ───────────────────────────────────────────────

    const rowMerge = useRecapMerge(
        () => ({ team_id: props.selectedTeamId, period_type: 'week', period_year: Number(props.weekStart.slice(0, 4)), week_start: props.weekStart }),
        () => {},
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
        sortDir,
        toggleSort,
        attentionOnly,
        attentionCount,
        filteredRows,
        rowCanParaphrase,
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
