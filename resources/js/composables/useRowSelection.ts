import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';
import type { PlanItemRow } from '@/types';

/**
 * Checked rows of the kegiatan tables. Only rows the server marks `can_send` (in kipApp, not locked) or `can_push`
 * (only in Kinetik, date come) can be checked. Rows that vanish from the page, or stop being selectable, drop out.
 */
export function useRowSelection(rows: MaybeRefOrGetter<PlanItemRow[]>) {
    const selected = ref<number[]>([]);
    const selectable = computed(() => toValue(rows).filter(r => r.can_send || r.can_push));
    const chosen = computed(() => selectable.value.filter(r => selected.value.includes(r.id)));

    watch(selectable, (list) => {
        const ids = new Set(list.map(r => r.id));
        selected.value = selected.value.filter(id => ids.has(id));
    });

    function toggle(id: number, on: boolean) {
        selected.value = on ? [...new Set([...selected.value, id])] : selected.value.filter(x => x !== id);
    }

    function toggleAll(list: PlanItemRow[], on: boolean) {
        const ids = list.filter(r => r.can_send || r.can_push).map(r => r.id);
        selected.value = on ? [...new Set([...selected.value, ...ids])] : selected.value.filter(id => !ids.includes(id));
    }

    return {
        selected,
        toggle,
        toggleAll,
        clear: () => { selected.value = []; },
        /** Checked rows that can be sent: the ones not sent yet. */
        toSend: computed(() => chosen.value.filter(r => r.can_send && !r.sent)),
        /** Checked rows that are only in Kinetik and can go to kipApp now. */
        toPush: computed(() => chosen.value.filter(r => r.can_push)),
        /** Checked rows that can be taken back: the ones already sent. */
        toCancel: computed(() => chosen.value.filter(r => r.sent)),
    };
}
