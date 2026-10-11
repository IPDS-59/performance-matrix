import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { RecapRow, RecapSegment } from '@/types';

/** The row whose override holds the text: the group lead for merged rows. */
export function textKey(row: RecapRow): string {
    return row.merge_key ?? row.row_key;
}

/** performance_plan_id / project_id to save the text on (row key is "plan:project"). */
export function textTarget(row: RecapRow): { performance_plan_id: number; project_id: number | null } {
    const [plan, project] = textKey(row).split(':');
    return { performance_plan_id: Number(plan), project_id: project ? Number(project) : null };
}

/**
 * Keep merged rows together after sorting or filtering: each group moves to
 * the position of its first row, lead row first.
 */
export function groupAdjacent(rows: RecapRow[]): RecapRow[] {
    const groups = new Map<string, RecapRow[]>();
    for (const row of rows) {
        const key = textKey(row);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key)!.push(row);
    }
    return [...groups.entries()].flatMap(([key, members]) =>
        [...members].sort((a, b) => Number(b.row_key === key) - Number(a.row_key === key)));
}

/** Number of rows in the row's merge group within the segment (1 = not merged). */
export function groupSize(seg: RecapSegment, row: RecapRow): number {
    return row.merge_key ? seg.rows.filter(r => r.merge_key === row.merge_key).length : 1;
}

export function isGroupLead(row: RecapRow): boolean {
    return row.merge_key === row.row_key;
}

/**
 * Row selection and the Gabungkan / Pisahkan requests for one recap page.
 *
 * @param period team_id and the period fields of the page
 * @param onDone called after a merge or split, e.g. to reset cached forms
 */
export function useRecapMerge(period: () => Record<string, string | number | null>, onDone: () => void) {
    const selected = ref<Record<string, string[]>>({});
    const busy = ref(false);

    const segKey = (seg: RecapSegment) => String(seg.project_id ?? 'none');

    function isSelected(seg: RecapSegment, row: RecapRow): boolean {
        return (selected.value[segKey(seg)] ?? []).includes(row.row_key);
    }

    function toggle(seg: RecapSegment, row: RecapRow) {
        const key = segKey(seg);
        const current = selected.value[key] ?? [];
        selected.value[key] = current.includes(row.row_key) ? current.filter(k => k !== row.row_key) : [...current, row.row_key];
    }

    function selectedCount(seg: RecapSegment): number {
        return (selected.value[segKey(seg)] ?? []).length;
    }

    function finish() {
        selected.value = {};
        onDone();
    }

    /** Merge the selected rows of a segment; the first row on screen becomes the lead. */
    function merge(seg: RecapSegment, visibleRows: RecapRow[]) {
        const keys = selected.value[segKey(seg)] ?? [];
        const rows = visibleRows.filter(r => keys.includes(r.row_key));
        if (rows.length < 2) return;
        busy.value = true;
        router.post(route('team-recap.merge'), {
            ...period(),
            project_id: seg.project_id,
            performance_plan_ids: rows.map(r => r.performance_plan_id),
        }, { preserveScroll: true, onSuccess: finish, onFinish: () => (busy.value = false) });
    }

    function split(row: RecapRow) {
        if (!row.merge_key) return;
        busy.value = true;
        router.post(route('team-recap.split'), { ...period(), merge_key: row.merge_key }, {
            preserveScroll: true, onSuccess: finish, onFinish: () => (busy.value = false),
        });
    }

    return { busy, isSelected, toggle, selectedCount, merge, split };
}
