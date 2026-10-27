<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import HeadHomeCard from '@/Components/Kinetik/HeadHomeCard.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { CalendarCheck, CircleCheck, ClipboardCheck, FileCheck, Send, TriangleAlert } from 'lucide-vue-next';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps<{
    week: { start: string; end: string };
    plan: { count: number; items: string[] };
    last_week: { total: number; filled: number; missing: string[] };
    sent: { total: number; sent: number; pending: string[] };
    skp: { total: number; open: string[] };
}>();

const { formatDate } = useDateFormat();

const noPlan = computed(() => (props.plan.count === 0 ? ['Belum ada rencana minggu ini. Batas membuat rencana: Senin pukul 09.00.'] : []));
const open = computed(() => [noPlan.value, props.last_week.missing, props.sent.pending, props.skp.open].filter((l) => l.length).length);
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
                {{ open ? `${open} dari 4 hal perlu Anda tindaklanjuti` : 'Semua beres' }}
            </p>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <HeadHomeCard
                :icon="ClipboardCheck" title="Rencana minggu ini" caption="Kegiatan yang Anda rencanakan"
                :value="plan.count" :items="noPlan" items-label="Perlu dilakukan"
                ok-text="Rencana minggu ini sudah ada." :href="route('weekly.index')" href-label="Buka Rekap Mingguan"
            />
            <HeadHomeCard
                :icon="CalendarCheck" title="Capaian minggu lalu" caption="Kegiatan yang capaiannya sudah diisi"
                :value="last_week.filled" :total="last_week.total" :items="last_week.missing" items-label="Capaian belum diisi"
                :ok-text="last_week.total ? 'Capaian minggu lalu sudah lengkap.' : 'Tidak ada kegiatan minggu lalu.'" :href="route('weekly.index')" href-label="Isi realisasi"
            />
            <HeadHomeCard
                :icon="Send" title="Kegiatan ke atasan" caption="Kegiatan bulan ini yang sudah dikirim"
                :value="sent.sent" :total="sent.total" :items="sent.pending" items-label="Belum dikirim"
                ok-text="Semua kegiatan sudah dikirim." :href="route('weekly.index')" href-label="Buka Rekap Mingguan"
            />
            <HeadHomeCard
                :icon="FileCheck" title="SKP saya" caption="SKP tahun ini yang belum selesai dinilai"
                :value="skp.total - skp.open.length" :total="skp.total || undefined" :items="skp.open" items-label="Status SKP"
                ok-text="Tidak ada SKP yang menunggu tindakan." :href="route('skp.index')" href-label="Buka SKP Saya"
            />
        </div>
    </AppLayout>
</template>
