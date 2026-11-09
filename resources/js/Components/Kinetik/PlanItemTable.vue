<script setup lang="ts">
import { computed } from 'vue';
import { CheckCircle2, ChevronDown, Eye } from 'lucide-vue-next';
import type { PlanItemRow } from '@/types';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { planStage, toneClass } from '@/composables/usePlanStage';
import { achievementPercent, periodText, withUnit } from '@/composables/useAchievement';
import { useDateFormat } from '@/composables/useDateFormat';

/**
 * The kegiatan of a week, laid out like kipApp's "Pelaksanaan Kinerja" table so people recognise it: No, Tanggal, Rencana Kinerja,
 * Kegiatan, Progress, Capaian, Bukti Dukung, Capaian SKP, Dikirim and an Aksi menu on every row. A click on a row opens the realisasi.
 * With the send setting on, rows can be checked for a bulk action.
 */
const props = withDefaults(defineProps<{ rows: PlanItemRow[]; canSendToAtasan: boolean; emptyText: string; selected: number[]; actions?: boolean }>(), { actions: true });
const emit = defineEmits<{
    open: [item: PlanItemRow];
    edit: [item: PlanItemRow];
    copy: [item: PlanItemRow];
    remove: [item: PlanItemRow];
    toggle: [id: number, on: boolean];
    'toggle-all': [rows: PlanItemRow[], on: boolean];
}>();
const selectable = computed(() => props.rows.filter(r => r.can_send || r.can_push));
const allChecked = computed(() => selectable.value.length > 0 && selectable.value.every(r => props.selected.includes(r.id)));
const showChecks = computed(() => props.canSendToAtasan || selectable.value.length > 0);
const { formatDate } = useDateFormat();
const short = (d: string) => formatDate(d, { day: 'numeric', month: 'short' });
const stageOf = (row: PlanItemRow) => planStage(row, props.canSendToAtasan);
const canChange = (row: PlanItemRow) => row.can_edit && !row.sent;
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[60rem] border-collapse text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-700">
                <tr>
                    <th v-if="showChecks" class="w-8 border-b px-3 py-3">
                        <Checkbox :model-value="allChecked" :disabled="!selectable.length" aria-label="Pilih semua kegiatan yang bisa dikirim" @update:model-value="(v: boolean | 'indeterminate') => emit('toggle-all', rows, v === true)" />
                    </th>
                    <th class="w-10 border-b px-3 py-3">No</th>
                    <th class="border-b px-3 py-3">Tanggal</th>
                    <th class="border-b px-3 py-3">Rencana Kinerja</th>
                    <th class="border-b px-3 py-3">Kegiatan</th>
                    <th class="border-b px-3 py-3">Progress</th>
                    <th class="border-b px-3 py-3">Capaian</th>
                    <th class="border-b px-3 py-3">Bukti Dukung</th>
                    <th class="border-b px-3 py-3 text-center">Capaian SKP</th>
                    <th class="border-b px-3 py-3">Dikirim</th>
                    <th v-if="actions" class="w-24 border-b px-3 py-3"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, i) in rows" :key="row.id" class="cursor-pointer border-b align-top transition-colors duration-150 hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none" tabindex="0" @click="emit('open', row)" @keydown.enter="emit('open', row)">
                    <td v-if="showChecks" class="w-8 px-3 py-3" @click.stop>
                        <Checkbox :model-value="selected.includes(row.id)" :disabled="!(row.can_send || row.can_push)" :aria-label="`Pilih ${row.description}`" @update:model-value="(v: boolean | 'indeterminate') => emit('toggle', row.id, v === true)" />
                    </td>
                    <td class="px-3 py-3 tabular-nums text-gray-600">{{ i + 1 }}</td>
                    <td class="whitespace-nowrap px-3 py-3 tabular-nums">
                        {{ periodText(row, short) }}
                        <span v-if="row.time_start" class="block text-xs text-gray-600">{{ row.time_start }} – {{ row.time_end }}</span>
                    </td>
                    <td class="max-w-[14rem] px-3 py-3 text-gray-700">{{ row.rk_name }}</td>
                    <td class="max-w-xs px-3 py-3">
                        <span class="font-medium text-gray-900">{{ row.description }}</span>
                        <span v-if="row.continued" class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-normal text-gray-700" title="Dimulai di minggu sebelumnya dan masih berjalan">Lanjutan</span>
                        <span :class="['mt-1 block w-fit rounded-full px-2 py-0.5 text-xs font-medium', toneClass[stageOf(row).tone]]">{{ stageOf(row).label }}</span>
                    </td>
                    <td class="w-36 px-3 py-3">
                        <div class="flex items-center gap-2">
                            <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-200" role="progressbar" :aria-valuenow="row.progress" aria-valuemin="0" aria-valuemax="100" :aria-label="`Progress ${row.progress}%`">
                                <div class="h-full rounded-full bg-emerald-500 transition-[width] duration-300 ease-out motion-reduce:transition-none" :style="{ width: `${row.progress}%` }" />
                            </div>
                            <CheckCircle2 v-if="row.progress >= 100" class="h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" />
                            <span v-else class="text-xs tabular-nums text-gray-600">{{ row.progress }}%</span>
                        </div>
                    </td>
                    <td class="max-w-xs px-3 py-3">
                        <span v-if="row.capaian !== null && row.capaian !== undefined" class="tabular-nums">
                            {{ withUnit(row.capaian, row.target_unit) }}
                            <span v-if="achievementPercent(row.capaian, row.target) !== null" class="text-gray-600">({{ achievementPercent(row.capaian, row.target) }}%)</span>
                        </span>
                        <span v-else class="text-gray-500">–</span>
                        <span v-if="row.target" class="block text-xs text-gray-600">Target {{ withUnit(row.target, row.target_unit) }}</span>
                        <span v-if="row.capaian_note" class="mt-0.5 line-clamp-2 block text-xs text-gray-700">{{ row.capaian_note }}</span>
                    </td>
                    <td class="px-3 py-3" @click.stop>
                        <Button v-if="row.evidence_url" as="a" :href="row.evidence_url" target="_blank" rel="noopener noreferrer" size="sm" class="h-8 gap-1 bg-emerald-600 text-white hover:bg-emerald-700"><Eye class="h-3.5 w-3.5" aria-hidden="true" /> Lihat</Button>
                        <span v-else class="text-xs italic text-gray-600">Belum ada</span>
                    </td>
                    <td class="px-3 py-3 text-center">
                        <CheckCircle2 v-if="row.is_capaian_skp" class="mx-auto h-4 w-4 text-emerald-600" aria-hidden="true" /><span v-if="row.is_capaian_skp" class="sr-only">Masuk capaian SKP</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-3 tabular-nums">{{ row.sent_at ? formatDate(row.sent_at, { day: 'numeric', month: 'short', year: 'numeric' }) : '–' }}</td>
                    <td v-if="actions" class="px-3 py-3" @click.stop>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button size="sm" class="h-8 gap-1 bg-emerald-600 text-white hover:bg-emerald-700" :aria-label="`Aksi untuk ${row.description}`">Aksi <ChevronDown class="h-3.5 w-3.5" aria-hidden="true" /></Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-48">
                                <DropdownMenuItem @select="emit('open', row)">Isi realisasi</DropdownMenuItem>
                                <DropdownMenuItem v-if="canChange(row)" @select="emit('edit', row)">Ubah</DropdownMenuItem>
                                <DropdownMenuItem @select="emit('copy', row)">Salin kegiatan</DropdownMenuItem>
                                <template v-if="row.can_delete">
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="text-red-700 focus:text-red-700" @select="emit('remove', row)">{{ row.in_kipapp ? 'Hapus' : 'Batalkan' }}</DropdownMenuItem>
                                </template>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <p v-if="!rows.length" class="px-4 py-8 text-center text-sm text-gray-600">{{ emptyText }}</p>
</template>
