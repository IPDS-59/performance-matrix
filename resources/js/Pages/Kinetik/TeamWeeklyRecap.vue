<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import RecapLockBar from '@/Components/Kinetik/RecapLockBar.vue';
import RecapToolbar from '@/Components/Kinetik/RecapToolbar.vue';
import MeetingChecklist from '@/Components/Kinetik/MeetingChecklist.vue';
import MemberCompletenessCard from '@/Components/Kinetik/MemberCompletenessCard.vue';
import RecapMergeCell from '@/Components/Kinetik/RecapMergeCell.vue';
import RecapMemberLines from '@/Components/Kinetik/RecapMemberLines.vue';
import { groupSize, isGroupLead } from '@/composables/useRecapMerge';
import { weeklyChecklist } from '@/composables/useMeetingChecklist';
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';
import { ChevronDown, ChevronUp, ExternalLink, Trash2, ChevronsUpDown } from 'lucide-vue-next';
import { useRecapExport } from '@/composables/useRecapExport';
import InputError from '@/Components/InputError.vue';
import { useTeamWeeklyRecap, type TeamWeeklyRecapProps } from '@/composables/useTeamWeeklyRecap';

const props = defineProps<TeamWeeklyRecapProps>();

const {
    formatWeekRange,
    navigate,
    achievementColor,
    sortDir,
    toggleSort,
    attentionOnly,
    attentionCount,
    filteredRows,
    expandedRows,
    toggleExpand,
    rowCanParaphrase,
    getParaForm,
    saveParaphrase,
    rowMerge,
    weeklyNoteForm,
    prefillFromMembers,
    saveWeeklyNote,
    evidenceTypeLabel,
    showEvidenceForm,
    evidenceForm,
    submitEvidence,
    deleteEvidence,
} = useTeamWeeklyRecap(props);

const checklist = computed(() => weeklyChecklist({
    members: props.members,
    weeklyNote: props.weeklyNote,
    evidences: props.evidences,
    lock: props.lock,
}));

const { exporting, download } = useRecapExport();
</script>

