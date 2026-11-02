<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck } from 'lucide-vue-next';
import { Badge } from '@/Components/ui/badge';

interface Check { key: string; title: string; why: string; fix: string; severity: 'high' | 'medium'; count: number; rows: Array<Record<string, string | null>> }

defineProps<{ checks: Check[] }>();
</script>

<template>
    <Head title="Integritas data" />
    <AppLayout>
        <template #title>Integritas data</template>
        <p class="mb-4 text-sm text-gray-700">Data yang kehilangan hubungan penting. Pengiriman ke kipApp ditahan untuk kegiatan yang RK-nya belum punya projek, supaya data resmi tidak rusak.</p>
        <div class="space-y-3">
            <section v-for="c in checks" :key="c.key" class="rounded-xl border bg-white p-4">
                <header class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2 font-semibold text-gray-900">
                        <CircleCheck v-if="c.count === 0" class="h-4 w-4 text-green-600" aria-hidden="true" />
                        <CircleAlert v-else class="h-4 w-4" :class="c.severity === 'high' ? 'text-red-600' : 'text-amber-600'" aria-hidden="true" />
                        {{ c.title }}
                    </h2>
                    <Badge :variant="c.count === 0 ? 'secondary' : 'destructive'">{{ c.count === 0 ? 'Aman' : `${c.count} data` }}</Badge>
                </header>
                <p class="mt-1 text-sm text-gray-700">{{ c.why }}</p>
                <p v-if="c.count" class="text-sm text-gray-700"><b>Cara memperbaiki:</b> {{ c.fix }}</p>
                <div v-if="c.rows.length" class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead><tr class="border-b text-gray-600"><th v-for="(_, h) in c.rows[0]" :key="h" class="py-1 pr-3 font-medium">{{ h }}</th></tr></thead>
                        <tbody><tr v-for="(row, i) in c.rows" :key="i" class="border-b last:border-0"><td v-for="(v, h) in row" :key="h" class="py-1 pr-3 align-top">{{ v ?? '-' }}</td></tr></tbody>
                    </table>
                    <p v-if="c.count > c.rows.length" class="mt-1 text-xs text-gray-600">Menampilkan {{ c.rows.length }} dari {{ c.count }}.</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
