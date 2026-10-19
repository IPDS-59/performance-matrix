import { describe, expect, it } from 'vitest';
import { dateEndAfterRangeToggle, defaultProgress, entryFormState, entryPayload, quarterBounds, todayIso } from '@/composables/usePlanEntry';
import type { PlanItemRow } from '@/types';

const row = (over: Partial<PlanItemRow> = {}): PlanItemRow => ({
    id: 7, team_id: 1, description: 'Entri data SE2026', performance_plan_id: 3, rk_name: 'RK', project_id: null,
    date_start: '2026-10-05', date_end: '2026-10-05', time_start: null, time_end: null, target: 12, target_unit: 'blok',
    capaian: 4, capaian_note: 'Empat blok', evidence_url: null, progress: 30, status: 'planned', source: 'member', push_error: null,
    in_kipapp: false, sent: false, sent_at: null, is_capaian_skp: false, startable: true, can_edit: true, can_delete: true, can_send: false, lock_reason: null,
    ...over,
});

describe('usePlanEntry', () => {
    it('formats today in local time', () => {
        expect(todayIso(new Date(2026, 9, 8, 23, 59))).toBe('2026-10-08');
    });

    it('starts at progres 100 for a date before today and 0 otherwise', () => {
        expect(defaultProgress('2026-10-07', '2026-10-08')).toBe(100);
        expect(defaultProgress('2026-10-08', '2026-10-08')).toBe(0);
        expect(defaultProgress('2026-10-09', '2026-10-08')).toBe(0);
    });

    it('gives the quarter bounds of the date inside the SKP year', () => {
        expect(quarterBounds(2026, '2026-10-08')).toEqual({ min: '2026-10-01', max: '2026-12-31' });
        expect(quarterBounds(2026, '2026-02-10')).toEqual({ min: '2026-01-01', max: '2026-03-31' });
        expect(quarterBounds(2026, '2026-05-01')).toEqual({ min: '2026-04-01', max: '2026-06-30' });
    });

    it('builds an empty form, an edit form and a copy form with the source as parent', () => {
        const empty = entryFormState(null, null, '2026-10-08', '2026-10-17');
        expect(empty).toMatchObject({ use_range: false, use_time: false, progres: 100, is_capaian_skp: true, parent_id: null, time_start: '08:00', time_end: '16:00' });

        const edit = entryFormState(row({ date_end: '2026-10-07', time_start: '09:00', time_end: '11:00' }), null, '', '2026-10-17');
        expect(edit).toMatchObject({ use_range: true, use_time: true, progres: 30, is_capaian_skp: false, parent_id: null });

        const copy = entryFormState(null, row(), '', '2026-10-17');
        expect(copy).toMatchObject({ description: 'Entri data SE2026', parent_id: 7, progres: 30 });
    });

    it('keeps progres out of useForm\'s own progress property and sends it as progress', () => {
        const form = entryFormState(null, null, '2026-10-08', '2026-10-17');
        expect(form).not.toHaveProperty('progress');
        expect(entryPayload({ ...form, progres: 40 })).toMatchObject({ progress: 40 });
        expect(entryPayload({ ...form, progres: '' })).toMatchObject({ progress: 0 });
    });

    it('sends an end date and times only when their checkboxes are on', () => {
        const form = entryFormState(null, null, '2026-10-08', '2026-10-08');
        form.date_end = '2026-10-10';

        expect(entryPayload(form)).toMatchObject({ date_start: '2026-10-08', date_end: '2026-10-08', time_start: null, time_end: null });
        expect(entryPayload({ ...form, use_range: true, use_time: true })).toMatchObject({ date_end: '2026-10-10', time_start: '08:00', time_end: '16:00' });
        expect(entryPayload({ ...form, use_time: true })).toMatchObject({ date_end: '2026-10-08', time_start: '08:00' });
    });

    it('keeps the first date when the range is turned off, and never an end before the start', () => {
        expect(dateEndAfterRangeToggle(false, '2026-10-08', '2026-10-10')).toBe('2026-10-08');
        expect(dateEndAfterRangeToggle(true, '2026-10-08', '2026-10-10')).toBe('2026-10-10');
        expect(dateEndAfterRangeToggle(true, '2026-10-12', '2026-10-10')).toBe('2026-10-12');
    });
});
