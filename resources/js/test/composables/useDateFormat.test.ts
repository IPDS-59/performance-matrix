import { describe, it, expect } from 'vitest';
import { useDateFormat } from '@/composables/useDateFormat';

describe('useDateFormat', () => {
    const { formatDate, formatWeekRange } = useDateFormat('id-ID');

    it('formats a full ISO timestamp to a friendly short date', () => {
        expect(formatDate('2026-06-01T00:00:00.000000Z')).toBe('1 Jun 2026');
    });

    it('formats a plain YYYY-MM-DD date without timezone drift', () => {
        expect(formatDate('2026-06-01')).toBe('1 Jun 2026');
    });

    it('returns empty string for null/undefined/empty', () => {
        expect(formatDate(null)).toBe('');
        expect(formatDate(undefined)).toBe('');
        expect(formatDate('')).toBe('');
    });

    it('returns the raw value when unparseable', () => {
        expect(formatDate('not-a-date')).toBe('not-a-date');
    });

    it('formats a week range with full month names', () => {
        expect(formatWeekRange('2026-06-01', '2026-06-07')).toBe('1 Juni 2026 — 7 Juni 2026');
    });
});
