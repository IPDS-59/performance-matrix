<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import RecapToolbar from '@/Components/Kinetik/RecapToolbar.vue';
import MemberCompletenessCard from '@/Components/Kinetik/MemberCompletenessCard.vue';
import RkPicker from '@/Components/Kinetik/RkPicker.vue';
import RkRecapPanel from '@/Components/Kinetik/RkRecapPanel.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { MemberCompleteness, PeriodRow, RkPanel, TeamOption, TeamRecapEvidence, TeamWeekPlan } from '@/types';
import TeamWeekPlans from '@/Components/Kinetik/TeamWeekPlans.vue';
import RecapPeriodTable from '@/Components/Kinetik/RecapPeriodTable.vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ExternalLink, Trash2 } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { useRecapExport } from '@/composables/useRecapExport';
import { useDateFormat } from '@/composables/useDateFormat';
import { evidenceTypeLabel, useRecapEvidence } from '@/composables/useRkRecap';

const props = defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    view: 'months' | 'weeks' | 'detail';
    periods: PeriodRow[];
    year: number;
    month: number | null;
    panels: RkPanel[];
    evidences: TeamRecapEvidence[];
    weekStart: string;
    weekEnd: string;
    prevWeek: string;
    nextWeek: string;
    canManage: boolean;
    currentEmployeeId: number | null;
    members: MemberCompleteness[];
    plans: TeamWeekPlan[];
}>();

const { formatWeekRange } = useDateFormat();
const { exporting, download } = useRecapExport();
const evidence = useRecapEvidence(() => ({ teamId: props.selectedTeamId, weekStart: props.weekStart }));

function navigate(params: Record<string, string | number>) {
    router.get(route('team-recap.weekly'), { team: props.selectedTeamId ?? undefined, week: props.weekStart, ...params }, { preserveState: false });
}

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const monthName = computed(() => `${MONTHS[(props.month ?? 1) - 1]} ${props.year}`);
const go = (query: Record<string, string | number | undefined>) => router.get(route('team-recap.weekly'), { team: props.selectedTeamId ?? undefined, ...query }, { preserveState: false });
const openMonth = (m: number | string) => go({ year: props.year, month: m });
const openWeek = (w: number | string) => go({ week: w });
const toMonths = () => go({ year: props.year });
const toWeeks = () => go({ year: props.year, month: props.month ?? undefined });
const shiftYear = (delta: number) => go({ year: props.year + delta });

const selected = ref<number | null>(props.panels[0]?.performance_plan_id ?? null);
const panel = computed(() => props.panels.find(p => p.performance_plan_id === selected.value) ?? props.panels[0] ?? null);
// The PJ, or the RK's PIC, writes for the RK.
const canEdit = computed(() => props.canManage || (panel.value !== null && props.currentEmployeeId !== null && panel.value.pic_employee_id === props.currentEmployeeId));
</script>

