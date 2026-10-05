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
