import { describe, it, expect } from 'vitest';
import { NO_PROJECT, isReadyToSave, quickFill, splitBulkErrors, toClaimPayload } from '@/composables/useQuickClaim';

function form(overrides = {}) {
    return { performance_plan_id: 5, project_id: NO_PROJECT, target: '', realization: '', target_unit: '', obstacle: '', ...overrides };
}

describe('useQuickClaim', () => {
    it('fills only empty fields with the common case', () => {
        const f = form({ target: '3' });
        quickFill(f);
        expect(f).toMatchObject({ target: '3', realization: '1', target_unit: 'Kegiatan', obstacle: '-' });
    });

    it('keeps a written obstacle', () => {
        const f = form({ obstacle: 'Server down' });
        quickFill(f);
        expect(f.obstacle).toBe('Server down');
    });

    it('needs an RK and a Kendala before saving', () => {
        expect(isReadyToSave(form())).toBe(false);
        expect(isReadyToSave(form({ obstacle: '-' }))).toBe(true);
        expect(isReadyToSave(form({ obstacle: '-', performance_plan_id: null }))).toBe(false);
    });

    it('turns the no-project sentinel into null', () => {
        expect(toClaimPayload(form()).project_id).toBeNull();
        expect(toClaimPayload(form({ project_id: '7' })).project_id).toBe(7);
    });

    it('maps bulk errors back to their batch position', () => {
        expect(splitBulkErrors({ 'claims.1.obstacle': 'Wajib', 'claims.0.target': 'Angka', other: 'x' }))
            .toEqual({ 1: { obstacle: 'Wajib' }, 0: { target: 'Angka' } });
    });
});
