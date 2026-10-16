<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { RecapClaimLine } from '@/types';
import { Pencil } from 'lucide-vue-next';
import FormDialog from '@/Components/FormDialog.vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

/**
 * The member claims behind a recap row: who did what (kipApp uraian) and
 * their numbers. The PJ can correct a member's target and realisasi here.
 */
const props = withDefaults(defineProps<{
    claims: RecapClaimLine[];
    canAdjust: boolean;
    /** Lines shown before "Lihat semua". */
    limit?: number;
}>(), { limit: 3 });

const showAll = ref(false);
const visible = computed(() => (showAll.value ? props.claims : props.claims.slice(0, props.limit)));

const editing = ref<number | null>(null);
const draft = ref({ target: '', realization: '' });
const saving = ref(false);

function start(line: RecapClaimLine) {
    editing.value = line.claim_id;
    draft.value = { target: line.target != null ? String(line.target) : '', realization: line.realization != null ? String(line.realization) : '' };
}

const editingLine = computed(() => props.claims.find(c => c.claim_id === editing.value) ?? null);

function save() {
    const line = editingLine.value;
    if (!line) return;
    saving.value = true;
    router.post(route('team-recap.claim-adjust', line.claim_id), {
        target: draft.value.target === '' ? null : draft.value.target,
        realization: draft.value.realization === '' ? null : draft.value.realization,
    }, {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
        onFinish: () => (saving.value = false),
    });
}

const number = (v: number | null) => (v == null ? '—' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(v));
</script>

<template>
    <ul v-if="claims.length" class="mt-2 space-y-1.5 border-l-2 border-gray-100 pl-2.5">
        <li v-for="line in visible" :key="line.claim_id" class="text-xs leading-snug">
            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <span class="rounded-full bg-indigo-50 px-1.5 py-px font-medium text-indigo-700">{{ line.name }}</span>
                <span class="min-w-0 flex-1 text-gray-600">{{ line.uraian ?? 'Tanpa uraian' }}</span>


                <span class="inline-flex items-center gap-1 whitespace-nowrap tabular-nums text-gray-700">
                    {{ number(line.realization) }} / {{ number(line.target) }} {{ line.target_unit ?? '' }}
                    <button
                        v-if="canAdjust"
                        type="button"
                        class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        :aria-label="`Koreksi angka ${line.name}`"
                        @click="start(line)"
                    >
                        <Pencil class="h-3 w-3" />
                    </button>
                </span>
            </div>
            <p v-if="line.adjusted_by" class="mt-0.5 text-[11px] text-amber-700">Angka dikoreksi {{ line.adjusted_by }}</p>
        </li>
        <li v-if="claims.length > limit">
            <button type="button" class="text-xs font-medium text-primary hover:underline" @click="showAll = !showAll">
                {{ showAll ? 'Tampilkan lebih sedikit' : `Lihat semua (${claims.length})` }}
            </button>
        </li>
    </ul>

    <FormDialog
        :open="editingLine !== null"
        title="Koreksi angka"
        :description="editingLine ? `${editingLine.name}: ${editingLine.uraian ?? 'Tanpa uraian'}` : ''"
        :processing="saving"
        @update:open="(v: boolean) => { if (!v) editing = null; }"
        @submit="save"
    >
        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5"><Label for="adj-target">Target</Label><Input id="adj-target" v-model="draft.target" type="number" min="0" step="any" /></div>
            <div class="space-y-1.5"><Label for="adj-real">Realisasi</Label><Input id="adj-real" v-model="draft.realization" type="number" min="0" step="any" /></div>
        </div>
    </FormDialog>
</template>
