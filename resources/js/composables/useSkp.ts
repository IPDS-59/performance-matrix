import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/** The steps of a SKP in kipApp's words, left to right. The atasan does the middle step. */
export const YEARLY_STEPS = ['Sedang dibuat', 'Menunggu ditetapkan atasan', 'Ditetapkan'] as const;
export const QUARTER_STEPS = ['Sedang dibuat', 'Diperiksa atasan', 'Dinilai'] as const;

/** Which step a kipApp statusskpid is at, 0 based. Unknown ids stay at the first step. */
export function stepOf(kind: 'yearly' | 'quarter', statusId: number | null): number {
    if (kind === 'yearly') return statusId === 6 || statusId === 7 ? 2 : statusId === 2 ? 1 : 0;

    return statusId === 7 ? 2 : statusId === 5 ? 1 : 0;
}

/** Sending and taking back a SKP, with a confirm step in the page. */
export function useSkpActions() {
    const processing = ref(false);
    const post = (name: string, params: Record<string, unknown>, method: 'post' | 'put' = 'post', path: Array<string | number> = []) => {
        processing.value = true;
        router[method](route(name, path), params, { preserveScroll: true, onFinish: () => (processing.value = false) });
    };

    return {
        processing,
        submit: (kind: 'yearly' | 'quarter', quarter?: number) => post('skp.submit', { kind, quarter }),
        cancel: (kind: 'yearly' | 'quarter', quarter?: number) => post('skp.cancel', { kind, quarter }),
        create: (quarter: number, rkIds: string[], onDone: () => void) => {
            processing.value = true;
            router.post(route('skp.quarter.create', quarter), { rk_ids: rkIds }, { preserveScroll: true, onSuccess: onDone, onFinish: () => (processing.value = false) });
        },
        addIki: (kind: 'yearly' | 'quarter', quarter: number | undefined, rkId: string, text: string, onDone: () => void) => {
            processing.value = true;
            router.post(route('skp.iki.store'), { kind, quarter, rk_id: rkId, text }, { preserveScroll: true, onSuccess: onDone, onFinish: () => (processing.value = false) });
        },
        deleteIki: (kind: 'yearly' | 'quarter', quarter: number | undefined, ikiId: string) => {
            processing.value = true;
            router.delete(route('skp.iki.destroy'), { data: { kind, quarter, iki_id: ikiId }, preserveScroll: true, onFinish: () => (processing.value = false) });
        },
        addRks: (quarter: number, rkIds: string[], onDone: () => void) => {
            processing.value = true;
            router.put(route('skp.quarter.rks', quarter), { rk_ids: rkIds }, { preserveScroll: true, onSuccess: onDone, onFinish: () => (processing.value = false) });
        },
    };
}
