<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import type { FraIndicatorRow } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import InputError from '@/Components/InputError.vue';

/** Quarter figures and the analysis columns of one indicator. */
const props = defineProps<{ indicator: FraIndicatorRow; quarter: number }>();
const emit = defineEmits<{ saved: [] }>();

const current = props.indicator.values[props.quarter];
const form = useForm({
    allocation_x: current?.allocation_x ?? '',
    allocation_y: current?.allocation_y ?? '',
    realization_x: current?.realization_x ?? '',
    realization_y: current?.realization_y ?? '',
    obstacle: current?.obstacle ?? '',
    solution: current?.solution ?? '',
    follow_up: current?.follow_up ?? '',
    pic: current?.pic ?? '',
    deadline: current?.deadline ?? '',
    evidence_url: current?.evidence_url ?? '',
    previous_follow_up_url: current?.previous_follow_up_url ?? '',
});

const percent = props.indicator.unit_type === 'percent';

function save() {
    form.transform((data: Record<string, unknown>) => Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])))
        .patch(route('fra.values', [props.indicator.id, props.quarter]), { preserveScroll: true, onSuccess: () => emit('saved') });
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="save">
        <fieldset class="grid gap-3 sm:grid-cols-2">
            <legend class="mb-1 text-sm font-semibold text-gray-800">Alokasi target (kumulatif sampai TW {{ quarter }})</legend>
            <div>
                <Label for="alloc-x" class="text-xs">{{ percent ? indicator.x_label : 'Nilai' }}</Label>
                <Input id="alloc-x" v-model="form.allocation_x" type="number" step="any" class="mt-1" />
                <InputError :message="form.errors.allocation_x" />
            </div>
            <div v-if="percent">
                <Label for="alloc-y" class="text-xs">{{ indicator.y_label }}</Label>
                <Input id="alloc-y" v-model="form.allocation_y" type="number" step="any" class="mt-1" />
                <InputError :message="form.errors.allocation_y" />
            </div>
        </fieldset>

        <fieldset class="grid gap-3 sm:grid-cols-2">
            <legend class="mb-1 text-sm font-semibold text-gray-800">Realisasi (kumulatif sampai TW {{ quarter }})</legend>
            <div>
                <Label for="real-x" class="text-xs">{{ percent ? indicator.x_label : 'Nilai' }}</Label>
                <Input id="real-x" v-model="form.realization_x" type="number" step="any" class="mt-1" />
                <InputError :message="form.errors.realization_x" />
            </div>
            <div v-if="percent">
                <Label for="real-y" class="text-xs">{{ indicator.y_label }}</Label>
                <Input id="real-y" v-model="form.realization_y" type="number" step="any" class="mt-1" />
                <InputError :message="form.errors.realization_y" />
            </div>
        </fieldset>

        <div>
            <Label for="fra-obstacle" class="text-xs">Kendala pada triwulan berjalan</Label>
            <Textarea id="fra-obstacle" v-model="form.obstacle" :rows="2" class="mt-1 text-sm" />
        </div>
        <div>
            <Label for="fra-solution" class="text-xs">Solusi yang sudah dilakukan</Label>
            <Textarea id="fra-solution" v-model="form.solution" :rows="2" class="mt-1 text-sm" />
        </div>
        <div>
            <Label for="fra-follow-up" class="text-xs">Rencana tindak lanjut</Label>
            <Textarea id="fra-follow-up" v-model="form.follow_up" :rows="2" class="mt-1 text-sm" />
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <Label for="fra-pic" class="text-xs">PIC tindak lanjut</Label>
                <Input id="fra-pic" v-model="form.pic" class="mt-1" />
            </div>
            <div>
                <Label for="fra-deadline" class="text-xs">Batas waktu tindak lanjut</Label>
                <Input id="fra-deadline" v-model="form.deadline" class="mt-1" placeholder="31 Oktober 2026" />
            </div>
        </div>
        <div>
            <Label for="fra-evidence" class="text-xs">Link bukti dukung kinerja</Label>
            <Input id="fra-evidence" v-model="form.evidence_url" class="mt-1" />
        </div>
        <div>
            <Label for="fra-prev" class="text-xs">Link bukti dukung tindak lanjut triwulan sebelumnya</Label>
            <Input id="fra-prev" v-model="form.previous_follow_up_url" class="mt-1" />
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Menyimpan…' : 'Simpan' }}</Button>
        </div>
    </form>
</template>
