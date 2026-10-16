<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { WeeklyPlanMember, WeeklyPlanProps } from '@/types';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ChevronLeft, ChevronRight, ExternalLink, Target } from 'lucide-vue-next';
import SyncMyActivitiesButton from '@/Components/Kinetik/SyncMyActivitiesButton.vue';
import PlanItemsPanel from '@/Components/Kinetik/PlanItemsPanel.vue';
import PlanFlowStrip from '@/Components/Kinetik/PlanFlowStrip.vue';
import PlanFlowGuide from '@/Components/Kinetik/PlanFlowGuide.vue';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/Components/ui/accordion';
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
                <SyncMyActivitiesButton />
            </div>

            <p class="mb-3 max-w-3xl text-sm leading-relaxed text-gray-600">
                Rencana Anda minggu ini, dan sampai mana rencana itu di kipApp. Data kegiatan dan RK Triwulan {{ roman[quarter - 1] }} berasal dari sinkronisasi kipApp terakhir.
            </p>

            <PlanFlowGuide />

            <div v-if="!members.length" class="rounded-md border border-dashed bg-gray-50 py-10 text-center text-sm text-gray-500">Anda belum menjadi anggota tim ini.</div>

            <div class="grid gap-4 xl:grid-cols-2">
                <section
                    v-for="m in members"
                    :key="m.employee_id"
                    :class="['flex flex-col rounded-md border bg-white', m.employee_id === currentEmployeeId ? 'border-primary/50 ring-1 ring-primary/20' : '']"
                    :aria-label="`Rencana ${m.name}`"
                >
                    <header class="space-y-2 border-b px-4 py-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                            <h2 class="text-lg font-semibold text-gray-900">{{ m.name }}<span v-if="m.employee_id === currentEmployeeId" class="ml-1.5 text-sm font-normal text-primary">(Anda)</span></h2>
                            <p class="text-xs tabular-nums text-gray-600">
                                {{ m.activity_count }} kegiatan triwulan ini<template v-if="m.unsent_count"> · <span class="font-medium text-orange-800">{{ m.unsent_count }} belum dikirim ke atasan</span></template>
                            </p>
                        </div>
                        <PlanFlowStrip :steps="m.flow" />
                    </header>

                    <div class="space-y-5 px-4 py-4">
                        <!-- Focus from the PJ -->
                        <div>
                            <h3 class="mb-1.5 flex items-center gap-1.5 text-sm font-semibold text-gray-800"><Target class="h-4 w-4 text-primary" aria-hidden="true" /> Fokus minggu ini</h3>
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
                            :can-evaluate="canManage"
                            :can-send="canSendToAtasan"
                        />

                        <Accordion type="multiple" class="rounded-md border">
                            <AccordionItem value="rk" class="border-b px-4">
                                <AccordionTrigger class="py-3 text-sm font-medium text-gray-800">
                                    RK belum ada kegiatan
                                    <span class="ml-2 rounded-full px-2 py-0.5 text-xs" :class="m.rks_without_activity.length ? 'bg-orange-100 text-orange-800' : 'bg-green-50 text-green-800'">{{ m.rks_without_activity.length }}</span>
                                </AccordionTrigger>
                                <AccordionContent>
                                    <p v-if="!m.rk_count" class="text-sm text-gray-600">Daftar RK belum tersinkron. Admin menjalankan Sinkronisasi Kegiatan.</p>
                                    <p v-else-if="!m.rks_without_activity.length" class="text-sm text-green-800">Semua RK sudah punya kegiatan.</p>
                                    <ul v-else class="list-disc space-y-1 pl-5 text-sm text-gray-700">
                                        <li v-for="rk in m.rks_without_activity" :key="rk.id">{{ rk.name }}</li>
                                    </ul>
                                </AccordionContent>
                            </AccordionItem>
                            <AccordionItem value="unfinished" class="border-b-0 px-4">
                                <AccordionTrigger class="py-3 text-sm font-medium text-gray-800">
                                    Kegiatan belum selesai
                                    <span class="ml-2 rounded-full px-2 py-0.5 text-xs" :class="m.unfinished.length ? 'bg-orange-100 text-orange-800' : 'bg-green-50 text-green-800'">{{ m.unfinished.length }}</span>
                                </AccordionTrigger>
                                <AccordionContent>
                                    <p v-if="!m.unfinished.length" class="text-sm text-gray-600">Tidak ada kegiatan dengan progres di bawah 100%.</p>
                                    <ul v-else class="divide-y">
                                        <li v-for="a in m.unfinished" :key="a.id" class="grid grid-cols-[1fr_auto] items-center gap-3 py-2 text-sm">
                                            <div class="min-w-0">
                                                <p class="leading-snug text-gray-900">{{ a.description }}</p>
                                                <p class="text-xs text-gray-600">{{ formatDate(a.date_start) }}<template v-if="a.rk_name"> · {{ a.rk_name }}</template></p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-100" role="progressbar" :aria-valuenow="a.progress" aria-valuemin="0" aria-valuemax="100" :aria-label="`Progres ${a.description}`">
                                                    <div class="h-full rounded-full bg-orange-500" :style="{ width: `${Math.min(100, a.progress)}%` }" />
                                                </div>
                                                <span class="w-10 text-right text-xs font-semibold tabular-nums text-orange-800">{{ Math.round(a.progress) }}%</span>
                                                <a v-if="a.evidence_url" :href="a.evidence_url" target="_blank" rel="noopener noreferrer" class="text-gray-500 hover:text-primary" :aria-label="`Bukti ${a.description}`"><ExternalLink class="h-4 w-4" /></a>
                                            </div>
                                        </li>
                                    </ul>
                                </AccordionContent>
                            </AccordionItem>
                        </Accordion>
                    </div>
                </section>
            </div>
        </template>
    </AppLayout>
</template>
