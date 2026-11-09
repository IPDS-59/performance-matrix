<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

/** One "Rekap Tim" in the sidebar, three periods here: the meeting of the week, of the month and of the quarter. Keeps the chosen team. */
const props = defineProps<{ current: 'weekly' | 'monthly' | 'quarterly'; teamId: number | null }>();
const tabs = [
    { key: 'weekly', label: 'Mingguan', name: 'team-recap.weekly' },
    { key: 'monthly', label: 'Bulanan', name: 'team-recap.monthly' },
    { key: 'quarterly', label: 'Triwulanan (FRA)', name: 'team-recap.quarterly' },
] as const;
</script>

<template>
    <nav class="mb-4 inline-flex rounded-lg border bg-white p-1" aria-label="Periode rekap tim">
        <Link
            v-for="t in tabs" :key="t.key" :href="route(t.name, props.teamId ? { team: props.teamId } : {})"
            :aria-current="current === t.key ? 'page' : undefined"
            :class="['rounded-md px-3 py-1.5 text-sm font-medium transition-colors duration-150', current === t.key ? 'bg-primary text-primary-foreground' : 'text-gray-700 hover:bg-gray-100']"
        >{{ t.label }}</Link>
    </nav>
</template>
