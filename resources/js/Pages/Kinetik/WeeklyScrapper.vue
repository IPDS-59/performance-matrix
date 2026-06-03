<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { KipActivity, ActivityClaim, PlanOption } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Check, ChevronsUpDown, ChevronLeft, ChevronRight, ExternalLink, RefreshCw } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { useDateFormat } from '@/composables/useDateFormat';

const { formatDate, formatWeekRange } = useDateFormat();

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    employee: { id: number; name: string; display_name: string | null } | null;
    activities: KipActivity[];
    recap: ActivityClaim[];
    plans: PlanOption[];
    weekStart: string;
    weekEnd: string;
    prevWeek: string;
    nextWeek: string;
}>();

// ── Week navigation ────────────────────────────────────────────────────────

function goToWeek(week: string) {
    router.get(route('weekly.index'), { week }, { preserveState: false });
}

// ── kipApp sync ────────────────────────────────────────────────────────────

const syncForm = useForm({});

function syncKipActivities() {
    syncForm.post(route('weekly.sync'), { preserveScroll: true });
}

// ── Manual activity form ───────────────────────────────────────────────────

const showAddForm = ref(false);

const manualForm = useForm({
    description: '',
    activity_date_start: props.weekStart,
    activity_date_end: '',
    start_time: '',
    end_time: '',
    evidence_url: '',
});

function submitManualActivity() {
    manualForm.post(route('weekly.activity.store'), {
        onSuccess: () => {
            manualForm.reset();
            showAddForm.value = false;
        },
    });
}

// ── Claim forms (one per activity row) ────────────────────────────────────

