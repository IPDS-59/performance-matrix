import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ref, nextTick } from 'vue';
import { usePerformanceForm } from '@/composables/usePerformanceForm';

// Mock useForm from Inertia — we only care about the data object behaviour
vi.mock('@inertiajs/vue3', () => {
    const useForm = vi.fn((initial: Record<string, unknown>) => {
        const data = { ...initial };
        return new Proxy(data, {
            get(target, prop) {
                return target[prop as string];
            },
            set(target, prop, value) {
                target[prop as string] = value;
                return true;
            },
        });
    });
    return { useForm };
});

// Mock the global route() helper (ziggy)
vi.stubGlobal('route', vi.fn(() => '/performance/batch'));

describe('usePerformanceForm', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('initialises period_month and period_year from filters', () => {
        const { form } = usePerformanceForm({ month: 4, year: 2026 });

        expect(form.period_month).toBe(4);
        expect(form.period_year).toBe(2026);
        expect(form.items).toHaveLength(0);
    });

    it('accepts a ref as initialFilters', () => {
        const filters = ref({ month: 7, year: 2025 });
        const { form } = usePerformanceForm(filters);

        expect(form.period_month).toBe(7);
        expect(form.period_year).toBe(2025);
    });

    it('addItem() pushes a new item with defaults', () => {
        const { form, addItem } = usePerformanceForm({ month: 1, year: 2026 });

        addItem(10);

        expect(form.items).toHaveLength(1);
        expect(form.items[0]).toEqual({
            work_item_id: 10,
            achievement_percentage: 0,
            issues: '',
            solutions: '',
            action_plan: '',
        });
    });

    it('addItem() does not add duplicate work_item_id', () => {
        const { form, addItem } = usePerformanceForm({ month: 1, year: 2026 });

        addItem(5);
        addItem(5);

        expect(form.items).toHaveLength(1);
    });

    it('addItem() allows different work_item_ids', () => {
        const { form, addItem } = usePerformanceForm({ month: 1, year: 2026 });

        addItem(1);
        addItem(2);
        addItem(3);

        expect(form.items).toHaveLength(3);
    });

    it('removeItem() removes the item with the given work_item_id', () => {
        const { form, addItem, removeItem } = usePerformanceForm({ month: 1, year: 2026 });

        addItem(1);
        addItem(2);
        removeItem(1);

        expect(form.items).toHaveLength(1);
        expect(form.items[0].work_item_id).toBe(2);
    });

    it('removeItem() is a no-op for unknown ids', () => {
        const { form, addItem, removeItem } = usePerformanceForm({ month: 1, year: 2026 });

        addItem(1);
        removeItem(999);

        expect(form.items).toHaveLength(1);
    });

    it('syncs period when ref filters change', async () => {
        const filters = ref({ month: 1, year: 2026 });
        const { form, addItem } = usePerformanceForm(filters);

        addItem(42);
        expect(form.items).toHaveLength(1);

        filters.value = { month: 6, year: 2026 };
        await nextTick();

        expect(form.period_month).toBe(6);
        expect(form.period_year).toBe(2026);
        expect(form.items).toHaveLength(0);
    });
});
