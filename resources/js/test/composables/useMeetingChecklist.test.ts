import { describe, it, expect } from 'vitest';
import { periodChecklist, weeklyChecklist } from '@/composables/useMeetingChecklist';
import type { MemberCompleteness, RecapRow, TeamRecapEvidence } from '@/types';

const member = (status: MemberCompleteness['status']): MemberCompleteness => ({ employee_id: 1, name: 'A', total: 1, saved: status === 'complete' ? 1 : 0, status });
const evidence = (type: TeamRecapEvidence['type']) => ({ id: 1, team_id: 1, period_type: 'week', type, url: 'https://x' }) as TeamRecapEvidence;

describe('weeklyChecklist', () => {
    it('ignores members without kipApp activity and names the missing evidence', () => {
        const steps = weeklyChecklist({
            members: [member('complete'), member('no_activity')],
            weeklyNote: null,
            evidences: [evidence('notula')],
            lock: null,
        });
        expect(steps.map(s => s.done)).toEqual([true, false, false, false]);
        expect(steps[2].detail).toBe('Belum ada: Dokumentasi, Daftar Hadir.');
    });

    it('is fully done when everything is in and locked', () => {
        const steps = weeklyChecklist({
            members: [member('complete')],
            weeklyNote: { id: 1, team_id: 1, week_start: '2026-06-01', uraian: 'x', obstacle: null, solution: null, follow_up_plan: null },
            evidences: [evidence('notula'), evidence('photo'), evidence('attendance')],
            lock: { locked_at: null, locked_by: 'PJ' },
        });
        expect(steps.every(s => s.done)).toBe(true);
    });
});

describe('periodChecklist', () => {
    it('counts signed-off rows', () => {
        const rows = [{ is_confirmed: true }, { is_confirmed: false }] as RecapRow[];
        const steps = periodChecklist({ segments: [{ project_id: null, project_name: 'X', rows }], lock: null });
        expect(steps[0]).toMatchObject({ done: false, detail: '1 dari 2 baris dikonfirmasi.' });
    });
});
