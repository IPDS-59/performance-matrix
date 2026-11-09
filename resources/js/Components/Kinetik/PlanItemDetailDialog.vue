<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { PlanItemRow } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import InputError from '@/Components/InputError.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { bulkCopy } from '@/composables/usePlanBulk';
import { toneClass, planStage } from '@/composables/usePlanStage';
import { achievementPercent, periodText, withUnit } from '@/composables/useAchievement';
import { useDateFormat } from '@/composables/useDateFormat';

/** Every field of one kegiatan, the result form, and the next action. */
const props = defineProps<{ item: PlanItemRow | null; canSendToAtasan: boolean; weekOf?: string | null }>();
const emit = defineEmits<{ close: []; edit: [item: PlanItemRow]; copy: [item: PlanItemRow] }>();

const { formatDate } = useDateFormat();
const stage = computed(() => (props.item ? planStage(props.item, props.canSendToAtasan) : null));
const percent = computed(() => (props.item ? achievementPercent(props.item.capaian, props.item.target) : null));

// `progres`, not `progress`: useForm already has a `progress` property (upload progress) that would overwrite it.
const form = useForm({ capaian: '' as string | number, capaian_note: '', kendala: '', evidence_url: '', progres: 0 as string | number, is_capaian_skp: true });
// kipApp offers "masuk capaian SKP" only while the progress is below 100. At 100 it always counts, so the choice is hidden.
const skpChoice = computed(() => Number(form.progres) < 100);
// Progres follows capaian over target until the user types a progress of their own.
const progressTyped = ref(false);
function onCapaianInput() {
    const target = Number(props.item?.target);
    if (progressTyped.value || form.capaian === '' || !(target > 0)) return;
    form.progres = Math.min(100, Math.max(0, Math.round((Number(form.capaian) / target) * 100)));
}

watch(() => props.item, (item) => {
    progressTyped.value = false;
    form.clearErrors();
    form.capaian = item?.capaian ?? '';
    form.capaian_note = item?.capaian_note ?? '';
    form.evidence_url = item?.evidence_url ?? '';
    form.kendala = item?.kendala ?? '';
    form.progres = item?.progress ?? 0;
    form.is_capaian_skp = item?.is_capaian_skp ?? true;
}, { immediate: true });

const payload = (data: Record<string, any>) => ({
    capaian: data.capaian === '' ? null : data.capaian,
    capaian_note: data.capaian_note || null,
    kendala: data.kendala || null,
    is_capaian_skp: Number(data.progres) >= 100 ? true : data.is_capaian_skp,
    // The week this realisasi belongs to, kept for a kegiatan that runs for several weeks.
    week: props.weekOf ?? undefined,
    evidence_url: data.evidence_url || null,
    progress: data.progres === '' ? null : data.progres,
});

function submit(method: 'patch' | 'post', name: string, transform = true) {
    if (!props.item) return;
    if (transform) form.transform(payload);
    const options = { preserveScroll: true, onSuccess: () => emit('close') };
    if (method === 'patch') form.patch(route(name, props.item.id), options);
    else form.post(route(name, props.item.id), options);
}

// Sending to kipApp takes a few seconds; a second click while it runs must not send the kegiatan again.
const acting = ref(false);

function act(name: string, data: Record<string, unknown> = {}) {
    if (!props.item || acting.value) return;
    acting.value = true;
    router.post(route(name, props.item.id), data, { preserveScroll: true, onSuccess: () => emit('close'), onFinish: () => (acting.value = false) });
}

/** A kegiatan imported from a spreadsheet waits when it looks like one already in kipApp; "Kirim tetap" says it is a different one. */
const waitsOnDuplicate = computed(() => !!props.item?.push_error?.startsWith('Mirip dengan kegiatan yang sudah ada di kipApp'));

// Sending, taking back and deleting ask first, with kipApp's own texts.
const confirming = ref<'send' | 'unsend' | 'cancel' | null>(null);
const confirmText = computed(() => {
    if (confirming.value === 'send') return bulkCopy.send;
    if (confirming.value === 'unsend') return bulkCopy.cancel;
    return props.item?.in_kipapp
        ? { title: 'Hapus kegiatan', description: 'Apakah akan menghapus kegiatan ini?', confirm: 'Ya' }
        : { title: 'Batalkan kegiatan', description: 'Batalkan kegiatan ini?', confirm: 'Ya' };
});

function run() {
    switch (stage.value?.action) {
        case 'push': return act('plan-items.push');
        case 'complete': return submit('post', 'plan-items.complete');
        case 'send': return void (confirming.value = 'send');
        case 'unsend': return void (confirming.value = 'unsend');
    }
}

function confirmed() {
    if (!props.item) return;
    if (confirming.value === 'send') act('plan-items.send');
    else if (confirming.value === 'unsend') act('plan-items.unsend');
    else router.delete(route('plan-items.destroy', props.item.id), { preserveScroll: true, onSuccess: () => emit('close') });
}

