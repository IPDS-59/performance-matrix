<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import RecapLockBar from '@/Components/Kinetik/RecapLockBar.vue';
import RecapToolbar from '@/Components/Kinetik/RecapToolbar.vue';
import MeetingChecklist from '@/Components/Kinetik/MeetingChecklist.vue';
import MemberCompletenessCard from '@/Components/Kinetik/MemberCompletenessCard.vue';
import WeeklyProjectCard from '@/Components/Kinetik/WeeklyProjectCard.vue';
import RecapPeriodSections from '@/Components/Kinetik/RecapPeriodSections.vue';
import { weeklyChecklist } from '@/composables/useMeetingChecklist';
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { ExternalLink, Trash2 } from 'lucide-vue-next';
import { useRecapExport } from '@/composables/useRecapExport';
import InputError from '@/Components/InputError.vue';
import { useTeamWeeklyRecap, type TeamWeeklyRecapProps } from '@/composables/useTeamWeeklyRecap';

const props = defineProps<TeamWeeklyRecapProps>();

const {
    formatWeekRange,
    navigate,
    sortDir,
    toggleSort,
    attentionOnly,
    attentionCount,
    filteredRows,
    rowCanParaphrase,
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
                <WeeklyProjectCard
                    v-for="seg in segments"
                    :key="seg.project_id ?? 'none'"
                    :seg="seg"
                    :rows="filteredRows(seg)"
                    :can-manage="canManage && !lock"
                    :can-paraphrase="rowCanParaphrase"
                    :attention="attentionCount(seg)"
                    :sort-dir="sortDir(String(seg.project_id ?? 'none'))"
                    :merge="rowMerge"
                    :payload="{ team_id: selectedTeamId, week_start: weekStart }"
                    @sort="toggleSort(String(seg.project_id ?? 'none'))"
                />
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

            <RecapPeriodSections v-if="previousWeeks.length" class="mt-8" title="Laporan Tersimpan — minggu sebelumnya" :sections="previousWeeks" />
        </template>
    </AppLayout>
</template>
