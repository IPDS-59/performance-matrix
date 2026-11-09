import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

export type BulkAction = 'send' | 'cancel' | 'push';

/** kipApp's own texts for the send and cancel confirmation. */
export const bulkCopy: Record<BulkAction, { title: string; description: string; confirm: string; route: string }> = {
    send: {
        title: 'Kirim kegiatan untuk dinilai',
        description: 'Apakah Anda yakin mengirim kegiatan ini untuk dinilai?',
        confirm: 'Ya',
        route: 'plan-items.send-many',
    },
    push: {
        title: 'Kirim ke kipApp',
        description: 'Kegiatan yang dipilih belum ada di kipApp. Kirim sekarang ke kipApp?',
        confirm: 'Ya',
        route: 'plan-items.push-many',
    },
    cancel: {
        title: 'Batalkan pengiriman',
        description: 'Apakah Anda yakin membatalkan pengiriman dari kegiatan yang dipilih?',
        confirm: 'Ya',
        route: 'plan-items.unsend-many',
    },
};

/** "Kirim ke kipApp (n)", "Kirim ke atasan (n)" and "Batalkan kirim (n)": ask first, then one request for all checked kegiatan. */
export function useBulkSend(ids: () => { send: number[]; cancel: number[]; push: number[] }, onDone: () => void) {
    const pending = ref<BulkAction | null>(null);

    function run() {
        const action = pending.value;
        if (!action) return;
        router.post(route(bulkCopy[action].route), { ids: ids()[action] }, { preserveScroll: true, onSuccess: onDone });
        pending.value = null;
    }

    return { pending, ask: (action: BulkAction) => { pending.value = action; }, run };
}
