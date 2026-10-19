<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { SkpQuarter } from '@/types';
import FormDialog from '@/Components/FormDialog.vue';
import { Checkbox } from '@/Components/ui/checkbox';

/** Pick the RK of the yearly SKP for a quarter SKP: all of them to create it, only the missing ones to add. */
const props = defineProps<{ open: boolean; quarter: SkpQuarter | null; processing: boolean }>();
const emit = defineEmits<{ 'update:open': [value: boolean]; submit: [rkIds: string[]] }>();

const adding = computed(() => props.quarter?.exists === true);
const choices = computed(() => (props.quarter?.candidates ?? []).filter((c) => !adding.value || !c.in_skp));
const picked = ref<string[]>([]);

watch(() => [props.open, props.quarter?.quarter], () => {
    // Creating starts with every RK ticked, like kipApp's list. Adding starts with none.
    picked.value = adding.value ? [] : choices.value.map((c) => c.id);
});

function toggle(id: string, on: boolean | 'indeterminate') {
    picked.value = on === true ? [...picked.value, id] : picked.value.filter((p) => p !== id);
}
</script>

<template>
    <FormDialog
        :open="open"
        :title="quarter ? (adding ? `Tambah RK ke SKP TW ${quarter.quarter}` : `Buat SKP TW ${quarter.quarter}`) : ''"
        :description="adding ? 'RK yang sudah ada di SKP ini tidak berubah.' : 'Pilih RK dari SKP Tahunan yang masuk ke SKP triwulan ini. RK bisa ditambah lagi selama SKP masih Sedang dibuat.'"
        :submit-label="adding ? 'Tambahkan' : 'Buat SKP'"
        :processing="processing"
        @update:open="(v: boolean) => emit('update:open', v)"
        @submit="picked.length && emit('submit', picked)"
    >
        <p v-if="!choices.length" class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">Semua RK SKP Tahunan sudah ada di SKP triwulan ini.</p>
        <ul v-else class="max-h-72 space-y-1 overflow-y-auto">
            <li v-for="c in choices" :key="c.id">
                <label class="flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-gray-50">
                    <Checkbox class="mt-0.5" :model-value="picked.includes(c.id)" @update:model-value="(v) => toggle(c.id, v)" />
                    <span class="leading-snug text-gray-800">{{ c.rencanakinerja }}
                        <span v-if="!c.jmliki" class="block text-xs text-amber-700">Belum punya IKI</span>
                    </span>
                </label>
            </li>
        </ul>
        <p v-if="choices.length && !picked.length" class="text-xs text-red-700">Pilih minimal satu RK.</p>
    </FormDialog>
</template>
