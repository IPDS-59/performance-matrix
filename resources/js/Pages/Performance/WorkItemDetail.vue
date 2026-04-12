<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, reactive, nextTick } from 'vue';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import InputError from '@/Components/InputError.vue';
import PerformanceTimeline from '@/Components/Performance/PerformanceTimeline.vue';
import type { ReviewEvent } from '@/Components/Performance/PerformanceTimeline.vue';
import BuktiDukungPicker from '@/Components/Performance/BuktiDukungPicker.vue';

// ── Types ──────────────────────────────────────────────────────────────────

interface Attachment {
    id: number;
    type: 'file' | 'link';
    file_name: string | null;
    title: string | null;
    url: string | null;
    status: 'pending' | 'approved' | 'rejected';
    review_note: string | null;
    display_url: string | null;
    reviewer: { name: string } | null;
}

interface ReportData {
    id: number;
    period_month: number;
    period_year: number;
    realization: number;
    achievement_percentage: number;
    issues: string | null;
    solutions: string | null;
    action_plan: string | null;
    approval_status: 'pending' | 'approved' | 'rejected';
    review_note: string | null;
    reviewed_at: string | null;
    reviews: ReviewEvent[];
    attachments: Attachment[];
}

interface MemberReport {
    employee: { id: number; name: string };
    target: number;
    target_unit: string;
    reports: ReportData[];
}

interface WorkItemData {
    id: number;
    number: number;
    description: string;
    target: number;
    target_unit: string;
    project: { id: number; name: string; team_name: string | null };
}

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    work_item: WorkItemData;
    reports: ReportData[] | null;
    member_reports: MemberReport[] | null;
    is_lead: boolean;
    year: number;
    employee_id: number;
}>();

// ── Constants ──────────────────────────────────────────────────────────────

const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// ── Employee: per-report form state ────────────────────────────────────────

interface FormState {
    realization: number | string;
    issues: string;
    solutions: string;
    action_plan: string;
    processing: boolean;
    errors: Record<string, string>;
}

const openFormType = reactive(new Map<number, 'edit' | 'resubmit'>());
const formStates = reactive(new Map<number, FormState>());

function openForm(report: ReportData, type: 'edit' | 'resubmit') {
    formStates.set(report.id, {
        realization: report.realization,
        issues: report.issues ?? '',
        solutions: report.solutions ?? '',
        action_plan: report.action_plan ?? '',
        processing: false,
        errors: {},
    });
    openFormType.set(report.id, type);
}

function closeForm(reportId: number) {
    openFormType.delete(reportId);
}

function getFormState(reportId: number): FormState {
    if (!formStates.has(reportId)) {
        formStates.set(reportId, { realization: 0, issues: '', solutions: '', action_plan: '', processing: false, errors: {} });
    }
    return formStates.get(reportId)!;
}

function saveEdit(report: ReportData) {
    const state = getFormState(report.id);
    state.processing = true;
    state.errors = {};
    router.post(route('performance.batch'), {
        period_month: report.period_month,
        period_year: props.year,
        items: [{ work_item_id: props.work_item.id, realization: state.realization, issues: state.issues, solutions: state.solutions, action_plan: state.action_plan }],
    }, {
        preserveScroll: true,
        onSuccess: () => closeForm(report.id),
        onFinish: () => { state.processing = false; },
        onError: (errors) => {
            state.errors = {};
            for (const [key, val] of Object.entries(errors)) {
                const match = key.match(/^items\.0\.(.+)$/);
                state.errors[match ? match[1] : key] = val as string;
            }
        },
    });
}

function saveResubmit(report: ReportData) {
    const state = getFormState(report.id);
    state.processing = true;
    state.errors = {};
    router.patch(route('performance.resubmit', report.id), {
        realization: state.realization,
        issues: state.issues,
        solutions: state.solutions,
        action_plan: state.action_plan,
    }, {
        preserveScroll: true,
        onSuccess: () => closeForm(report.id),
        onFinish: () => { state.processing = false; },
        onError: (errors) => { state.errors = errors; },
    });
}

// ── Employee: new report form ──────────────────────────────────────────────

const reportedMonths = computed(() => new Set((props.reports ?? []).map(r => r.period_month)));
const availableMonths = computed(() =>
    Array.from({ length: 12 }, (_, i) => i + 1).filter(m => !reportedMonths.value.has(m)),
);

