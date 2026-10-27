<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type { FraIndicatorRow } from '@/types';
import { Button } from '@/Components/ui/button';
import { fmt } from '@/composables/useFra';

/** One indicator as the "Data Monitoring" sheet: twelve months, X and Y rows, the computed value coloured against the target. */
const props = defineProps<{ indicator: FraIndicatorRow; year: number; month: number; editable: boolean }>();

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const percent = props.indicator.unit_type === 'percent';

const blank = (v: number | null | undefined) => (v === null || v === undefined ? '' : v);
const form = useForm({
    months: Object.fromEntries(MONTHS.map((_, i) => [i + 1, { x: blank(props.indicator.months[i + 1]?.x), y: blank(props.indicator.months[i + 1]?.y) }])) as Record<number, { x: number | string; y: number | string }>,
});

const num = (v: number | string) => (v === '' || v === null ? null : Number(v));
const dirty = computed(() => form.isDirty);

/** The monthly value, the same arithmetic as the sheet: X over Y times 100, or the value itself. */
function monthValue(m: number): number | null {
    const c = form.months[m];
    const x = num(c.x);
    const y = num(c.y);
    if (percent) return x === null && y === null ? null : y ? (x ?? 0) / y * 100 : 0;
    return x;
}

/** Running figure from January to month m, like the sheet's cumulative columns: X and Y added up for a percent row; for a value row the sum, or the average when the row is itself a percentage. */
function cumulative(m: number): number | null {
    if (num(form.months[m].x) === null && num(form.months[m].y) === null) return null;
    const used = Array.from({ length: m }, (_, i) => form.months[i + 1]).filter((c) => num(c.x) !== null || num(c.y) !== null);
    if (!used.length) return null;
    const sumX = used.reduce((n, c) => n + (num(c.x) ?? 0), 0);
    if (percent) {
        const sumY = used.reduce((n, c) => n + (num(c.y) ?? 0), 0);
        return sumY ? (sumX / sumY) * 100 : 0;
    }
    return props.indicator.percent_label ? sumX / used.length : sumX;
}

const now = new Date();
const lastMonth = computed(() => (props.year < now.getFullYear() ? 12 : props.year > now.getFullYear() ? 0 : now.getMonth() + 1));

/** green: target met, yellow: some progress, red: empty or zero in a month that has passed, gray: still ahead. */
function tone(m: number, v: number | null = monthValue(m)): string {
    if (v === null || v <= 0) return m <= lastMonth.value ? 'bg-red-50 text-red-700' : 'bg-gray-50 text-gray-400';
    return v >= props.indicator.target_value ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-800';
}

function save() {
    form.transform((d: { months: Record<number, { x: number | string; y: number | string }> }) => ({ months: Object.fromEntries(Object.entries(d.months).map(([k, c]) => [k, { x: num(c.x), y: num(c.y) }])) }))
        .put(route('fra.months', props.indicator.id), { preserveScroll: true });
}

const cellInput = 'w-full min-w-0 rounded border border-gray-200 bg-white px-1.5 py-1 text-right text-sm tabular-nums focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[56rem] table-fixed border-collapse text-sm">
            <colgroup><col class="w-64"><col v-for="m in MONTHS" :key="m"></colgroup>
            <thead>
                <tr class="border-b bg-gray-50 text-xs text-gray-600">
                    <th class="sticky left-0 z-10 bg-gray-50 px-3 py-2 text-left font-medium">Bulan</th>
                    <th v-for="(m, i) in MONTHS" :key="m" class="px-1.5 py-2 text-center font-medium" :scope="'col'">{{ m }}</th>
                </tr>
            </thead>
            <tbody>
                <template v-if="percent">
                    <tr v-for="axis in (['x', 'y'] as const)" :key="axis" class="border-b">
                        <th scope="row" class="sticky left-0 z-10 bg-white px-3 py-1.5 text-left text-xs font-normal text-gray-600" :title="axis === 'x' ? indicator.x_label ?? '' : indicator.y_label ?? ''">
                            <span class="line-clamp-2">{{ axis === 'x' ? indicator.x_label : indicator.y_label }}</span>
                        </th>
                        <td v-for="(m, i) in MONTHS" :key="m" class="px-1 py-1">
                            <input v-if="editable" v-model="form.months[i + 1][axis]" type="number" step="any" :class="cellInput" :aria-label="`${axis.toUpperCase()} ${m}`">
                            <span v-else class="block px-1.5 text-right tabular-nums text-gray-700">{{ fmt(num(form.months[i + 1][axis])) }}</span>
                        </td>
                    </tr>
                </template>
                <tr v-else class="border-b">
                    <th scope="row" class="sticky left-0 z-10 bg-white px-3 py-1.5 text-left text-xs font-normal text-gray-600">Nilai {{ indicator.unit }}</th>
                    <td v-for="(m, i) in MONTHS" :key="m" class="px-1 py-1">
                        <input v-if="editable" v-model="form.months[i + 1].x" type="number" step="any" :class="cellInput" :aria-label="`Nilai ${m}`">
                        <span v-else class="block px-1.5 text-right tabular-nums text-gray-700">{{ fmt(num(form.months[i + 1].x)) }}</span>
                    </td>
                </tr>
                <tr class="border-b">
                    <th scope="row" class="sticky left-0 z-10 bg-white px-3 py-1.5 text-left text-xs font-semibold text-gray-800">
                        {{ percent || indicator.percent_label ? '% bulan ini' : 'Nilai bulan ini' }}
                    </th>
                    <td v-for="(m, i) in MONTHS" :key="m" class="px-1 py-1">
                        <span :class="['block rounded px-1.5 py-1 text-right text-sm font-medium tabular-nums', tone(i + 1)]">{{ fmt(monthValue(i + 1)) }}</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row" class="sticky left-0 z-10 bg-white px-3 py-1.5 text-left text-xs font-semibold text-gray-800">
                        Kumulatif <span class="font-normal text-gray-400">target {{ fmt(indicator.target_value) }}</span>
                    </th>
                    <td v-for="(m, i) in MONTHS" :key="m" class="px-1 py-1">
                        <span :class="['block rounded px-1.5 py-1 text-right text-sm font-semibold tabular-nums', i + 1 === month ? 'ring-2 ring-primary' : '', tone(i + 1, cumulative(i + 1))]">{{ fmt(cumulative(i + 1)) }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-if="editable" class="flex items-center justify-end gap-3 border-t px-3 py-2">
            <span v-if="dirty" class="text-xs text-amber-700">Ada perubahan yang belum disimpan.</span>
            <Button size="sm" :disabled="!dirty || form.processing" @click="save">{{ form.processing ? 'Menyimpan…' : 'Simpan bulanan' }}</Button>
        </div>
        <slot name="footer" />
    </div>
</template>
