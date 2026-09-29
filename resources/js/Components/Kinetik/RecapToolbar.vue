<script setup lang="ts">
import type { TeamOption } from '@/types';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ChevronLeft, ChevronRight, Download, TriangleAlert } from 'lucide-vue-next';

defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    periodLabel: string;
    prevLabel: string;
    nextLabel: string;
    attentionTotal: number;
    exporting: boolean;
}>();

const attentionOnly = defineModel<boolean>('attentionOnly', { required: true });

const emit = defineEmits<{
    'change-team': [teamId: number];
    prev: [];
    next: [];
    export: [];
}>();
</script>

<template>
    <div class="mb-4 rounded-lg border bg-white p-3 sm:p-4">
        <!-- Row 1: what you are looking at -->
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <Select
                :model-value="selectedTeamId != null ? String(selectedTeamId) : undefined"
                @update:model-value="(v) => emit('change-team', Number(v))"
            >
                <SelectTrigger class="w-full lg:w-auto lg:min-w-[18rem] lg:max-w-md" aria-label="Pilih tim">
                    <SelectValue placeholder="Pilih tim" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                </SelectContent>
            </Select>

            <div class="flex items-center justify-between rounded-md border bg-gray-50/60 lg:justify-center">
                <Button variant="ghost" size="icon" class="h-9 w-9" :aria-label="prevLabel" :title="prevLabel" @click="emit('prev')">
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <span class="min-w-[13rem] px-2 text-center text-sm font-semibold text-gray-800 tabular-nums">{{ periodLabel }}</span>
                <Button variant="ghost" size="icon" class="h-9 w-9" :aria-label="nextLabel" :title="nextLabel" @click="emit('next')">
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <!-- Row 2: what you can do -->
        <div class="mt-3 flex flex-wrap items-center gap-2 border-t pt-3">
            <button
                type="button"
                :aria-pressed="attentionOnly"
                :class="[
                    'inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-400',
                    attentionOnly
                        ? 'border-orange-400 bg-orange-50 text-orange-700'
                        : 'border-gray-200 bg-white text-gray-600 hover:border-orange-300 hover:text-orange-700',
                ]"
                title="Tampilkan hanya baris dengan capaian di bawah 100%, ada kendala, atau belum dikonfirmasi"
                @click="attentionOnly = !attentionOnly"
            >
                <TriangleAlert class="h-3.5 w-3.5" />
                Perlu perhatian
                <span :class="['rounded-full px-1.5 text-[11px] tabular-nums', attentionOnly ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600']">
                    {{ attentionTotal }}
                </span>
            </button>

            <slot name="actions" />

            <Button
                size="sm"
                variant="outline"
                class="ml-auto"
                :disabled="exporting"
                title="Unduh semua tim yang dapat Anda lihat dalam format Rapat Mingguan/Bulanan"
                @click="emit('export')"
            >
                <Download class="mr-1.5 h-4 w-4" />
                {{ exporting ? 'Menyiapkan…' : 'Unduh Excel' }}
            </Button>
        </div>
    </div>
</template>
