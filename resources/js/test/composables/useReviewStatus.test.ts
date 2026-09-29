import { describe, expect, it } from 'vitest';
import { reviewStatus } from '@/composables/useReviewStatus';

describe('reviewStatus', () => {
    it('uses the meeting thresholds', () => {
        expect(reviewStatus(null)).toBe('none');
        expect(reviewStatus(120)).toBe('achieved');
        expect(reviewStatus(100)).toBe('achieved');
        expect(reviewStatus(99.9)).toBe('progress');
        expect(reviewStatus(70)).toBe('progress');
        expect(reviewStatus(69.9)).toBe('low');
        expect(reviewStatus(0)).toBe('low');
    });
});
