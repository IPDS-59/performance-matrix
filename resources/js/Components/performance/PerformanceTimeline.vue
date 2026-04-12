<script setup lang="ts">
export interface ReviewEvent {
    id: number;
    action: 'submitted' | 'resubmitted' | 'approved' | 'rejected';
    note: string | null;
    created_at: string;
    actor: { id: number; name: string } | null;
}

const props = defineProps<{
    reviews: ReviewEvent[];
}>();

const actionConfig: Record<ReviewEvent['action'], { label: string; dotClass: string; badgeClass: string }> = {
    submitted: {
        label: 'Diajukan',
        dotClass: 'bg-blue-400 ring-blue-100',
        badgeClass: 'border-blue-200 bg-blue-50 text-blue-700',
    },
    resubmitted: {
        label: 'Diajukan Ulang',
        dotClass: 'bg-purple-400 ring-purple-100',
        badgeClass: 'border-purple-200 bg-purple-50 text-purple-700',
    },
    approved: {
        label: 'Disetujui',
        dotClass: 'bg-green-400 ring-green-100',
        badgeClass: 'border-green-200 bg-green-50 text-green-700',
    },
    rejected: {
        label: 'Ditolak',
        dotClass: 'bg-red-400 ring-red-100',
        badgeClass: 'border-red-200 bg-red-50 text-red-700',
    },
};

function formatDate(isoString: string): string {
    return new Date(isoString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <div v-if="reviews.length" class="space-y-0">
        <div
            v-for="(rv, index) in reviews"
            :key="rv.id"
            class="relative flex gap-3"
        >
            <!-- Vertical line -->
            <div class="flex flex-col items-center">
                <div
                    :class="['mt-1 h-2.5 w-2.5 shrink-0 rounded-full ring-2', actionConfig[rv.action].dotClass]"
                />
                <div v-if="index < reviews.length - 1" class="mt-1 w-px flex-1 bg-gray-200" />
            </div>

            <!-- Content -->
            <div :class="['pb-4 min-w-0 flex-1', index === reviews.length - 1 ? 'pb-0' : '']">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span
                        :class="['inline-flex items-center rounded border px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide', actionConfig[rv.action].badgeClass]"
                    >
                        {{ actionConfig[rv.action].label }}
                    </span>
                    <span v-if="rv.actor" class="text-xs text-gray-600">{{ rv.actor.name }}</span>
                    <span class="ml-auto shrink-0 text-[10px] text-gray-400">{{ formatDate(rv.created_at) }}</span>
                </div>
                <p v-if="rv.note" class="mt-1 text-xs text-gray-600 italic">{{ rv.note }}</p>
            </div>
        </div>
    </div>
    <p v-else class="text-xs text-gray-400 italic">Belum ada riwayat.</p>
</template>
