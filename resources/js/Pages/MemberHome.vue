<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import HeadHomeCard from '@/Components/Kinetik/HeadHomeCard.vue';
import MeetingSummaryTable, { type MeetingSummaryData } from '@/Components/Kinetik/MeetingSummaryTable.vue';
import TeamStatusCard, { type TeamStatus } from '@/Components/Kinetik/TeamStatusCard.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { CalendarCheck, CircleCheck, ClipboardCheck, TriangleAlert } from 'lucide-vue-next';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps<{
    week: { start: string; end: string };
    plan: { count: number; items: string[] };
    last_week: { total: number; filled: number; missing: string[] };
    sent: { total: number; sent: number; pending: string[] };
    skp: { total: number; open: string[] };
    /** The meetings of the member's own teams this month; a plain member opens only the results. */
    meetings?: MeetingSummaryData;
    /** Only for a PJ: per team they lead, who still owes a plan or a realisasi. */
    teamStatus?: TeamStatus[];
}>();

const { formatDate } = useDateFormat();

// The cards open this week, not the month list; the realisasi card lists only what still lacks its capaian.
const weekHref = computed(() => route('weekly.index', { week: props.week.start }));
const realisasiHref = computed(() => route('weekly.index', { week: props.week.start, perlu: 'realisasi' }));
const noPlan = computed(() => (props.plan.count === 0 ? ['Belum ada rencana minggu ini. Batas membuat rencana: Senin pukul 09.00.'] : []));
const open = computed(() => [noPlan.value, props.last_week.missing].filter((l) => l.length).length);
</script>

<template>
    <Head title="Beranda" />
    <AppLayout>
        <template #title>Beranda</template>

        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Minggu ini</p>
                <p class="text-lg font-semibold text-gray-900">{{ formatDate(week.start) }} sampai {{ formatDate(week.end) }}</p>
            </div>
            <p :class="['inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium', open ? 'bg-amber-50 text-amber-800' : 'bg-green-50 text-green-700']">
                <component :is="open ? TriangleAlert : CircleCheck" class="h-4 w-4" aria-hidden="true" />
                {{ open ? `${open} dari 2 hal perlu Anda tindaklanjuti` : 'Semua beres' }}
            </p>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <HeadHomeCard
                :icon="ClipboardCheck" title="Rencana minggu ini" caption="Kegiatan yang Anda rencanakan"
                :value="plan.count" :items="noPlan" items-label="Perlu dilakukan"
                ok-text="Rencana minggu ini sudah ada." :href="weekHref" href-label="Buka minggu ini"
            />
            <HeadHomeCard
                :icon="CalendarCheck" title="Capaian minggu lalu" caption="Kegiatan yang capaiannya sudah diisi"
                :value="last_week.filled" :total="last_week.total" :items="last_week.missing" items-label="Capaian belum diisi"
                :ok-text="last_week.total ? 'Capaian minggu lalu sudah lengkap.' : 'Tidak ada kegiatan minggu lalu.'" :href="realisasiHref" href-label="Isi realisasi"
            />
        </div>

        <section v-if="teamStatus?.length" class="mt-8" aria-labelledby="status-tim">
            <h2 id="status-tim" class="mb-3 text-base font-semibold text-gray-900">Status anggota tim</h2>
            <div class="space-y-4"><TeamStatusCard v-for="t in teamStatus" :key="t.team_id" :team="t" /></div>
        </section>

        <section v-if="meetings" class="mt-8" aria-labelledby="ringkasan-rapat">
            <h2 id="ringkasan-rapat" class="mb-1 text-base font-semibold text-gray-900">Ringkasan Rapat</h2>
            <p class="mb-3 text-xs text-gray-600">Klik status minggu atau rapat bulanan untuk membaca hasil rapatnya.</p>
            <MeetingSummaryTable v-bind="meetings" route-name="dashboard" :only="['meetings']" />
        </section>
    </AppLayout>
</template>
