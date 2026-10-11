<script setup lang="ts">
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/Components/ui/accordion';
import { PLAN_FLOW_GUIDE } from '@/data/planFlowGuide';
</script>

<template>
    <Accordion type="single" collapsible class="mb-4 rounded-lg border bg-white">
        <AccordionItem value="guide" class="border-b-0">
            <AccordionTrigger class="px-4 py-3 text-sm font-semibold text-gray-800">Urutan perencanaan di kipApp</AccordionTrigger>
            <AccordionContent class="px-4 pb-4">
                <ol class="space-y-3">
                    <li v-for="(step, i) in PLAN_FLOW_GUIDE" :key="step.title" class="grid grid-cols-[1.75rem_1fr] gap-x-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary" aria-hidden="true">{{ i + 1 }}</span>
                        <div>
                            <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-gray-900">
                                {{ step.title }}
                                <span class="rounded-full px-2 py-0.5 text-xs font-normal" :class="step.where === 'Kinetik' ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-700'">{{ step.where }}</span>
                            </p>
                            <p class="text-sm leading-snug text-gray-600">{{ step.body }}</p>
                        </div>
                    </li>
                </ol>
            </AccordionContent>
        </AccordionItem>
    </Accordion>
</template>