const showNewForm = ref(false);
const newForm = useForm({
    period_month: 1 as number,
    realization: '' as number | string,
    issues: '',
    solutions: '',
    action_plan: '',
});

function openNewForm() {
    newForm.period_month = availableMonths.value[0] ?? 1;
    newForm.realization = '';
    newForm.issues = '';
    newForm.solutions = '';
    newForm.action_plan = '';
    showNewForm.value = true;
}

function submitNew() {
    const submittedMonth = newForm.period_month;
    router.post(route('performance.batch'), {
        period_month: newForm.period_month,
        period_year: props.year,
        items: [{ work_item_id: props.work_item.id, realization: newForm.realization, issues: newForm.issues, solutions: newForm.solutions, action_plan: newForm.action_plan }],
    }, {
        preserveScroll: true,
        onSuccess: async () => {
            showNewForm.value = false;
            newForm.reset();
            await nextTick();
            const newReport = (props.reports ?? []).find(r => r.period_month === submittedMonth);
            if (newReport) {
                document.getElementById(`report-${newReport.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        },
    });
}

// ── Lead: review form state ────────────────────────────────────────────────

const rejectNoteMap = ref<Record<number, string>>({});
const showRejectFormMap = ref<Record<number, boolean>>({});

function approveReport(reportId: number) {
    router.patch(route('performance.approve', reportId), {}, { preserveScroll: true });
}

function submitReject(reportId: number) {
    router.patch(route('performance.reject', reportId), { review_note: rejectNoteMap.value[reportId] ?? '' }, {
        preserveScroll: true,
        onSuccess: () => { showRejectFormMap.value[reportId] = false; },
    });
}

// ── Helpers ────────────────────────────────────────────────────────────────

function statusBadgeClass(status: string): string {
    if (status === 'approved') return 'border-green-200 bg-green-50 text-green-700';
    if (status === 'rejected') return 'border-red-200 bg-red-50 text-red-700';
    return 'border-yellow-200 bg-yellow-50 text-yellow-700';
}

function statusLabel(status: string): string {
    if (status === 'approved') return 'Disetujui';
    if (status === 'rejected') return 'Ditolak';
    return 'Menunggu Persetujuan';
}

function attachmentStatusColor(status: string): string {
    if (status === 'approved') return 'border-green-200 bg-green-50 text-green-600';
    if (status === 'rejected') return 'border-red-200 bg-red-50 text-red-600';
    return 'border-gray-200 bg-gray-50 text-gray-500';
}

function attachmentStatusLabel(status: string): string {
    if (status === 'approved') return 'Disetujui';
    if (status === 'rejected') return 'Ditolak';
    return 'Menunggu';
}

function pct(realization: number, target: number): number {
    return target > 0 ? Math.min(100, Math.round((realization / target) * 100)) : 0;
}
</script>

<template>
    <Head :title="`${work_item.description} — Kinerja`" />
    <AppLayout>
        <template #title>
            <nav class="flex items-center gap-1.5 text-sm text-gray-500">
                <a :href="route('performance.index')" class="hover:text-gray-800">Kinerja</a>
                <span class="text-gray-300">/</span>
                <a :href="route('performance.projects.show', work_item.project.id)" class="hover:text-gray-800">
                    {{ work_item.project.name }}
                </a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-800 font-medium truncate max-w-48">{{ work_item.description }}</span>
            </nav>
        </template>

        <!-- Work item header -->
        <div class="mb-6">
            <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                <span>{{ work_item.project.team_name }}</span>
                <span class="text-gray-300">·</span>
                <span>Rincian #{{ work_item.number }}</span>
                <span class="text-gray-300">·</span>
                <span>{{ year }}</span>
            </div>
            <h2 class="text-lg font-semibold text-gray-800">{{ work_item.description }}</h2>
            <p class="mt-0.5 text-sm text-gray-500">
                Target: <strong>{{ Number(work_item.target).toLocaleString('id') }} {{ work_item.target_unit }}</strong>
            </p>
        </div>

        <!-- ── Employee view ──────────────────────────────────────────────── -->
        <template v-if="!is_lead">
            <!-- Existing reports -->
            <div class="space-y-4">
                <div
                    v-for="report in (reports ?? [])"
                    :key="report.id"
                    :id="`report-${report.id}`"
                    class="rounded-lg border border-gray-200 bg-white shadow-sm"
                >
                    <!-- Report header -->
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-3">
                        <span class="font-medium text-gray-700">{{ monthNames[report.period_month] }} {{ report.period_year }}</span>
                        <span class="text-gray-300">·</span>
                        <span class="text-sm text-gray-600">
                            {{ Number(report.realization).toLocaleString('id') }} {{ work_item.target_unit }}
                            <span class="text-gray-400">({{ pct(report.realization, work_item.target) }}%)</span>
                        </span>
                        <span
                            :class="['ml-auto inline-flex items-center rounded border px-2 py-0.5 text-xs font-medium', statusBadgeClass(report.approval_status)]"
                        >
                            {{ statusLabel(report.approval_status) }}
                        </span>
                    </div>

                    <div class="p-4 space-y-4">
                        <!-- Kendala / Solusi / RTL -->
                        <div v-if="report.issues || report.solutions || report.action_plan" class="grid gap-3 sm:grid-cols-3 text-sm">
                            <div v-if="report.issues">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mb-1">Kendala</p>
                                <p class="text-gray-700 whitespace-pre-line">{{ report.issues }}</p>
                            </div>
                            <div v-if="report.solutions">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mb-1">Solusi</p>
                                <p class="text-gray-700 whitespace-pre-line">{{ report.solutions }}</p>
                            </div>
                            <div v-if="report.action_plan">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mb-1">Rencana Tindak Lanjut</p>
                                <p class="text-gray-700 whitespace-pre-line">{{ report.action_plan }}</p>
                            </div>
                        </div>

                        <!-- Review note (if any) -->
                        <div v-if="report.review_note" class="rounded border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
                            <span class="font-medium">Catatan reviewer:</span> {{ report.review_note }}
                        </div>

                        <!-- Timeline -->
                        <div>
                            <p class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Riwayat</p>
                            <PerformanceTimeline :reviews="report.reviews" />
                        </div>

                        <!-- Attachments -->
                        <div v-if="report.attachments.length">
                            <p class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Bukti Dukung</p>
                            <div class="space-y-1.5">
                                <div
                                    v-for="att in report.attachments"
                                    :key="att.id"
                                    class="flex items-center gap-2 rounded border border-gray-100 bg-gray-50 px-3 py-2 text-sm"
                                >
                                    <span class="min-w-0 flex-1 truncate">
                                        <a v-if="att.display_url" :href="att.display_url" target="_blank" rel="noopener" class="text-blue-600 hover:underline">
                                            {{ att.title || att.file_name || att.url }}
                                        </a>
                                        <span v-else class="text-gray-600">{{ att.title || att.file_name || att.url }}</span>
                                    </span>
                                    <span :class="['shrink-0 rounded border px-1.5 py-0.5 text-[10px]', attachmentStatusColor(att.status)]">
                                        {{ attachmentStatusLabel(att.status) }}
                                    </span>
                                    <form
                                        v-if="report.approval_status !== 'approved'"
                                        :action="route('report-attachments.destroy', att.id)"
                                        method="POST"
                                        @submit.prevent="router.delete(route('report-attachments.destroy', att.id), { preserveScroll: true })"
                                    >
                                        <button type="submit" class="text-[10px] text-red-400 hover:text-red-600">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Attachment upload form -->
                        <div v-if="report.approval_status !== 'approved'">
                            <p class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Tambah Bukti Dukung</p>
                            <BuktiDukungPicker :report-id="report.id" />
                        </div>

                        <!-- Action buttons -->
                        <div v-if="report.approval_status !== 'approved'" class="flex flex-wrap gap-2 pt-1">
                            <template v-if="openFormType.get(report.id) === undefined">
                                <Button
                                    v-if="report.approval_status === 'pending'"
                                    size="sm"
                                    variant="outline"
                                    @click="openForm(report, 'edit')"
                                >
                                    Edit
                                </Button>
                                <Button
                                    v-if="report.approval_status === 'rejected'"
                                    size="sm"
                                    @click="openForm(report, 'resubmit')"
                                >
                                    Ajukan Ulang
                                </Button>
                            </template>
                            <Button
                                v-else
                                size="sm"
                                variant="ghost"
                                class="text-gray-500"
                                @click="closeForm(report.id)"
                            >
                                Batal
                            </Button>
                        </div>

                        <!-- Inline edit / resubmit form -->
                        <div
                            v-if="openFormType.has(report.id)"
                            class="rounded-lg border border-gray-100 bg-gray-50 p-4 space-y-3"
                        >
                            <div>
                                <Label class="text-xs">Realisasi ({{ work_item.target_unit }})</Label>
                                <Input
                                    v-model="getFormState(report.id).realization"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="mt-1"
                                />
                                <InputError :message="getFormState(report.id).errors.realization" />
                            </div>
                            <div>
                                <Label class="text-xs">Kendala</Label>
                                <Textarea v-model="getFormState(report.id).issues" class="mt-1 min-h-16 text-sm" />
                            </div>
                            <div>
                                <Label class="text-xs">Solusi</Label>
                                <Textarea v-model="getFormState(report.id).solutions" class="mt-1 min-h-16 text-sm" />
                            </div>
                            <div>
                                <Label class="text-xs">Rencana Tindak Lanjut</Label>
                                <Textarea v-model="getFormState(report.id).action_plan" class="mt-1 min-h-16 text-sm" />
                            </div>
                            <Button
                                size="sm"
                                :disabled="getFormState(report.id).processing"
                                @click="openFormType.get(report.id) === 'resubmit' ? saveResubmit(report) : saveEdit(report)"
                            >
                                <span v-if="openFormType.get(report.id) === 'resubmit'">Ajukan Ulang</span>
                                <span v-else>Simpan</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New report form -->
            <div class="mt-6">
                <div v-if="!showNewForm && availableMonths.length > 0">
                    <Button variant="outline" @click="openNewForm">
                        + Tambah Laporan Bulan Lain
                    </Button>
                </div>

                <div v-if="showNewForm" class="rounded-lg border border-blue-200 bg-blue-50/30 p-4 space-y-3">
                    <h3 class="text-sm font-medium text-gray-700">Laporan Baru</h3>
                    <div>
                        <Label class="text-xs">Bulan</Label>
                        <select
                            v-model="newForm.period_month"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="m in availableMonths" :key="m" :value="m">{{ monthNames[m] }}</option>
                        </select>
                    </div>
                    <div>
                        <Label class="text-xs">Realisasi ({{ work_item.target_unit }})</Label>
                        <Input v-model="newForm.realization" type="number" step="0.01" min="0" class="mt-1" />
                        <InputError :message="newForm.errors['items.0.realization']" />
                    </div>
                    <div>
                        <Label class="text-xs">Kendala</Label>
                        <Textarea v-model="newForm.issues" class="mt-1 min-h-16 text-sm" />
                    </div>
                    <div>
                        <Label class="text-xs">Solusi</Label>
                        <Textarea v-model="newForm.solutions" class="mt-1 min-h-16 text-sm" />
                    </div>
                    <div>
                        <Label class="text-xs">Rencana Tindak Lanjut</Label>
                        <Textarea v-model="newForm.action_plan" class="mt-1 min-h-16 text-sm" />
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button size="sm" :disabled="newForm.processing" @click="submitNew">Simpan</Button>
                        <Button size="sm" variant="ghost" @click="showNewForm = false">Batal</Button>
                        <span class="text-[11px] text-gray-400 ml-1">Bukti dukung dapat ditambahkan setelah laporan disimpan.</span>
                    </div>
                </div>

                <p v-if="!showNewForm && availableMonths.length === 0 && (reports ?? []).length > 0" class="mt-4 text-sm text-gray-400">
                    Semua 12 bulan sudah dilaporkan.
                </p>

                <p v-if="(reports ?? []).length === 0 && !showNewForm" class="mt-4 text-sm text-gray-500">
                    Belum ada laporan untuk tahun ini.
                    <button class="ml-1 text-blue-600 underline" @click="openNewForm">Tambah laporan pertama.</button>
                </p>
            </div>
        </template>

        <!-- ── Lead view ─────────────────────────────────────────────────── -->
        <template v-else>
            <div class="space-y-8">
                <div v-for="member in (member_reports ?? [])" :key="member.employee.id">
                    <!-- Member header -->
                    <div class="mb-3 flex items-center gap-2">
                        <h3 class="font-semibold text-gray-700">{{ member.employee.name }}</h3>
                        <span class="text-xs text-gray-400">·</span>
                        <span class="text-xs text-gray-500">
                            Target: {{ Number(member.target).toLocaleString('id') }} {{ member.target_unit }}
                        </span>
                    </div>

                    <!-- Reports -->
                    <div v-if="!member.reports.length" class="rounded-lg border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-400">
                        Belum ada laporan dari {{ member.employee.name }}.
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="report in member.reports"
                            :key="report.id"
                            class="rounded-lg border border-gray-200 bg-white shadow-sm"
                        >
                            <!-- Report header -->
                            <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-3">
                                <span class="font-medium text-gray-700">{{ monthNames[report.period_month] }} {{ report.period_year }}</span>
                                <span class="text-gray-300">·</span>
                                <span class="text-sm text-gray-600">
                                    {{ Number(report.realization).toLocaleString('id') }} {{ work_item.target_unit }}
                                    <span class="text-gray-400">({{ pct(report.realization, member.target) }}%)</span>
                                </span>
                                <span
                                    :class="['ml-auto inline-flex items-center rounded border px-2 py-0.5 text-xs font-medium', statusBadgeClass(report.approval_status)]"
                                >
                                    {{ statusLabel(report.approval_status) }}
                                </span>
                            </div>

                            <div class="p-4 space-y-4">
                                <!-- Kendala / Solusi / RTL -->
                                <div v-if="report.issues || report.solutions || report.action_plan" class="grid gap-3 sm:grid-cols-3 text-sm">
                                    <div v-if="report.issues">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mb-1">Kendala</p>
                                        <p class="text-gray-700 whitespace-pre-line">{{ report.issues }}</p>
                                    </div>
                                    <div v-if="report.solutions">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mb-1">Solusi</p>
                                        <p class="text-gray-700 whitespace-pre-line">{{ report.solutions }}</p>
                                    </div>
                                    <div v-if="report.action_plan">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mb-1">Rencana Tindak Lanjut</p>
                                        <p class="text-gray-700 whitespace-pre-line">{{ report.action_plan }}</p>
                                    </div>
                                </div>

                                <!-- Review note -->
                                <div v-if="report.review_note" class="rounded border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">
                                    <span class="font-medium">Catatan:</span> {{ report.review_note }}
                                </div>

                                <!-- Timeline -->
                                <div>
                                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Riwayat</p>
                                    <PerformanceTimeline :reviews="report.reviews" />
                                </div>

                                <!-- Attachments -->
                                <div v-if="report.attachments.length">
                                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Bukti Dukung</p>
                                    <div class="space-y-1.5">
                                        <div
                                            v-for="att in report.attachments"
                                            :key="att.id"
                                            class="flex items-center gap-2 rounded border border-gray-100 bg-gray-50 px-3 py-2 text-sm"
                                        >
                                            <span class="min-w-0 flex-1 truncate">
                                                <a v-if="att.display_url" :href="att.display_url" target="_blank" rel="noopener" class="text-blue-600 hover:underline">
                                                    {{ att.title || att.file_name || att.url }}
                                                </a>
                                                <span v-else class="text-gray-600">{{ att.title || att.file_name || att.url }}</span>
                                            </span>
                                            <span :class="['shrink-0 rounded border px-1.5 py-0.5 text-[10px]', attachmentStatusColor(att.status)]">
                                                {{ attachmentStatusLabel(att.status) }}
                                            </span>
                                            <template v-if="att.status === 'pending'">
                                                <button
                                                    type="button"
                                                    class="shrink-0 rounded bg-green-500 px-2 py-0.5 text-[10px] text-white hover:bg-green-600"
                                                    @click="router.patch(route('report-attachments.review', att.id), { status: 'approved' }, { preserveScroll: true })"
                                                >&#10003;</button>
                                                <button
                                                    type="button"
                                                    class="shrink-0 rounded bg-red-400 px-2 py-0.5 text-[10px] text-white hover:bg-red-500"
                                                    @click="router.patch(route('report-attachments.review', att.id), { status: 'rejected' }, { preserveScroll: true })"
                                                >&#10007;</button>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Approve / Reject actions -->
                                <div v-if="report.approval_status === 'pending'">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Button size="sm" class="bg-green-600 hover:bg-green-700 text-white" @click="approveReport(report.id)">
                                            Setujui
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="border-red-300 text-red-600 hover:bg-red-50"
                                            @click="showRejectFormMap[report.id] = !showRejectFormMap[report.id]"
                                        >
                                            Tolak
                                        </Button>
                                    </div>

                                    <div v-if="showRejectFormMap[report.id]" class="mt-2 flex gap-2">
                                        <input
                                            v-model="rejectNoteMap[report.id]"
                                            type="text"
                                            placeholder="Alasan penolakan..."
                                            class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
                                        />
                                        <Button size="sm" class="bg-red-500 hover:bg-red-600 text-white" @click="submitReject(report.id)">
                                            Kirim
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
