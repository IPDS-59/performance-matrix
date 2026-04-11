import { reactive, toRefs } from 'vue';

export interface MatrixFilters {
    year: number;
    month: number;
    teamId: number | null;
}

export function useFilters(defaults?: Partial<MatrixFilters>) {
    const state = reactive<MatrixFilters>({
        year: defaults?.year ?? new Date().getFullYear(),
        month: defaults?.month ?? new Date().getMonth() + 1,
        teamId: defaults?.teamId ?? null,
    });

    function reset() {
        state.year = new Date().getFullYear();
        state.month = new Date().getMonth() + 1;
        state.teamId = null;
    }

    return { ...toRefs(state), reset };
}
