<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import SkpEvidenceTable from '@/Components/Kinetik/SkpEvidenceTable.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { ChevronLeft, CircleAlert } from 'lucide-vue-next';
import type { SkpEvidenceRow, SkpRatingRow } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';

const props = defineProps<{
    skp: { id: number; label: string; employee: string };
    rks: SkpRatingRow[];
    perilaku: SkpRatingRow[];
    evidence: SkpEvidenceRow[];
    loadError: string | null;
}>();

// The level kipApp shows for a score (GET /umpanbalik/nilai).
const levelOf = (kind: 'rk' | 'perilaku', n: number) => (n >= (kind === 'rk' ? 99 : 91) ? 3 : n >= 80 ? 2 : 1);
const LEVELS: Record<number, string> = { 1: 'Di bawah ekspektasi', 2: 'Sesuai ekspektasi', 3: 'Di atas ekspektasi' };

type Draft = { nilai: string; umpanbalik: string };
const draft = (rows: SkpRatingRow[]) => reactive<Record<string, Draft>>(Object.fromEntries(rows.map(r => [String(r.id), { nilai: r.nilai?.toString() ?? '', umpanbalik: r.umpanbalik ?? '' }])));
const rkDraft = draft(props.rks);
const perilakuDraft = draft(props.perilaku);

const processing = ref(false);
const confirm = ref(false);
const allScored = computed(() => props.rks.length > 0 && [...props.rks, ...props.perilaku].every(r => r.nilai !== null));

function save(kind: 'rk' | 'perilaku', row: SkpRatingRow) {
    const d = (kind === 'rk' ? rkDraft : perilakuDraft)[String(row.id)];
    processing.value = true;
    router.put(route(kind === 'rk' ? 'skp.rate.rk' : 'skp.rate.perilaku', props.skp.id), { id: row.id, nilai: d.nilai, umpanbalik: d.umpanbalik || null }, { preserveScroll: true, onFinish: () => (processing.value = false) });
}

function finish() {
    processing.value = true;
    router.post(route('skp.rate.finish', props.skp.id), {}, { onFinish: () => (processing.value = false) });
}

const groups = computed(() => [
    { kind: 'rk' as const, title: 'Rencana Kinerja', rows: props.rks, drafts: rkDraft },
    { kind: 'perilaku' as const, title: 'Perilaku kerja (BerAKHLAK)', rows: props.perilaku, drafts: perilakuDraft },
]);
</script>

<template>
    <Head :title="`Nilai ${skp.label}`" />
    <AppLayout>
        <template #title>Nilai {{ skp.label }}</template>

        <div class="mx-auto max-w-4xl space-y-4">
            <Link :href="route('skp.approvals')" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900"><ChevronLeft class="mr-1 h-4 w-4" aria-hidden="true" /> Persetujuan SKP</Link>
            <p class="text-sm text-gray-700">Penilaian untuk <strong>{{ skp.employee }}</strong>. Nilai disimpan ke kipApp dengan akun Anda, tiap baris dengan tombol Simpan.</p>

            <p v-if="loadError" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
                <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" /><span>{{ loadError }}</span>
            </p>

            <section class="rounded-lg border bg-white p-4" aria-labelledby="bukti-title">
                <h2 id="bukti-title" class="mb-2 text-sm font-semibold text-gray-900">Bahan penilaian dari Kinetik</h2>
                <SkpEvidenceTable :rows="evidence" />
            </section>

            <section v-for="g in groups" :key="g.kind" class="overflow-hidden rounded-lg border bg-white">
                <h2 class="border-b bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-900">{{ g.title }}</h2>
                <p v-if="!g.rows.length" class="px-4 py-6 text-sm text-gray-500">Belum ada data dari kipApp.</p>
                <ul class="divide-y divide-gray-100">
                    <li v-for="r in g.rows" :key="r.id" class="grid gap-3 px-4 py-3 sm:grid-cols-[1fr_7rem] sm:items-start">
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-gray-900">{{ r.name }}</p>
                            <Textarea v-model="g.drafts[String(r.id)].umpanbalik" rows="2" placeholder="Umpan balik (opsional)" :aria-label="`Umpan balik ${r.name}`" />
                        </div>
                        <div class="space-y-2">
                            <Input v-model="g.drafts[String(r.id)].nilai" type="number" min="0" max="100" inputmode="numeric" placeholder="0 - 100" :aria-label="`Nilai ${r.name}`" />
                            <p class="min-h-4 text-xs text-gray-600">{{ g.drafts[String(r.id)].nilai !== '' ? LEVELS[levelOf(g.kind, Number(g.drafts[String(r.id)].nilai))] : '' }}</p>
                            <Button size="sm" variant="outline" class="w-full" :disabled="processing || g.drafts[String(r.id)].nilai === ''" @click="save(g.kind, r)">Simpan</Button>
                        </div>
                    </li>
                </ul>
            </section>

            <div class="flex items-center justify-end gap-3">
                <p v-if="!allScored" class="text-xs text-gray-500">Simpan nilai semua RK dan perilaku untuk bisa menyelesaikan.</p>
                <Button :disabled="processing || !allScored" @click="confirm = true">Selesai menilai</Button>
            </div>
        </div>

        <ConfirmDialog v-model:open="confirm" :title="`Selesaikan penilaian ${skp.label}`" :description="`Penilaian ${skp.label} milik ${skp.employee} dikirim sebagai selesai. Nilai tidak bisa diubah lagi dari sini.`" confirm-label="Selesai menilai" @confirm="finish" />
    </AppLayout>
</template>
