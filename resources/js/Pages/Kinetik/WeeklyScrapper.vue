<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { KipActivity, ActivityClaim, PlanOption, ProjectOption } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/Components/ui/table';
import { ChevronLeft, ChevronRight, ExternalLink, Lock, Zap } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { useDateFormat } from '@/composables/useDateFormat';
import { NO_PROJECT, isReadyToSave, quickFill, splitBulkErrors, suggestProject, toClaimPayload } from '@/composables/useQuickClaim';

const { formatDate, formatWeekRange } = useDateFormat();

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    employee: { id: number; name: string; display_name: string | null } | null;
    activities: KipActivity[];
    recap: ActivityClaim[];
    plans: PlanOption[];
    projects: ProjectOption[];
    /** performance_plan_id → the Projek of the member's latest claim on it. */
    recentProjects: Record<number, number>;
    weekStart: string;
    weekEnd: string;
    prevWeek: string;
    nextWeek: string;
    isPj: boolean;
}>();

// ── Week navigation ────────────────────────────────────────────────────────

function goToWeek(week: string) {
    router.get(route('weekly.index'), { week }, { preserveState: false });
}

// ── Claim forms (one per activity row) ────────────────────────────────────

type ClaimFormData = {
    kip_activity_id: number;
    performance_plan_id: number | null;
    project_id: string;
    work_item_id: null;
    target: string;
    realization: string;
    target_unit: string;
    obstacle: string;
    solution: string;
    follow_up_plan: string;
    activity_date_start: string;
    activity_date_end: string;
    start_time: string;
    end_time: string;
    evidence_url: string;
    status: string;
};


function projectOptions(planId: number | null): ProjectOption[] {
    const plan = props.plans.find(p => p.id === planId);
    if (!plan || plan.project_id || !plan.team_id) return [];
    const inTeam = props.projects.filter(p => p.team_id === plan.team_id);
    // kipApp hangs Projek under the leader's RK: offer only those when known.
    const narrowed = plan.project_candidates?.length ? inTeam.filter(p => plan.project_candidates.includes(p.id)) : [];
    return narrowed.length ? narrowed : inTeam;
}

// kipApp links an RK to a team, not a Projek. Pre-select, in order: the Projek
// the member chose last time for this RK, the Projek named in the RK text, or
// the member's only Projek in the team. The member can still change it.
function defaultProjectId(planId: number | null): string {
    const options = projectOptions(planId);
    if (!options.length) return NO_PROJECT;

    const recent = planId ? props.recentProjects[planId] : undefined;
    if (recent && options.some(p => p.id === recent)) return String(recent);

    const plan = props.plans.find(p => p.id === planId);
    const named = plan ? suggestProject(plan.description, options) : null;
    if (named) return String(named.id);

    const own = options.filter(p => p.is_member);
    return own.length === 1 ? String(own[0].id) : NO_PROJECT;
}

function makeClaimForm(activity: KipActivity) {
    const c = activity.claim;
    const planId = c?.performance_plan_id ?? activity.matched_plan_id ?? null;
    return useForm<ClaimFormData>({
        kip_activity_id: activity.id,
        performance_plan_id: planId,
        project_id: c ? (c.project_id ? String(c.project_id) : NO_PROJECT) : defaultProjectId(planId),
        work_item_id: null,
        target: c?.target != null ? String(c.target) : '',
        realization: c?.realization != null ? String(c.realization) : '',
        target_unit: c?.target_unit ?? '',
        obstacle: c?.obstacle ?? '',
        solution: c?.solution ?? '',
        follow_up_plan: c?.follow_up_plan ?? '',
        activity_date_start: c?.activity_date_start ?? activity.activity_date_start,
        activity_date_end: c?.activity_date_end ?? activity.activity_date_end ?? '',
        start_time: c?.start_time ?? activity.time_start ?? '',
        end_time: c?.end_time ?? activity.time_end ?? '',
        evidence_url: c?.evidence_url ?? activity.evidence_url ?? '',
        status: 'saved',
    });
}

