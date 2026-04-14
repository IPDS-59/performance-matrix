import { ref } from 'vue';

export function useAsync<T>() {
    const loading = ref(false);
    const error = ref<string | null>(null);

    async function run(fn: () => Promise<T>): Promise<T | undefined> {
        loading.value = true;
        error.value = null;
        try {
            return await fn();
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : 'Terjadi kesalahan.';
        } finally {
            loading.value = false;
        }
    }

    return { loading, error, run };
}
