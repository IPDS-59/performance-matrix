import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import { useRowSelection } from '@/composables/useRowSelection';
import type { PlanItemRow } from '@/types';
import { bulkCopy } from '@/composables/usePlanBulk';

const row = (id: number, over: Partial<PlanItemRow> = {}) => ({ id, can_send: true, sent: false, ...over }) as PlanItemRow;

describe('useRowSelection', () => {
    it('lets only rows the server marks sendable be checked, and splits them into send and cancel', async () => {
        const rows = ref([row(1), row(2, { sent: true }), row(3, { can_send: false })]);
        const s = useRowSelection(rows);

        s.toggleAll(rows.value, true);
        expect(s.selected.value).toEqual([1, 2]);
        expect(s.toSend.value.map(r => r.id)).toEqual([1]);
        expect(s.toCancel.value.map(r => r.id)).toEqual([2]);

        s.toggle(1, false);
        expect(s.selected.value).toEqual([2]);
        s.clear();
        expect(s.selected.value).toEqual([]);
    });

    it('drops a checked row that leaves the page or stops being sendable', async () => {
        const rows = ref([row(1), row(2)]);
        const s = useRowSelection(rows);
        s.toggleAll(rows.value, true);

        rows.value = [row(1, { can_send: false })];
        await Promise.resolve();
        expect(s.selected.value).toEqual([]);
    });

    it('keeps kipApp texts for the confirmation', () => {
        expect(bulkCopy.send.description).toBe('Apakah Anda yakin mengirim kegiatan ini untuk dinilai?');
        expect(bulkCopy.cancel.description).toBe('Apakah Anda yakin membatalkan pengiriman dari kegiatan yang dipilih?');
    });
});
