import type { PlanItemRow } from '@/types';

/** Search over kegiatan, RK, uraian capaian and the dates (as YYYY-MM-DD and as shown on screen). */
export function planRowMatches(row: PlanItemRow, query: string, format: (d: string) => string): boolean {
    const q = query.trim().toLowerCase();
    if (!q) return true;
    const haystack = [
        row.description, row.rk_name, row.capaian_note,
        row.date_start, row.date_end, format(row.date_start), format(row.date_end),
        row.time_start, row.time_end,
    ];
    return haystack.filter(Boolean).join(' ').toLowerCase().includes(q);
}
