import { describe, expect, it } from 'vitest';
import { TOURS } from '@/data/tours';

describe('page tours', () => {
    const entries = Object.entries(TOURS);

    it('has at least one step per tour, each with a selector, a title and a description', () => {
        for (const [key, tour] of entries) {
            expect(tour.steps.length, key).toBeGreaterThan(0);
            for (const step of tour.steps) {
                expect(step.element.trim(), key).not.toBe('');
                expect(step.title.trim(), key).not.toBe('');
                expect(step.description.trim().length, `${key}: ${step.title}`).toBeGreaterThan(20);
            }
        }
    });

    it('does not use the same selector twice in one tour', () => {
        for (const [key, tour] of entries) {
            const selectors = tour.steps.map((s) => s.element);
            expect(new Set(selectors).size, key).toBe(selectors.length);
        }
    });
});
