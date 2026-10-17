<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import RecapToolbar from '@/Components/Kinetik/RecapToolbar.vue';
import RkPicker from '@/Components/Kinetik/RkPicker.vue';
import RkRecapPanel from '@/Components/Kinetik/RkRecapPanel.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { PeriodRow, RkPanel, TeamOption } from '@/types';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import RecapPeriodTable from '@/Components/Kinetik/RecapPeriodTable.vue';
import { useRecapExport } from '@/composables/useRecapExport';

const props = defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    view: 'list' | 'detail';
    months: PeriodRow[];
    panels: RkPanel[];
    year: number;
    month: number | null;
    canManage: boolean;
    currentEmployeeId: number | null;
}>();

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const monthLabel = computed(() => `${MONTHS[(props.month ?? 1) - 1]} ${props.year}`);
const periodStart = computed(() => `${props.year}-${String(props.month ?? 1).padStart(2, '0')}-01`);

const { exporting, download } = useRecapExport();

function navigate(params: Record<string, string | number>) {
    router.get(route('team-recap.monthly'), { team: props.selectedTeamId ?? undefined, year: props.year, month: props.month ?? undefined, ...params }, { preserveState: false });
}

const openMonth = (m: number | string) => navigate({ month: m });
const backToList = () => router.get(route('team-recap.monthly'), { team: props.selectedTeamId ?? undefined, year: props.year }, { preserveState: false });
const shiftYear = (delta: number) => router.get(route('team-recap.monthly'), { team: props.selectedTeamId ?? undefined, year: props.year + delta }, { preserveState: false });


const selected = ref<number | null>(props.panels[0]?.performance_plan_id ?? null);
const panel = computed(() => props.panels.find(p => p.performance_plan_id === selected.value) ?? props.panels[0] ?? null);
const canEdit = computed(() => props.canManage || (panel.value !== null && props.currentEmployeeId !== null && panel.value.pic_employee_id === props.currentEmployeeId));
</script>

<template>
    <Head title="Rekap Bulanan" />
    <AppLayout>
        <template #title>Rekap Tim (Bulanan)</template>

        <div v-if="!teams.length" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Anda belum tergabung dalam tim mana pun.
        </div>

        <template v-else>
            <template v-if="view === 'list'">
                <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border bg-white p-3">
                    <Select :model-value="selectedTeamId != null ? String(selectedTeamId) : undefined" @update:model-value="(v) => router.get(route('team-recap.monthly'), { team: Number(v), year }, { preserveState: false })">
                        <SelectTrigger class="w-full sm:w-auto sm:min-w-[16rem]" aria-label="Pilih tim"><SelectValue placeholder="Pilih tim" /></SelectTrigger>
                        <SelectContent><SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem></SelectContent>
                    </Select>
                    <div class="flex items-center rounded-md border">
                        <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Tahun sebelumnya" @click="shiftYear(-1)"><ChevronLeft class="h-4 w-4" /></Button>
                        <span class="min-w-24 px-2 text-center text-sm font-semibold tabular-nums">{{ year }}</span>
                        <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Tahun berikutnya" @click="shiftYear(1)"><ChevronRight class="h-4 w-4" /></Button>
                    </div>
                    <p class="text-sm text-gray-600">Pilih bulan untuk membuka rekapnya.</p>
                </div>
                <RecapPeriodTable v-if="selectedTeamId" :rows="months" period-label="Bulan" @open="openMonth" />
            </template>

            <template v-else>
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <Button variant="ghost" size="sm" class="-ml-2" @click="backToList"><ChevronLeft class="mr-1 h-4 w-4" aria-hidden="true" /> Semua bulan {{ year }}</Button>
                <h2 class="text-lg font-semibold tabular-nums">{{ monthLabel }}</h2>
            </div>
            <RecapToolbar
                :teams="teams"
                :selected-team-id="selectedTeamId"
                :period-label="monthLabel"
                :exporting="exporting"
                hide-period-nav
                @change-team="navigate({ team: $event })"
                @export="download({ period_type: 'month', year, month: month ?? 1 }, `Rapat Bulanan ${monthLabel}.xlsx`)"
            />

            <template v-if="selectedTeamId">
                <p v-if="!panels.length" class="rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-600">Tim ini belum punya RK.</p>
                <template v-else-if="panel">
                    <RkPicker v-model="selected" :panels="panels" />
                    <RkRecapPanel :panel="panel" :team-id="selectedTeamId" period-type="month" :period-start="periodStart" :can-edit="canEdit" />
                </template>
            </template>
            </template>
        </template>
    </AppLayout>
</template>
