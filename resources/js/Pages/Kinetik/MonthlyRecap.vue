<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import RecapToolbar from '@/Components/Kinetik/RecapToolbar.vue';
import RkPicker from '@/Components/Kinetik/RkPicker.vue';
import RkRecapPanel from '@/Components/Kinetik/RkRecapPanel.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { RkPanel, TeamOption } from '@/types';
import { useRecapExport } from '@/composables/useRecapExport';

const props = defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    panels: RkPanel[];
    year: number;
    month: number;
    canManage: boolean;
    currentEmployeeId: number | null;
}>();

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const monthLabel = computed(() => `${MONTHS[props.month - 1]} ${props.year}`);
const periodStart = computed(() => `${props.year}-${String(props.month).padStart(2, '0')}-01`);

const { exporting, download } = useRecapExport();

function navigate(params: Record<string, string | number>) {
    router.get(route('team-recap.monthly'), { team: props.selectedTeamId ?? undefined, year: props.year, month: props.month, ...params }, { preserveState: false });
}

function shiftMonth(delta: number) {
    const d = new Date(props.year, props.month - 1 + delta, 1);
    navigate({ year: d.getFullYear(), month: d.getMonth() + 1 });
}

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
            <RecapToolbar
                :teams="teams"
                :selected-team-id="selectedTeamId"
                :period-label="monthLabel"
                prev-label="Bulan sebelumnya"
                next-label="Bulan berikutnya"
                :exporting="exporting"
                @change-team="navigate({ team: $event })"
                @prev="shiftMonth(-1)"
                @next="shiftMonth(1)"
                @export="download({ period_type: 'month', year, month }, `Rapat Bulanan ${monthLabel}.xlsx`)"
            />

            <template v-if="selectedTeamId">
                <p v-if="!panels.length" class="rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-600">Tim ini belum punya RK.</p>
                <template v-else-if="panel">
                    <RkPicker v-model="selected" :panels="panels" />
                    <RkRecapPanel :panel="panel" :team-id="selectedTeamId" period-type="month" :period-start="periodStart" :can-edit="canEdit" />
                </template>
            </template>
        </template>
    </AppLayout>
</template>
