import { describe, expect, it } from 'vitest';
import { creditProgress, formatAk, nextStepLabel } from '@/composables/useCreditStatus';
import type { CreditSummary } from '@/types';

const credit = (over: Partial<CreditSummary>) => ({ employee_id: 1, name: 'A', jabatan: null, golongan: null, pangkat: null, status: 'progress', ...over }) as CreditSummary;

describe('useCreditStatus', () => {
    it('formats AK with Indonesian decimals', () => {
        expect(formatAk(23.438)).toBe('23,438');
        expect(formatAk(null)).toBe('—');
    });

    it('caps progress at 100 and names the next step', () => {
        expect(creditProgress(credit({ earned: 25, target: 100 }))).toBe(25);
        expect(creditProgress(credit({ earned: 120, target: 100 }))).toBe(100);
        expect(nextStepLabel(credit({ kind: 'jenjang', next_label: 'Ahli Muda' }))).toBe('naik jenjang ke Ahli Muda');
        expect(nextStepLabel(credit({ kind: 'pangkat', next_label: 'III/b' }))).toBe('naik pangkat ke III/b');
        expect(nextStepLabel(credit({ kind: null }))).toBeNull();
    });
});
