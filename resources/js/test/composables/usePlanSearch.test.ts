import { describe, expect, it } from 'vitest';
import { planRowMatches } from '@/composables/usePlanSearch';
import type { PlanItemRow } from '@/types';

const row = { description: 'Entri data', rk_name: 'RK Inovasi', capaian_note: 'Blok sembilan selesai', date_start: '2026-10-05', date_end: '2026-10-07', time_start: '09:00', time_end: null } as PlanItemRow;
const format = (d: string) => (d === '2026-10-05' ? '5 Oktober 2026' : '7 Oktober 2026');

describe('planRowMatches', () => {
    it('finds kegiatan, RK, capaian, dates and times', () => {
        for (const q of ['entri', 'inovasi', 'sembilan', '2026-10-07', 'oktober', '09:00']) expect(planRowMatches(row, q, format)).toBe(true);
        expect(planRowMatches(row, 'november', format)).toBe(false);
    });

    it('matches everything on an empty query', () => {
        expect(planRowMatches(row, '  ', format)).toBe(true);
    });
});
