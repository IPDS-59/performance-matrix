<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Info } from 'lucide-vue-next';
import type { AttentionGroup } from '@/types';

/** What needs a look first. Red: act now. Amber: soon. Blue: for information. */
defineProps<{ groups: AttentionGroup[] }>();

const tone = {
    high: { card: 'border-red-200 bg-red-50/60', badge: 'bg-red-600 text-white', icon: AlertTriangle, iconClass: 'text-red-700', label: 'Perlu tindakan' },
    medium: { card: 'border-amber-200 bg-amber-50/60', badge: 'bg-amber-500 text-white', icon: AlertTriangle, iconClass: 'text-amber-700', label: 'Segera' },
    info: { card: 'border-blue-200 bg-blue-50/60', badge: 'bg-blue-600 text-white', icon: Info, iconClass: 'text-blue-700', label: 'Informasi' },
} as const;
</script>

<template>
    <section class="mb-4" aria-labelledby="attention-title">
        <h2 id="attention-title" class="mb-2 text-sm font-semibold text-gray-900">Perlu perhatian</h2>
        <p v-if="!groups.length" class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
            <CheckCircle2 class="h-4 w-4" aria-hidden="true" /> Tidak ada yang perlu ditindaklanjuti saat ini.
        </p>
        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article v-for="g in groups" :key="g.key" :class="['rounded-lg border p-4', tone[g.severity].card]">
                <header class="flex items-start gap-2">
                    <component :is="tone[g.severity].icon" :class="['mt-0.5 h-4 w-4 shrink-0', tone[g.severity].iconClass]" aria-hidden="true" />
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-semibold leading-snug text-gray-900">{{ g.title }}</h3>
                        <p class="text-xs text-gray-700">{{ g.hint }}</p>
                    </div>
                    <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums', tone[g.severity].badge]" :aria-label="`${g.count} item, ${tone[g.severity].label}`">{{ g.count }}</span>
                </header>
                <ul class="mt-2 space-y-0.5 text-sm text-gray-800">
                    <li v-for="s in g.samples" :key="s" class="truncate">{{ s }}</li>
                    <li v-if="g.count > g.samples.length" class="text-xs text-gray-600">dan {{ g.count - g.samples.length }} lainnya</li>
                </ul>
                <Link :href="route(g.route)" class="mt-2 inline-block text-xs font-medium text-primary underline-offset-2 hover:underline">Buka halamannya</Link>
            </article>
        </div>
    </section>
</template>