type ClaimFormData = {
    kip_activity_id: number;
    performance_plan_id: number | null;
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

function makeClaimForm(activity: KipActivity) {
    const c = activity.claim;
    return useForm<ClaimFormData>({
        kip_activity_id: activity.id,
        performance_plan_id: c?.performance_plan_id ?? null,
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

// Plan picker open state per activity
const planPickerOpen = ref<Record<number, boolean>>({});

function planLabel(activityId: number): string {
    const planId = claimForms.value[activityId]?.performance_plan_id;
    if (!planId) return '— Pilih RK —';
    const plan = props.plans.find(p => p.id === planId);
    return plan ? `${plan.description} (${plan.project_name})` : '— Pilih RK —';
}

function submitClaim(activityId: number) {
    const form = claimForms.value[activityId];
    if (!form) return;
    form.post(route('weekly.claim'));
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
            <!-- kipApp sync -->
            <div class="mb-3 flex justify-end">
                <Button size="sm" variant="outline" :disabled="syncForm.processing" @click="syncKipActivities">
                    <RefreshCw :class="['mr-1 h-4 w-4', syncForm.processing ? 'animate-spin' : '']" />
                    {{ syncForm.processing ? 'Menyinkronkan…' : 'Sinkronkan kipApp' }}
                </Button>
            </div>

            <!-- Week navigator -->
            <div class="mb-6 flex items-center justify-between gap-4 rounded-md border bg-white px-4 py-3">
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100 transition-colors"
                    @click="goToWeek(prevWeek)"
                    title="Minggu sebelumnya"
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
                    title="Minggu berikutnya"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>

            <!-- Tambah Kegiatan collapsible -->
            <div class="mb-6 rounded-md border bg-white">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-4 py-3 text-sm font-medium text-gray-800 hover:bg-gray-50 transition-colors"
                    @click="showAddForm = !showAddForm"
                >
                    <span>Tambah Kegiatan Manual</span>
                    <svg
                        :class="['h-4 w-4 text-gray-400 transition-transform', showAddForm ? 'rotate-180' : '']"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div v-if="showAddForm" class="border-t px-4 py-4">
                    <form @submit.prevent="submitManualActivity" class="space-y-3">
                        <div>
                            <Label for="manual-desc">Uraian Kegiatan <span class="text-red-500">*</span></Label>
                            <textarea
                                id="manual-desc"
                                v-model="manualForm.description"
                                rows="3"
                                class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                placeholder="Deskripsi kegiatan..."
                            />
                            <InputError :message="manualForm.errors.description" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="manual-date-start">Tanggal Mulai <span class="text-red-500">*</span></Label>
                                <Input id="manual-date-start" type="date" v-model="manualForm.activity_date_start" class="mt-1" />
                                <InputError :message="manualForm.errors.activity_date_start" />
                            </div>
                            <div>
                                <Label for="manual-date-end">Tanggal Selesai</Label>
                                <Input id="manual-date-end" type="date" v-model="manualForm.activity_date_end" class="mt-1" />
                                <InputError :message="manualForm.errors.activity_date_end" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="manual-time-start">Jam Mulai</Label>
                                <Input id="manual-time-start" type="time" v-model="manualForm.start_time" class="mt-1" />
                            </div>
                            <div>
                                <Label for="manual-time-end">Jam Selesai</Label>
                                <Input id="manual-time-end" type="time" v-model="manualForm.end_time" class="mt-1" />
                            </div>
                        </div>
                        <div>
                            <Label for="manual-evidence">URL Bukti Dukung</Label>
                            <Input id="manual-evidence" type="url" v-model="manualForm.evidence_url" class="mt-1" placeholder="https://..." />
                            <InputError :message="manualForm.errors.evidence_url" />
                        </div>
                        <div class="flex justify-end gap-2 pt-1">
                            <Button type="button" variant="outline" size="sm" @click="showAddForm = false">Batal</Button>
                            <Button type="submit" size="sm" :disabled="manualForm.processing">Tambah</Button>
                        </div>
                    </form>
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
                                </div>
                            </div>
                        </div>

                        <!-- Claim form -->
                        <div v-if="claimForms[activity.id]" class="px-4 py-4">
                            <form @submit.prevent="submitClaim(activity.id)" class="space-y-3">
                                <!-- RK picker -->
                                <div>
                                    <Label>Rencana Kinerja (RK) <span class="text-red-500">*</span></Label>
                                    <Popover v-model:open="planPickerOpen[activity.id]">
                                        <PopoverTrigger as-child>
                                            <Button
                                                variant="outline"
                                                role="combobox"
                                                class="mt-1 w-full justify-between font-normal text-left"
                                            >
                                                <span class="truncate">{{ planLabel(activity.id) }}</span>
                                                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent class="w-[--radix-popover-trigger-width] p-0">
                                            <Command>
                                                <CommandInput placeholder="Cari rencana kinerja..." />
                                                <CommandList>
                                                    <CommandEmpty>Tidak ada hasil.</CommandEmpty>
                                                    <CommandGroup>
                                                        <CommandItem
                                                            v-for="plan in plans"
                                                            :key="plan.id"
                                                            :value="`${plan.description} ${plan.project_name} ${plan.team_name}`"
                                                            @select="() => {
                                                                claimForms[activity.id].performance_plan_id = plan.id;
                                                                planPickerOpen[activity.id] = false;
                                                            }"
                                                        >
                                                            <div class="min-w-0 flex-1">
                                                                <p class="truncate text-sm">{{ plan.description }}</p>
                                                                <p class="truncate text-xs text-gray-500">{{ plan.project_name }} · {{ plan.team_name }}</p>
                                                            </div>
                                                            <Check
                                                                v-if="claimForms[activity.id].performance_plan_id === plan.id"
                                                                class="ml-2 h-4 w-4 shrink-0"
                                                            />
                                                        </CommandItem>
                                                    </CommandGroup>
                                                </CommandList>
                                            </Command>
                                        </PopoverContent>
                                    </Popover>
                                    <InputError :message="claimForms[activity.id].errors.performance_plan_id" />
                                </div>

                                <!-- Target / realization / unit -->
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <Label :for="`target-${activity.id}`">Target</Label>
                                        <Input
                                            :id="`target-${activity.id}`"
                                            type="number"
                                            step="any"
                                            min="0"
                                            v-model="claimForms[activity.id].target"
                                            class="mt-1"
                                        />
                                    </div>
                                    <div>
                                        <Label :for="`realization-${activity.id}`">Realisasi</Label>
                                        <Input
                                            :id="`realization-${activity.id}`"
                                            type="number"
                                            step="any"
                                            min="0"
                                            v-model="claimForms[activity.id].realization"
                                            class="mt-1"
                                        />
                                    </div>
                                    <div>
                                        <Label :for="`unit-${activity.id}`">Satuan</Label>
                                        <Input
                                            :id="`unit-${activity.id}`"
                                            v-model="claimForms[activity.id].target_unit"
                                            class="mt-1"
                                            placeholder="Kegiatan"
                                        />
                                    </div>
                                </div>

                                <!-- Achievement display -->
                                <div class="rounded-md bg-gray-50 px-3 py-2 text-sm">
                                    Capaian: <span class="font-semibold">{{ computedAchievement(activity.id) }}</span>
                                </div>

                                <!-- Kendala / Solusi / RTL -->
                                <div>
                                    <Label :for="`obstacle-${activity.id}`">Kendala</Label>
                                    <textarea
                                        :id="`obstacle-${activity.id}`"
                                        v-model="claimForms[activity.id].obstacle"
                                        rows="2"
                                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                        placeholder="Kendala yang dihadapi..."
                                    />
                                </div>
                                <div>
                                    <Label :for="`solution-${activity.id}`">Solusi</Label>
                                    <textarea
                                        :id="`solution-${activity.id}`"
                                        v-model="claimForms[activity.id].solution"
                                        rows="2"
                                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                        placeholder="Solusi yang diterapkan..."
                                    />
                                </div>
                                <div>
                                    <Label :for="`rtl-${activity.id}`">Rencana Tindak Lanjut</Label>
                                    <textarea
                                        :id="`rtl-${activity.id}`"
                                        v-model="claimForms[activity.id].follow_up_plan"
                                        rows="2"
                                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                        placeholder="Rencana tindak lanjut..."
                                    />
                                </div>

                                <div class="flex justify-end pt-1">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :disabled="claimForms[activity.id].processing || !claimForms[activity.id].performance_plan_id"
                                    >
                                        Simpan ke Rekap
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
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                                <th class="px-4 py-3 text-left">Rencana Kinerja</th>
                                <th class="px-4 py-3 text-left">Kegiatan</th>
                                <th class="px-4 py-3 text-right">Capaian</th>
                                <th class="px-4 py-3 text-left">Kendala</th>
                                <th class="px-4 py-3 text-left">Solusi</th>
                                <th class="px-4 py-3 text-left">RTL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="claim in recap" :key="claim.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 align-top">
                                    <p class="font-medium text-gray-800">{{ claim.performance_plan?.description ?? '—' }}</p>
                                    <p class="text-xs text-gray-500">{{ claim.performance_plan?.project?.name ?? '' }}</p>
                                </td>
                                <td class="px-4 py-3 align-top text-gray-700">
                                    {{ claim.kip_activity?.description ?? '—' }}
                                </td>
                                <td class="px-4 py-3 align-top text-right">
                                    <span
                                        v-if="claim.achievement != null"
                                        :class="['font-semibold', achievementColor(claim.achievement)]"
                                    >
                                        {{ parseFloat(String(claim.achievement)).toFixed(2) }}%
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3 align-top text-gray-600 max-w-xs">{{ claim.obstacle ?? '—' }}</td>
                                <td class="px-4 py-3 align-top text-gray-600 max-w-xs">{{ claim.solution ?? '—' }}</td>
                                <td class="px-4 py-3 align-top text-gray-600 max-w-xs">{{ claim.follow_up_plan ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
