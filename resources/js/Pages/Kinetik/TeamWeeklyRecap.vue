<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';
import { ChevronLeft, ChevronRight, ChevronDown, ChevronUp, ExternalLink, Trash2, ChevronsUpDown } from 'lucide-vue-next';
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
    weeklyNoteForm,
    prefillFromMembers,
    saveWeeklyNote,
    evidenceTypeLabel,
    showEvidenceForm,
    evidenceForm,
    submitEvidence,
    deleteEvidence,
} = useTeamWeeklyRecap(props);
</script>

<template>
    <Head title="Rekap Tim" />
    <AppLayout>
        <template #title>Rekap Tim (Mingguan)</template>

        <div v-if="!teams.length" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Anda belum tergabung dalam tim mana pun.
        </div>

        <template v-else>
            <!-- Controls -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-md border bg-white px-4 py-3">
                <Select
                    :model-value="selectedTeamId != null ? String(selectedTeamId) : undefined"
                    @update:model-value="(v) => navigate({ team: Number(v) })"
                >
                    <SelectTrigger class="w-auto min-w-[16rem]">
                        <SelectValue placeholder="Pilih tim" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                    </SelectContent>
                </Select>

                <div class="flex items-center gap-3">
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Minggu sebelumnya" @click="navigate({ week: prevWeek })">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="text-sm font-medium text-gray-700">{{ formatWeekRange(weekStart, weekEnd) }}</span>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Minggu berikutnya" @click="navigate({ week: nextWeek })">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        :class="['inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors', attentionOnly ? 'border-orange-400 bg-orange-50 text-orange-700' : 'border-gray-200 bg-white text-gray-600 hover:border-orange-300 hover:text-orange-600']"
                        @click="attentionOnly = !attentionOnly"
                    >
                        Perlu perhatian
                        <span :class="['rounded-full px-1.5 py-0.5 text-xs', attentionOnly ? 'bg-orange-400 text-white' : 'bg-gray-200 text-gray-600']">
                            {{ segments.reduce((sum, seg) => sum + attentionCount(seg), 0) }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Segments by project -->
            <div v-if="!segments.length" class="mb-6 rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                Belum ada rekap tersimpan untuk tim ini pada minggu ini.
            </div>

            <div v-else class="mb-8 space-y-6">
                <div v-for="seg in segments" :key="seg.project_id ?? 'none'" class="overflow-hidden rounded-md border bg-white">
                    <div class="flex items-center justify-between border-b bg-gray-50 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-800">{{ seg.project_name }}</h3>
                        <span v-if="attentionCount(seg) > 0" class="inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">
                            {{ attentionCount(seg) }} perlu perhatian
                        </span>
                    </div>

                    <Table class="w-full text-sm">
                        <TableHeader>
                            <TableRow class="border-b bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                                <TableHead class="text-left">Rencana Kinerja</TableHead>
                                <TableHead class="text-left text-xs">Kontributor</TableHead>
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
                            <template v-for="row in filteredRows(seg)" :key="row.performance_plan_id">
                                <TableRow class="hover:bg-gray-50">
                                    <TableCell class="align-top">
                                        <p class="font-medium text-gray-800">{{ row.rk_description }}</p>
                                        <p v-if="row.rk_code" class="text-xs text-gray-500">{{ row.rk_code }}</p>
                                    </TableCell>
                                    <TableCell class="align-top text-xs text-gray-600">{{ row.contributors.join(', ') || '—' }}</TableCell>
                                    <TableCell class="text-right align-top text-gray-700">{{ row.target }} {{ row.target_unit ?? '' }}</TableCell>
                                    <TableCell class="text-right align-top text-gray-700">{{ row.realization }}</TableCell>
                                    <TableCell class="text-right align-top">
                                        <span v-if="row.achievement != null" :class="achievementColor(row.achievement)">{{ row.achievement.toFixed(2) }}%</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </TableCell>
                                    <TableCell class="text-center align-top">
                                        <button
                                            type="button"
                                            class="flex h-6 w-6 items-center justify-center rounded hover:bg-gray-100"
                                            :title="expandedRows[row.performance_plan_id] ? 'Tutup detail' : 'Lihat detail anggota'"
                                            @click="toggleExpand(row.performance_plan_id)"
                                        >
                                            <ChevronDown v-if="!expandedRows[row.performance_plan_id]" class="h-4 w-4 text-gray-500" />
                                            <ChevronUp v-else class="h-4 w-4 text-gray-500" />
                                        </button>
                                    </TableCell>
                                </TableRow>

                                <!-- Expand panel -->
                                <TableRow v-if="expandedRows[row.performance_plan_id]" :key="`${row.performance_plan_id}-panel`" class="bg-gray-50">
                                    <TableCell colspan="7" class="px-6 py-4">
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

                                            <!-- PJ per-plan fields: Solusi / RTL only -->
                                            <template v-if="rowCanParaphrase(row)">
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
                                            <template v-else-if="row.pj_solution || row.pj_follow_up_plan">
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
            <div class="mb-8 rounded-md border bg-white">
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
            <div>
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
