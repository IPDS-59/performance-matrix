import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { RkEntry, RkPanel } from '@/types';

/** Entries that pass the search, the person filter and the "unread only" switch. */
export function filterEntries(
    entries: RkEntry[],
    opts: { query: string; onlyUnread: boolean; person: number | null; read: ReadonlySet<number> },
): RkEntry[] {
    const query = opts.query.trim().toLowerCase();

    return entries.filter(entry =>
        (opts.person === null || entry.employee_id === opts.person)
        && (!opts.onlyUnread || !opts.read.has(entry.employee_id))
        && (!query || `${entry.name} ${entry.lines.join(' ')}`.toLowerCase().includes(query)));
}

/** "Sisipkan": adds one sentence after what the PJ already wrote. */
export function appendSentence(text: string, line: string): string {
    const current = text.trim();
    return current ? `${current} ${line.trim()}` : line.trim();
}

export type PanelState = 'locked' | 'written' | 'empty';

/** Where an RK stands in the meeting: locked, written but open, or nothing yet. */
export function panelState(panel: Pick<RkPanel, 'recap'>): PanelState {
    if (panel.recap?.locked) return 'locked';
    return panel.recap?.paraphrase ? 'written' : 'empty';
}

/** Evidence of the weekly meeting (notula, foto, daftar hadir): the add form and delete. */
export function useRecapEvidence(getParams: () => { teamId: number | null; weekStart: string }) {
    const show = ref(false);
    const form = ref({ type: 'notula', title: '', url: '', errors: {} as Record<string, string>, processing: false });

    function submit() {
        const { teamId, weekStart } = getParams();
        form.value.processing = true;
        router.post(route('team-recap.evidence.store'), {
            team_id: teamId,
            week_start: weekStart,
            type: form.value.type,
            title: form.value.title,
            url: form.value.url,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                form.value.title = '';
                form.value.url = '';
                show.value = false;
            },
            onError: (errors: Record<string, string>) => { form.value.errors = errors; },
            onFinish: () => { form.value.processing = false; },
        });
    }

    function remove(id: number) {
        router.delete(route('team-recap.evidence.destroy', id), { preserveScroll: true });
    }

    return { show, form, submit, remove };
}

export const evidenceTypeLabel: Record<string, string> = { notula: 'Notula', photo: 'Foto', attendance: 'Daftar Hadir' };
