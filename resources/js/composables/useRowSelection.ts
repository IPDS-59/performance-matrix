import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';
import type { PlanItemRow } from '@/types';

/**
 * Checked rows of the kegiatan tables. Only rows the server marks `can_send` can be checked
 * (in kipApp, not locked). Rows that vanish from the page, or stop being selectable, drop out.
 */
export function useRowSelection(rows: MaybeRefOrGetter<PlanItemRow[]>) {
    const selected = ref<number[]>([]);
    const selectable = computed(() => toValue(rows).filter(r => r.can_send));
    const chosen = computed(() => selectable.value.filter(r => selected.value.includes(r.id)));

    watch(selectable, (list) => {
        const ids = new Set(list.map(r => r.id));
        selected.value = selected.value.filter(id => ids.has(id));
    });

    function toggle(id: number, on: boolean) {
        selected.value = on ? [...new Set([...selected.value, id])] : selected.value.filter(x => x !== id);
    }

    function toggleAll(list: PlanItemRow[], on: boolean) {
        const ids = list.filter(r => r.can_send).map(r => r.id);
        selected.value = on ? [...new Set([...selected.value, ...ids])] : selected.value.filter(id => !ids.includes(id));
    }

    return {
        selected,
        toggle,
        toggleAll,
        clear: () => { selected.value = []; },
        /** Checked rows that can be sent: the ones not sent yet. */
        toSend: computed(() => chosen.value.filter(r => !r.sent)),
        /** Checked rows that can be taken back: the ones already sent. */
        toCancel: computed(() => chosen.value.filter(r => r.sent)),
    };
}
