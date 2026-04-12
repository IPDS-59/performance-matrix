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
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog';

// ── Types ──────────────────────────────────────────────────────────────────

interface Attachment {
    id: number;
    type: 'file' | 'link';
    file_name: string | null;
    mime_type: string | null;
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
    target_reached: boolean;
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

const deleteDialogOpen = ref(false);
const deleteTargetId = ref<number | null>(null);

// ── Attachment preview ─────────────────────────────────────────────────────
const previewAttachment = ref<Attachment | null>(null);

function attachmentLabel(att: Attachment): string {
    return att.title || att.file_name || att.url || 'Lampiran';
}

function isImage(att: Attachment): boolean {
    return att.type === 'file' && (att.mime_type?.startsWith('image/') ?? false);
}

function isPdf(att: Attachment): boolean {
    return att.type === 'file' && att.mime_type === 'application/pdf';
}

function deleteReport(report: ReportData) {
    deleteTargetId.value = report.id;
    deleteDialogOpen.value = true;
}

function confirmDelete() {
    const id = deleteTargetId.value;
    if (!id) return;
    deleteDialogOpen.value = false;
    deleteTargetId.value = null;
    router.delete(route('performance.destroy', id), { preserveScroll: true });
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
const newFormSubmitting = ref(false);
const newForm = useForm({
    period_month: 1 as number,
    realization: '' as number | string,
    issues: '',
    solutions: '',
    action_plan: '',
});

// ── Pending attachments for the new form ──────────────────────────────────

interface PendingFile { kind: 'file'; file: File; title: string; localId: number }
interface PendingUrl  { kind: 'url';  url: string; title: string; localId: number }
type PendingAttachment = PendingFile | PendingUrl;

let pendingIdCounter = 0;
const newPendingAttachments = ref<PendingAttachment[]>([]);
const newAttachTab = ref<'file' | 'url'>('file');
const newAttachFile = ref<File | null>(null);
const newAttachFileTitle = ref('');
const newAttachFileError = ref('');
const newAttachFileInput = ref<HTMLInputElement | null>(null);
const newAttachUrl = ref('');
const newAttachUrlTitle = ref('');
const newAttachUrlError = ref('');

const ALLOWED_MIME = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
const MAX_BYTES = 10 * 1024 * 1024;

function formatBytes(b: number): string {
    return b < 1048576 ? `${(b / 1024).toFixed(1)} KB` : `${(b / 1048576).toFixed(1)} MB`;
}

function onNewAttachFileChange(e: Event) {
    const f = (e.target as HTMLInputElement).files?.[0];
    if (!f) return;
    if (!ALLOWED_MIME.includes(f.type)) { newAttachFileError.value = 'Format tidak didukung. Gunakan PDF, JPG, PNG, atau WEBP.'; return; }
    if (f.size > MAX_BYTES) { newAttachFileError.value = `Maks 10 MB (file ini ${formatBytes(f.size)}).`; return; }
    newAttachFileError.value = '';
    newAttachFile.value = f;
}

function addPendingFile() {
    if (!newAttachFile.value) { newAttachFileError.value = 'Pilih file terlebih dahulu.'; return; }
    newPendingAttachments.value.push({ kind: 'file', file: newAttachFile.value, title: newAttachFileTitle.value, localId: ++pendingIdCounter });
    newAttachFile.value = null;
    newAttachFileTitle.value = '';
    newAttachFileError.value = '';
    if (newAttachFileInput.value) newAttachFileInput.value.value = '';
}

function addPendingUrl() {
    newAttachUrlError.value = '';
    if (!newAttachUrl.value.trim()) { newAttachUrlError.value = 'URL tidak boleh kosong.'; return; }
    try { new URL(newAttachUrl.value.trim()); } catch { newAttachUrlError.value = 'Format URL tidak valid.'; return; }
    newPendingAttachments.value.push({ kind: 'url', url: newAttachUrl.value.trim(), title: newAttachUrlTitle.value, localId: ++pendingIdCounter });
    newAttachUrl.value = '';
    newAttachUrlTitle.value = '';
}

function removePending(localId: number) {
    newPendingAttachments.value = newPendingAttachments.value.filter(a => a.localId !== localId);
}

function inertiaPost(url: string, data: Record<string, unknown> | FormData, options: Record<string, unknown> = {}): Promise<void> {
    return new Promise<void>((resolve) => {
        router.post(url, data as Parameters<typeof router.post>[1], { ...options, onFinish: () => resolve() });
    });
}

function openNewForm() {
    newForm.period_month = availableMonths.value[0] ?? 1;
    newForm.realization = '';
    newForm.issues = '';
    newForm.solutions = '';
    newForm.action_plan = '';
    newPendingAttachments.value = [];
    newAttachFile.value = null;
    newAttachFileTitle.value = '';
    newAttachUrl.value = '';
    newAttachUrlTitle.value = '';
    showNewForm.value = true;
}

async function submitNew() {
    const submittedMonth = newForm.period_month;
    const pendingAtts = [...newPendingAttachments.value];
    newFormSubmitting.value = true;

    // Step 1: create the report
    await inertiaPost(route('performance.batch'), {
        period_month: newForm.period_month,
        period_year: props.year,
        items: [{ work_item_id: props.work_item.id, realization: newForm.realization, issues: newForm.issues, solutions: newForm.solutions, action_plan: newForm.action_plan }],
    } as Record<string, unknown>, { preserveScroll: true });

    showNewForm.value = false;
    newForm.reset();
    newPendingAttachments.value = [];

    await nextTick();
    const newReport = (props.reports ?? []).find(r => r.period_month === submittedMonth);

    // Step 2: upload queued attachments sequentially
    if (newReport && pendingAtts.length > 0) {
        for (const att of pendingAtts) {
            if (att.kind === 'file') {
                const fd = new FormData();
                fd.append('type', 'file');
                fd.append('file', att.file);
                if (att.title) fd.append('title', att.title);
                await inertiaPost(route('report-attachments.store', newReport.id), fd, { forceFormData: true, preserveScroll: true });
            } else {
                await inertiaPost(route('report-attachments.store', newReport.id), {
                    type: 'link',
                    url: att.url,
                    title: att.title || null,
                } as Record<string, unknown>, { preserveScroll: true });
            }
        }
    }

    newFormSubmitting.value = false;

    // Scroll to the newly created report card
    await nextTick();
    const finalReport = (props.reports ?? []).find(r => r.period_month === submittedMonth);
    if (finalReport) {
        document.getElementById(`report-${finalReport.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
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
                                    <button
                                        type="button"
                                        class="min-w-0 flex-1 truncate text-left text-blue-600 hover:underline"
                                        @click="previewAttachment = att"
                                    >{{ attachmentLabel(att) }}</button>
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
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="text-red-500 hover:bg-red-50 hover:text-red-700"
                                    @click="deleteReport(report)"
                                >
                                    Hapus
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
                <div v-if="work_item.target_reached" class="flex items-center gap-2 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Target sudah tercapai — tidak dapat menambah laporan baru untuk rincian ini.
                </div>
                <div v-else-if="!showNewForm && availableMonths.length > 0">
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

                    <!-- ── Bukti dukung queue ──────────────────────────────── -->
                    <div class="rounded-lg border border-gray-200 bg-white p-3 space-y-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Bukti Dukung (opsional)</p>

                        <!-- Tab toggle -->
                        <div class="flex gap-1 rounded-lg bg-gray-100 p-0.5 w-fit">
                            <button
                                type="button"
                                :class="['rounded-md px-3 py-1 text-xs transition-colors', newAttachTab === 'file' ? 'bg-white shadow-sm font-medium text-gray-700' : 'text-gray-500 hover:text-gray-700']"
                                @click="newAttachTab = 'file'"
                            >File</button>
                            <button
                                type="button"
                                :class="['rounded-md px-3 py-1 text-xs transition-colors', newAttachTab === 'url' ? 'bg-white shadow-sm font-medium text-gray-700' : 'text-gray-500 hover:text-gray-700']"
                                @click="newAttachTab = 'url'"
                            >URL</button>
                        </div>

                        <!-- File input -->
                        <div v-if="newAttachTab === 'file'" class="space-y-1.5">
                            <div
                                class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-3 hover:border-blue-300 hover:bg-blue-50/50 transition-colors"
                                @click="newAttachFileInput?.click()"
                            >
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                </svg>
                                <span class="text-xs text-gray-500">
                                    <span v-if="newAttachFile">{{ newAttachFile.name }} <span class="text-gray-400">({{ formatBytes(newAttachFile.size) }})</span></span>
                                    <span v-else><span class="text-blue-600">Klik pilih file</span> · PDF, JPG, PNG, WEBP · Maks 10 MB</span>
                                </span>
                                <input ref="newAttachFileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only" @change="onNewAttachFileChange" />
                            </div>
                            <InputError :message="newAttachFileError" />
                            <div v-if="newAttachFile" class="flex items-center gap-2">
                                <Input v-model="newAttachFileTitle" placeholder="Label (opsional)" class="h-7 text-xs flex-1" />
                                <Button type="button" size="sm" class="h-7 text-xs px-2.5" @click="addPendingFile">Tambah</Button>
                            </div>
                        </div>

                        <!-- URL input -->
                        <div v-else class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5">
                                        <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                        </svg>
                                    </div>
                                    <Input v-model="newAttachUrl" type="url" placeholder="https://…" class="h-7 pl-7 text-xs" @keydown.enter.prevent="addPendingUrl" />
                                </div>
                                <Input v-model="newAttachUrlTitle" placeholder="Label (opsional)" class="h-7 text-xs w-32" />
                                <Button type="button" size="sm" class="h-7 text-xs px-2.5" @click="addPendingUrl">Tambah</Button>
                            </div>
                            <InputError :message="newAttachUrlError" />
                        </div>

                        <!-- Queued list -->
                        <div v-if="newPendingAttachments.length" class="space-y-1 pt-1">
                            <div
                                v-for="att in newPendingAttachments"
                                :key="att.localId"
                                class="flex items-center gap-2 rounded-md border border-gray-100 bg-gray-50 px-2.5 py-1.5 text-xs"
                            >
                                <svg v-if="att.kind === 'file'" class="h-3 w-3 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                </svg>
                                <svg v-else class="h-3 w-3 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                </svg>
                                <span class="flex-1 truncate text-gray-700">
                                    {{ att.title || (att.kind === 'file' ? att.file.name : att.url) }}
                                </span>
                                <button type="button" class="shrink-0 text-gray-400 hover:text-red-500 transition-colors" @click="removePending(att.localId)">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button size="sm" :disabled="newFormSubmitting" @click="submitNew">
                            <svg v-if="newFormSubmitting" class="mr-1.5 h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ newFormSubmitting ? 'Menyimpan…' : 'Simpan' }}
                        </Button>
                        <Button size="sm" variant="ghost" :disabled="newFormSubmitting" @click="showNewForm = false">Batal</Button>
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
                                            <button
                                                type="button"
                                                class="min-w-0 flex-1 truncate text-left text-blue-600 hover:underline"
                                                @click="previewAttachment = att"
                                            >{{ attachmentLabel(att) }}</button>
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

    <!-- Attachment preview dialog -->
    <Dialog :open="previewAttachment !== null" @update:open="(v) => { if (!v) previewAttachment = null }">
        <DialogContent class="max-w-3xl p-0 overflow-hidden">
            <DialogHeader class="px-4 py-3 border-b">
                <DialogTitle class="truncate text-sm">{{ previewAttachment ? attachmentLabel(previewAttachment) : '' }}</DialogTitle>
            </DialogHeader>
            <div v-if="previewAttachment" class="overflow-auto">
                <!-- Image -->
                <div v-if="isImage(previewAttachment)" class="flex items-center justify-center bg-gray-50 p-4 min-h-[300px]">
                    <img :src="previewAttachment.display_url!" :alt="attachmentLabel(previewAttachment)" class="max-h-[70vh] w-auto max-w-full rounded shadow" />
                </div>
                <!-- PDF -->
                <div v-else-if="isPdf(previewAttachment)" class="h-[75vh]">
                    <iframe :src="previewAttachment.display_url!" class="h-full w-full border-0" />
                </div>
                <!-- URL / link -->
                <div v-else-if="previewAttachment.type === 'link'" class="h-[75vh]">
                    <iframe :src="previewAttachment.url!" class="h-full w-full border-0" sandbox="allow-scripts allow-same-origin allow-forms allow-popups" />
                </div>
                <!-- Fallback: file that can't be previewed inline -->
                <div v-else class="flex flex-col items-center justify-center gap-3 py-12 text-gray-500">
                    <svg class="h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p class="text-sm">Pratinjau tidak tersedia untuk tipe file ini.</p>
                    <a :href="previewAttachment.display_url!" download class="text-sm text-blue-600 hover:underline">Unduh file</a>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Delete report confirmation dialog -->
    <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Hapus laporan ini?</AlertDialogTitle>
                <AlertDialogDescription>
                    Data laporan yang sudah dihapus tidak dapat dipulihkan, termasuk semua bukti dukung yang telah diunggah.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Batal</AlertDialogCancel>
                <AlertDialogAction class="bg-red-600 hover:bg-red-700 focus:ring-red-600" @click="confirmDelete">
                    Hapus
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
