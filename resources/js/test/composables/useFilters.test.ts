import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { useFilters } from '@/composables/useFilters';

describe('useFilters', () => {
    const FIXED_YEAR = 2026;
    const FIXED_MONTH = 4; // April

    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(FIXED_YEAR, FIXED_MONTH - 1, 11));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('defaults to current year and month when no args', () => {
        const { year, month, teamId } = useFilters();

        expect(year.value).toBe(FIXED_YEAR);
        expect(month.value).toBe(FIXED_MONTH);
        expect(teamId.value).toBeNull();
    });

    it('accepts partial defaults', () => {
        const { year, month, teamId } = useFilters({ year: 2025, teamId: 3 });

        expect(year.value).toBe(2025);
        expect(month.value).toBe(FIXED_MONTH);
        expect(teamId.value).toBe(3);
    });

    it('accepts full defaults', () => {
        const { year, month, teamId } = useFilters({ year: 2024, month: 7, teamId: 1 });

        expect(year.value).toBe(2024);
        expect(month.value).toBe(7);
        expect(teamId.value).toBe(1);
    });

    it('reset() restores to current date defaults', () => {
        const { year, month, teamId, reset } = useFilters({ year: 2024, month: 1, teamId: 5 });

        reset();

        expect(year.value).toBe(FIXED_YEAR);
        expect(month.value).toBe(FIXED_MONTH);
        expect(teamId.value).toBeNull();
    });

    it('returned refs are reactive', () => {
        const { year, month, teamId } = useFilters();

        year.value = 2030;
        month.value = 12;
        teamId.value = 7;

        expect(year.value).toBe(2030);
        expect(month.value).toBe(12);
        expect(teamId.value).toBe(7);
    });
});