<template>
    <Head title="Rekap Tim" />
    <AppLayout>
        <template #title>Rekap Tim (Mingguan)</template>

        <div v-if="!teams.length" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Anda belum tergabung dalam tim mana pun.
        </div>

        <template v-else>
            <RecapToolbar
                v-model:attention-only="attentionOnly"
                :teams="teams"
                :selected-team-id="selectedTeamId"
                :period-label="formatWeekRange(weekStart, weekEnd)"
                prev-label="Minggu sebelumnya"
                next-label="Minggu berikutnya"
                :attention-total="segments.reduce((sum, seg) => sum + attentionCount(seg), 0)"
                :exporting="exporting"
                @change-team="navigate({ team: $event })"
                @prev="navigate({ week: prevWeek })"
                @next="navigate({ week: nextWeek })"
                @export="download({ period_type: 'week', week: weekStart }, `Rapat Mingguan ${weekStart}.xlsx`)"
            >
            </RecapToolbar>

            <MeetingChecklist v-if="selectedTeamId" :steps="checklist" />

            <RecapLockBar :team-id="selectedTeamId" :lock="lock" :can-lock="canLock" :period="{ period_type: 'week', period_year: Number(weekStart.slice(0, 4)), week_start: weekStart }" />

            <MemberCompletenessCard v-if="selectedTeamId" :members="members" />

            <!-- Segments by project -->
            <div v-if="!segments.length" class="mb-6 rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                Belum ada rekap tersimpan untuk tim ini pada minggu ini.
            </div>

            <div v-else class="mb-8 space-y-6">
                <div v-for="seg in segments" :key="seg.project_id ?? 'none'" class="overflow-hidden rounded-md border bg-white">
                    <div class="flex items-center justify-between gap-3 border-b bg-gray-50 px-4 py-3">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-gray-800">{{ seg.project_name }}</h3>
                            <p v-if="seg.leader_rk" class="mt-0.5 text-xs leading-snug text-primary">RK Ketua: {{ seg.leader_rk }}</p>
                        </div>
                        <Button
                            v-if="canManage && rowMerge.selectedCount(seg) >= 2"
                            size="sm"
                            class="ml-auto h-7 px-2.5 text-xs"
                            :disabled="rowMerge.busy.value"
                            @click="rowMerge.merge(seg, filteredRows(seg))"
                        >
                            Gabungkan {{ rowMerge.selectedCount(seg) }} baris
                        </Button>
                        <span v-if="attentionCount(seg) > 0" class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">
                            {{ attentionCount(seg) }} perlu perhatian
                        </span>
                    </div>

                    <Table class="w-full text-sm">
                        <TableHeader>
                            <TableRow class="border-b bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                                <TableHead class="min-w-[13rem] text-left sm:min-w-[18rem]">Rencana Kinerja</TableHead>
                                <TableHead class="hidden text-left text-xs md:table-cell">Kontributor</TableHead>
                                <TableHead class="text-right">Target</TableHead>
                                <TableHead class="text-right">Realisasi</TableHead>
                                <TableHead class="cursor-pointer select-none text-right" @click="toggleSort(String(seg.project_id ?? 'none'))">
                                    <span class="inline-flex items-center gap-1">
                                        Capaian
                                        <ChevronsUpDown class="h-3 w-3" :class="{ 'rotate-180': sortDir(String(seg.project_id ?? 'none')) === 'desc' }" />
                                    </span>
                                </TableHead>
                                <TableHead class="w-8" />
                            </TableRow>
                        </TableHeader>
                        <TableBody class="divide-y divide-gray-100">
                            <template v-for="row in filteredRows(seg)" :key="row.row_key">
                                <TableRow :class="['hover:bg-gray-50', groupSize(seg, row) > 1 ? 'border-l-2 border-l-primary/60' : '']">
                                    <TableCell class="min-w-[13rem] whitespace-normal align-top sm:min-w-[18rem]">
                                        <RecapMergeCell
                                            :row="row"
                                            :size="groupSize(seg, row)"
                                            :lead="isGroupLead(row)"
                                            :selectable="canManage"
                                            :selected="rowMerge.isSelected(seg, row)"
                                            :busy="rowMerge.busy.value"
                                            @toggle="rowMerge.toggle(seg, row)"
                                            @split="rowMerge.split(row)"
                                        >
                                            <p class="font-medium leading-snug text-gray-800">{{ row.rk_description }}</p>
                                            <p v-if="row.rk_code" class="text-xs text-gray-500">{{ row.rk_code }}</p>
                                            <RecapMemberLines :claims="row.claims ?? []" :can-adjust="canManage" />
                                        </RecapMergeCell>
                                    </TableCell>
                                    <TableCell class="hidden min-w-[10rem] max-w-[16rem] whitespace-normal align-top md:table-cell text-xs leading-snug text-gray-600">{{ row.contributors.join(', ') || '—' }}</TableCell>
                                    <TableCell class="text-right align-top tabular-nums text-gray-700">{{ row.target }} {{ row.target_unit ?? '' }}</TableCell>
                                    <TableCell class="text-right align-top tabular-nums text-gray-700">{{ row.realization }}</TableCell>
                                    <TableCell class="text-right align-top tabular-nums">
                                        <span v-if="row.achievement != null" :class="achievementColor(row.achievement)">{{ row.achievement.toFixed(2) }}%</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </TableCell>
                                    <TableCell class="text-center align-top">
                                        <button
                                            type="button"
                                            class="flex h-6 w-6 items-center justify-center rounded hover:bg-gray-100"
                                            :title="expandedRows[row.row_key] ? 'Tutup detail' : 'Lihat detail anggota'"
                                            @click="toggleExpand(row.row_key)"
                                        >
                                            <ChevronDown v-if="!expandedRows[row.row_key]" class="h-4 w-4 text-gray-500" />
                                            <ChevronUp v-else class="h-4 w-4 text-gray-500" />
                                        </button>
                                    </TableCell>
                                </TableRow>

                                <!-- Expand panel -->
                                <TableRow v-if="expandedRows[row.row_key]" :key="`${row.row_key}-panel`" class="bg-gray-50">
                                    <TableCell colspan="7" class="whitespace-normal px-4 py-4 sm:px-6">
                                        <div class="space-y-4">
                                            <!-- Member uraian (read-only reference) -->
                                            <div>
                                                <p class="mb-1 text-xs font-medium text-gray-500">Uraian Kegiatan Anggota</p>
                                                <p v-if="row.uraian_aggregated" class="whitespace-pre-line rounded bg-white px-3 py-2 text-sm text-gray-700 ring-1 ring-gray-200">{{ row.uraian_aggregated }}</p>
                                                <p v-else class="rounded bg-white px-3 py-2 text-sm text-gray-400 ring-1 ring-gray-200">—</p>
                                            </div>

                                            <!-- Member kendala (read-only) -->
                                            <div>
                                                <p class="mb-1 text-xs font-medium text-gray-500">Kendala (anggota)</p>
                                                <p class="rounded bg-white px-3 py-2 text-sm text-gray-700 ring-1 ring-gray-200">{{ row.obstacle_aggregated || '—' }}</p>
                                            </div>

                                            <!-- PJ per-plan fields: Uraian / Solusi / RTL -->
                                            <template v-if="rowCanParaphrase(row)">
                                                <div>
                                                    <Label class="text-xs">Uraian (PJ)</Label>
                                                    <Textarea v-model="getParaForm(row).uraian" :rows="3" class="mt-1 text-sm" placeholder="Kosongkan untuk memakai uraian kegiatan anggota di Excel" />
                                                </div>
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                    <div>
                                                        <Label class="text-xs">Solusi (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).solution" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs">RTL (PJ)</Label>
                                                        <Textarea v-model="getParaForm(row).follow_up_plan" :rows="2" class="mt-1 text-sm" />
                                                    </div>
                                                </div>
                                                <div class="flex justify-end">
                                                    <Button size="sm" :disabled="getParaForm(row).saving" @click="saveParaphrase(row)">
                                                        Simpan
                                                    </Button>
                                                </div>
                                            </template>

                                            <!-- Read-only for non-PJ -->
                                            <template v-else-if="row.pj_uraian || row.pj_solution || row.pj_follow_up_plan">
                                                <div v-if="row.pj_uraian">
                                                    <p class="mb-1 text-xs font-medium text-gray-500">Uraian (PJ)</p>
                                                    <p class="whitespace-pre-line text-sm text-gray-700">{{ row.pj_uraian }}</p>
                                                </div>
                                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">Solusi (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_solution || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-xs font-medium text-gray-500">RTL (PJ)</p>
                                                        <p class="text-sm text-gray-700">{{ row.pj_follow_up_plan || '—' }}</p>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- Single PJ weekly summary form -->
            <div id="ringkasan-pj" class="mb-8 scroll-mt-4 rounded-md border bg-white">
                <div class="flex items-center justify-between border-b bg-gray-50 px-4 py-3">
                    <h2 class="text-sm font-semibold text-gray-800">Ringkasan Mingguan PJ</h2>
                    <span v-if="!canManage" class="text-xs text-gray-400">Hanya PJ yang dapat mengisi ringkasan</span>
                </div>

                <div v-if="canManage" class="space-y-4 p-4">
                    <!-- Uraian -->
                    <div>
                        <div class="mb-1 flex items-center justify-between">
                            <Label class="text-xs font-medium">Uraian Kegiatan</Label>
                            <button
                                v-if="segments.some(s => s.rows.some(r => r.uraian_items?.length))"
                                type="button"
                                class="text-xs text-primary hover:underline"
                                @click="prefillFromMembers"
                            >
                                Pre-fill dari data anggota
                            </button>
                        </div>
                        <Textarea
                            v-model="weeklyNoteForm.uraian"
                            :rows="5"
                            class="text-sm"
                            placeholder="Tuliskan ringkasan seluruh kegiatan tim minggu ini…"
                        />
                    </div>

                    <!-- Kendala / Solusi / RTL -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <Label class="mb-1 text-xs font-medium">Kendala</Label>
                            <Textarea v-model="weeklyNoteForm.obstacle" :rows="3" class="text-sm" placeholder="Kendala yang dihadapi minggu ini…" />
                        </div>
                        <div>
                            <Label class="mb-1 text-xs font-medium">Solusi</Label>
                            <Textarea v-model="weeklyNoteForm.solution" :rows="3" class="text-sm" placeholder="Solusi yang diterapkan…" />
                        </div>
                        <div>
                            <Label class="mb-1 text-xs font-medium">RTL</Label>
                            <Textarea v-model="weeklyNoteForm.follow_up_plan" :rows="3" class="text-sm" placeholder="Rencana tindak lanjut…" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button size="sm" :disabled="weeklyNoteForm.saving" @click="saveWeeklyNote">
                            Simpan Ringkasan
                        </Button>
                    </div>
                </div>

                <!-- Read-only view for non-PJ -->
                <div v-else class="p-4">
                    <div v-if="weeklyNote">
                        <div class="mb-4">
                            <p class="mb-1 text-xs font-medium text-gray-500">Uraian Kegiatan</p>
                            <p class="whitespace-pre-line text-sm text-gray-800">{{ weeklyNote.uraian || '—' }}</p>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <p class="mb-1 text-xs font-medium text-gray-500">Kendala</p>
                                <p class="text-sm text-gray-700">{{ weeklyNote.obstacle || '—' }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs font-medium text-gray-500">Solusi</p>
                                <p class="text-sm text-gray-700">{{ weeklyNote.solution || '—' }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs font-medium text-gray-500">RTL</p>
                                <p class="text-sm text-gray-700">{{ weeklyNote.follow_up_plan || '—' }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">Belum ada ringkasan mingguan dari PJ.</p>
                </div>
            </div>

            <!-- Bukti Dukung Rapat -->
            <div id="bukti-rapat" class="scroll-mt-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">Bukti Dukung Rapat</h2>
                    <Button v-if="canManage" size="sm" variant="outline" @click="showEvidenceForm = !showEvidenceForm">
                        {{ showEvidenceForm ? 'Tutup' : 'Tambah Bukti' }}
                    </Button>
                    <span v-else class="text-xs text-gray-400">Hanya PJ yang dapat menambah bukti</span>
                </div>

                <form v-if="canManage && showEvidenceForm" class="mb-4 space-y-3 rounded-md border bg-white p-4" @submit.prevent="submitEvidence">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label for="ev-type">Jenis</Label>
                            <Select v-model="evidenceForm.type">
                                <SelectTrigger id="ev-type" class="mt-1 w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="notula">Notula</SelectItem>
                                    <SelectItem value="photo">Foto</SelectItem>
                                    <SelectItem value="attendance">Daftar Hadir</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label for="ev-title">Judul</Label>
                            <Input id="ev-title" v-model="evidenceForm.title" class="mt-1" placeholder="Rapat koordinasi..." />
                        </div>
                    </div>
                    <div>
                        <Label for="ev-url">URL Bukti Dukung <span class="text-red-500">*</span></Label>
                        <Input id="ev-url" v-model="evidenceForm.url" type="url" class="mt-1" placeholder="https://..." />
                        <InputError :message="evidenceForm.errors.url" />
                    </div>
                    <div class="flex justify-end">
                        <Button type="submit" size="sm" :disabled="evidenceForm.processing">Simpan Bukti</Button>
                    </div>
                </form>

                <div v-if="!evidences.length" class="rounded-md border border-dashed border-gray-200 bg-gray-50 py-8 text-center text-sm text-gray-400">
                    Belum ada bukti dukung untuk minggu ini.
                </div>
                <ul v-else class="divide-y divide-gray-100 overflow-hidden rounded-md border bg-white">
                    <li v-for="ev in evidences" :key="ev.id" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ evidenceTypeLabel[ev.type] ?? ev.type }}</span>
                            <span class="ml-2 text-sm text-gray-800">{{ ev.title || '—' }}</span>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <a :href="ev.url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                                Buka <ExternalLink class="h-3 w-3" />
                            </a>
                            <button v-if="canManage" type="button" class="text-gray-400 hover:text-red-600" title="Hapus" @click="deleteEvidence(ev.id)">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </template>
    </AppLayout>
</template>
