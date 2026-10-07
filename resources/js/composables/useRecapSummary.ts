import type { RecapRow, RecapSection, RecapSegment, RecapSummaryText } from '@/types';

export const NO_PROJECT_KEY = 'none';

export const projectKey = (projectId: number | null | undefined): string => (projectId == null ? NO_PROJECT_KEY : String(projectId));

/** Members write "-" when there is nothing to report. */
const said = (v: string | null | undefined) => (v && v.trim() !== '-' ? v.trim() : null);

/** What a row says: the PJ's uraian, else the members' uraian. */
function rowText(row: RecapRow): string {
    const members = (row.claims ?? []).map(c => c.uraian).filter((u): u is string => !!u);
    return row.pj_uraian?.trim() || [...new Set(members)].join('; ') || row.rk_description;
}

/**
 * Draft of a Projek's monthly or quarterly narrative from its lower periods:
 * one line per week (or month) that has something to say, for the uraian and
 * for Permasalahan, Solusi and RTL.
 */
export function draftProjectSummary(projectId: number | null, sections: RecapSection[]): Record<keyof RecapSummaryText, string> {
    const lines = (pick: (row: RecapRow) => string | null) => sections
        .map(section => {
            const segment = section.segments.find(s => projectKey(s.project_id) === projectKey(projectId));
            const texts = [...new Set((segment?.rows ?? []).map(pick).filter((t): t is string => !!t))];
            return texts.length ? `${section.label}: ${texts.join('; ')}` : null;
        })
        .filter((line): line is string => line !== null)
        .join('\n');

    return {
        body: lines(rowText),
        obstacle: lines(r => said(r.obstacle)),
        solution: lines(r => said(r.solution)),
        follow_up_plan: lines(r => said(r.follow_up_plan)),
    };
}

/** Projek that appear in the period or any of its sections, in order of first appearance. */
export function summaryProjects(segments: RecapSegment[], sections: RecapSection[]): Array<{ id: number | null; name: string; leaderRk: string | null }> {
    const seen = new Map<string, { id: number | null; name: string; leaderRk: string | null }>();
    for (const seg of [...segments, ...sections.flatMap(s => s.segments)]) {
        const key = projectKey(seg.project_id);
        const known = seen.get(key);
        if (!known) seen.set(key, { id: seg.project_id, name: seg.project_name, leaderRk: seg.leader_rk ?? null });
        else if (!known.leaderRk && seg.leader_rk) known.leaderRk = seg.leader_rk;
    }
    return [...seen.values()];
}
