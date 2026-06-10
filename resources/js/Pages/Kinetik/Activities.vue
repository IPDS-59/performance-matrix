<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { CheckCircle2, Circle, ExternalLink } from 'lucide-vue-next';
import type { KipActivityRow, Paginated } from '@/types';

const props = defineProps<{
    activities: Paginated<KipActivityRow>;
    filters: { q: string; status: 'all' | 'claimed' | 'unclaimed' };
    stats: { total: number; claimed: number };
    canViewAll: boolean;
}>();

const search = ref(props.filters.q);
const status = ref(props.filters.status);

let debounce: ReturnType<typeof setTimeout> | undefined;
watch([search, status], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            route('kip-activities.index'),
            { q: search.value || undefined, status: status.value === 'all' ? undefined : status.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head :title="canViewAll ? 'Kegiatan kipApp' : 'Kegiatan Saya'" />
    <AppLayout>
        <template #title>{{ canViewAll ? 'Kegiatan kipApp' : 'Kegiatan Saya' }}</template>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-gray-500">
                {{ stats.claimed }} dari {{ stats.total }} kegiatan sudah diklaim
            </div>
            <div class="flex items-center gap-2">
                <select
                    v-model="status"
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                >
                    <option value="all">Semua status</option>
                    <option value="claimed">Sudah diklaim</option>
                    <option value="unclaimed">Belum diklaim</option>
                </select>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Cari uraian / RK / pegawai…"
                    class="w-64 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                />
            </div>
        </div>

        <div class="rounded-md border bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead v-if="canViewAll">Pegawai</TableHead>
                        <TableHead>Tanggal</TableHead>
                        <TableHead>Uraian</TableHead>
                        <TableHead>Rencana Kinerja</TableHead>
                        <TableHead class="text-center">Progres</TableHead>
                        <TableHead class="text-center">Status</TableHead>
                        <TableHead class="text-center">Bukti</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="act in activities.data" :key="act.id">
                        <TableCell v-if="canViewAll">
                            <div class="font-medium">{{ act.employee_name }}</div>
                            <div v-if="act.nip_lama" class="font-mono text-xs text-gray-400">{{ act.nip_lama }}</div>
                        </TableCell>
                        <TableCell class="whitespace-nowrap text-sm">{{ formatDate(act.date_start) }}</TableCell>
                        <TableCell class="max-w-md">
                            <div class="line-clamp-2 text-sm">{{ act.description ?? '—' }}</div>
                        </TableCell>
                        <TableCell class="max-w-xs">
                            <div class="line-clamp-2 text-sm text-gray-600">{{ act.rk_name ?? '—' }}</div>
                        </TableCell>
                        <TableCell class="text-center text-sm">{{ act.progress != null ? act.progress + '%' : '—' }}</TableCell>
                        <TableCell class="text-center">
                            <span
                                :class="act.is_claimed
                                    ? 'inline-flex items-center gap-1 rounded-full border border-green-200 bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700'
                                    : 'inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500'"
                            >
                                <component :is="act.is_claimed ? CheckCircle2 : Circle" class="h-3 w-3" />
                                {{ act.is_claimed ? 'Diklaim' : 'Belum' }}
                            </span>
                        </TableCell>
                        <TableCell class="text-center">
                            <a v-if="act.evidence_url" :href="act.evidence_url" target="_blank" rel="noopener" class="inline-flex text-blue-600 hover:text-blue-800">
                                <ExternalLink class="h-4 w-4" />
                            </a>
                            <span v-else class="text-gray-300">—</span>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="activities.data.length === 0">
                        <TableCell :colspan="canViewAll ? 7 : 6" class="py-10 text-center text-sm text-gray-400">
                            Belum ada kegiatan tersinkron.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div v-if="activities.last_page > 1" class="mt-4 flex items-center justify-center gap-1">
            <template v-for="link in activities.links" :key="link.label">
                <button
                    v-if="link.url"
                    type="button"
                    :class="['rounded px-3 py-1.5 text-xs transition-colors', link.active ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100']"
                    @click="router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                    v-html="link.label"
                />
                <span v-else class="px-2 py-1.5 text-xs text-gray-300" v-html="link.label" />
            </template>
        </div>
    </AppLayout>
</template>
