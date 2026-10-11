import { describe, expect, it } from 'vitest';
import { hasObstacle } from '@/composables/useTeamWeeklyRecap';

describe('hasObstacle', () => {
    it('ignores the placeholders members write for "no obstacle"', () => {
        for (const v of [null, undefined, '', ' - ', '—', 'N/A']) expect(hasObstacle(v)).toBe(false);
    });

    it('counts real text', () => {
        expect(hasObstacle('Bukti dukung kabkot belum lengkap')).toBe(true);
    });
});

describe('rowAchievement', () => {
    it('adds the numbers of the merged kegiatan', async () => {
        const { rowAchievement } = await import('@/composables/useTeamWeeklyRecap');
        const line = (target: number | null, realization: number | null) => ({ target, realization });
        expect(rowAchievement({ claims: [line(3, 3), line(2, 1)] } as never)).toBe(80);
        expect(rowAchievement({ claims: [line(null, null)] } as never)).toBeNull();
    });
});
