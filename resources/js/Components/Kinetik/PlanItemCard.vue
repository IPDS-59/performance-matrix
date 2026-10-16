<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Pencil, X } from 'lucide-vue-next';
import type { PlanEvaluation, PlanItemRow } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import InputError from '@/Components/InputError.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import FormDialog from '@/Components/FormDialog.vue';
import { planStage, toneClass } from '@/composables/usePlanStage';
import { useDateFormat } from '@/composables/useDateFormat';

/** One planned task: where it stands in the kipApp chain, its next action, and the Friday evaluation. */
const props = defineProps<{
    plan: PlanItemRow;
    /** The member themselves or the PJ. */
    canEdit: boolean;
    /** The PJ may correct the Friday evaluation. */
    canEvaluate?: boolean;
    /** Show "Kirim ke atasan" and "Batalkan kirim". */
    canSend?: boolean;
}>();
const emit = defineEmits<{ edit: [plan: PlanItemRow] }>();

const { formatDate } = useDateFormat();
const stage = computed(() => planStage(props.plan, props.canSend ?? false));
const open = computed(() => props.plan.status !== 'cancelled');

const evalLabel: Record<PlanEvaluation['state'], string> = { done: 'Selesai', in_progress: 'Berjalan', not_started: 'Belum dikerjakan' };
const evalClass: Record<PlanEvaluation['state'], string> = {
    done: 'bg-green-50 text-green-800',
    in_progress: 'bg-amber-50 text-amber-800',
    not_started: 'bg-gray-100 text-gray-700',
};

type Panel = 'complete' | 'correct' | null;
const panel = ref<Panel>(null);
const confirming = ref<'cancel' | 'send' | 'unsend' | null>(null);

const completion = useForm({ capaian: '', evidence_url: '' });
const correction = useForm({ status: '' as string, reason: '' });

function openComplete() {
    completion.reset();
    completion.clearErrors();
    panel.value = 'complete';
}

function saveComplete() {
    completion.post(route('plan-items.complete', props.plan.id), { preserveScroll: true, onSuccess: () => (panel.value = null) });
}

function openCorrect() {
    correction.clearErrors();
    correction.status = props.plan.evaluation.overridden ? props.plan.evaluation.state : props.plan.evaluation.computed_state;
    correction.reason = props.plan.evaluation.override_reason ?? '';
    panel.value = 'correct';
}

function saveCorrect() {
    correction.patch(route('plan-items.evaluate', props.plan.id), { preserveScroll: true, onSuccess: () => (panel.value = null) });
}

function clearCorrect() {
    router.patch(route('plan-items.evaluate', props.plan.id), { status: '', reason: '' }, { preserveScroll: true, onSuccess: () => (panel.value = null) });
}

function runPrimary() {
    const action = stage.value.action;
    if (action === 'push') router.post(route('plan-items.push', props.plan.id), {}, { preserveScroll: true });
    else if (action === 'complete') openComplete();
    else if (action === 'send' || action === 'unsend') confirming.value = action;
}

function confirmed() {
    const kind = confirming.value;
    if (kind === 'cancel') router.delete(route('plan-items.destroy', props.plan.id), { preserveScroll: true });
    else if (kind === 'send') router.post(route('plan-items.send', props.plan.id), {}, { preserveScroll: true });
    else if (kind === 'unsend') router.post(route('plan-items.unsend', props.plan.id), {}, { preserveScroll: true });
}

const confirmCopy = computed(() => ({
    cancel: { title: 'Batalkan rencana?', description: `"${props.plan.description}" dibatalkan. Rencana ini masih terlihat di evaluasi Jumat.`, label: 'Batalkan rencana', variant: 'destructive' as const },
    send: { title: 'Kirim ke atasan?', description: 'Atasan akan menilai kegiatan ini di kipApp. Pengiriman bisa dibatalkan selama belum dinilai.', label: 'Kirim ke atasan', variant: 'warning' as const },
    unsend: { title: 'Batalkan pengiriman?', description: 'Kegiatan kembali ke status belum dikirim dan bisa diubah lagi di kipApp.', label: 'Batalkan kirim', variant: 'warning' as const },
}[confirming.value ?? 'cancel']));
</script>

