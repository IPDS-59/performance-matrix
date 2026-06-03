<script setup lang="ts">
import { computed } from 'vue';
import type { TrendPoint } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { VisXYContainer, VisLine, VisArea, VisAxis, VisTooltip } from '@unovis/vue';
import { Line as UnovisLine, Area as UnovisArea, CurveType } from '@unovis/ts';

interface TrendDatum { month: number; label: string; value: number | null }

const props = defineProps<{
    trend: TrendPoint[];
    year: number;
    months: { value: number; label: string }[];
}>();

const trendLabels = computed(() => props.months.map(m => m.label.substring(0, 3)));

const trendUnovisData = computed<TrendDatum[]>(() => {
    const dataByMonth: (number | null)[] = Array(12).fill(null);
    for (const point of props.trend) {
        dataByMonth[point.period_month - 1] = point.avg_achievement;
    }
    return dataByMonth.map((v, i) => ({ month: i, label: trendLabels.value[i], value: v }));
});

const trendX = (d: TrendDatum) => d.month;
const trendY = [(d: TrendDatum) => d.value ?? undefined];
const trendXTickFormat = (_tick: number, i: number) => trendUnovisData.value[i]?.label ?? '';
const trendYTickFormat = (v: number) => `${v}%`;
const trendLineColor = '#1B4B8A';
const trendAreaColor = 'rgba(27,75,138,0.08)';

const formatTrendTooltip = (d: TrendDatum) =>
    `<div style="padding:4px 8px;font-size:13px"><strong>${d.label}</strong><br/>${d.value?.toFixed(1) ?? '-'}%</div>`;

const trendTooltipTriggers = computed(() => ({
    [UnovisLine.selectors.line]: formatTrendTooltip,
    [UnovisArea.selectors.area]: formatTrendTooltip,
}));
</script>

<template>
    <Card class="mt-6">
        <CardHeader>
            <CardTitle class="text-base">Tren Capaian 12 Bulan — {{ year }}</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="h-52">
                <VisXYContainer :data="trendUnovisData" :yDomain="[0, 100]" :style="{ height: '100%' }">
                    <VisArea :x="trendX" :y="trendY" :color="trendAreaColor" :curveType="CurveType.MonotoneX" :opacity="1" />
                    <VisLine :x="trendX" :y="trendY" :color="trendLineColor" :curveType="CurveType.MonotoneX" />
                    <VisAxis type="x" :tickFormat="trendXTickFormat" :numTicks="12" :gridLine="false" />
                    <VisAxis type="y" :tickFormat="trendYTickFormat" />
                    <VisTooltip :triggers="trendTooltipTriggers" />
                </VisXYContainer>
            </div>
        </CardContent>
    </Card>
</template>