// Initialize a form map from activity id → useForm instance
const claimForms = ref<Record<number, ReturnType<typeof useForm<ClaimFormData>>>>(
    Object.fromEntries(props.activities.map(a => [a.id, makeClaimForm(a)]))
);

// When Inertia refreshes props after a claim, re-sync form state so the
// "Tersimpan" badge and collapsed form reflect the server's fresh data.
watch(() => props.activities, (newActivities) => {
    newActivities.forEach(activity => {
        const form = claimForms.value[activity.id];
        if (!form) {
            claimForms.value[activity.id] = makeClaimForm(activity);
        } else if (activity.is_claimed && !form.isDirty) {
            claimForms.value[activity.id] = makeClaimForm(activity);
        }
    });
});

// Per-activity form expansion. A claimed activity's data already lives in
// "Rekap Tersimpan" below, so its form starts collapsed (showing only the
// header + an "Ubah" toggle); unclaimed activities show the form to fill.
const expandedForms = ref<Record<number, boolean>>({});

function isFormOpen(activity: KipActivity): boolean {
    if (activity.locked) return false;
    return !activity.is_claimed || expandedForms.value[activity.id] === true;
}

function toggleForm(activityId: number) {
    expandedForms.value[activityId] = !expandedForms.value[activityId];
}

// kipApp's RK matched one of the member's RKs: shown read-only. Otherwise the
// member picks the RK here instead of being stuck with an empty one.
function hasAutoPlan(activity: KipActivity): boolean {
    return activity.matched_plan_id != null && props.plans.some(p => p.id === activity.matched_plan_id);
}

// RK choices grouped by team, for the manual pick.
const plansByTeam = computed(() => {
    const groups = new Map<string, PlanOption[]>();
    for (const plan of [...props.plans].sort((a, b) => a.description.localeCompare(b.description))) {
        if (!groups.has(plan.team_name)) groups.set(plan.team_name, []);
        groups.get(plan.team_name)!.push(plan);
    }
    return [...groups.entries()].sort(([a], [b]) => a.localeCompare(b));
});

function choosePlan(activityId: number, value: unknown) {
    const form = claimForms.value[activityId];
    form.performance_plan_id = value ? Number(value) : null;
    form.project_id = defaultProjectId(form.performance_plan_id);
}

function planLabel(activityId: number): string {
    const planId = claimForms.value[activityId]?.performance_plan_id;
    if (!planId) return '—';
    const plan = props.plans.find(p => p.id === planId);
    if (!plan) return '—';
    return plan.project_name ? `${plan.description} (${plan.project_name})` : plan.description;
}

function submitClaim(activityId: number) {
    const form = claimForms.value[activityId];
    if (!form) return;
    form.transform((data: ClaimFormData) => toClaimPayload(data)).post(route('weekly.claim'), {
        preserveScroll: true,
        onSuccess: () => {
            expandedForms.value[activityId] = false;
        },
    });
}

const claimedCount = computed(() => props.activities.filter(a => a.is_claimed).length);

// ── Faster entry: quick-fill and save all ─────────────────────────────────

// Activities the member still has to claim (a PJ lock freezes the rest).
const pendingActivities = computed(() => props.activities.filter(a => !a.is_claimed && !a.locked));
const readyActivities = computed(() =>
    pendingActivities.value.filter(a => claimForms.value[a.id] && isReadyToSave(claimForms.value[a.id])),
);
const lockedCount = computed(() => props.activities.filter(a => a.locked).length);

function quickFillOne(activityId: number) {
    const form = claimForms.value[activityId];
    if (form) quickFill(form);
}

function quickFillAll() {
    pendingActivities.value.forEach(a => quickFillOne(a.id));
}

const savingAll = ref(false);

