import { describe, expect, it } from 'vitest';
import { groupAdjacent, textTarget } from '@/composables/useRecapMerge';
import type { RecapRow } from '@/types';

const row = (row_key: string, merge_key: string | null = null) => ({ row_key, merge_key }) as RecapRow;

describe('useRecapMerge helpers', () => {
    it('keeps a merged group together, lead first, at its first position', () => {
        const sorted = [row('3:1', '1:1'), row('2:1'), row('1:1', '1:1'), row('4:1')];
        expect(groupAdjacent(sorted).map(r => r.row_key)).toEqual(['1:1', '3:1', '2:1', '4:1']);
    });

    it('saves merged text on the lead row', () => {
        expect(textTarget(row('3:7', '1:7'))).toEqual({ performance_plan_id: 1, project_id: 7 });
        expect(textTarget(row('5:'))).toEqual({ performance_plan_id: 5, project_id: null });
    });
});
