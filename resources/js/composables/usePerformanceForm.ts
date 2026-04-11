import { useForm } from '@inertiajs/vue3';
import { toRefs, reactive, MaybeRefOrGetter, toValue, watch } from 'vue';

export interface PerformanceItem {
    work_item_id: number;
    achievement_percentage: number | string;
    issues: string;
    solutions: string;
    action_plan: string;
}

export interface PerformanceFormState {
    period_month: number;
    period_year: number;
    items: PerformanceItem[];
}

export function usePerformanceForm(initialFilters: MaybeRefOrGetter<{ month: number; year: number }>) {
    const form = useForm<PerformanceFormState>({
        period_month: toValue(initialFilters).month,
        period_year: toValue(initialFilters).year,
        items: [],
    });

    watch(
        () => toValue(initialFilters),
        (val) => {
            form.period_month = val.month;
            form.period_year = val.year;
            form.items = [];
        },
    );

    function addItem(workItemId: number) {
        if (form.items.some((i) => i.work_item_id === workItemId)) return;
        form.items.push({
            work_item_id: workItemId,
            achievement_percentage: 0,
            issues: '',
            solutions: '',
            action_plan: '',
        });
    }

    function removeItem(workItemId: number) {
        form.items = form.items.filter((i) => i.work_item_id !== workItemId);
    }

    function submit() {
        form.post(route('performance.batch'), {
            preserveScroll: true,
        });
    }

    return { form, addItem, removeItem, submit };
}
