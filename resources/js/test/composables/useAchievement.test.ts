import { describe, expect, it } from 'vitest';
import { achievementPercent, periodText, withUnit } from '@/composables/useAchievement';

describe('achievementPercent', () => {
    it('rounds capaian over target', () => {
        expect(achievementPercent(9, 11)).toBe(82);
        expect(achievementPercent(0, 5)).toBe(0);
        expect(achievementPercent(12, 10)).toBe(120);
    });

    it('is null without both numbers or with a zero target', () => {
        expect(achievementPercent(null, 5)).toBeNull();
        expect(achievementPercent(3, null)).toBeNull();
        expect(achievementPercent(3, 0)).toBeNull();
    });
});

describe('withUnit', () => {
    it('joins number and unit, and shows a dash for no number', () => {
        expect(withUnit(9, 'blok')).toBe('9 blok');
        expect(withUnit(9, null)).toBe('9');
        expect(withUnit(null, 'blok')).toBe('–');
    });
});

describe('periodText', () => {
    const fmt = (d: string) => d.slice(8);
    it('shows a range, one day, and a clock time', () => {
        expect(periodText({ date_start: '2026-10-12', date_end: '2026-10-14', time_start: null, time_end: null }, fmt)).toBe('12 – 14');
        expect(periodText({ date_start: '2026-10-12', date_end: '2026-10-12', time_start: '09:00', time_end: '11:00' }, fmt)).toBe('12, 09:00–11:00');
    });
});
