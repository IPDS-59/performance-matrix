<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import type { FraIndicatorRow } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import InputError from '@/Components/InputError.vue';

/** The analysis columns of the sheet for one indicator and one month. */
const props = defineProps<{ indicator: FraIndicatorRow; month: number }>();
const emit = defineEmits<{ saved: [] }>();

const current = props.indicator.months[props.month];
const form = useForm({
    obstacle: current?.obstacle ?? '',
    solution: current?.solution ?? '',
    follow_up: current?.follow_up ?? '',
    pic: current?.pic ?? '',
    deadline: current?.deadline ?? '',
    evidence_url: current?.evidence_url ?? '',
    previous_follow_up_url: current?.previous_follow_up_url ?? '',
});

function save() {
    form.transform((data: Record<string, unknown>) => Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])))
        .patch(route('fra.analysis', [props.indicator.id, props.month]), { preserveScroll: true, onSuccess: () => emit('saved') });
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="save">
        <div>
            <Label for="fra-obstacle" class="text-xs">Kendala yang dihadapi</Label>
            <Textarea id="fra-obstacle" v-model="form.obstacle" :rows="2" class="mt-1 text-sm" />
            <InputError :message="form.errors.obstacle" />
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
            <Label for="fra-prev" class="text-xs">Link bukti dukung tindak lanjut bulan sebelumnya</Label>
            <Input id="fra-prev" v-model="form.previous_follow_up_url" class="mt-1" />
        </div>
        <p class="text-xs text-gray-600">Untuk file Excel triwulanan, analisis yang dipakai adalah yang ditulis pada bulan terakhir triwulan (Maret, Juni, September, Desember).</p>
        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Menyimpan…' : 'Simpan' }}</Button>
        </div>
    </form>
</template>