// kipApp takes no future date, so a plan waits for its start date.
const waitsForStart = computed(() => stage.value?.action === 'push' && !props.item?.startable);
// Only a finished kegiatan goes to kipApp.
const incomplete = computed(() => (stage.value?.action === 'push' ? (props.item?.missing_for_kip ?? []) : []));
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
                <dt class="text-gray-600">Masuk capaian SKP</dt><dd>{{ item.is_capaian_skp ? 'Ya' : 'Tidak' }}</dd>
                <dt v-if="item.sent_at" class="text-gray-600">Dikirim</dt><dd v-if="item.sent_at" class="tabular-nums">{{ formatDate(item.sent_at) }}</dd>
                <dt class="text-gray-600">Status</dt>
                <dd><span :class="['rounded-full px-2 py-0.5 text-xs font-medium', toneClass[stage.tone]]">{{ stage.label }}</span></dd>
            </dl>
            <p v-if="item.lock_reason" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ item.lock_reason }}</p>
            <p v-if="item.push_error" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">Belum terkirim ke kipApp: {{ item.push_error }}</p>
            <Button v-if="waitsOnDuplicate" type="button" variant="outline" size="sm" :disabled="acting" @click="act('plan-items.push', { force: true })">Kirim tetap</Button>

            <form id="plan-result" class="space-y-3 border-t pt-3" @submit.prevent="submit('patch', 'plan-items.result')">
                <h3 class="text-sm font-semibold text-gray-900">Realisasi</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="pr-cap">Capaian (angka{{ item.target_unit ? `, satuan ${item.target_unit}` : '' }})</Label>
                        <Input id="pr-cap" v-model="form.capaian" type="number" min="0" step="any" @input="onCapaianInput" />
                        <InputError :message="form.errors.capaian" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="pr-prog">Progres (%)</Label>
                        <Input id="pr-prog" v-model="form.progres" type="number" min="0" max="100" @input="progressTyped = true" />
                        <p v-if="!progressTyped && Number(item.target) > 0" class="text-xs text-gray-600">Dihitung dari capaian dibagi target.</p>
                        <InputError :message="form.errors.progress" />
                    </div>
                </div>
                <div class="space-y-1.5">
                    <Label for="pr-url">Bukti dukung (tautan)</Label>
                    <Input id="pr-url" v-model="form.evidence_url" type="url" placeholder="https://…" />
                    <InputError :message="form.errors.evidence_url" />
                </div>
                <div class="space-y-1.5">
                    <Label for="pr-note">Uraian capaian (teks yang tampil di kipApp)</Label>
                    <Textarea id="pr-note" v-model="form.capaian_note" :rows="3" />
                    <p class="text-xs text-gray-600">kipApp tidak punya angka capaian. Yang tampil di sana hanya uraian ini, progres, dan bukti dukung. Angka capaian di atas dipakai untuk rapat tim di Kinetik.</p>
                    <InputError :message="form.errors.capaian_note" />
                </div>
                <div class="space-y-1.5">
                    <Label for="pr-kendala">Permasalahan (opsional)</Label>
                    <Textarea id="pr-kendala" v-model="form.kendala" :rows="2" placeholder="Kendala yang perlu dibahas di rapat" />
                    <InputError :message="form.errors.kendala" />
                </div>
                <label v-if="skpChoice" class="flex items-start gap-1.5 text-sm">
                    <input v-model="form.is_capaian_skp" type="checkbox" class="mt-0.5" />
                    <span>Masukkan ke capaian SKP<span class="block text-xs text-gray-600">Progres belum 100. Centang bila kegiatan ini tetap dihitung di SKP bulan ini. Kegiatan yang lanjut ke bulan depan sebaiknya tidak dicentang.</span></span>
                </label>
            </form>

            <DialogFooter class="flex-wrap gap-2">
                <Button v-if="item?.can_delete" type="button" variant="ghost" class="mr-auto text-red-700" @click="confirming = 'cancel'">{{ item.in_kipapp ? 'Hapus kegiatan' : 'Batalkan rencana' }}</Button>
                <Button v-if="item?.status !== 'cancelled'" type="button" variant="outline" @click="item && emit('copy', item)">Salin kegiatan</Button>
                <Button v-if="item?.can_edit" type="button" variant="outline" @click="item && emit('edit', item)">Ubah</Button>
                <Button type="submit" form="plan-result" variant="outline" :disabled="form.processing || item?.status === 'cancelled'">Simpan realisasi</Button>
                <Button v-if="stage?.action" type="button" :disabled="form.processing || acting || waitsForStart || incomplete.length > 0" :title="waitsForStart ? 'Kegiatan belum bisa dikirim sebelum tanggal mulainya.' : incomplete.length ? `Isi dulu: ${incomplete.join(', ')}` : undefined" @click="run">{{ stage?.actionLabel }}</Button>
            </DialogFooter>
            <p v-if="item?.sent" class="text-xs text-gray-600">Kegiatan yang sudah dikirim ke atasan tidak bisa dihapus. Batalkan kirim dulu, lalu hapus.</p>
            <p v-if="incomplete.length" class="text-right text-xs text-amber-800">Belum lengkap, jadi belum dikirim ke kipApp. Isi dulu: {{ incomplete.join(', ') }}. Setelah lengkap, Kinetik mengirimnya sendiri.</p>
            <p v-if="waitsForStart" class="text-right text-xs text-gray-600">Kegiatan belum bisa dikirim sebelum tanggal mulainya. Kinetik mengirimnya otomatis pada hari itu.</p>

            <ConfirmDialog
                :open="confirming !== null"
                :title="confirmText.title"
                :description="confirmText.description"
                :confirm-label="confirmText.confirm"
                cancel-label="Tidak"
                :variant="confirming === 'cancel' ? 'destructive' : 'warning'"
                @update:open="(v: boolean) => { if (!v) confirming = null; }"
                @confirm="confirmed"
            />
        </DialogContent>
    </Dialog>
</template>
