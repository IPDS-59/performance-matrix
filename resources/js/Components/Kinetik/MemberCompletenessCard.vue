<script setup lang="ts">
import { computed, ref } from 'vue';
import type { MemberCompleteness } from '@/types';
import { Search } from 'lucide-vue-next';
import CollapsibleSection from '@/Components/Kinetik/CollapsibleSection.vue';
import { Input } from '@/Components/ui/input';
import { formatNumber } from '@/composables/useNumber';

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

const summary = computed(() => (withActivity.value.length ? `${formatNumber(completeCount.value)} dari ${formatNumber(withActivity.value.length)} sudah mengisi capaian${owing.value ? `, ${formatNumber(owing.value)} belum lengkap` : ''}` : 'Belum ada kegiatan'));
const query = ref('');
const shown = computed(() => {
    const q = query.value.trim().toLowerCase();
    return q ? sorted.value.filter(m => m.name.toLowerCase().includes(q)) : sorted.value;
});
</script>

<template>
    <CollapsibleSection id="kelengkapan-anggota" title="Kelengkapan anggota" :summary="summary">
        <p class="px-4 pt-3 text-xs text-gray-600">Anggota yang punya kegiatan dan sudah mengisi capaian semua kegiatannya. Yang belum lengkap tampil lebih dulu.</p>
        <div v-if="members.length > 9" class="relative px-4 py-2">
            <Search class="pointer-events-none absolute left-7 top-4 h-4 w-4 text-gray-500" aria-hidden="true" />
            <Input v-model="query" class="h-9 max-w-xs pl-9" placeholder="Cari anggota" aria-label="Cari anggota" />
        </div>
        <ul class="grid max-h-72 gap-x-6 overflow-y-auto px-4 py-2 sm:grid-cols-2 lg:grid-cols-3">
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
    </CollapsibleSection>
</template>
