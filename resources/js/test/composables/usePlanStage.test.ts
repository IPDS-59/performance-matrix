import { describe, expect, it } from 'vitest';
import { needsManualPush, planStage } from '@/composables/usePlanStage';

const plan = (over: Partial<Parameters<typeof planStage>[0]>) => ({ status: 'planned' as const, in_kipapp: false, sent: false, ...over });

describe('planStage', () => {
    it('walks the plan through the kipApp chain with one next action each', () => {
        expect(planStage(plan({}))).toMatchObject({ key: 'planned', action: 'push', step: 0 });
        expect(planStage(plan({ status: 'pushed', in_kipapp: true }))).toMatchObject({ key: 'in_kipapp', action: 'complete', step: 1 });
        expect(planStage(plan({ status: 'in_progress', in_kipapp: true }))).toMatchObject({ key: 'in_progress', action: 'complete', step: 2 });
        expect(planStage(plan({ status: 'done', in_kipapp: true }))).toMatchObject({ key: 'done', action: 'send', actionLabel: 'Kirim ke atasan', step: 3 });
        expect(planStage(plan({ status: 'done', in_kipapp: true, sent: true }))).toMatchObject({ key: 'sent', action: 'unsend', step: 4 });
    });

    it('shows no send step while the setting is off', () => {
        const done = plan({ status: 'done', in_kipapp: true });
        const sent = plan({ status: 'done', in_kipapp: true, sent: true });

        expect(planStage(done, false)).toMatchObject({ key: 'done', action: null, actionLabel: null });
        expect(planStage(sent, false)).toMatchObject({ key: 'sent', action: null });
        expect(planStage(plan({}), false).action).toBe('push');
        expect(planStage(plan({ status: 'pushed', in_kipapp: true }), false).action).toBe('complete');
    });

    it('keeps "kirim ke kipApp" and "kirim ke atasan" apart', () => {
        expect(planStage(plan({})).actionLabel).toBe('Kirim ke kipApp');
        expect(planStage(plan({ status: 'done', in_kipapp: true })).actionLabel).toBe('Kirim ke atasan');
    });
});

describe('needsManualPush', () => {
    const row = (over: Partial<Parameters<typeof needsManualPush>[0]> = {}) => ({ source: 'member', push_error: null, ...over });

    it('hides the button for a kegiatan Kinetik sends itself once complete', () => {
        expect(needsManualPush(row())).toBe(false);
        expect(needsManualPush(row({ source: 'pj' }))).toBe(false);
        expect(needsManualPush(row({ source: 'rtl' }))).toBe(false);
    });

    it('keeps it for an imported kegiatan, which is never sent on its own', () => {
        expect(needsManualPush(row({ source: 'import' }))).toBe(true);
    });

    it('keeps it to try again after kipApp refused', () => {
        expect(needsManualPush(row({ push_error: 'kipApp tidak dapat dihubungi.' }))).toBe(true);
    });

    it('leaves a look-alike import to "Kirim tetap"', () => {
        expect(needsManualPush(row({ source: 'import', push_error: 'Mirip dengan kegiatan yang sudah ada di kipApp (2026-11-02): "x".' }))).toBe(false);
    });
});
