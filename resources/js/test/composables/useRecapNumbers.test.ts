import { describe, expect, it } from 'vitest';
import { achievementOf, totalsOf } from '@/composables/useRecapNumbers';

describe('useRecapNumbers', () => {
    it('gives no capaian until target and realisasi are filled', () => {
        expect(achievementOf('', '2')).toBeNull();
        expect(achievementOf('0', '2')).toBeNull();
        expect(achievementOf('4', '')).toBeNull();
        expect(achievementOf('4', '3')).toBe(75);
    });

    it('adds up the edited numbers of merged claims', () => {
        const claims = [{ claim_id: 1 }, { claim_id: 2 }] as never;
        const numbers = {
            1: { target: '3', realization: '3', target_unit: '' },
            2: { target: '2', realization: '1', target_unit: 'laporan' },
        };
        expect(totalsOf(claims, numbers)).toEqual({ target: 5, realization: 4, unit: 'laporan', achievement: 80 });
    });
});
