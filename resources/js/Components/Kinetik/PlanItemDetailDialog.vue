<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import type { PlanItemRow } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import InputError from '@/Components/InputError.vue';
import { toneClass, planStage } from '@/composables/usePlanStage';
import { achievementPercent, periodText, withUnit } from '@/composables/useAchievement';
import { useDateFormat } from '@/composables/useDateFormat';

/** Every field of one kegiatan, the result form, and the next action. */
const props = defineProps<{ item: PlanItemRow | null; canSendToAtasan: boolean }>();
const emit = defineEmits<{ close: []; edit: [item: PlanItemRow] }>();

const { formatDate } = useDateFormat();
const stage = computed(() => (props.item ? planStage(props.item, props.canSendToAtasan) : null));
const percent = computed(() => (props.item ? achievementPercent(props.item.capaian, props.item.target) : null));

const form = useForm({ capaian: '' as string | number, capaian_note: '', evidence_url: '', progress: 0 as string | number });
watch(() => props.item, (item) => {
    form.clearErrors();
    form.capaian = item?.capaian ?? '';
    form.capaian_note = item?.capaian_note ?? '';
    form.evidence_url = item?.evidence_url ?? '';
    form.progress = item?.progress ?? 0;
}, { immediate: true });

const payload = (data: Record<string, any>) => ({
    capaian: data.capaian === '' ? null : data.capaian,
    capaian_note: data.capaian_note || null,
    evidence_url: data.evidence_url || null,
    progress: data.progress === '' ? null : data.progress,
});

function submit(method: 'patch' | 'post', name: string, transform = true) {
    if (!props.item) return;
    if (transform) form.transform(payload);
    const options = { preserveScroll: true, onSuccess: () => emit('close') };
    if (method === 'patch') form.patch(route(name, props.item.id), options);
    else form.post(route(name, props.item.id), options);
}

function act(name: string) {
    if (!props.item) return;
    router.post(route(name, props.item.id), {}, { preserveScroll: true, onSuccess: () => emit('close') });
}

function run() {
    switch (stage.value?.action) {
        case 'push': return act('plan-items.push');
        case 'complete': return submit('post', 'plan-items.complete');
        case 'send': return act('plan-items.send');
        case 'unsend': return act('plan-items.unsend');
    }
}

function cancel() {
    if (!props.item || !confirm('Batalkan kegiatan ini?')) return;
    router.delete(route('plan-items.destroy', props.item.id), { preserveScroll: true, onSuccess: () => emit('close') });
}
</script>

<template>
    <Dialog :open="item !== null" @update:open="(v: boolean) => { if (!v) emit('close'); }">
        <DialogContent v-if="item && stage" class="max-h-[90vh] max-w-lg overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ item.description }}</DialogTitle>
                <DialogDescription>{{ item.rk_name }}</DialogDescription>
            </DialogHeader>

            <dl class="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-1.5 text-sm">
                <dt class="text-gray-600">Tanggal</dt><dd class="tabular-nums">{{ periodText(item, d => formatDate(d)) }}</dd>
                <dt class="text-gray-600">Target</dt><dd class="tabular-nums">{{ withUnit(item.target, item.target_unit) }}</dd>
                <dt class="text-gray-600">Capaian</dt>
                <dd class="tabular-nums">{{ withUnit(item.capaian, item.target_unit) }}<span v-if="percent !== null" class="ml-1 text-gray-600">({{ percent }}%)</span></dd>
                <dt class="text-gray-600">Progres</dt><dd class="tabular-nums">{{ item.progress }}%</dd>
                <dt class="text-gray-600">Status</dt>
                <dd><span :class="['rounded-full px-2 py-0.5 text-xs font-medium', toneClass[stage.tone]]">{{ stage.label }}</span></dd>
            </dl>
            <p v-if="item.push_error" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">Belum terkirim ke kipApp: {{ item.push_error }}</p>

            <form id="plan-result" class="space-y-3 border-t pt-3" @submit.prevent="submit('patch', 'plan-items.result')">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="pr-cap">Capaian (angka{{ item.target_unit ? `, satuan ${item.target_unit}` : '' }})</Label>
                        <Input id="pr-cap" v-model="form.capaian" type="number" min="0" step="any" />
                        <InputError :message="form.errors.capaian" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="pr-prog">Progres (%)</Label>
                        <Input id="pr-prog" v-model="form.progress" type="number" min="0" max="100" />
                        <InputError :message="form.errors.progress" />
                    </div>
                </div>
                <div class="space-y-1.5">
                    <Label for="pr-note">Uraian capaian (teks ke kipApp)</Label>
                    <Textarea id="pr-note" v-model="form.capaian_note" :rows="2" />
                    <InputError :message="form.errors.capaian_note" />
                </div>
                <div class="space-y-1.5">
                    <Label for="pr-url">Bukti dukung (tautan)</Label>
                    <Input id="pr-url" v-model="form.evidence_url" type="url" placeholder="https://…" />
                    <InputError :message="form.errors.evidence_url" />
                </div>
            </form>

            <DialogFooter class="flex-wrap gap-2">
                <Button v-if="item?.status === 'planned'" type="button" variant="ghost" class="mr-auto text-red-700" @click="cancel">Batalkan</Button>
                <Button v-if="item?.status === 'planned'" type="button" variant="outline" @click="item && emit('edit', item)">Ubah</Button>
                <Button type="submit" form="plan-result" variant="outline" :disabled="form.processing || item?.status === 'cancelled'">Simpan capaian</Button>
                <Button v-if="stage?.action" type="button" :disabled="form.processing" @click="run">{{ stage?.actionLabel }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
