<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { RecapSegment, TeamRecapEvidence, TeamOption } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { ChevronLeft, ChevronRight, ExternalLink, Trash2 } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { useDateFormat } from '@/composables/useDateFormat';

const { formatWeekRange } = useDateFormat();

const props = defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    segments: RecapSegment[];
    evidences: TeamRecapEvidence[];
    weekStart: string;
    weekEnd: string;
    prevWeek: string;
    nextWeek: string;
}>();

function navigate(params: Record<string, string | number>) {
    router.get(route('team-recap.weekly'), {
        team: props.selectedTeamId ?? undefined,
        week: props.weekStart,
        ...params,
    }, { preserveState: false });
}

function achievementColor(val: number | null): string {
    const n = Number(val ?? 0);
    if (n >= 80) return 'text-green-600';
    if (n >= 50) return 'text-yellow-600';
    return 'text-red-600';
}

const evidenceTypeLabel: Record<string, string> = {
    notula: 'Notula',
    photo: 'Foto',
    attendance: 'Daftar Hadir',
};

// ── Evidence form ──────────────────────────────────────────────────────────

const showEvidenceForm = ref(false);

const evidenceForm = useForm({
    team_id: props.selectedTeamId,
    project_id: null as number | null,
    week_start: props.weekStart,
    type: 'notula',
    title: '',
    url: '',
});

function submitEvidence() {
    evidenceForm.team_id = props.selectedTeamId;
    evidenceForm.week_start = props.weekStart;
    evidenceForm.post(route('team-recap.evidence.store'), {
        preserveScroll: true,
        onSuccess: () => {
            evidenceForm.reset('title', 'url');
            showEvidenceForm.value = false;
        },
    });
}

function deleteEvidence(id: number) {
    router.delete(route('team-recap.evidence.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Rekap Tim" />
    <AppLayout>
        <template #title>Rekap Tim (Mingguan)</template>

        <div v-if="!teams.length" class="rounded-md border border-yellow-200 bg-yellow-50 p-6 text-center text-sm text-yellow-800">
            Anda belum tergabung dalam tim mana pun.
        </div>

        <template v-else>
            <!-- Controls -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-md border bg-white px-4 py-3">
                <select
                    :value="selectedTeamId ?? undefined"
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    @change="navigate({ team: ($event.target as HTMLSelectElement).value })"
                >
                    <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>

                <div class="flex items-center gap-3">
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Minggu sebelumnya" @click="navigate({ week: prevWeek })">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="text-sm font-medium text-gray-700">{{ formatWeekRange(weekStart, weekEnd) }}</span>
                    <button type="button" class="flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100" title="Minggu berikutnya" @click="navigate({ week: nextWeek })">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Segments by project -->
            <div v-if="!segments.length" class="mb-6 rounded-md border border-dashed border-gray-200 bg-gray-50 py-10 text-center text-sm text-gray-400">
                Belum ada rekap tersimpan untuk tim ini pada minggu ini.
            </div>

            <div v-else class="mb-8 space-y-6">
                <div v-for="seg in segments" :key="seg.project_id ?? 'none'" class="overflow-hidden rounded-md border bg-white">
                    <div class="border-b bg-gray-50 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-800">{{ seg.project_name }}</h3>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-2 text-left">Rencana Kinerja</th>
                                <th class="px-4 py-2 text-right">Target</th>
                                <th class="px-4 py-2 text-right">Realisasi</th>
                                <th class="px-4 py-2 text-right">Capaian</th>
                                <th class="px-4 py-2 text-left">Kontributor</th>
                                <th class="px-4 py-2 text-left">Kendala</th>
                                <th class="px-4 py-2 text-left">Solusi</th>
                                <th class="px-4 py-2 text-left">RTL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in seg.rows" :key="row.performance_plan_id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 align-top">
                                    <p class="font-medium text-gray-800">{{ row.rk_description }}</p>
                                    <p v-if="row.rk_code" class="text-xs text-gray-500">{{ row.rk_code }}</p>
                                </td>
                                <td class="px-4 py-3 text-right align-top text-gray-700">{{ row.target }} {{ row.target_unit ?? '' }}</td>
                                <td class="px-4 py-3 text-right align-top text-gray-700">{{ row.realization }}</td>
                                <td class="px-4 py-3 text-right align-top">
                                    <span v-if="row.achievement != null" :class="['font-semibold', achievementColor(row.achievement)]">{{ row.achievement.toFixed(2) }}%</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3 align-top text-xs text-gray-600">{{ row.contributors.join(', ') || '—' }}</td>
                                <td class="px-4 py-3 align-top text-gray-600">{{ row.obstacle ?? '—' }}</td>
                                <td class="px-4 py-3 align-top text-gray-600">{{ row.solution ?? '—' }}</td>
                                <td class="px-4 py-3 align-top text-gray-600">{{ row.follow_up_plan ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Evidence (notula / foto / DH) -->
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">Bukti Dukung Rapat</h2>
                    <Button size="sm" variant="outline" @click="showEvidenceForm = !showEvidenceForm">
                        {{ showEvidenceForm ? 'Tutup' : 'Tambah Bukti' }}
                    </Button>
                </div>

                <form v-if="showEvidenceForm" class="mb-4 space-y-3 rounded-md border bg-white p-4" @submit.prevent="submitEvidence">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <Label for="ev-type">Jenis</Label>
                            <select id="ev-type" v-model="evidenceForm.type" class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                                <option value="notula">Notula</option>
                                <option value="photo">Foto</option>
                                <option value="attendance">Daftar Hadir</option>
                            </select>
                        </div>
                        <div>
                            <Label for="ev-title">Judul</Label>
                            <Input id="ev-title" v-model="evidenceForm.title" class="mt-1" placeholder="Rapat koordinasi..." />
                        </div>
                    </div>
                    <div>
                        <Label for="ev-url">URL Bukti Dukung <span class="text-red-500">*</span></Label>
                        <Input id="ev-url" v-model="evidenceForm.url" type="url" class="mt-1" placeholder="https://..." />
                        <InputError :message="evidenceForm.errors.url" />
                    </div>
                    <div class="flex justify-end">
                        <Button type="submit" size="sm" :disabled="evidenceForm.processing">Simpan Bukti</Button>
                    </div>
                </form>

                <div v-if="!evidences.length" class="rounded-md border border-dashed border-gray-200 bg-gray-50 py-8 text-center text-sm text-gray-400">
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
                            <button type="button" class="text-gray-400 hover:text-red-600" title="Hapus" @click="deleteEvidence(ev.id)">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </template>
    </AppLayout>
</template>
