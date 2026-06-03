<script setup lang="ts">
import { useAchievementColor } from '@/composables/useAchievementColor';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Progress } from '@/Components/ui/progress';

const props = defineProps<{
    orgAvg: number;
    activeCount: number;
    goodCount: number;
    total: number;
}>();

const { achievementColor, progressVariant } = useAchievementColor();
</script>

<template>
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-xs font-medium text-gray-500 uppercase tracking-wide">Rata-rata Organisasi</CardTitle>
            </CardHeader>
            <CardContent>
                <p :class="['text-3xl font-bold', achievementColor(orgAvg)]">{{ orgAvg.toFixed(1) }}%</p>
                <Progress :model-value="orgAvg" class="mt-2 h-1.5" :indicator-class="progressVariant(orgAvg)" />
            </CardContent>
        </Card>
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-xs font-medium text-gray-500 uppercase tracking-wide">Tim Aktif</CardTitle>
            </CardHeader>
            <CardContent>
                <p class="text-3xl font-bold text-gray-800">{{ activeCount }}</p>
                <p class="mt-1 text-xs text-gray-400">tim dengan laporan</p>
            </CardContent>
        </Card>
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-xs font-medium text-gray-500 uppercase tracking-wide">Tim Capaian &#8805; 80%</CardTitle>
            </CardHeader>
            <CardContent>
                <p class="text-3xl font-bold text-green-600">{{ goodCount }}</p>
                <p class="mt-1 text-xs text-gray-400">dari {{ total }} tim</p>
            </CardContent>
        </Card>
    </div>
</template>
