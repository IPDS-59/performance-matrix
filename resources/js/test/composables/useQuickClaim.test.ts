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

    it('needs RK, target, realisasi, satuan, Kendala and a required Projek before saving', () => {
        const ready = { obstacle: '-', target: '1', realization: '1', target_unit: 'Kegiatan' };
        expect(isReadyToSave(form())).toBe(false);
        expect(isReadyToSave(form(ready))).toBe(true);
        expect(isReadyToSave(form({ ...ready, performance_plan_id: null }))).toBe(false);
        expect(isReadyToSave(form({ ...ready, target: '0' }))).toBe(false);
        expect(isReadyToSave(form({ ...ready, target_unit: ' ' }))).toBe(false);
        expect(isReadyToSave(form(ready), true)).toBe(false);
        expect(isReadyToSave(form({ ...ready, project_id: '7' }), true)).toBe(true);
    });

    it('takes realisasi from the kipApp progres and the unit from the RK', () => {
        const f = form();
        quickFill(f, { progress: 50, unit: 'Dokumen' });
        expect(f).toMatchObject({ target: '1', realization: '0.5', target_unit: 'Dokumen' });
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

import { suggestProject } from '@/composables/useQuickClaim';

describe('suggestProject', () => {
    const projects = [
        { id: 1, name: 'Metodologi dan Pengolahan Survei Statistik Kependudukan dan Ketenagakerjaan' },
        { id: 2, name: 'Metodologi dan Pengolahan Survei Statistik Kesejahteraan Rakyat' },
        { id: 3, name: 'Pengelolaan Jaringan dan Internet' },
        { id: 4, name: 'Pembangunan dan Pengembangan Inovasi' },
    ];

    it('finds the project named in the RK text', () => {
        expect(suggestProject('Terlaksananya Dukungan Metodologi dan Pengolahan Survei terkait Statistik Kependudukan dan Ketenagakerjaan yang Berkualitas', projects)?.id).toBe(1);
        expect(suggestProject('Terlaksananya Dukungan Metodologi dan Pengolahan Survei terkait Statistik Kesejahteraan Rakyat yang Berkualitas', projects)?.id).toBe(2);
        expect(suggestProject('Terlaksananya Pengelolaan Jaringan dan Internet yang Handal dan sesuai SLA', projects)?.id).toBe(3);
    });

    it('stays empty when no project clearly matches', () => {
        expect(suggestProject('Tersedianya Inovasi yang Bermanfaat', projects)).toBeNull();
        expect(suggestProject('Terselenggaranya Zona Integritas WBK', projects)).toBeNull();
    });
});
