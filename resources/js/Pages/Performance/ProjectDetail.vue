<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/Components/ui/badge';

// ── Types ──────────────────────────────────────────────────────────────────

interface ProjectData {
    id: number;
    name: string;
    year: number;
    leader_id: number | null;
    team: { id: number; name: string } | null;
    leader: { id: number; name: string } | null;
}

interface EmployeeWorkItem {
    id: number;
    number: number;
    description: string;
    target: number;
    target_unit: string;
    year_realization: number;
    year_pct: number;
    report_count: number;
    has_pending: boolean;
    has_rejected: boolean;
    all_approved: boolean;
}

interface AssignedMember {
    employee_id: number;
    name: string;
    target: number;
    target_unit: string;
}

interface LeadWorkItem {
    id: number;
    number: number;
    description: string;
    target: number;
    target_unit: string;
    assigned_members: AssignedMember[];
    pending_count: number;
    approved_count: number;
    rejected_count: number;
    total_report_count: number;
}

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    project: ProjectData;
    work_items: EmployeeWorkItem[] | LeadWorkItem[];
    is_lead: boolean;
    year: number;
}>();

// ── Helpers ────────────────────────────────────────────────────────────────

function progressBarColor(pct: number): string {
    if (pct >= 80) return 'bg-green-500';
    if (pct >= 50) return 'bg-yellow-400';
    return 'bg-red-400';
}

function pctTextColor(pct: number): string {
    if (pct >= 80) return 'text-green-600';
    if (pct >= 50) return 'text-yellow-600';
    return 'text-red-500';
}

const employeeItems = () => props.work_items as EmployeeWorkItem[];
const leadItems = () => props.work_items as LeadWorkItem[];
</script>

