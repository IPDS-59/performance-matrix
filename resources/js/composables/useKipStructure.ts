import axios from 'axios';
import { ref, toValue, watch, type MaybeRefOrGetter } from 'vue';
import type { KipChoice } from '@/types';

/** The RK Ketua of a tim in kipApp and the IKI JPT a new one can hang on, loaded when the tim changes. */
export function useKipStructure(teamId: MaybeRefOrGetter<number | null>) {
    const rkKetua = ref<KipChoice[]>([]);
    const ikiJpt = ref<KipChoice[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    async function load() {
        const id = toValue(teamId);
        rkKetua.value = [];
        ikiJpt.value = [];
        error.value = null;
        if (id === null) return;
        loading.value = true;
        try {
            const { data } = await axios.get(route('kip.structure.options'), { params: { team_id: id } });
            rkKetua.value = data.rkKetua;
            ikiJpt.value = data.ikiJpt;
            error.value = data.error ?? null;
        } catch {
            error.value = 'Daftar dari kipApp belum bisa dimuat.';
        } finally {
            loading.value = false;
        }
    }

    watch(() => toValue(teamId), load, { immediate: true });

    return { rkKetua, ikiJpt, loading, error, reload: load };
}