function saveAll() {
    const batch = readyActivities.value;
    if (!batch.length) return;
    batch.forEach(a => claimForms.value[a.id].clearErrors());
    savingAll.value = true;
    router.post(route('weekly.claim-bulk'), {
        claims: batch.map(a => toClaimPayload(claimForms.value[a.id].data())),
    }, {
        preserveScroll: true,
        onError: (errors: Record<string, string>) => {
            const byIndex = splitBulkErrors(errors);
            Object.entries(byIndex).forEach(([index, fieldErrors]) => {
                const form = claimForms.value[batch[Number(index)].id];
                Object.entries(fieldErrors).forEach(([field, message]) => form.setError(field as keyof ClaimFormData, message));
            });
        },
        onFinish: () => { savingAll.value = false; },
    });
}

// ── Auto-computed achievement display ─────────────────────────────────────

function computedAchievement(activityId: number): string {
    const form = claimForms.value[activityId];
    if (!form) return '—';
    const t = parseFloat(form.target);
    const r = parseFloat(form.realization);
    if (!isNaN(t) && t > 0 && !isNaN(r)) {
        return (r / t * 100).toFixed(2) + '%';
    }
    return '—';
}

// ── Recap achievement color ────────────────────────────────────────────────

function achievementColor(val: number | string | null | undefined): string {
    const n = parseFloat(String(val ?? 0));
    if (n >= 80) return 'text-green-600';
    if (n >= 50) return 'text-yellow-600';
    return 'text-red-600';
}
</script>

