import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { RecapSection, TeamOption, TeamRecapEvidence, RecapLockState, MemberCompleteness, WeeklyRow, WeeklySegment } from '@/types';
import { useDateFormat } from '@/composables/useDateFormat';

export interface TeamWeeklyRecapProps {
    teams: TeamOption[];
    selectedTeamId: number | null;
    segments: WeeklySegment[];
    evidences: TeamRecapEvidence[];
    weekStart: string;
    weekEnd: string;
    prevWeek: string;
    nextWeek: string;
    canManage: boolean;
    canLock: boolean;
    lock: RecapLockState | null;
    currentEmployeeId: number | null;
    members: MemberCompleteness[];
    previousWeeks: RecapSection[];
}

/** Members write "-" (or "—", "N/A") when there is no obstacle. */
export function hasObstacle(text: string | null | undefined): boolean {
    return !['', '-', '—', 'n/a'].includes((text ?? '').trim().toLowerCase());
}

/** Capaian of a whole output row: the sum of its kegiatan. */
export function rowAchievement(row: WeeklyRow): number | null {
    const target = row.claims.reduce((n, c) => n + (c.target ?? 0), 0);
    const realization = row.claims.reduce((n, c) => n + (c.realization ?? 0), 0);
    return target > 0 ? (realization / target) * 100 : null;
}

export function useTeamWeeklyRecap(props: TeamWeeklyRecapProps) {
    const { formatWeekRange } = useDateFormat();

    function navigate(params: Record<string, string | number>) {
        router.get(route('team-recap.weekly'), {
            team: props.selectedTeamId ?? undefined,
            week: props.weekStart,
            ...params,
        }, { preserveState: false });
    }

    // ── "Perlu perhatian" filter ───────────────────────────────────────────

    const attentionOnly = ref(false);

    function needsAttention(row: WeeklyRow): boolean {
        return (rowAchievement(row) ?? 0) < 100 || hasObstacle(row.obstacle);
    }

    function attentionCount(seg: WeeklySegment): number {
        return seg.rows.filter(needsAttention).length;
    }

    function filteredRows(seg: WeeklySegment): WeeklyRow[] {
        return attentionOnly.value ? seg.rows.filter(needsAttention) : seg.rows;
    }

    // ── Who may write the PJ text of a row ─────────────────────────────────

    function rowCanParaphrase(row: WeeklyRow): boolean {
        if (props.lock) return false;
        return props.canManage || (props.currentEmployeeId !== null && row.claims.some(c => c.pic_employee_id === props.currentEmployeeId));
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
        attentionOnly,
        attentionCount,
        filteredRows,
        rowCanParaphrase,
        evidenceTypeLabel,
        showEvidenceForm,
        evidenceForm,
        submitEvidence,
        deleteEvidence,
    };
}
