<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { WeeklyPlanMember, WeeklyPlanProps } from '@/types';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ChevronLeft, ChevronRight, ExternalLink, Target } from 'lucide-vue-next';
import PlanItemsPanel from '@/Components/Kinetik/PlanItemsPanel.vue';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps<WeeklyPlanProps>();
const { formatDate, formatWeekRange } = useDateFormat();
const roman = ['I', 'II', 'III', 'IV'];

function navigate(params: Record<string, string | number>) {
    router.get(route('weekly-plan.index'), { team: props.selectedTeamId ?? undefined, week: props.weekStart, ...params }, { preserveState: false });
}

// The member's own card first, then the team in name order.
const members = computed(() => [...props.members].sort((a, b) =>
    Number(b.employee_id === props.currentEmployeeId) - Number(a.employee_id === props.currentEmployeeId)));

const drafts = ref<Record<number, string>>({});
const saving = ref<number | null>(null);
watch(() => props.members, (list) => {
    drafts.value = Object.fromEntries(list.map(m => [m.employee_id, m.focus ?? '']));
}, { immediate: true });

function saveFocus(member: WeeklyPlanMember) {
    saving.value = member.employee_id;
    router.post(route('weekly-plan.focus'), {
        team_id: props.selectedTeamId,
        employee_id: member.employee_id,
        week_start: props.weekStart,
        body: drafts.value[member.employee_id] ?? '',
    }, { preserveScroll: true, onFinish: () => (saving.value = null) });
}
</script>

