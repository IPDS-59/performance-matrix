<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import RecapLockBar from '@/Components/Kinetik/RecapLockBar.vue';
import RecapToolbar from '@/Components/Kinetik/RecapToolbar.vue';
import MemberCompletenessCard from '@/Components/Kinetik/MemberCompletenessCard.vue';
import WeeklyProjectCard from '@/Components/Kinetik/WeeklyProjectCard.vue';
import SavedWeeklyReport from '@/Components/Kinetik/SavedWeeklyReport.vue';
import RecapPeriodSections from '@/Components/Kinetik/RecapPeriodSections.vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ExternalLink, Trash2 } from 'lucide-vue-next';
import { useRecapExport } from '@/composables/useRecapExport';
import InputError from '@/Components/InputError.vue';
import { useTeamWeeklyRecap, type TeamWeeklyRecapProps } from '@/composables/useTeamWeeklyRecap';

const props = defineProps<TeamWeeklyRecapProps>();

const {
    formatWeekRange,
    navigate,
    attentionOnly,
    attentionCount,
    filteredRows,
    rowCanParaphrase,
    evidenceTypeLabel,
    showEvidenceForm,
    evidenceForm,
    submitEvidence,
    deleteEvidence,
} = useTeamWeeklyRecap(props);

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

            <MemberCompletenessCard v-if="selectedTeamId" :members="members" />

            <!-- One card per Projek -->
            <div v-if="!segments.length" class="mb-6 rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                Belum ada kegiatan yang diklaim anggota untuk tim ini pada minggu ini.
            </div>
            <div v-else class="mb-6 space-y-6">
                <WeeklyProjectCard
                    v-for="seg in segments"
                    :key="seg.project_id ?? 'none'"
                    :seg="seg"
                    :rows="filteredRows(seg)"
                    :can-manage="canManage && !lock"
                    :can-paraphrase="rowCanParaphrase"
                    :attention="attentionCount(seg)"
                    :payload="{ team_id: selectedTeamId, week_start: weekStart }"
                />
            </div>

            <SavedWeeklyReport :segments="segments" :week-start="weekStart" :week-end="weekEnd" />

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

            <RecapPeriodSections v-if="previousWeeks.length" class="mt-8" title="Minggu sebelumnya" :sections="previousWeeks" />

            <RecapLockBar
                :team-id="selectedTeamId"
                :lock="lock"
                :can-lock="canLock"
                hint="Kunci rekap setelah rapat selesai, supaya klaim anggota dan isi laporan minggu ini tidak berubah lagi. PJ dapat membuka kunci kapan saja."
                :period="{ period_type: 'week', period_year: Number(weekStart.slice(0, 4)), week_start: weekStart }"
            />
        </template>
    </AppLayout>
</template>