<template>
    <Head title="Rekap Mingguan" />
    <AppLayout>
        <template #title>Rekap Mingguan</template>

        <!-- No employee state -->
        <div v-if="!employee" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Akun Anda belum terhubung ke data pegawai. Hubungi administrator untuk mengatur data pegawai.
        </div>

        <template v-else>
            <!-- Week navigator -->
            <div class="mb-6 flex items-center justify-between gap-4 rounded-md border bg-white px-4 py-3">
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100 transition-colors"
                    @click="goToWeek(prevWeek)"
                    title="Minggu sebelumnya" aria-label="Minggu sebelumnya"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>

                <span class="text-sm font-medium text-gray-700">
                    {{ formatWeekRange(weekStart, weekEnd) }}
                </span>

                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100 transition-colors"
                    @click="goToWeek(nextWeek)"
                    title="Minggu berikutnya" aria-label="Minggu berikutnya"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>

            <!-- Week progress + batch actions -->
            <div v-if="activities.length" class="mb-6 rounded-lg border bg-white px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800">
                            <span class="tabular-nums">{{ claimedCount }}</span> dari <span class="tabular-nums">{{ activities.length }}</span> kegiatan sudah disimpan
                        </p>
                        <p class="text-xs text-gray-500">
                            <template v-if="claimedCount === activities.length">Minggu ini lengkap.</template>
                            <template v-else-if="pendingActivities.length">{{ pendingActivities.length }} kegiatan menunggu diklaim.</template>
                            <template v-if="lockedCount"> {{ lockedCount }} kegiatan ada di periode yang sudah dikunci PJ.</template>
                        </p>
                    </div>
                    <div v-if="pendingActivities.length" class="flex w-full flex-wrap gap-2 sm:w-auto">
                        <Button type="button" variant="outline" size="sm" class="flex-1 sm:flex-none" title="Isi kolom kosong: target 1, realisasi 1, satuan Kegiatan, kendala -" @click="quickFillAll">
                            <Zap class="mr-1.5 h-4 w-4" />
                            Isi cepat semua
                        </Button>
                        <Button type="button" size="sm" class="flex-1 sm:flex-none" :disabled="!readyActivities.length || savingAll" @click="saveAll">
                            {{ savingAll ? 'Menyimpan…' : `Simpan semua (${readyActivities.length})` }}
                        </Button>
                    </div>
                </div>
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100" role="progressbar" :aria-valuenow="claimedCount" :aria-valuemax="activities.length" aria-label="Kegiatan tersimpan">
                    <div class="h-full rounded-full bg-primary transition-[width] duration-300" :style="{ width: `${(claimedCount / activities.length) * 100}%` }" />
                </div>
            </div>

            <!-- Activities table with claim forms -->
            <div class="mb-6">
                <h2 class="mb-3 text-sm font-semibold text-gray-700">Kegiatan Minggu Ini</h2>

                <div v-if="!activities.length" class="rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                    Belum ada kegiatan untuk minggu ini.
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="activity in activities"
                        :key="activity.id"
                        class="rounded-md border bg-white"
                    >
                        <!-- Activity header -->
                        <div class="border-b bg-gray-50 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-800">{{ activity.description }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        {{ formatDate(activity.activity_date_start) }}
                                        <template v-if="activity.activity_date_end && activity.activity_date_end !== activity.activity_date_start">
                                            — {{ formatDate(activity.activity_date_end) }}
                                        </template>
                                        <template v-if="activity.time_start">
                                            &nbsp;·&nbsp;{{ activity.time_start }}
                                            <template v-if="activity.time_end"> — {{ activity.time_end }}</template>
                                        </template>
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <a
                                        v-if="activity.evidence_url"
                                        :href="activity.evidence_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                                    >
                                        Bukti <ExternalLink class="h-3 w-3" />
                                    </a>
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-xs font-medium',
                                            activity.is_claimed
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-gray-100 text-gray-600'
                                        ]"
                                    >
                                        {{ activity.is_claimed ? 'Tersimpan' : 'Belum diklaim' }}
                                    </span>
                                    <span v-if="activity.locked" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800" title="PJ sudah mengunci rekap periode ini">
                                        <Lock class="h-3 w-3" aria-hidden="true" />
                                        Dikunci PJ
                                    </span>
                                    <Button
                                        v-if="activity.is_claimed && !activity.locked"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 px-2 text-xs"
                                        @click="toggleForm(activity.id)"
                                    >
                                        {{ isFormOpen(activity) ? 'Tutup' : 'Ubah' }}
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Claim form (collapsed once claimed; reopen via "Ubah") -->
                        <div v-if="claimForms[activity.id] && isFormOpen(activity)" class="px-4 py-4">
                            <form @submit.prevent="submitClaim(activity.id)" class="space-y-5">
                                <!-- 1. Where this activity counts -->
                                <fieldset class="space-y-3">
                                    <legend class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Rencana Kinerja</legend>
                                    <div v-if="hasAutoPlan(activity)">
                                        <p class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm leading-snug text-gray-800">
                                            {{ planLabel(activity.id) }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-500">Terisi otomatis dari kipApp.</p>
                                        <InputError :message="claimForms[activity.id].errors.performance_plan_id" />
                                    </div>
                                    <div v-else>
                                        <Label :for="`plan-${activity.id}`">Pilih RK</Label>
                                        <Select
                                            :model-value="claimForms[activity.id].performance_plan_id ? String(claimForms[activity.id].performance_plan_id) : undefined"
                                            @update:model-value="(v) => choosePlan(activity.id, v)"
                                        >
                                            <SelectTrigger :id="`plan-${activity.id}`" class="mt-1 h-auto min-h-9 w-full whitespace-normal text-left">
                                                <SelectValue placeholder="Pilih Rencana Kinerja" />
                                            </SelectTrigger>
                                            <SelectContent class="max-w-[min(42rem,90vw)]">
                                                <template v-for="[team, teamPlans] in plansByTeam" :key="team">
                                                    <div class="px-2 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ team }}</div>
                                                    <SelectItem v-for="plan in teamPlans" :key="plan.id" :value="String(plan.id)" class="whitespace-normal">
                                                        {{ plan.project_name ? `${plan.description} (${plan.project_name})` : plan.description }}
                                                    </SelectItem>
                                                </template>
                                            </SelectContent>
                                        </Select>
                                        <p class="mt-1 text-xs text-amber-700">
                                            RK kipApp<template v-if="activity.rk_name"> "{{ activity.rk_name }}"</template> tidak ditemukan di tim Anda. Pilih RK yang sesuai.
                                        </p>
                                        <InputError :message="claimForms[activity.id].errors.performance_plan_id" />
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        <!-- Projek: kipApp RKs are team-wide, so the member picks the project -->
                                        <div v-if="projectOptions(claimForms[activity.id].performance_plan_id).length" class="sm:col-span-2">
                                            <Label :for="`project-${activity.id}`">Projek</Label>
                                            <Select v-model="claimForms[activity.id].project_id">
                                                <SelectTrigger :id="`project-${activity.id}`" class="mt-1 w-full">
                                                    <SelectValue placeholder="Pilih projek" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem :value="NO_PROJECT">— Tanpa projek —</SelectItem>
                                                    <SelectItem
                                                        v-for="project in projectOptions(claimForms[activity.id].performance_plan_id)"
                                                        :key="project.id"
                                                        :value="String(project.id)"
                                                    >
                                                        {{ project.name }}{{ project.is_member ? '' : ' (bukan anggota)' }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError :message="claimForms[activity.id].errors.project_id" />
                                        </div>
                                        <!-- Optional hours (Probis item 6) -->
                                        <div>
                                            <Label :for="`start-${activity.id}`">Jam Mulai <span class="font-normal text-gray-400">(opsional)</span></Label>
                                            <Input :id="`start-${activity.id}`" type="time" v-model="claimForms[activity.id].start_time" class="mt-1" />
                                        </div>
                                        <div>
                                            <Label :for="`end-${activity.id}`">Jam Selesai <span class="font-normal text-gray-400">(opsional)</span></Label>
                                            <Input :id="`end-${activity.id}`" type="time" v-model="claimForms[activity.id].end_time" class="mt-1" />
                                        </div>
                                    </div>
                                </fieldset>

                                <!-- 2. Numbers -->
                                <fieldset>
                                    <legend class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Capaian</legend>
                                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                        <div>
                                            <Label :for="`target-${activity.id}`">Target</Label>
                                            <Input :id="`target-${activity.id}`" type="number" step="any" min="0" inputmode="decimal" v-model="claimForms[activity.id].target" class="mt-1 tabular-nums" />
                                        </div>
                                        <div>
                                            <Label :for="`realization-${activity.id}`">Realisasi</Label>
                                            <Input :id="`realization-${activity.id}`" type="number" step="any" min="0" inputmode="decimal" v-model="claimForms[activity.id].realization" class="mt-1 tabular-nums" />
                                        </div>
                                        <div>
                                            <Label :for="`unit-${activity.id}`">Satuan</Label>
                                            <Input :id="`unit-${activity.id}`" v-model="claimForms[activity.id].target_unit" class="mt-1" placeholder="Kegiatan" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium leading-none">Capaian</p>
                                            <p class="mt-1 flex h-9 items-center rounded-md bg-gray-50 px-3 text-sm font-semibold tabular-nums text-gray-800" aria-live="polite">
                                                {{ computedAchievement(activity.id) }}
                                            </p>
                                        </div>
                                    </div>
                                </fieldset>

                                <!-- 3. Notes. Solusi & RTL are PJ-only (agreed in the team meeting). -->
                                <fieldset class="space-y-3">
                                    <legend class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Catatan</legend>
                                    <div>
                                        <Label :for="`obstacle-${activity.id}`">Kendala <span class="text-red-600" aria-hidden="true">*</span></Label>
                                        <Textarea
                                            :id="`obstacle-${activity.id}`"
                                            v-model="claimForms[activity.id].obstacle"
                                            :rows="2"
                                            class="mt-1"
                                            placeholder="Kendala yang dihadapi..."
                                            :aria-describedby="`obstacle-hint-${activity.id}`"
                                            required
                                        />
                                        <p :id="`obstacle-hint-${activity.id}`" class="mt-1 text-xs text-gray-500">Wajib diisi. Tulis "-" jika tidak ada kendala.</p>
                                        <InputError :message="claimForms[activity.id].errors.obstacle" />
                                    </div>
                                    <div v-if="isPj" class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <Label :for="`solution-${activity.id}`">Solusi</Label>
                                            <Textarea :id="`solution-${activity.id}`" v-model="claimForms[activity.id].solution" :rows="2" class="mt-1" placeholder="Solusi yang diterapkan..." />
                                        </div>
                                        <div>
                                            <Label :for="`rtl-${activity.id}`">Rencana Tindak Lanjut</Label>
                                            <Textarea :id="`rtl-${activity.id}`" v-model="claimForms[activity.id].follow_up_plan" :rows="2" class="mt-1" placeholder="Rencana tindak lanjut..." />
                                        </div>
                                    </div>
                                </fieldset>

                                <div class="flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:items-center sm:justify-end">
                                    <Button type="button" variant="ghost" size="sm" class="w-full sm:mr-auto sm:w-auto" title="Isi kolom kosong: target 1, realisasi 1, satuan Kegiatan, kendala -" @click="quickFillOne(activity.id)">
                                        <Zap class="mr-1.5 h-4 w-4" />
                                        Isi cepat
                                    </Button>
                                    <p v-if="!claimForms[activity.id].performance_plan_id" class="text-xs text-amber-700">
                                        RK belum cocok dengan data kipApp. Sinkronkan ulang atau hubungi admin.
                                    </p>
                                    <Button
                                        type="submit"
                                        class="w-full sm:w-auto"
                                        :disabled="claimForms[activity.id].processing || !claimForms[activity.id].performance_plan_id"
                                    >
                                        {{ claimForms[activity.id].processing ? 'Menyimpan…' : activity.is_claimed ? 'Simpan perubahan' : 'Simpan ke Rekap' }}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rekap Tersimpan -->
            <div>
                <h2 class="mb-3 text-sm font-semibold text-gray-700">Rekap Tersimpan</h2>

                <div v-if="!recap.length" class="rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                    Belum ada rekap tersimpan untuk minggu ini.
                </div>

                <div v-else class="overflow-hidden rounded-md border bg-white">
                    <Table class="w-full text-sm">
                        <TableHeader>
                            <TableRow class="border-b bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                                <TableHead class="text-left">Rencana Kinerja</TableHead>
                                <TableHead class="text-left">Kegiatan</TableHead>
                                <TableHead class="text-right">Capaian</TableHead>
                                <TableHead class="text-left">Kendala</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody class="divide-y divide-gray-100">
                            <TableRow v-for="claim in recap" :key="claim.id" class="hover:bg-gray-50">
                                <TableCell class="min-w-[16rem] whitespace-normal align-top">
                                    <p class="font-medium leading-snug text-gray-800">{{ claim.performance_plan?.description ?? '—' }}</p>
                                    <p class="text-xs text-gray-500">{{ claim.project?.name ?? claim.performance_plan?.project?.name ?? '' }}</p>
                                </TableCell>
                                <TableCell class="min-w-[14rem] whitespace-normal align-top leading-snug text-gray-700">
                                    {{ claim.kip_activity?.description ?? '—' }}
                                </TableCell>
                                <TableCell class="align-top text-right tabular-nums">
                                    <span
                                        v-if="claim.achievement != null"
                                        :class="['font-semibold', achievementColor(claim.achievement)]"
                                    >
                                        {{ parseFloat(String(claim.achievement)).toFixed(2) }}%
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </TableCell>
                                <TableCell class="min-w-[12rem] max-w-xs whitespace-normal align-top leading-snug text-gray-600">{{ claim.obstacle ?? '—' }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
