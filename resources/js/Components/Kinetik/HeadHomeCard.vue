<script setup lang="ts">
import { computed, ref, type Component } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CircleCheck, TriangleAlert } from 'lucide-vue-next';

const props = defineProps<{
    icon: Component;
    title: string;
    caption: string;
    value: number;
    total?: number;
    /** Names (or short rows) that still need a follow-up. Empty means all clear. */
    items: string[];
    itemsLabel: string;
    okText: string;
    href: string;
    hrefLabel: string;
}>();

const LIMIT = 8;
const expanded = ref(false);
const shown = computed(() => (expanded.value ? props.items : props.items.slice(0, LIMIT)));
const hidden = computed(() => props.items.length - LIMIT);

const ratio = computed(() => (props.total ? props.value / props.total : props.items.length ? 0 : 1));
const tone = computed(() => (!props.items.length ? 'ok' : props.total && ratio.value >= 0.5 ? 'warn' : props.total ? 'bad' : 'warn'));

const TONES = {
    ok: { ring: 'border-green-200', chip: 'bg-green-50 text-green-700', bar: 'bg-green-500', icon: 'bg-green-50 text-green-700', label: 'Aman' },
    warn: { ring: 'border-amber-200', chip: 'bg-amber-50 text-amber-800', bar: 'bg-amber-500', icon: 'bg-amber-50 text-amber-700', label: 'Perlu dicek' },
    bad: { ring: 'border-red-200', chip: 'bg-red-50 text-red-700', bar: 'bg-red-500', icon: 'bg-red-50 text-red-700', label: 'Perlu tindakan' },
} as const;
const t = computed(() => TONES[tone.value]);
</script>

<template>
    <section :class="['flex flex-col rounded-xl border bg-white shadow-sm', t.ring]">
        <header class="flex items-start gap-3 p-5 pb-3">
            <span :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-lg', t.icon]">
                <component :is="icon" class="h-5 w-5" aria-hidden="true" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-semibold text-gray-900">{{ title }}</h2>
                <p class="text-xs text-gray-500">{{ caption }}</p>
            </div>
            <span :class="['inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium', t.chip]">
                <component :is="tone === 'ok' ? CircleCheck : TriangleAlert" class="h-3.5 w-3.5" aria-hidden="true" />
                {{ t.label }}
            </span>
        </header>

        <div class="px-5">
            <p class="flex items-baseline gap-1.5">
                <span class="text-4xl font-semibold tabular-nums tracking-tight text-gray-900">{{ $n(value) }}</span>
                <span v-if="total !== undefined" class="text-base font-medium text-gray-400 tabular-nums">dari {{ $n(total) }}</span>
            </p>
            <div v-if="total" class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100" role="progressbar" :aria-valuenow="value" aria-valuemin="0" :aria-valuemax="total">
                <div :class="['h-full rounded-full transition-all', t.bar]" :style="{ width: `${Math.round(ratio * 100)}%` }" />
            </div>
        </div>

        <div class="flex-1 px-5 pb-4 pt-4">
            <p v-if="!items.length" class="flex items-center gap-2 rounded-lg bg-green-50 px-3 py-2.5 text-sm text-green-800">
                <CircleCheck class="h-4 w-4 shrink-0" aria-hidden="true" /> {{ okText }}
            </p>
            <template v-else>
                <p class="mb-2 text-xs font-medium text-gray-500">{{ itemsLabel }} <span class="tabular-nums">({{ items.length }})</span></p>
                <ul class="flex flex-wrap gap-1.5">
                    <li v-for="(name, i) in shown" :key="i" class="max-w-full truncate rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700">{{ name }}</li>
                    <li v-if="!expanded && hidden > 0">
                        <button type="button" class="rounded-md border border-dashed border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" @click="expanded = true">
                            +{{ hidden }} lainnya
                        </button>
                    </li>
                </ul>
            </template>
        </div>

        <footer class="border-t px-5 py-3">
            <Link :href="href" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                {{ hrefLabel }} <ArrowRight class="h-4 w-4" aria-hidden="true" />
            </Link>
        </footer>
    </section>
</template>