<template>
    <Head title="Rekap Tim" />
    <AppLayout>
        <template #title>Rekap Tim (Mingguan)</template>

        <div v-if="!teams.length" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Anda belum tergabung dalam tim mana pun.
        </div>

        <template v-else>
            <template v-if="view !== 'detail'">
                <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border bg-white p-3">
                    <Select :model-value="selectedTeamId != null ? String(selectedTeamId) : undefined" @update:model-value="(v) => go({ team: Number(v), year })">
                        <SelectTrigger class="w-full sm:w-auto sm:min-w-[16rem]" aria-label="Pilih tim"><SelectValue placeholder="Pilih tim" /></SelectTrigger>
                        <SelectContent><SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem></SelectContent>
                    </Select>
                    <template v-if="view === 'months'">
                        <div class="flex items-center rounded-md border">
                            <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Tahun sebelumnya" @click="shiftYear(-1)"><ChevronLeft class="h-4 w-4" /></Button>
                            <span class="min-w-24 px-2 text-center text-sm font-semibold tabular-nums">{{ year }}</span>
                            <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Tahun berikutnya" @click="shiftYear(1)"><ChevronRight class="h-4 w-4" /></Button>
                        </div>
                        <p class="text-sm text-gray-600">Pilih bulan, lalu pilih minggunya.</p>
                    </template>
                    <template v-else>
                        <Button variant="ghost" size="sm" @click="toMonths"><ChevronLeft class="mr-1 h-4 w-4" aria-hidden="true" /> Semua bulan {{ year }}</Button>
                        <h2 class="text-lg font-semibold">{{ monthName }}</h2>
                    </template>
                </div>
                <RecapPeriodTable v-if="selectedTeamId && view === 'months'" :rows="periods" period-label="Bulan" @open="openMonth" />
                <RecapPeriodTable v-else-if="selectedTeamId" :rows="periods" period-label="Minggu" @open="openWeek" />
            </template>

            <template v-else>
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <Button variant="ghost" size="sm" class="-ml-2" @click="toWeeks"><ChevronLeft class="mr-1 h-4 w-4" aria-hidden="true" /> Minggu di {{ monthName }}</Button>
                <h2 class="text-lg font-semibold tabular-nums">{{ formatWeekRange(weekStart, weekEnd) }}</h2>
            </div>
            <RecapToolbar
                :teams="teams"
                :selected-team-id="selectedTeamId"
                :period-label="formatWeekRange(weekStart, weekEnd)"
                :exporting="exporting"
                hide-period-nav
                @change-team="navigate({ team: $event })"
                @export="download({ period_type: 'week', week: weekStart }, `Rapat Mingguan ${weekStart}.xlsx`)"
            />

            <TeamWeekPlans v-if="selectedTeamId" :plans="plans" />

            <MemberCompletenessCard v-if="selectedTeamId" :members="members" />

            <template v-if="selectedTeamId">
                <p v-if="!panels.length" class="mb-6 rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-600">Tim ini belum punya RK.</p>
                <template v-else-if="panel">
                    <RkPicker v-model="selected" :panels="panels" />
                    <RkRecapPanel class="mb-6" :panel="panel" :team-id="selectedTeamId" period-type="week" :period-start="weekStart" :can-edit="canEdit" />
                </template>
            </template>

            <!-- Bukti Dukung Rapat -->
            <div v-if="selectedTeamId" id="bukti-rapat" class="scroll-mt-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">Bukti Dukung Rapat</h2>
                    <Button v-if="canManage" size="sm" variant="outline" @click="evidence.show.value = !evidence.show.value">
                        {{ evidence.show.value ? 'Tutup' : 'Tambah Bukti' }}
                    </Button>
                    <span v-else class="text-xs text-gray-600">Hanya PJ yang dapat menambah bukti</span>
                </div>

                <form v-if="canManage && evidence.show.value" class="mb-4 space-y-3 rounded-md border bg-white p-4" @submit.prevent="evidence.submit">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label for="ev-type">Jenis</Label>
                            <Select v-model="evidence.form.value.type">
                                <SelectTrigger id="ev-type" class="mt-1 w-full"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, type) in evidenceTypeLabel" :key="type" :value="type">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label for="ev-title">Judul</Label>
                            <Input id="ev-title" v-model="evidence.form.value.title" class="mt-1" placeholder="Rapat koordinasi..." />
                        </div>
                    </div>
                    <div>
                        <Label for="ev-url">URL Bukti Dukung <span class="text-red-500">*</span></Label>
                        <Input id="ev-url" v-model="evidence.form.value.url" type="url" class="mt-1" placeholder="https://..." />
                        <InputError :message="evidence.form.value.errors.url" />
                    </div>
                    <div class="flex justify-end">
                        <Button type="submit" size="sm" :disabled="evidence.form.value.processing">Simpan Bukti</Button>
                    </div>
                </form>

                <div v-if="!evidences.length" class="rounded-md border border-dashed border-gray-200 bg-gray-50 py-8 text-center text-sm text-gray-600">
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
                            <button v-if="canManage" type="button" class="text-gray-400 hover:text-red-600" title="Hapus" aria-label="Hapus bukti" @click="evidence.remove(ev.id)">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
            </template>
        </template>
    </AppLayout>
</template>
