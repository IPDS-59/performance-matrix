<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { RecapSegment, RecapRow, TeamOption } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { ChevronLeft, ChevronRight, Pencil, ExternalLink } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';

const props = defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    segments: RecapSegment[];
    year: number;
    quarter: number;
    pics: TeamOption[];
}>();

const quarterLabel = computed(() => `Triwulan ${props.quarter} ${props.year}`);

function navigate(params: Record<string, string | number>) {
    router.get(route('team-recap.quarterly'), {
        team: props.selectedTeamId ?? undefined,
        year: props.year,
        quarter: props.quarter,
        ...params,
    }, { preserveState: false });
}

function shiftQuarter(delta: number) {
    let q = props.quarter + delta;
    let y = props.year;
    if (q < 1) { q = 4; y -= 1; }
    if (q > 4) { q = 1; y += 1; }
    navigate({ year: y, quarter: q });
}

function achievementColor(val: number | null): string {
    const n = Number(val ?? 0);
    if (n >= 80) return 'text-green-600';
    if (n >= 50) return 'text-yellow-600';
    return 'text-red-600';
}

// ── FRA paraphrase + follow-up editor ──────────────────────────────────────

const editingPlanId = ref<number | null>(null);

const form = useForm({
    team_id: props.selectedTeamId,
    performance_plan_id: null as number | null,
    period_type: 'quarter',
    period_year: props.year,
    period_quarter: props.quarter,
    obstacle: '',
    solution: '',
    follow_up_plan: '',
    follow_up_evidence_url: '',
    follow_up_pic_employee_id: null as number | null,
    follow_up_deadline: '',
});

function openEditor(row: RecapRow) {
    editingPlanId.value = row.performance_plan_id;
    form.team_id = props.selectedTeamId;
    form.performance_plan_id = row.performance_plan_id;
    form.period_year = props.year;
    form.period_quarter = props.quarter;
    form.obstacle = row.obstacle ?? '';
    form.solution = row.solution ?? '';
    form.follow_up_plan = row.follow_up_plan ?? '';
    form.follow_up_evidence_url = row.follow_up_evidence_url ?? '';
    form.follow_up_pic_employee_id = row.follow_up_pic_employee_id ?? null;
    form.follow_up_deadline = row.follow_up_deadline ?? '';
}

function submitOverride() {
    form.post(route('team-recap.override.store'), {
        preserveScroll: true,
        onSuccess: () => { editingPlanId.value = null; },
    });
}
</script>

<template>
    <Head title="Rekap Triwulanan" />
    <AppLayout>
        <template #title>Rekap Triwulanan (FRA)</template>

        <div v-if="!teams.length" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Anda belum tergabung dalam tim mana pun.
        </div>

        <template v-else>
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-md border bg-white px-4 py-3">
                <select
                    :value="selectedTeamId ?? undefined"
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    @change="navigate({ team: ($event.target as HTMLSelectElement).value })"
                >
                    <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>

                <div class="flex items-center gap-3">
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Triwulan sebelumnya" @click="shiftQuarter(-1)">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="text-sm font-medium text-gray-700">{{ quarterLabel }}</span>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Triwulan berikutnya" @click="shiftQuarter(1)">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div v-if="!segments.length" class="rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                Belum ada rekap untuk tim ini pada triwulan ini.
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
                                    <textarea v-model="form.obstacle" rows="2" class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                </div>
                                <div>
                                    <Label>Solusi</Label>
                                    <textarea v-model="form.solution" rows="2" class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                </div>
                                <div>
                                    <Label>Tindak Lanjut</Label>
                                    <textarea v-model="form.follow_up_plan" rows="2" class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                </div>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div class="sm:col-span-1">
                                        <Label for="fra-pic">PIC</Label>
                                        <select id="fra-pic" v-model.number="form.follow_up_pic_employee_id" class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                                            <option :value="null">— Pilih PIC —</option>
                                            <option v-for="p in pics" :key="p.id" :value="p.id">{{ p.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label for="fra-deadline">Batas Waktu</Label>
                                        <Input id="fra-deadline" v-model="form.follow_up_deadline" type="date" class="mt-1" />
                                    </div>
                                    <div>
                                        <Label for="fra-url">Bukti Dukung Tindak Lanjut</Label>
                                        <Input id="fra-url" v-model="form.follow_up_evidence_url" type="url" class="mt-1" placeholder="https://..." />
                                        <InputError :message="form.errors.follow_up_evidence_url" />
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <Button type="button" size="sm" variant="outline" @click="editingPlanId = null">Batal</Button>
                                    <Button type="submit" size="sm" :disabled="form.processing">Simpan</Button>
                                </div>
                            </form>

                            <!-- Read view -->
                            <template v-else>
                                <dl class="mt-2 grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
                                    <div>
                                        <dt class="text-xs font-medium text-gray-400">Kendala</dt>
                                        <dd class="text-gray-700">{{ row.obstacle ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-gray-400">Solusi</dt>
                                        <dd class="text-gray-700">{{ row.solution ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-gray-400">Tindak Lanjut</dt>
                                        <dd class="text-gray-700">{{ row.follow_up_plan ?? '—' }}</dd>
                                    </div>
                                </dl>
                                <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-gray-500">
                                    <span>PIC: <span class="text-gray-700">{{ row.follow_up_pic ?? '—' }}</span></span>
                                    <span>Batas: <span class="text-gray-700">{{ row.follow_up_deadline ?? '—' }}</span></span>
                                    <a v-if="row.follow_up_evidence_url" :href="row.follow_up_evidence_url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-primary hover:underline">
                                        Bukti Tindak Lanjut <ExternalLink class="h-3 w-3" />
                                    </a>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
