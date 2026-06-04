<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { RecapSegment, RecapRow, TeamOption } from '@/types';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { ChevronLeft, ChevronRight, Pencil } from 'lucide-vue-next';

const props = defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    segments: RecapSegment[];
    year: number;
    month: number;
}>();

const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

const monthLabel = computed(() => `${MONTHS[props.month - 1]} ${props.year}`);

function navigate(params: Record<string, string | number>) {
    router.get(route('team-recap.monthly'), {
        team: props.selectedTeamId ?? undefined,
        year: props.year,
        month: props.month,
        ...params,
    }, { preserveState: false });
}

function shiftMonth(delta: number) {
    let m = props.month + delta;
    let y = props.year;
    if (m < 1) { m = 12; y -= 1; }
    if (m > 12) { m = 1; y += 1; }
    navigate({ year: y, month: m });
}

function achievementColor(val: number | null): string {
    const n = Number(val ?? 0);
    if (n >= 80) return 'text-green-600';
    if (n >= 50) return 'text-yellow-600';
    return 'text-red-600';
}

// ── Paraphrase editor ──────────────────────────────────────────────────────

const editingPlanId = ref<number | null>(null);

const form = useForm({
    team_id: props.selectedTeamId,
    performance_plan_id: null as number | null,
    period_type: 'month',
    period_year: props.year,
    period_month: props.month,
    obstacle: '',
    solution: '',
    follow_up_plan: '',
});

function openEditor(row: RecapRow) {
    editingPlanId.value = row.performance_plan_id;
    form.team_id = props.selectedTeamId;
    form.performance_plan_id = row.performance_plan_id;
    form.period_year = props.year;
    form.period_month = props.month;
    form.obstacle = row.obstacle ?? '';
    form.solution = row.solution ?? '';
    form.follow_up_plan = row.follow_up_plan ?? '';
}

function submitOverride() {
    form.post(route('team-recap.override.store'), {
        preserveScroll: true,
        onSuccess: () => { editingPlanId.value = null; },
    });
}
</script>

<template>
    <Head title="Rekap Bulanan" />
    <AppLayout>
        <template #title>Rekap Bulanan</template>

        <div v-if="!teams.length" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Anda belum tergabung dalam tim mana pun.
        </div>

        <template v-else>
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
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Bulan sebelumnya" @click="shiftMonth(-1)">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="text-sm font-medium text-gray-700">{{ monthLabel }}</span>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Bulan berikutnya" @click="shiftMonth(1)">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div v-if="!segments.length" class="rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                Belum ada rekap untuk tim ini pada bulan ini.
            </div>

            <div v-else class="space-y-6">
                <div v-for="seg in segments" :key="seg.project_id ?? 'none'" class="overflow-hidden rounded-md border bg-white">
                    <div class="border-b bg-gray-50 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-800">{{ seg.project_name }}</h3>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <div v-for="row in seg.rows" :key="row.performance_plan_id" class="px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-800">{{ row.rk_description }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        Target {{ row.target }} {{ row.target_unit ?? '' }} · Realisasi {{ row.realization }} ·
                                        <span v-if="row.achievement != null" :class="['font-semibold', achievementColor(row.achievement)]">{{ row.achievement.toFixed(2) }}%</span>
                                    </p>
                                </div>
                                <Button size="sm" variant="outline" class="shrink-0" @click="openEditor(row)">
                                    <Pencil class="mr-1 h-3 w-3" /> Parafrase
                                </Button>
                            </div>

                            <!-- Editor -->
                            <form v-if="editingPlanId === row.performance_plan_id" class="mt-3 space-y-3 rounded-md border bg-gray-50 p-3" @submit.prevent="submitOverride">
                                <div>
                                    <Label>Kendala</Label>
                                    <Textarea v-model="form.obstacle" :rows="2" class="mt-1" />
                                    <p class="mt-1 text-xs text-gray-400">Asli: {{ row.obstacle_aggregated ?? '—' }}</p>
                                </div>
                                <div>
                                    <Label>Solusi</Label>
                                    <Textarea v-model="form.solution" :rows="2" class="mt-1" />
                                    <p class="mt-1 text-xs text-gray-400">Asli: {{ row.solution_aggregated ?? '—' }}</p>
                                </div>
                                <div>
                                    <Label>Rencana Tindak Lanjut</Label>
                                    <Textarea v-model="form.follow_up_plan" :rows="2" class="mt-1" />
                                    <p class="mt-1 text-xs text-gray-400">Asli: {{ row.follow_up_aggregated ?? '—' }}</p>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <Button type="button" size="sm" variant="outline" @click="editingPlanId = null">Batal</Button>
                                    <Button type="submit" size="sm" :disabled="form.processing">Simpan Parafrase</Button>
                                </div>
                            </form>

                            <!-- Read view -->
                            <dl v-else class="mt-2 grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
                                <div>
                                    <dt class="text-xs font-medium text-gray-400">Kendala</dt>
                                    <dd class="text-gray-700">{{ row.obstacle ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-gray-400">Solusi</dt>
                                    <dd class="text-gray-700">{{ row.solution ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-gray-400">RTL</dt>
                                    <dd class="text-gray-700">{{ row.follow_up_plan ?? '—' }}</dd>
                                </div>
                            </dl>
                            <p v-if="row.is_overridden" class="mt-1 text-xs italic text-blue-500">Telah diparafrase</p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
