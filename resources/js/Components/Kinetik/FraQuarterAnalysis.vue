<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { FraAnalysisFields, FraIndicatorRow } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

/**
 * The analysis of one indicator for one quarter, in two versions from the same data. The system version is compiled from the months
 * and cannot be changed here. The edited version starts as a copy of it: the PJ trims and rewrites it, and only this one goes to the Excel
 * when it has text. A field left empty falls back to the system's.
 */
const props = defineProps<{ indicator: FraIndicatorRow; quarter: number; editable: boolean }>();

const FIELDS: Array<{ key: keyof FraAnalysisFields; label: string; long: boolean }> = [
    { key: 'obstacle', label: 'Kendala yang dihadapi', long: true },
    { key: 'solution', label: 'Solusi yang sudah dilakukan', long: true },
    { key: 'follow_up', label: 'Rencana tindak lanjut', long: true },
    { key: 'pic', label: 'PIC tindak lanjut', long: false },
    { key: 'deadline', label: 'Batas waktu tindak lanjut', long: false },
    { key: 'evidence_url', label: 'Link bukti dukung kinerja', long: true },
    { key: 'previous_follow_up_url', label: 'Link bukti dukung tindak lanjut sebelumnya', long: true },
];

const value = computed(() => props.indicator.values[props.quarter]);
const system = computed<FraAnalysisFields>(() => value.value?.system_analysis ?? ({} as FraAnalysisFields));
const edited = computed<FraAnalysisFields>(() => value.value?.edited_analysis ?? ({} as FraAnalysisFields));
const hasEdit = computed(() => FIELDS.some((f) => !!edited.value[f.key]));

// The form starts from the edited text, or the system's where nothing was edited.
const form = useForm(Object.fromEntries(FIELDS.map((f) => [f.key, edited.value[f.key] ?? system.value[f.key] ?? ''])) as Record<keyof FraAnalysisFields, string>);

const changed = computed(() => FIELDS.some((f) => (form[f.key] ?? '') !== (edited.value[f.key] ?? system.value[f.key] ?? '')));

function save() {
    // A field equal to the system's is stored as empty, so it keeps following the months.
    form.transform((data: Record<keyof FraAnalysisFields, string>) => Object.fromEntries(FIELDS.map((f) => [f.key, data[f.key] && data[f.key] !== (system.value[f.key] ?? '') ? data[f.key] : null])))
        .patch(route('fra.quarter-analysis', [props.indicator.id, props.quarter]), { preserveScroll: true });
}

function reset() {
    router.delete(route('fra.quarter-analysis.reset', [props.indicator.id, props.quarter]), { preserveScroll: true });
}
</script>

<template>
    <div class="grid gap-4 border-t px-4 py-3 lg:grid-cols-2">
        <section aria-label="Versi sistem">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Versi sistem <span class="font-normal normal-case">(dari tiga bulan, hanya baca)</span></h3>
            <dl class="space-y-2 text-sm">
                <div v-for="f in FIELDS" :key="f.key">
                    <dt class="text-xs text-gray-500">{{ f.label }}</dt>
                    <dd class="whitespace-pre-line rounded bg-gray-50 px-2 py-1 text-gray-800">{{ system[f.key] || '-' }}</dd>
                </div>
            </dl>
        </section>

        <section aria-label="Versi sunting">
            <h3 class="mb-2 flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-gray-500">
                <span>Versi sunting <span class="font-normal normal-case">(dipakai di Excel bila terisi)</span></span>
                <span v-if="hasEdit" class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium normal-case text-blue-700">Sudah disunting</span>
            </h3>
            <form v-if="editable" class="space-y-2" @submit.prevent="save">
                <div v-for="f in FIELDS" :key="f.key">
                    <Label :for="`fq-${indicator.id}-${f.key}`" class="text-xs text-gray-500">{{ f.label }}</Label>
                    <Textarea v-if="f.long" :id="`fq-${indicator.id}-${f.key}`" v-model="form[f.key]" :rows="f.key === 'obstacle' ? 3 : 2" class="mt-0.5 text-sm" />
                    <Input v-else :id="`fq-${indicator.id}-${f.key}`" v-model="form[f.key]" class="mt-0.5" />
                </div>
                <div class="flex items-center justify-end gap-2 pt-1">
                    <Button v-if="hasEdit" type="button" variant="outline" size="sm" @click="reset">Kembalikan ke versi sistem</Button>
                    <Button type="submit" size="sm" :disabled="!changed || form.processing">{{ form.processing ? 'Menyimpan…' : 'Simpan versi sunting' }}</Button>
                </div>
            </form>
            <dl v-else class="space-y-2 text-sm">
                <div v-for="f in FIELDS" :key="f.key">
                    <dt class="text-xs text-gray-500">{{ f.label }}</dt>
                    <dd class="whitespace-pre-line rounded bg-white px-2 py-1 text-gray-800">{{ edited[f.key] || system[f.key] || '-' }}</dd>
                </div>
            </dl>
        </section>
    </div>
</template>
