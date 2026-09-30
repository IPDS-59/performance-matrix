import type { RecapRow, RecapSection, RecapSegment } from '@/types';

export const NO_PROJECT_KEY = 'none';

export const projectKey = (projectId: number | null | undefined): string => (projectId == null ? NO_PROJECT_KEY : String(projectId));

/** What a row says: the PJ's uraian, else the members' uraian. */
function rowText(row: RecapRow): string {
    const members = (row.claims ?? []).map(c => c.uraian).filter((u): u is string => !!u);
    return row.pj_uraian?.trim() || [...new Set(members)].join('; ') || row.rk_description;
}

/**
 * Draft of a Projek's monthly or quarterly narrative from its lower periods:
 * one line per week (or month) that has rows for the Projek.
 */
export function draftProjectSummary(projectId: number | null, sections: RecapSection[]): string {
    return sections
        .map(section => {
            const segment = section.segments.find(s => projectKey(s.project_id) === projectKey(projectId));
            if (!segment?.rows.length) return null;
            return `${section.label}: ${segment.rows.map(rowText).join('; ')}`;
        })
        .filter((line): line is string => line !== null)
        .join('\n');
}

/** Projek that appear in the period or any of its sections, in order of first appearance. */
export function summaryProjects(segments: RecapSegment[], sections: RecapSection[]): Array<{ id: number | null; name: string }> {
    const seen = new Map<string, { id: number | null; name: string }>();
    for (const seg of [...segments, ...sections.flatMap(s => s.segments)]) {
        const key = projectKey(seg.project_id);
        if (!seen.has(key)) seen.set(key, { id: seg.project_id, name: seg.project_name });
    }
    return [...seen.values()];
}