<template>
    <li class="px-4 py-3">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium leading-snug text-gray-900">{{ plan.description }}</p>
                <p class="mt-0.5 text-xs leading-relaxed text-gray-600">
                    {{ formatDate(plan.date_start) }}<template v-if="plan.date_end !== plan.date_start"> – {{ formatDate(plan.date_end) }}</template>
                    <template v-if="plan.rk_name"> · {{ plan.rk_name }}</template>
                    <template v-if="plan.target"> · target {{ plan.target }} {{ plan.target_unit ?? '' }}</template>
                </p>
            </div>
            <div v-if="canEdit && plan.status === 'planned'" class="flex shrink-0 gap-0.5">
                <button type="button" class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-primary focus-visible:ring-2 focus-visible:ring-primary" :aria-label="`Ubah rencana ${plan.description}`" @click="emit('edit', plan)"><Pencil class="h-4 w-4" /></button>
                <button type="button" class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-red-600 focus-visible:ring-2 focus-visible:ring-primary" :aria-label="`Batalkan rencana ${plan.description}`" @click="confirming = 'cancel'"><X class="h-4 w-4" /></button>
            </div>
        </div>

        <!-- Where the plan stands: five segments, one label -->
        <div class="mt-2 flex items-center gap-3">
            <div class="flex w-24 shrink-0 gap-0.5" role="img" :aria-label="`Tahap ${stage.step + 1} dari 5: ${stage.label}`">
                <span v-for="n in 5" :key="n" class="h-1.5 flex-1 rounded-full" :class="n <= stage.step + 1 ? 'bg-primary' : 'bg-gray-200'" />
            </div>
            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="toneClass[stage.tone]">{{ stage.label }}</span>
            <span v-if="plan.source === 'pj'" class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">Dari PJ</span>
            <span v-if="plan.source === 'rtl'" class="rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-800">Dari RTL</span>
        </div>

        <p v-if="plan.push_error" class="mt-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm leading-snug text-red-900" role="status">
            Belum terkirim ke kipApp: {{ plan.push_error }}
        </p>

        <!-- Friday evaluation -->
        <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-600">
            <span class="rounded-full px-2 py-0.5 font-medium" :class="evalClass[plan.evaluation.state]">{{ evalLabel[plan.evaluation.state] }}</span>
            <span>
                <template v-if="plan.evaluation.overridden">Dikoreksi PJ: {{ plan.evaluation.override_reason }}</template>
                <template v-else-if="plan.evaluation.activity_count">Dari kipApp: {{ plan.evaluation.activity_count }} kegiatan, progres rata-rata {{ Math.round(plan.evaluation.progress ?? 0) }}%</template>
                <template v-else>Belum ada kegiatan kipApp yang cocok</template>
            </span>
            <button v-if="canEvaluate && panel !== 'correct'" type="button" class="font-medium text-primary underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-primary" @click="openCorrect">Koreksi</button>
        </p>

        <div v-if="canEdit && open && stage.action && panel === null" class="mt-3">
            <Button type="button" :variant="stage.action === 'unsend' ? 'outline' : 'default'" size="sm" class="h-8" @click="runPrimary">{{ stage.actionLabel }}</Button>
        </div>

        <FormDialog
            :open="panel === 'complete'"
            title="Tandai selesai"
            :description="plan.description"
            submit-label="Simpan dan kirim"
            :processing="completion.processing"
            @update:open="(v: boolean) => { if (!v) panel = null; }"
            @submit="saveComplete"
        >
            <label class="block text-xs font-medium text-gray-700">Capaian
                <Textarea v-model="completion.capaian" :rows="2" class="mt-1 text-sm" placeholder="Apa yang sudah selesai (opsional)" />
            </label>
            <label class="block text-xs font-medium text-gray-700">Link bukti dukung
                <Input v-model="completion.evidence_url" type="url" class="mt-1 h-9 text-sm" placeholder="https://… (opsional)" />
            </label>
            <InputError :message="completion.errors.evidence_url || completion.errors.capaian" />
            <p class="text-xs text-gray-600">Progres 100% dikirim ke kipApp atas nama Anda.</p>
        </FormDialog>

        <FormDialog
            :open="panel === 'correct'"
            title="Koreksi hasil"
            :description="plan.description"
            :processing="correction.processing"
            @update:open="(v: boolean) => { if (!v) panel = null; }"
            @submit="saveCorrect"
        >
            <Select v-model="correction.status">
                <SelectTrigger class="h-9 w-full text-sm" aria-label="Hasil rencana"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="(label, key) in evalLabel" :key="key" :value="key">{{ label }}</SelectItem>
                </SelectContent>
            </Select>
            <Input v-model="correction.reason" class="h-9 text-sm" placeholder="Alasan koreksi (wajib)" aria-label="Alasan koreksi" required maxlength="500" />
            <InputError :message="correction.errors.reason || correction.errors.status" />
            <Button v-if="plan.evaluation.overridden" type="button" variant="outline" size="sm" @click="clearCorrect">Hapus koreksi</Button>
        </FormDialog>

        <ConfirmDialog
            :open="confirming !== null"
            :title="confirmCopy.title"
            :description="confirmCopy.description"
            :confirm-label="confirmCopy.label"
            :variant="confirmCopy.variant"
            @update:open="(v: boolean) => { if (!v) confirming = null; }"
            @confirm="confirmed"
        />
    </li>
</template>
