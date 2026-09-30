import { describe, expect, it } from 'vitest';
import { draftProjectSummary, summaryProjects } from '@/composables/useRecapSummary';
import type { RecapRow, RecapSection } from '@/types';

const row = (over: Partial<RecapRow>) => ({ rk_description: 'RK', pj_uraian: null, claims: [], ...over }) as RecapRow;
const sections: RecapSection[] = [
    { label: 'Minggu 1', start: '2026-06-01', end: '2026-06-07', segments: [{ project_id: 5, project_name: 'Sakernas', rows: [row({ pj_uraian: 'Pelatihan innas' })] }] },
    { label: 'Minggu 2', start: '2026-06-08', end: '2026-06-14', segments: [{ project_id: 7, project_name: 'Jaringan', rows: [row({})] }] },
    { label: 'Minggu 3', start: '2026-06-15', end: '2026-06-21', segments: [{ project_id: 5, project_name: 'Sakernas', rows: [row({ claims: [
        { claim_id: 1, name: 'A', uraian: 'Entri data', target: 1, realization: 1, target_unit: null, achievement: 100, adjusted_by: null },
        { claim_id: 2, name: 'B', uraian: 'Entri data', target: 1, realization: 1, target_unit: null, achievement: 100, adjusted_by: null },
    ] })] }] },
];

describe('useRecapSummary', () => {
    it('drafts one line per section that has the Projek', () => {
        expect(draftProjectSummary(5, sections)).toBe('Minggu 1: Pelatihan innas\nMinggu 3: Entri data');
    });

    it('lists every Projek of the period once', () => {
        expect(summaryProjects([], sections).map(p => p.name)).toEqual(['Sakernas', 'Jaringan']);
    });
});