<template>
    <Head :title="`${project.name} — Kinerja`" />
    <AppLayout>
        <template #title>
            <nav class="flex items-center gap-1.5 text-sm text-gray-500">
                <a :href="route('performance.index')" class="hover:text-gray-800">Kinerja</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-800 font-medium">{{ project.name }}</span>
            </nav>
        </template>

        <!-- Project header -->
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <Badge variant="outline" class="text-xs font-normal text-gray-500">
                        {{ project.team?.name ?? '—' }}
                    </Badge>
                    <span class="text-xs text-gray-400">·</span>
                    <span class="text-xs text-gray-500">{{ project.year }}</span>
                    <span v-if="is_lead" class="text-xs text-gray-400">·</span>
                    <Badge v-if="is_lead" class="border-blue-200 bg-blue-50 text-blue-700 text-xs font-normal">
                        Ketua Tim
                    </Badge>
                </div>
                <h2 class="mt-1 text-lg font-semibold text-gray-800">{{ project.name }}</h2>
                <p v-if="project.leader && !is_lead" class="mt-0.5 text-xs text-gray-500">
                    Ketua: {{ project.leader.name }}
                </p>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!work_items.length" class="py-16 text-center text-gray-400">
            <p class="font-medium">Belum ada rincian kegiatan.</p>
            <p v-if="is_lead" class="mt-1 text-sm">Tambahkan rincian kegiatan dari halaman manajemen proyek.</p>
            <p v-else class="mt-1 text-sm">Anda belum ditugaskan ke rincian kegiatan manapun di proyek ini.</p>
        </div>

        <!-- ── Employee view ──────────────────────────────────────────────── -->
        <template v-else-if="!is_lead">
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left w-10">#</th>
                            <th class="px-4 py-3 text-left">Rincian Kegiatan</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Target</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap hidden sm:table-cell">Realisasi</th>
                            <th class="px-4 py-3 text-center whitespace-nowrap hidden md:table-cell">Progress</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="wi in employeeItems()"
                            :key="wi.id"
                            class="hover:bg-gray-50 transition"
                        >
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ wi.number }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800 leading-snug">{{ wi.description }}</p>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">
                                {{ Number(wi.target).toLocaleString('id') }} {{ wi.target_unit }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap hidden sm:table-cell">
                                {{ Number(wi.year_realization).toLocaleString('id') }} {{ wi.target_unit }}
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-1.5 rounded-full bg-gray-200 overflow-hidden">
                                        <div
                                            :class="['h-full rounded-full transition-all', progressBarColor(wi.year_pct)]"
                                            :style="`width: ${wi.year_pct}%`"
                                        />
                                    </div>
                                    <span :class="['text-xs font-medium w-10 text-right shrink-0', pctTextColor(wi.year_pct)]">
                                        {{ wi.year_pct }}%
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    v-if="wi.all_approved"
                                    class="inline-flex items-center rounded border border-green-200 bg-green-50 px-2 py-0.5 text-[10px] font-medium text-green-700"
                                >
                                    Disetujui
                                </span>
                                <span
                                    v-else-if="wi.has_rejected"
                                    class="inline-flex items-center rounded border border-red-200 bg-red-50 px-2 py-0.5 text-[10px] font-medium text-red-700"
                                >
                                    Ditolak
                                </span>
                                <span
                                    v-else-if="wi.has_pending"
                                    class="inline-flex items-center rounded border border-yellow-200 bg-yellow-50 px-2 py-0.5 text-[10px] font-medium text-yellow-700"
                                >
                                    Menunggu
                                </span>
                                <span
                                    v-else-if="wi.report_count === 0"
                                    class="inline-flex items-center rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] font-medium text-gray-500"
                                >
                                    Belum lapor
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a
                                    :href="route('performance.work-items.show', wi.id)"
                                    class="inline-flex items-center rounded bg-blue-600 px-2.5 py-1 text-[11px] font-medium text-white hover:bg-blue-700 transition"
                                >
                                    Laporan
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- ── Lead view ─────────────────────────────────────────────────── -->
        <template v-else>
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left w-10">#</th>
                            <th class="px-4 py-3 text-left">Rincian Kegiatan</th>
                            <th class="px-4 py-3 text-left hidden lg:table-cell">Anggota</th>
                            <th class="px-4 py-3 text-center whitespace-nowrap">Laporan</th>
                            <th class="px-4 py-3 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="wi in leadItems()"
                            :key="wi.id"
                            class="hover:bg-gray-50 transition"
                        >
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ wi.number }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800 leading-snug">{{ wi.description }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    Target: {{ Number(wi.target).toLocaleString('id') }} {{ wi.target_unit }}
                                </p>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="m in wi.assigned_members"
                                        :key="m.employee_id"
                                        class="inline-flex items-center rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[10px] text-gray-600"
                                    >
                                        {{ m.name }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-center gap-1">
                                    <span
                                        v-if="wi.pending_count > 0"
                                        class="inline-flex items-center rounded border border-yellow-200 bg-yellow-50 px-1.5 py-0.5 text-[10px] font-medium text-yellow-700"
                                    >
                                        {{ wi.pending_count }} menunggu
                                    </span>
                                    <span
                                        v-if="wi.rejected_count > 0"
                                        class="inline-flex items-center rounded border border-red-200 bg-red-50 px-1.5 py-0.5 text-[10px] font-medium text-red-700"
                                    >
                                        {{ wi.rejected_count }} ditolak
                                    </span>
                                    <span
                                        v-if="wi.approved_count > 0"
                                        class="inline-flex items-center rounded border border-green-200 bg-green-50 px-1.5 py-0.5 text-[10px] font-medium text-green-700"
                                    >
                                        {{ wi.approved_count }} disetujui
                                    </span>
                                    <span
                                        v-if="wi.total_report_count === 0"
                                        class="inline-flex items-center rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[10px] text-gray-400"
                                    >
                                        Belum ada laporan
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a
                                    :href="route('performance.work-items.show', wi.id)"
                                    class="inline-flex items-center rounded bg-blue-600 px-2.5 py-1 text-[11px] font-medium text-white hover:bg-blue-700 transition"
                                >
                                    Tinjau
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </AppLayout>
</template>
