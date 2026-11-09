<script setup lang="ts">
import { ref } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

/** A bordered section whose header is one line (title, summary) and whose body opens on demand. The body keeps its state while closed. */
const props = defineProps<{ title: string; summary?: string; id?: string; defaultOpen?: boolean }>();
const open = ref(props.defaultOpen ?? false);
// A link to the section (#id) opens it, so "see who is behind" from a notice lands on the list, not on a closed header.
if (props.id && typeof window !== 'undefined' && window.location.hash === `#${props.id}`) open.value = true;
const bodyId = `${props.id ?? Math.random().toString(36).slice(2)}-body`;
</script>

<template>
    <section :id="id" class="scroll-mt-4 rounded-lg border bg-white">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
            :aria-expanded="open"
            :aria-controls="bodyId"
            @click="open = !open"
        >
            <span class="flex min-w-0 flex-wrap items-baseline gap-x-3">
                <span class="text-sm font-semibold text-gray-800">{{ title }}</span>
                <span v-if="summary" class="text-xs text-gray-600">{{ summary }}</span>
            </span>
            <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-500 transition-transform duration-200 ease-out motion-reduce:transition-none', open ? 'rotate-180' : '']" aria-hidden="true" />
        </button>
        <!-- grid-rows 0fr to 1fr animates the height without measuring it -->
        <div :id="bodyId" :class="['grid transition-[grid-template-rows] duration-200 ease-out motion-reduce:transition-none', open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]']" :inert="!open">
            <div class="min-h-0 overflow-hidden"><div class="border-t"><slot /></div></div>
        </div>
    </section>
</template>
