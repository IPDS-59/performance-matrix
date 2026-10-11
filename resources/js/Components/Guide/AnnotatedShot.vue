<script setup lang="ts">
import { ref } from 'vue';
import type { GuideShot } from '@/data/guide';

defineProps<{ shot: GuideShot }>();

// Hovering a legend item highlights its marker on the screenshot, and vice versa.
const active = ref<number | null>(null);
</script>

<template>
    <figure class="mt-3 overflow-hidden rounded-lg border bg-gray-50 print:break-inside-avoid">
        <div class="relative">
            <img
                :src="shot.src"
                :alt="shot.alt"
                :width="shot.width"
                :height="shot.height"
                loading="lazy"
                class="block h-auto w-full"
            />
            <span
                v-for="m in shot.markers"
                :key="m.n"
                :style="{ left: `${m.x}%`, top: `${m.y}%` }"
                :class="[
                    'marker absolute flex h-6 w-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full text-xs font-bold text-white shadow ring-2 ring-white tabular-nums transition-transform',
                    active === m.n ? 'scale-125 bg-orange-500' : 'bg-primary',
                ]"
                aria-hidden="true"
                @mouseenter="active = m.n"
                @mouseleave="active = null"
            >{{ m.n }}</span>
        </div>
        <figcaption class="border-t bg-white px-3 py-2.5">
            <ol class="grid gap-x-4 gap-y-1.5 sm:grid-cols-2">
                <li
                    v-for="m in shot.markers"
                    :key="m.n"
                    :class="['flex items-start gap-2 rounded px-1 py-0.5 text-sm leading-snug transition-colors', active === m.n ? 'bg-orange-50' : '']"
                    @mouseenter="active = m.n"
                    @mouseleave="active = null"
                >
                    <span class="mt-px flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-white tabular-nums">{{ m.n }}</span>
                    <span class="text-gray-700">{{ m.label }}</span>
                </li>
            </ol>
        </figcaption>
    </figure>
</template>

<style scoped>
/* A soft pulse draws the eye to the markers; off when the user prefers less motion. */
@media (prefers-reduced-motion: no-preference) {
    .marker::after {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: 9999px;
        border: 2px solid currentColor;
        color: rgb(27 75 138 / 0.5);
        animation: pulse 2.4s ease-out infinite;
    }
}

@keyframes pulse {
    0% { transform: scale(0.9); opacity: 0.9; }
    100% { transform: scale(1.6); opacity: 0; }
}

@media print {
    .marker::after { display: none; }
}
</style>
