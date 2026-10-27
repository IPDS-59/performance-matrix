import { describe, expect, it } from 'vitest';
import { formatNumber } from '@/composables/useNumber';

describe('formatNumber', () => {
    it('groups thousands the Indonesian way', () => {
        expect(formatNumber(1293)).toBe('1.293');
        expect(formatNumber('1293.5')).toBe('1.293,5');
        expect(formatNumber(1234567)).toBe('1.234.567');
    });
    it('shows a dash for nothing', () => {
        expect(formatNumber(null)).toBe('-');
        expect(formatNumber(undefined)).toBe('-');
    });
});
