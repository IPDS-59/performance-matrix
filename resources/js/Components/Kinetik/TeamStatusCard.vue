<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { CircleCheck, TriangleAlert } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { formatNumber } from '@/composables/useNumber';

export interface TeamStatus {
    team_id: number;
    name: string;
    week: string;
    complete: number;
    members: Array<{ employee_id: number; name: string; plan_count: number; last_total: number; last_filled: number; complete: boolean }>;
}

/** One team of the PJ: a row per person, whoever still owes a plan or a realisasi first. A big team shows its first rows and expands. */
const props = defineProps<{ team: TeamStatus }>();
const PREVIEW = 6;
const expanded = ref(false);
const rows = computed(() => (expanded.value ? props.team.members : props.team.members.slice(0, PREVIEW)));
const owing = computed(() => props.team.members.length - props.team.complete);
</script>

<template>
    <section class="rounded-lg border bg-white" :aria-label="`Status anggota ${team.name}`">
        <div class="flex flex-wrap items-center gap-3 border-b px-4 py-3">
            <h3 class="text-sm font-semibold">{{ team.name }}</h3>
            <p :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium', owing ? 'bg-amber-50 text-amber-800' : 'bg-green-50 text-green-700']">
                <component :is="owing ? TriangleAlert : CircleCheck" class="h-3.5 w-3.5" aria-hidden="true" />
                {{ owing ? `${formatNumber(owing)} dari ${formatNumber(team.members.length)} anggota belum lengkap` : 'Semua anggota lengkap' }}
            </p>
            <Link :href="route('team-recap.weekly', { team: team.team_id, week: team.week, tab: 'tabel' })" class="ml-auto text-sm text-primary underline-offset-2 hover:underline">Buka tabel rapat</Link>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                <tr><th class="px-4 py-2">Anggota</th><th class="px-4 py-2">Rencana minggu ini</th><th class="px-4 py-2">Realisasi minggu lalu</th></tr>
            </thead>
            <tbody>
                <tr v-for="m in rows" :key="m.employee_id" class="border-t">
                    <td class="px-4 py-2 font-medium">{{ m.name }}</td>
                    <td class="px-4 py-2">
                        <span v-if="m.plan_count" class="text-green-700">{{ formatNumber(m.plan_count) }} kegiatan</span>
                        <span v-else class="font-medium text-amber-800">Belum ada</span>
                    </td>
                    <td class="px-4 py-2">
                        <span v-if="!m.last_total" class="text-gray-500">Tidak ada kegiatan</span>
                        <span v-else-if="m.last_filled === m.last_total" class="text-green-700">Lengkap ({{ formatNumber(m.last_total) }})</span>
                        <span v-else class="font-medium text-amber-800">{{ formatNumber(m.last_filled) }} dari {{ formatNumber(m.last_total) }} terisi</span>
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-if="team.members.length > PREVIEW" class="border-t px-4 py-2 text-center">
            <Button variant="ghost" size="sm" :aria-expanded="expanded" @click="expanded = !expanded">{{ expanded ? 'Ciutkan' : `Tampilkan semua ${formatNumber(team.members.length)} anggota` }}</Button>
        </div>
    </section>
</template>