<template>
    <Head title="Rencana Minggu Ini" />
    <AppLayout>
        <template #title>Rencana Minggu Ini</template>

        <div v-if="!teams.length" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Anda belum tergabung dalam tim mana pun.
        </div>

        <template v-else>
            <div class="mb-4 flex flex-col gap-3 rounded-lg border bg-white p-3 sm:p-4 lg:flex-row lg:items-center lg:justify-between">
                <Select :model-value="selectedTeamId != null ? String(selectedTeamId) : undefined" @update:model-value="(v) => navigate({ team: Number(v) })">
                    <SelectTrigger class="w-full lg:w-auto lg:min-w-[18rem] lg:max-w-md" aria-label="Pilih tim">
                        <SelectValue placeholder="Pilih tim" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <div class="flex items-center justify-between rounded-md border bg-gray-50/60 lg:justify-center">
                    <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Minggu sebelumnya" @click="navigate({ week: prevWeek })"><ChevronLeft class="h-4 w-4" /></Button>
                    <span class="min-w-[13rem] px-2 text-center text-sm font-semibold tabular-nums text-gray-800">{{ formatWeekRange(weekStart, weekEnd) }}</span>
                    <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Minggu berikutnya" @click="navigate({ week: nextWeek })"><ChevronRight class="h-4 w-4" /></Button>
                </div>
            </div>

            <p class="mb-4 text-sm text-gray-600">
                Untuk setiap anggota: fokus dari PJ minggu ini, RK yang belum punya kegiatan di Triwulan {{ roman[quarter - 1] }}, dan kegiatan yang progresnya belum 100%.
                Data kegiatan dan RK berasal dari sinkronisasi kipApp terakhir.
            </p>

            <div v-if="!members.length" class="rounded-md border border-dashed bg-gray-50 py-10 text-center text-sm text-gray-500">Tim ini belum punya anggota.</div>

            <div class="grid gap-4 lg:grid-cols-2">
                <section
                    v-for="m in members"
                    :key="m.employee_id"
                    :class="['flex flex-col rounded-md border bg-white', m.employee_id === currentEmployeeId ? 'border-primary/50 ring-1 ring-primary/20' : '']"
                    :aria-label="`Rencana ${m.name}`"
                >
                    <header class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 border-b px-4 py-3">
                        <h2 class="text-sm font-semibold text-gray-800">{{ m.name }}<span v-if="m.employee_id === currentEmployeeId" class="ml-1.5 text-xs font-normal text-primary">(Anda)</span></h2>
                        <p class="text-xs tabular-nums text-gray-500">
                            {{ m.activity_count }} kegiatan triwulan ini<template v-if="m.unsent_count"> · <span class="text-orange-700">{{ m.unsent_count }} belum dikirim</span></template>
                        </p>
                    </header>

                    <div class="space-y-4 px-4 py-3">
                        <!-- Focus from the PJ -->
                        <div>
                            <p class="mb-1 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500"><Target class="h-3.5 w-3.5" aria-hidden="true" /> Fokus minggu ini</p>
                            <template v-if="canManage">
                                <Textarea v-model="drafts[m.employee_id]" :rows="2" class="text-sm" :aria-label="`Fokus minggu ini untuk ${m.name}`" placeholder="Contoh: selesaikan entri SE2026 blok 12–20, lalu rapat ISO Kamis" />
                                <div class="mt-1.5 flex justify-end">
                                    <Button size="sm" :disabled="saving === m.employee_id || (drafts[m.employee_id] ?? '') === (m.focus ?? '')" @click="saveFocus(m)">
                                        {{ saving === m.employee_id ? 'Menyimpan…' : 'Simpan' }}
                                    </Button>
                                </div>
                            </template>
                            <p v-else-if="m.focus" class="whitespace-pre-line rounded border border-primary/20 bg-primary/5 px-3 py-2 text-sm text-gray-800">{{ m.focus }}</p>
                            <p v-else class="text-sm text-gray-400">Belum ada fokus dari PJ.</p>
                        </div>

                        <PlanItemsPanel
                            :team-id="selectedTeamId!"
                            :employee-id="m.employee_id"
                            :member-name="m.name"
                            :plans="m.plans"
                            :rk-options="rkOptions"
                            :project-options="projectOptions"
                            :week-start="weekStart"
                            :week-end="weekEnd"
                            :can-edit="canManage || m.employee_id === currentEmployeeId"
                        />

                        <!-- RK with no kegiatan yet -->
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">RK belum ada kegiatan ({{ m.rks_without_activity.length }})</p>
                            <p v-if="!m.rk_count" class="text-sm text-gray-400">Daftar RK belum tersinkron. Admin menjalankan Sinkronisasi Kegiatan.</p>
                            <p v-else-if="!m.rks_without_activity.length" class="text-sm text-green-700">Semua RK sudah punya kegiatan.</p>
                            <ul v-else class="list-disc space-y-0.5 pl-5 text-sm text-gray-700">
                                <li v-for="rk in m.rks_without_activity" :key="rk.id">{{ rk.name }}</li>
                            </ul>
                        </div>

                        <!-- Unfinished kegiatan -->
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Belum selesai ({{ m.unfinished.length }})</p>
                            <p v-if="!m.unfinished.length" class="text-sm text-gray-400">Tidak ada kegiatan dengan progres di bawah 100%.</p>
                            <ul v-else class="divide-y rounded border">
                                <li v-for="a in m.unfinished" :key="a.id" class="grid grid-cols-[1fr_auto] items-center gap-3 px-3 py-2 text-sm">
                                    <div class="min-w-0">
                                        <p class="leading-snug text-gray-800">{{ a.description }}</p>
                                        <p class="text-xs text-gray-500">{{ formatDate(a.date_start) }}<template v-if="a.rk_name"> · {{ a.rk_name }}</template></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-100" role="progressbar" :aria-valuenow="a.progress" aria-valuemin="0" aria-valuemax="100" :aria-label="`Progres ${a.description}`">
                                            <div class="h-full rounded-full bg-orange-500" :style="{ width: `${Math.min(100, a.progress)}%` }" />
                                        </div>
                                        <span class="w-9 text-right text-xs font-semibold tabular-nums text-orange-700">{{ Math.round(a.progress) }}%</span>
                                        <a v-if="a.evidence_url" :href="a.evidence_url" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-primary" :aria-label="`Bukti ${a.description}`"><ExternalLink class="h-3.5 w-3.5" /></a>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>
            </div>
        </template>
    </AppLayout>
</template>
