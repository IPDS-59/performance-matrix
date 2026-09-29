<script setup lang="ts">
import { computed, ref } from 'vue';
import type { MemberCompleteness } from '@/types';
import { ChevronDown } from 'lucide-vue-next';

const props = defineProps<{ members: MemberCompleteness[] }>();

const STATUS: Record<MemberCompleteness['status'], { label: string; classes: string; order: number }> = {
    empty: { label: 'Belum mengisi', classes: 'bg-red-50 text-red-700 border-red-200', order: 0 },
    partial: { label: 'Sebagian', classes: 'bg-amber-50 text-amber-800 border-amber-200', order: 1 },
    complete: { label: 'Lengkap', classes: 'bg-green-50 text-green-700 border-green-200', order: 2 },
    no_activity: { label: 'Tidak ada kegiatan', classes: 'bg-gray-50 text-gray-600 border-gray-200', order: 3 },
};

// Members who still owe input come first, as the PJ acts on them.
const sorted = computed(() => [...props.members].sort((a, b) => STATUS[a.status].order - STATUS[b.status].order));
const withActivity = computed(() => props.members.filter(m => m.status !== 'no_activity'));
const completeCount = computed(() => withActivity.value.filter(m => m.status === 'complete').length);
const owing = computed(() => withActivity.value.length - completeCount.value);

// Big teams (ZI has 100+ members): collapsed by default when everyone is done.
const open = ref(owing.value > 0);
</script>

<template>
    <section id="kelengkapan-anggota" class="mb-4 scroll-mt-4 rounded-lg border bg-white">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-gray-800">Kelengkapan anggota</span>
                <span class="block text-xs text-gray-500">
                    <span class="tabular-nums">{{ completeCount }} dari {{ withActivity.length }}</span> anggota dengan kegiatan kipApp sudah menyimpan semua klaimnya.
                    <template v-if="owing"> {{ owing }} belum lengkap.</template>
                </span>
            </span>
            <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-400 transition-transform', open ? 'rotate-180' : '']" aria-hidden="true" />
        </button>
        <ul v-if="open" class="grid gap-x-6 border-t px-4 py-2 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="m in sorted" :key="m.employee_id" class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-b-0">
                <span class="min-w-0 truncate text-sm text-gray-800" :title="m.name">{{ m.name }}</span>
                <span class="flex shrink-0 items-center gap-2">
                    <span v-if="m.status !== 'no_activity'" class="text-xs text-gray-500 tabular-nums">{{ m.saved }}/{{ m.total }}</span>
                    <span :class="['rounded-full border px-2 py-0.5 text-[11px] font-medium', STATUS[m.status].classes]">{{ STATUS[m.status].label }}</span>
                </span>
            </li>
            <li v-if="!members.length" class="py-3 text-sm text-gray-500">Tim ini belum punya anggota.</li>
        </ul>
    </section>
</template>
