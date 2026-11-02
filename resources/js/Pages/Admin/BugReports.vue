<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { useDateFormat } from '@/composables/useDateFormat';
import { Badge } from '@/Components/ui/badge';

interface Report { id: number; title: string; description: string; page_url: string | null; status: string; admin_note: string | null; reporter: string; reporter_email: string | null; created_at: string }

defineProps<{ reports: Report[]; statuses: Record<string, string> }>();
const { formatDate } = useDateFormat();

function setStatus(report: Report, status: string) {
    router.put(route('admin.bug-reports.update', report.id), { status, admin_note: report.admin_note }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Laporan masalah" />
    <AppLayout>
        <template #title>Laporan masalah</template>
        <p v-if="!reports.length" class="rounded-xl border bg-white p-6 text-sm text-gray-600">Belum ada laporan. Pegawai melaporkan lewat tombol "Laporkan masalah" di bagian atas halaman.</p>
        <ul v-else class="space-y-3">
            <li v-for="r in reports" :key="r.id" class="rounded-xl border bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-gray-900">{{ r.title }}</p>
                        <p class="text-xs text-gray-600">{{ r.reporter }} · {{ formatDate(r.created_at) }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge variant="secondary">{{ statuses[r.status] ?? r.status }}</Badge>
                        <select class="rounded-md border px-2 py-1 text-sm" :value="r.status" :aria-label="`Status laporan ${r.title}`" @change="setStatus(r, ($event.target as HTMLSelectElement).value)">
                            <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-gray-800">{{ r.description }}</p>
                <a v-if="r.page_url" :href="r.page_url" class="mt-2 inline-block break-all text-xs text-primary underline" target="_blank" rel="noopener">{{ r.page_url }}</a>
            </li>
        </ul>
    </AppLayout>
</template>
