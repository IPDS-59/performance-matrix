<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import SkpRkDialog from '@/Components/Kinetik/SkpRkDialog.vue';
import SkpIkiList from '@/Components/Kinetik/SkpIkiList.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Check, CircleAlert, ExternalLink } from 'lucide-vue-next';
import type { SkpOverview, SkpQuarter } from '@/types';
import { Button } from '@/Components/ui/button';
import { QUARTER_STEPS, YEARLY_STEPS, stepOf, useSkpActions } from '@/composables/useSkp';

const props = defineProps<{ overview: SkpOverview; loadError: string | null }>();
const actions = useSkpActions();

const dialogQuarter = ref<SkpQuarter | null>(null);
const dialogOpen = computed({ get: () => dialogQuarter.value !== null, set: (v) => { if (!v) dialogQuarter.value = null; } });

type Ask = { title: string; description: string; run: () => void } | null;
const ask = ref<Ask>(null);
const askOpen = computed({ get: () => ask.value !== null, set: (v) => { if (!v) ask.value = null; } });

function submitDialog(rkIds: string[]) {
    const q = dialogQuarter.value;
    if (!q) return;
    const done = () => (dialogQuarter.value = null);
    if (q.exists) actions.addRks(q.quarter, rkIds, done);
    else actions.create(q.quarter, rkIds, done);
}

function confirmSubmit(kind: 'yearly' | 'quarter', quarter?: number) {
    ask.value = kind === 'yearly'
        ? { title: 'Kirim SKP Tahunan', description: 'Anda yakin mengirim SKP ini untuk ditetapkan atasan? RK dan IKI tidak bisa diubah sampai atasan mengembalikannya.', run: () => actions.submit('yearly') }
        : { title: `Kirim SKP TW ${quarter}`, description: 'Anda yakin mengirim SKP ini untuk dinilai atasan?', run: () => actions.submit('quarter', quarter) };
}

function confirmCancel(kind: 'yearly' | 'quarter', quarter?: number) {
    ask.value = { title: 'Batalkan pengiriman SKP', description: 'SKP kembali ke Sedang dibuat dan bisa diubah lagi.', run: () => actions.cancel(kind, quarter) };
}

const yearlyStep = computed(() => stepOf('yearly', props.overview.yearly?.status_id ?? null));
</script>

