<script setup lang="ts">
import { computed } from 'vue';
import type { FraIndicatorRow, FraSummary } from '@/types';
import { buildFraSheet, fmt } from '@/composables/useFra';

const props = defineProps<{ indicators: FraIndicatorRow[]; summary: FraSummary; year: number }>();

/** The same rows as the Excel download; the first seven are the title block and the header. */
const rows = computed(() => buildFraSheet(props.indicators, props.summary, props.year));
const header = computed(() => rows.value[6]);
const body = computed(() => rows.value.slice(7));

const show = (cell: string | number | null | undefined) => (typeof cell === 'number' ? fmt(cell) : (cell ?? ''));
const isUrl = (cell: unknown) => typeof cell === 'string' && /^https?:\/\//.test(cell);
/** A row with only the first cells filled is a tujuan or sasaran heading. */
const isHeading = (row: Array<string | number | null>) => row.length <= 2;
</script>

<template>
    <div class="max-h-[75vh] overflow-auto rounded-md border bg-white">
        <table class="min-w-max border-separate border-spacing-0 text-xs">
            <thead>
                <tr>
                    <th v-for="(cell, i) in header" :key="i"
                        :class="['sticky top-0 z-20 border-b border-r bg-gray-100 px-2 py-2 text-left font-semibold text-gray-700', i === 0 ? 'left-0 z-30 w-20' : '', i === 1 ? 'left-20 z-30 w-72' : '', i > 1 ? 'min-w-28' : '']">
                        {{ cell }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, r) in body" :key="r">
                    <td v-if="isHeading(row)" :colspan="header.length" class="sticky left-0 border-b bg-blue-50 px-2 py-1.5 font-semibold text-gray-800">
                        {{ row.filter(Boolean).join(' ') }}
                    </td>
                    <template v-else>
                        <td v-for="(_, i) in header" :key="i"
                            :class="['border-b border-r px-2 py-1 align-top', typeof row[i] === 'number' ? 'text-right tabular-nums' : '', i === 0 ? 'sticky left-0 z-10 w-20 bg-white' : '', i === 1 ? 'sticky left-20 z-10 w-72 whitespace-normal bg-white' : '', i >= 23 && i <= 25 ? 'min-w-64 whitespace-pre-line' : '']">
                            <a v-if="isUrl(row[i])" :href="String(row[i])" target="_blank" rel="noopener" class="text-blue-700 underline">Tautan</a>
                            <template v-else>{{ show(row[i]) }}</template>
                        </td>
                    </template>
                </tr>
            </tbody>
        </table>
    </div>
</template>
