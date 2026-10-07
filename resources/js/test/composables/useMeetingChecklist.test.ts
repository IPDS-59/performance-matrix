import { describe, it, expect } from 'vitest';
import { periodChecklist } from '@/composables/useMeetingChecklist';
import type { RecapRow } from '@/types';


describe('periodChecklist', () => {
    it('counts signed-off rows', () => {
        const rows = [{ is_confirmed: true }, { is_confirmed: false }] as RecapRow[];
        const steps = periodChecklist({ segments: [{ project_id: null, project_name: 'X', rows }], lock: null });
        expect(steps[0]).toMatchObject({ done: false, detail: '1 dari 2 baris dikonfirmasi.' });
    });
});
