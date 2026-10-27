<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarCheck, CircleCheck, ClipboardCheck, FileCheck, Send } from 'lucide-vue-next';
import { useDateFormat } from '@/composables/useDateFormat';

defineProps<{
    week: { start: string; end: string };
    plans: { total: number; filled: number; missing: string[] };
    sent: { total: number; sent: number; pending: string[] };
    meetings: { week_label: string; total: number; missing: string[] };
    skp: { count: number; rows: { employee: string; label: string; since: string | null }[] };
}>();

const { formatDate } = useDateFormat();
</script>

<template>
    <Head title="Beranda" />
    <AppLayout>
        <template #title>Beranda</template>

        <p class="mb-4 text-sm text-gray-600">Minggu {{ formatDate(week.start) }} sampai {{ formatDate(week.end) }}.</p>

        <div class="grid gap-4 md:grid-cols-2">
            <section class="rounded-lg border bg-white p-4" data-tour="head-plans">
                <header class="flex items-center gap-2 text-sm font-semibold text-gray-800"><ClipboardCheck class="h-4 w-4 text-primary" /> Rencana minggu ini</header>
                <p class="mt-2 text-3xl font-semibold tabular-nums">{{ plans.filled }}<span class="text-lg text-gray-400">/{{ plans.total }}</span></p>
                <p class="text-sm text-gray-600">anggota sudah membuat rencana</p>
                <p v-if="!plans.missing.length" class="mt-3 flex items-center gap-1.5 text-sm text-green-700"><CircleCheck class="h-4 w-4" /> Semua sudah membuat rencana.</p>
                <template v-else>
                    <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Belum ({{ plans.missing.length }})</p>
                    <p class="mt-1 text-sm text-gray-800">{{ plans.missing.join(', ') }}</p>
                </template>
                <Link :href="route('plan-compliance.index')" class="mt-3 inline-block text-sm text-primary hover:underline">Kepatuhan Rencana</Link>
            </section>

            <section class="rounded-lg border bg-white p-4" data-tour="head-sent">
                <header class="flex items-center gap-2 text-sm font-semibold text-gray-800"><Send class="h-4 w-4 text-primary" /> Kegiatan ke atasan</header>
                <p class="mt-2 text-3xl font-semibold tabular-nums">{{ sent.sent }}<span class="text-lg text-gray-400">/{{ sent.total }}</span></p>
                <p class="text-sm text-gray-600">anggota sudah mengirim semua kegiatannya</p>
                <p v-if="!sent.pending.length" class="mt-3 flex items-center gap-1.5 text-sm text-green-700"><CircleCheck class="h-4 w-4" /> Tidak ada yang tertunda.</p>
                <template v-else>
                    <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Belum kirim ({{ sent.pending.length }})</p>
                    <p class="mt-1 text-sm text-gray-800">{{ sent.pending.join(', ') }}</p>
                </template>
            </section>

            <section class="rounded-lg border bg-white p-4" data-tour="head-meetings">
                <header class="flex items-center gap-2 text-sm font-semibold text-gray-800"><CalendarCheck class="h-4 w-4 text-primary" /> Rapat mingguan ({{ meetings.week_label }})</header>
                <p class="mt-2 text-3xl font-semibold tabular-nums">{{ meetings.total - meetings.missing.length }}<span class="text-lg text-gray-400">/{{ meetings.total }}</span></p>
                <p class="text-sm text-gray-600">tim sudah punya rekap minggu lalu</p>
                <p v-if="!meetings.missing.length" class="mt-3 flex items-center gap-1.5 text-sm text-green-700"><CircleCheck class="h-4 w-4" /> Semua tim sudah merekap.</p>
                <template v-else>
                    <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Belum ada rekap ({{ meetings.missing.length }})</p>
                    <p class="mt-1 text-sm text-gray-800">{{ meetings.missing.join(', ') }}</p>
                </template>
                <Link :href="route('team-recap.meetings')" class="mt-3 inline-block text-sm text-primary hover:underline">Ringkasan Rapat</Link>
            </section>

            <section class="rounded-lg border bg-white p-4" data-tour="head-skp">
                <header class="flex items-center gap-2 text-sm font-semibold text-gray-800"><FileCheck class="h-4 w-4 text-primary" /> SKP menunggu Anda</header>
                <p class="mt-2 text-3xl font-semibold tabular-nums">{{ skp.count }}</p>
                <p class="text-sm text-gray-600">SKP menunggu persetujuan</p>
                <p v-if="!skp.count" class="mt-3 flex items-center gap-1.5 text-sm text-green-700"><CircleCheck class="h-4 w-4" /> Tidak ada yang menunggu.</p>
                <ul v-else class="mt-3 divide-y text-sm">
                    <li v-for="(r, i) in skp.rows" :key="i" class="flex justify-between gap-2 py-1.5">
                        <span class="truncate text-gray-800">{{ r.employee }}</span>
                        <span class="shrink-0 text-gray-500">{{ r.label }}</span>
                    </li>
                </ul>
                <Link :href="route('skp.approvals')" class="mt-3 inline-block text-sm text-primary hover:underline">Persetujuan SKP</Link>
            </section>
        </div>
    </AppLayout>
</template>
