<script setup lang="ts">
import { computed } from 'vue';
import { AlertTriangle, CheckCircle2, Circle, HelpCircle } from 'lucide-vue-next';
import type { PlanFlowStep } from '@/types';
import { firstBlocker } from '@/composables/usePlanStage';

/** The five steps from RK to "terkirim ke atasan" for one member, with the message of the first blocker. */
const props = defineProps<{ steps: PlanFlowStep[] }>();

const blocker = computed(() => firstBlocker(props.steps));

const icon = { done: CheckCircle2, todo: Circle, blocked: AlertTriangle, unknown: HelpCircle } as const;
const tone = {
    done: 'text-green-700',
    todo: 'text-gray-600',
    blocked: 'text-red-700',
    unknown: 'text-gray-400',
} as const;
const stateText = { done: 'selesai', todo: 'belum', blocked: 'terhambat', unknown: 'belum diketahui' } as const;
</script>

<template>
    <div>
        <ol class="flex flex-wrap gap-x-4 gap-y-1.5" aria-label="Alur kipApp">
            <li v-for="step in steps" :key="step.key" class="flex items-center gap-1.5 text-xs" :class="tone[step.state]" :title="step.hint">
                <component :is="icon[step.state]" class="h-4 w-4 shrink-0" aria-hidden="true" />
                <span class="font-medium">{{ step.label }}</span>
                <span class="sr-only">: {{ stateText[step.state] }}. {{ step.hint }}</span>
            </li>
        </ol>
        <p v-if="blocker" class="mt-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm leading-snug text-red-900" role="status">
            <span class="font-semibold">{{ blocker.label }}:</span> {{ blocker.hint }}
        </p>
    </div>
</template>
