import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { RecapSegment, RecapRow, TeamOption, TeamRecapEvidence, WeeklyTeamNote } from '@/types';
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
    weeklyNote: WeeklyTeamNote | null;
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

    // ── Single weekly PJ note ──────────────────────────────────────────────

    const weeklyNoteForm = ref({
        uraian: props.weeklyNote?.uraian ?? '',
        obstacle: props.weeklyNote?.obstacle ?? '',
        solution: props.weeklyNote?.solution ?? '',
        follow_up_plan: props.weeklyNote?.follow_up_plan ?? '',
        saving: false,
    });

    function prefillUraianFromMembers() {
        // Collect all uraian_items across every row in every segment
        const allItems = props.segments.flatMap(seg =>
            seg.rows.flatMap(row => row.uraian_items ?? [])
        );
        if (!allItems.length) return;

        // Group by contributor name
        const byPerson = new Map<string, string[]>();
        for (const item of allItems) {
            if (!byPerson.has(item.name)) byPerson.set(item.name, []);
            byPerson.get(item.name)!.push(item.uraian);
        }

        // Format as numbered list: "1. Nama\n   - uraian"
        const lines: string[] = [];
        let i = 1;
        for (const [name, uraians] of byPerson) {
            lines.push(`${i}. ${name}`);
            for (const u of uraians) {
                lines.push(`   - ${u}`);
            }
            i++;
        }
        weeklyNoteForm.value.uraian = lines.join('\n');
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
        weeklyNoteForm,
        prefillUraianFromMembers,
        saveWeeklyNote,
        evidenceTypeLabel,
        showEvidenceForm,
        evidenceForm,
        submitEvidence,
        deleteEvidence,
    };
}
