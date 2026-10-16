<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ChevronLeft, ChevronRight, Plus, Search } from 'lucide-vue-next';
import type { MemberWeekProps, PlanItemRow } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import SyncMyActivitiesButton from '@/Components/Kinetik/SyncMyActivitiesButton.vue';
import PlanItemTable from '@/Components/Kinetik/PlanItemTable.vue';
import PlanItemFormDialog from '@/Components/Kinetik/PlanItemFormDialog.vue';
import PlanItemDetailDialog from '@/Components/Kinetik/PlanItemDetailDialog.vue';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps<MemberWeekProps>();
const { formatDate } = useDateFormat();
const range = (a?: string, b?: string) => (a && b ? `${formatDate(a, { day: 'numeric', month: 'long' })} – ${formatDate(b, { day: 'numeric', month: 'long', year: 'numeric' })}` : '');

const query = ref('');
const matches = (row: PlanItemRow) => !query.value || `${row.description} ${row.rk_name ?? ''}`.toLowerCase().includes(query.value.toLowerCase());
const lastRows = computed(() => (props.lastWeek ?? []).filter(matches));
const thisRows = computed(() => (props.thisWeek ?? []).filter(matches));

const detailId = ref<number | null>(null);
// The detail follows the fresh props, so a saved capaian shows at once.
const detail = computed(() => [...(props.lastWeek ?? []), ...(props.thisWeek ?? [])].find(r => r.id === detailId.value) ?? null);
const formOpen = ref(false);
const editing = ref<PlanItemRow | null>(null);

function add() { editing.value = null; formOpen.value = true; }
function edit(item: PlanItemRow) { detailId.value = null; editing.value = item; formOpen.value = true; }
function go(week?: string) { if (week) router.get(route('weekly.index'), { week }, { preserveState: false }); }
</script>

<template>
    <Head title="Rekap Mingguan" />
    <AppLayout>
        <template #title>Rekap Mingguan</template>

        <div v-if="!employee" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Akun Anda belum terhubung ke data pegawai.
        </div>

        <template v-else>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-500" aria-hidden="true" />
                    <Input v-model="query" class="pl-9" placeholder="Cari kegiatan atau RK" aria-label="Cari kegiatan" />
                </div>
                <div class="flex items-center rounded-md border bg-white">
                    <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Minggu sebelumnya" @click="go(prevWeek)"><ChevronLeft class="h-4 w-4" /></Button>
                    <span class="min-w-[13rem] px-2 text-center text-sm font-medium tabular-nums">{{ range(weekStart, weekEnd) }}</span>
                    <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Minggu berikutnya" @click="go(nextWeek)"><ChevronRight class="h-4 w-4" /></Button>
                </div>
                <SyncMyActivitiesButton />
                <Button class="ml-auto" @click="add"><Plus class="mr-1 h-4 w-4" aria-hidden="true" /> Tambah</Button>
            </div>

            <p v-if="!rkSynced" class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                Daftar RK Anda belum tersinkron. Tekan “Sinkronkan data saya” untuk menarik RK dan kegiatan dari kipApp.
            </p>
            <p v-if="focus" class="mb-4 rounded-md border bg-white px-4 py-2 text-sm"><span class="font-semibold">Fokus dari PJ:</span> {{ focus }}</p>

            <section class="mb-6 rounded-lg border bg-white" aria-labelledby="h-last">
                <h2 id="h-last" class="border-b px-4 py-3 text-sm font-semibold">Capaian minggu lalu <span class="font-normal text-gray-600">{{ range(lastWeekStart, lastWeekEnd) }}</span></h2>
                <PlanItemTable :rows="lastRows" :can-send-to-atasan="canSendToAtasan" empty-text="Tidak ada kegiatan minggu lalu." @open="detailId = $event.id" />
            </section>

            <section class="rounded-lg border bg-white" aria-labelledby="h-now">
                <h2 id="h-now" class="border-b px-4 py-3 text-sm font-semibold">Rencana minggu ini <span class="font-normal text-gray-600">{{ range(weekStart, weekEnd) }}</span></h2>
                <PlanItemTable :rows="thisRows" :can-send-to-atasan="canSendToAtasan" empty-text="Belum ada rencana. Tekan “Tambah”." @open="detailId = $event.id" />
            </section>

            <PlanItemFormDialog v-model:open="formOpen" :item="editing" :rk-options="rkOptions ?? []" :project-options="projectOptions ?? []" :default-date="weekStart ?? ''" />
            <PlanItemDetailDialog :item="detail" :can-send-to-atasan="canSendToAtasan" @close="detailId = null" @edit="edit" />
        </template>
    </AppLayout>
</template>
