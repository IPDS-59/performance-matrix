<script setup lang="ts">
import { ArrowRight, Info } from 'lucide-vue-next';
import type { GuideStatusFlow, StatusTone } from '@/data/guide';

/** One status flow of the guide: the statuses as a chain (or a colour scale), then what each one means and what to do. */
defineProps<{ flow: GuideStatusFlow }>();

const CHIP: Record<StatusTone, string> = {
    gray: 'bg-gray-100 text-gray-700 border-gray-200',
    blue: 'bg-blue-50 text-blue-800 border-blue-200',
    amber: 'bg-amber-50 text-amber-800 border-amber-200',
    green: 'bg-green-50 text-green-800 border-green-200',
    emerald: 'bg-emerald-100 text-emerald-900 border-emerald-300',
    red: 'bg-red-50 text-red-800 border-red-200',
};
</script>

<template>
    <section :id="`status-${flow.id}`" class="scroll-mt-4 rounded-lg border bg-white p-4 print:break-inside-avoid" :aria-labelledby="`status-${flow.id}-h`">
        <h3 :id="`status-${flow.id}-h`" class="text-base font-semibold text-gray-900">{{ flow.title }}</h3>
        <p class="mt-0.5 text-xs text-gray-500">{{ flow.where }}</p>
        <p class="mt-2 text-sm leading-relaxed text-gray-700">{{ flow.intro }}</p>

        <!-- The chain, or the scale -->
        <ol class="mt-4 flex flex-wrap items-center gap-y-2" :aria-label="flow.scale ? 'Daftar status' : 'Urutan status'">
            <template v-for="(stage, i) in flow.stages" :key="stage.label">
                <li :class="['rounded-md border px-2.5 py-1.5 text-sm font-medium', CHIP[stage.tone]]">
                    <span v-if="!flow.scale" class="mr-1.5 text-xs opacity-60">{{ i + 1 }}</span>{{ stage.label }}
                </li>
                <li v-if="!flow.scale && i < flow.stages.length - 1" class="px-1.5 text-gray-400" aria-hidden="true"><ArrowRight class="h-4 w-4" /></li>
                <li v-else-if="flow.scale && i < flow.stages.length - 1" class="w-2" aria-hidden="true" />
            </template>
        </ol>

        <!-- Meaning and next step -->
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b text-xs uppercase tracking-wide text-gray-500">
                        <th class="w-48 py-2 pr-3 font-medium">Status</th>
                        <th class="py-2 pr-3 font-medium">Artinya</th>
                        <th class="py-2 font-medium">Yang terjadi atau yang perlu Anda lakukan</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="stage in [...flow.stages, ...(flow.sides ?? [])]" :key="stage.label" class="align-top">
                        <td class="py-2 pr-3"><span :class="['inline-block rounded-md border px-2 py-0.5 text-xs font-medium', CHIP[stage.tone]]">{{ stage.label }}</span></td>
                        <td class="py-2 pr-3 leading-snug text-gray-700">{{ stage.meaning }}</td>
                        <td class="py-2 leading-snug text-gray-700">{{ stage.next }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="flow.sides?.length" class="mt-2 text-xs text-gray-500">Baris paling bawah adalah keadaan di luar alur utama.</p>

        <p v-if="flow.note" class="mt-3 flex gap-2 rounded-md bg-blue-50 px-3 py-2 text-sm leading-snug text-blue-900">
            <Info class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" /><span>{{ flow.note }}</span>
        </p>
    </section>
</template>
