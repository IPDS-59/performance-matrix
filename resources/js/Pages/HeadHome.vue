<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import HeadHomeCard from '@/Components/Kinetik/HeadHomeCard.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { CalendarCheck, CircleCheck, ClipboardCheck, FileCheck, Send, TriangleAlert } from 'lucide-vue-next';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps<{
    week: { start: string; end: string };
    plans: { total: number; filled: number; missing: string[] };
    sent: { total: number; sent: number; pending: string[] };
    meetings: { week_label: string; total: number; missing: string[] };
    skp: { count: number; rows: { employee: string; label: string; since: string | null }[] };
}>();

const { formatDate } = useDateFormat();

const skpItems = computed(() => props.skp.rows.map((r) => `${r.employee}, ${r.label}`));
const open = computed(() => [props.plans.missing, props.sent.pending, props.meetings.missing].filter((l) => l.length).length + (props.skp.count ? 1 : 0));
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
                {{ open ? `${open} dari 4 hal perlu ditindaklanjuti` : 'Semua aman minggu ini' }}
            </p>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <HeadHomeCard
                :icon="ClipboardCheck" title="Rencana minggu ini" caption="Anggota yang sudah membuat rencana"
                :value="plans.filled" :total="plans.total" :items="plans.missing" items-label="Belum membuat rencana"
                ok-text="Semua anggota sudah membuat rencana." :href="route('plan-compliance.index')" href-label="Buka Kepatuhan Rencana"
            />
            <HeadHomeCard
                :icon="Send" title="Kegiatan ke atasan" caption="Anggota yang sudah mengirim semua kegiatannya"
                :value="sent.sent" :total="sent.total" :items="sent.pending" items-label="Masih ada yang belum dikirim"
                ok-text="Tidak ada kegiatan yang tertunda." :href="route('team-recap.overview')" href-label="Buka Review Bersama"
            />
            <HeadHomeCard
                :icon="CalendarCheck" :title="`Rapat mingguan, ${meetings.week_label}`" caption="Tim yang sudah punya rekap minggu lalu"
                :value="meetings.total - meetings.missing.length" :total="meetings.total" :items="meetings.missing" items-label="Belum ada rekap"
                ok-text="Semua tim sudah merekap." :href="route('team-recap.meetings')" href-label="Buka Ringkasan Rapat"
            />
            <HeadHomeCard
                :icon="FileCheck" title="SKP menunggu Anda" caption="Menunggu persetujuan pimpinan"
                :value="skp.count" :items="skpItems" items-label="Pegawai dan jenis SKP"
                ok-text="Tidak ada SKP yang menunggu." :href="route('skp.approvals')" href-label="Buka Persetujuan SKP"
            />
        </div>
    </AppLayout>
</template>
