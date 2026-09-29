<script setup lang="ts">
import { computed } from 'vue';
import type { ChecklistStep } from '@/types';
import { CircleCheck, Circle } from 'lucide-vue-next';

const props = defineProps<{ steps: ChecklistStep[] }>();

const doneCount = computed(() => props.steps.filter(s => s.done).length);

function jump(target?: string) {
    if (target) document.getElementById(target)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <section class="mb-4 rounded-lg border bg-white p-3 sm:p-4" aria-labelledby="siap-rapat-title">
        <div class="mb-3 flex items-baseline justify-between gap-2">
            <h2 id="siap-rapat-title" class="text-sm font-semibold text-gray-800">Siap rapat</h2>
            <p class="text-xs text-gray-500 tabular-nums">{{ doneCount }} dari {{ steps.length }} langkah selesai</p>
        </div>
        <ol class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            <li v-for="(step, index) in steps" :key="step.label">
                <button
                    type="button"
                    :class="[
                        'flex h-full w-full items-start gap-2.5 rounded-md border px-3 py-2 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                        step.done ? 'border-green-200 bg-green-50/70' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50',
                        step.target ? 'cursor-pointer' : 'cursor-default',
                    ]"
                    :disabled="!step.target"
                    @click="jump(step.target)"
                >
                    <CircleCheck v-if="step.done" class="mt-0.5 h-4 w-4 shrink-0 text-green-600" aria-hidden="true" />
                    <Circle v-else class="mt-0.5 h-4 w-4 shrink-0 text-gray-300" aria-hidden="true" />
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-gray-800">{{ index + 1 }}. {{ step.label }}</span>
                        <span :class="['block text-xs', step.done ? 'text-green-700' : 'text-gray-500']">{{ step.detail }}</span>
                    </span>
                    <span class="sr-only">{{ step.done ? 'selesai' : 'belum selesai' }}</span>
                </button>
            </li>
        </ol>
    </section>
</template>
