<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { CreditStatus, CreditSummary, TeamOption } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import InputError from '@/Components/InputError.vue';
import { Info, Pencil } from 'lucide-vue-next';
import { CREDIT_STATUS_META, creditProgress, formatAk, nextStepLabel } from '@/composables/useCreditStatus';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps<{
    teams: TeamOption[];
    selectedTeamId: number | null;
    rows: CreditSummary[];
    canEditBase: boolean;
}>();

const { formatDate } = useDateFormat();

function changeTeam(value: unknown) {
    router.get(route('credit.team'), value === 'all' ? {} : { team: Number(value) }, { preserveScroll: true });
}

// ── Status filter (client side) ─────────────────────────────────────────────

const STATUS_FILTERS: Array<CreditStatus | 'all'> = ['all', 'ready', 'ak_ready', 'near', 'progress', 'top', 'no_data', 'non_jf'];
const statusFilter = ref<CreditStatus | 'all'>('all');
const countOf = (status: CreditStatus | 'all') => (status === 'all' ? props.rows.length : props.rows.filter(r => r.status === status).length);
const visibleRows = computed(() => (statusFilter.value === 'all' ? props.rows : props.rows.filter(r => r.status === statusFilter.value)));

// ── Admin: AK awal from the last PAK ────────────────────────────────────────

const editing = ref<number | null>(null);
const baseForm = useForm({ ak_base: '' as string | number, ak_base_date: '' });

function editBase(row: CreditSummary) {
    editing.value = row.employee_id;
    baseForm.ak_base = row.ak_base ?? '';
    baseForm.ak_base_date = row.ak_base_date ?? '';
    baseForm.clearErrors();
}

function saveBase(row: CreditSummary) {
    baseForm
        .transform((data: { ak_base: string | number; ak_base_date: string }) => ({ ak_base: data.ak_base === '' ? null : data.ak_base, ak_base_date: data.ak_base_date || null }))
        .put(route('credit.base', row.employee_id), { preserveScroll: true, onSuccess: () => (editing.value = null) });
}
</script>