<template>
    <Head title="SKP Saya" />
    <AppLayout>
        <template #title>SKP Saya</template>

        <div class="mx-auto max-w-4xl space-y-4">
            <p v-if="loadError" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
                <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <span>SKP belum bisa dibaca dari kipApp: {{ loadError }}</span>
            </p>

            <!-- SKP Tahunan -->
            <section class="rounded-lg border bg-white p-5" aria-labelledby="tahunan-title">
                <h2 id="tahunan-title" class="text-sm font-semibold text-gray-900">SKP Tahunan {{ overview.year }}</h2>
                <template v-if="overview.yearly">
                    <ol class="mt-3 grid gap-2 sm:grid-cols-3" aria-label="Tahap SKP Tahunan">
                        <li v-for="(label, i) in YEARLY_STEPS" :key="label" :class="['flex items-center gap-2 rounded-md border px-3 py-2 text-sm', i < yearlyStep || (i === 2 && yearlyStep === 2) ? 'border-green-200 bg-green-50 text-green-900' : i === yearlyStep ? 'border-primary bg-blue-50 font-medium text-gray-900' : 'border-gray-200 text-gray-500']">
                            <Check v-if="i < yearlyStep || (i === 2 && yearlyStep === 2)" class="h-4 w-4 shrink-0" aria-hidden="true" />
                            <span v-else class="grid h-4 w-4 shrink-0 place-content-center rounded-full border text-[10px]">{{ i + 1 }}</span>
                            {{ label }}
                        </li>
                    </ol>
                    <p class="mt-3 text-sm text-gray-600">
                        Status di kipApp: <b class="text-gray-900">{{ overview.yearly.status }}</b>, {{ overview.yearly.jml_rk }} RK<template v-if="overview.yearly.atasan">, atasan {{ overview.yearly.atasan }}</template>.
                    </p>
                    <p v-if="overview.yearly.blockers.length" class="mt-2 text-sm text-amber-800">Belum bisa dikirim: {{ overview.yearly.blockers.join('; ') }}.</p>
                    <p v-if="overview.yearly.status_id === 2" class="mt-2 text-sm text-gray-700">Menunggu atasan menetapkan, di halaman Persetujuan SKP Kinetik atau di kipApp. Penetapan dilakukan atasan sendiri dengan akun kipApp-nya.</p>
                    <SkpIkiList v-if="overview.yearly.rk_list.length" :rks="overview.yearly.rk_list" :can-edit="overview.yearly.can_edit_iki" :processing="actions.processing.value" @add="(rk, text, done) => actions.addIki('yearly', undefined, rk, text, done)" @remove="(id) => actions.deleteIki('yearly', undefined, id)" />
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button v-if="overview.yearly.can_submit" size="sm" :disabled="actions.processing.value" @click="confirmSubmit('yearly')">Kirim untuk ditetapkan</Button>
                        <Button v-if="overview.yearly.can_cancel" size="sm" variant="outline" :disabled="actions.processing.value" @click="confirmCancel('yearly')">Batalkan pengiriman</Button>
                        <Button as="a" href="https://kipapp.bps.go.id/" target="_blank" rel="noopener" size="sm" variant="ghost">Buka kipApp <ExternalLink class="ml-1 h-3.5 w-3.5" aria-hidden="true" /></Button>
                    </div>
                </template>
                <p v-else class="mt-2 text-sm text-gray-600">SKP Tahunan {{ overview.year }} belum ada di kipApp. RK Tahunan dibuat di kipApp lebih dulu.</p>
            </section>

            <!-- SKP per triwulan -->
            <section class="space-y-3" aria-labelledby="triwulan-title">
                <h2 id="triwulan-title" class="text-sm font-semibold text-gray-900">SKP per triwulan</h2>
                <article v-for="q in overview.quarters" :key="q.quarter" :class="['rounded-lg border bg-white p-4', q.is_current && 'ring-2 ring-primary/30']" :aria-label="`SKP TW ${q.quarter}`">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            TW {{ q.quarter }}
                            <span v-if="q.is_current" class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">Sekarang</span>
                        </h3>
                        <span v-if="q.exists" :class="['rounded-full px-2 py-0.5 text-xs font-medium', q.status_id === 7 ? 'bg-emerald-100 text-emerald-900' : q.status_id === 5 ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-700']">{{ q.status }}</span>
                        <span v-else class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Belum dibuat</span>
                    </div>

                    <template v-if="q.exists">
                        <p class="mt-1 text-sm text-gray-600">{{ q.jml_rk }} RK, {{ q.jml_kegiatan }} kegiatan.</p>
                        <ol class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500" :aria-label="`Tahap SKP TW ${q.quarter}`">
                            <li v-for="(label, i) in QUARTER_STEPS" :key="label" :class="i <= stepOf('quarter', q.status_id) ? 'font-medium text-gray-900' : ''">{{ i + 1 }}. {{ label }}</li>
                        </ol>
                        <p v-if="q.blockers.length" class="mt-2 text-sm text-amber-800">Belum bisa dikirim: {{ q.blockers.join('; ') }}.</p>
                        <SkpIkiList v-if="q.rk_list.length" :rks="q.rk_list" :can-edit="q.can_edit_iki" :processing="actions.processing.value" @add="(rk, text, done) => actions.addIki('quarter', q.quarter, rk, text, done)" @remove="(id) => actions.deleteIki('quarter', q.quarter, id)" />
                    </template>
                    <p v-else-if="q.create_blocker" class="mt-1 text-sm text-gray-600">{{ q.create_blocker }}</p>

                    <div v-if="q.can_create || q.can_add_rk || q.can_submit || q.can_cancel" class="mt-3 flex flex-wrap gap-2">
                        <Button v-if="q.can_create" size="sm" :disabled="actions.processing.value" @click="dialogQuarter = q">Buat SKP TW {{ q.quarter }}</Button>
                        <Button v-if="q.can_add_rk" size="sm" variant="outline" :disabled="actions.processing.value" @click="dialogQuarter = q">Tambah RK</Button>
                        <Button v-if="q.can_submit" size="sm" :disabled="actions.processing.value" @click="confirmSubmit('quarter', q.quarter)">Kirim untuk dinilai</Button>
                        <Button v-if="q.can_cancel" size="sm" variant="outline" :disabled="actions.processing.value" @click="confirmCancel('quarter', q.quarter)">Batalkan pengiriman</Button>
                    </div>
                </article>
            </section>

            <p class="text-xs leading-relaxed text-gray-500">
                Kinetik mengirim dan membatalkan atas nama Anda, dengan aturan yang sama seperti kipApp. Atasan menetapkan SKP Tahunan dan mengembalikan SKP dengan akun kipApp-nya sendiri. Menilai SKP triwulan di kipApp.
            </p>
        </div>

        <SkpRkDialog v-model:open="dialogOpen" :quarter="dialogQuarter" :processing="actions.processing.value" @submit="submitDialog" />
        <ConfirmDialog v-model:open="askOpen" :title="ask?.title" :description="ask?.description" confirm-label="Ya" @confirm="ask?.run()" />
    </AppLayout>
</template>
