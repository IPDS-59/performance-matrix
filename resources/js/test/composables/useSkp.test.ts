import { describe, expect, it } from 'vitest';
import { stepOf } from '@/composables/useSkp';

describe('stepOf', () => {
    it('places a yearly SKP on its three steps', () => {
        expect(stepOf('yearly', 1)).toBe(0);
        expect(stepOf('yearly', 2)).toBe(1);
        expect(stepOf('yearly', 6)).toBe(2);
        expect(stepOf('yearly', null)).toBe(0);
    });

    it('places a quarter SKP on its three steps', () => {
        expect(stepOf('quarter', 1)).toBe(0);
        expect(stepOf('quarter', 5)).toBe(1);
        expect(stepOf('quarter', 7)).toBe(2);
    });
});