<template>
    <Head title="Angka Kredit Tim" />
    <AppLayout>
        <template #title>Angka Kredit Tim</template>

        <div class="mb-4 flex flex-col gap-3 rounded-lg border bg-white p-3 sm:p-4 lg:flex-row lg:items-center">
            <Select :model-value="selectedTeamId !== null ? String(selectedTeamId) : 'all'" @update:model-value="changeTeam">
                <SelectTrigger class="w-full lg:w-80" aria-label="Pilih tim">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{ teams.length > 1 ? 'Semua tim' : teams[0]?.name }}</SelectItem>
                    <template v-if="teams.length > 1">
                        <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                    </template>
                </SelectContent>
            </Select>

            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Filter status Angka Kredit">
                <button
                    v-for="status in STATUS_FILTERS"
                    v-show="status === 'all' || countOf(status) > 0"
                    :key="status"
                    type="button"
                    :aria-pressed="statusFilter === status"
                    :class="[
                        'inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                        statusFilter === status ? 'border-primary bg-primary text-primary-foreground' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900',
                    ]"
                    @click="statusFilter = status"
                >
                    {{ status === 'all' ? 'Semua' : CREDIT_STATUS_META[status].label }}
                    <span class="tabular-nums opacity-75">{{ countOf(status) }}</span>
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border bg-white">
            <Table class="text-sm">
                <TableHeader>
                    <TableRow class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <TableHead class="min-w-[14rem]">Pegawai</TableHead>
                        <TableHead class="min-w-[10rem]">Menuju</TableHead>
                        <TableHead class="min-w-[12rem]">Angka Kredit</TableHead>
                        <TableHead class="text-right">Kurang</TableHead>
                        <TableHead class="min-w-[9rem]">Min. 2 tahun</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead v-if="canEditBase" class="w-10"><span class="sr-only">AK awal</span></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="divide-y divide-gray-100">
                    <template v-for="row in visibleRows" :key="row.employee_id">
                        <TableRow class="hover:bg-gray-50">
                            <TableCell class="whitespace-normal leading-snug">
                                <p class="font-medium text-gray-900">{{ row.name }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ row.jabatan ?? '—' }}<template v-if="row.golongan"> · {{ row.golongan }}</template>
                                </p>
                            </TableCell>
                            <TableCell class="whitespace-normal text-gray-700">{{ nextStepLabel(row) ?? '—' }}</TableCell>
                            <TableCell>
                                <div v-if="row.target" class="flex items-center gap-2">
                                    <div class="h-1.5 w-20 overflow-hidden rounded-full bg-gray-100">
                                        <div class="h-full rounded-full bg-primary" :style="{ width: `${creditProgress(row)}%` }" />
                                    </div>
                                    <span class="tabular-nums text-gray-900">{{ formatAk(row.earned) }} <span class="text-gray-500">/ {{ formatAk(row.target) }}</span></span>
                                </div>
                                <span v-else class="text-xs text-gray-400">—</span>
                                <p v-if="row.estimated" class="mt-0.5 text-xs text-amber-700">termasuk estimasi {{ formatAk(row.estimated) }}</p>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                <template v-if="row.gap">
                                    {{ formatAk(row.gap) }}
                                    <span class="block text-xs text-gray-500">± {{ row.quarters_to_go }} TW</span>
                                </template>
                                <template v-else-if="row.gap === 0">0</template>
                                <template v-else>—</template>
                            </TableCell>
                            <TableCell class="text-gray-700">{{ row.eligible_from ? formatDate(row.eligible_from) : '—' }}</TableCell>
                            <TableCell>
                                <span :class="['inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium', CREDIT_STATUS_META[row.status].chip]">
                                    {{ CREDIT_STATUS_META[row.status].label }}
                                </span>
                            </TableCell>
                            <TableCell v-if="canEditBase">
                                <button
                                    v-if="row.status !== 'non_jf'"
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                    :aria-label="`Ubah AK awal dari PAK untuk ${row.name}`"
                                    @click="editing === row.employee_id ? (editing = null) : editBase(row)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="editing === row.employee_id" class="bg-gray-50 hover:bg-gray-50">
                            <TableCell :colspan="7" class="whitespace-normal px-4 py-3">
                                <form class="flex flex-wrap items-end gap-3" @submit.prevent="saveBase(row)">
                                    <div>
                                        <Label :for="`ak-${row.employee_id}`" class="text-xs">AK kumulatif menurut PAK terakhir</Label>
                                        <Input :id="`ak-${row.employee_id}`" v-model="baseForm.ak_base" type="number" step="0.001" min="0" class="mt-1 w-40" />
                                        <InputError :message="baseForm.errors.ak_base" />
                                    </div>
                                    <div>
                                        <Label :for="`akd-${row.employee_id}`" class="text-xs">Berlaku sampai tanggal</Label>
                                        <Input :id="`akd-${row.employee_id}`" v-model="baseForm.ak_base_date" type="date" class="mt-1 w-44" />
                                        <InputError :message="baseForm.errors.ak_base_date" />
                                    </div>
                                    <Button type="submit" size="sm" :disabled="baseForm.processing">Simpan</Button>
                                    <Button type="button" size="sm" variant="ghost" @click="editing = null">Batal</Button>
                                    <p class="basis-full text-xs text-gray-500">Kosongkan keduanya untuk kembali ke estimasi dari predikat kipApp.</p>
                                </form>
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-if="!visibleRows.length">
                        <TableCell :colspan="7" class="py-10 text-center text-sm text-gray-500">Tidak ada pegawai dengan status ini.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <p class="mt-3 flex gap-2 text-xs leading-relaxed text-gray-500">
            <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
            <span>
                Estimasi dari golongan dan predikat SKP di kipApp (PermenPANRB 1/2023, PerBKN 3/2023). SKP yang belum dinilai dihitung Baik.
                Usulan kenaikan tetap memakai PAK resmi.
            </span>
        </p>
    </AppLayout>
</template>
