<script setup lang="ts">
import { computed, ref } from 'vue';
import type { MemberCompleteness } from '@/types';
import { ChevronDown, Search } from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';

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
const query = ref('');
const shown = computed(() => {
    const q = query.value.trim().toLowerCase();
    return q ? sorted.value.filter(m => m.name.toLowerCase().includes(q)) : sorted.value;
});
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
                    <span class="tabular-nums">{{ completeCount }} dari {{ withActivity.length }}</span> anggota yang punya kegiatan sudah mengisi capaian semua kegiatannya.
                    <template v-if="owing"> {{ owing }} belum lengkap.</template>
                </span>
            </span>
            <ChevronDown :class="['h-4 w-4 shrink-0 text-gray-400 transition-transform', open ? 'rotate-180' : '']" aria-hidden="true" />
        </button>
        <div v-if="open && members.length > 9" class="relative border-t px-4 py-2">
            <Search class="pointer-events-none absolute left-7 top-4 h-4 w-4 text-gray-500" aria-hidden="true" />
            <Input v-model="query" class="h-9 max-w-xs pl-9" placeholder="Cari anggota" aria-label="Cari anggota" />
        </div>
        <ul v-if="open" class="grid max-h-72 gap-x-6 overflow-y-auto border-t px-4 py-2 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="m in shown" :key="m.employee_id" class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-b-0">
                <span class="min-w-0 truncate text-sm text-gray-800" :title="m.name">{{ m.name }}</span>
                <span class="flex shrink-0 items-center gap-2">
                    <span v-if="m.status !== 'no_activity'" class="text-xs text-gray-500 tabular-nums">{{ m.saved }}/{{ m.total }}</span>
                    <span :class="['rounded-full border px-2 py-0.5 text-[11px] font-medium', STATUS[m.status].classes]">{{ STATUS[m.status].label }}</span>
                </span>
            </li>
            <li v-if="!members.length" class="py-3 text-sm text-gray-500">Tim ini belum punya anggota.</li>
            <li v-else-if="!shown.length" class="py-3 text-sm text-gray-500">Tidak ada anggota yang cocok.</li>
        </ul>
    </section>
</template>
