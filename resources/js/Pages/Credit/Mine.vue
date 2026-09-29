<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { CreditSummary } from '@/types';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Award, CalendarClock, Info } from 'lucide-vue-next';
import { CREDIT_STATUS_META, creditProgress, formatAk, nextStepLabel } from '@/composables/useCreditStatus';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps<{ credit: CreditSummary }>();

const { formatDate } = useDateFormat();
const meta = computed(() => CREDIT_STATUS_META[props.credit.status]);
const tracked = computed(() => !['no_data', 'non_jf'].includes(props.credit.status));
const waitingForTime = computed(() => !!props.credit.eligible_from && props.credit.eligible_from > new Date().toISOString().slice(0, 10));
</script>

<template>
    <Head title="Angka Kredit Saya" />
    <AppLayout>
        <template #title>Angka Kredit Saya</template>

        <div class="mx-auto max-w-4xl space-y-4">
            <!-- Who and where -->
            <section class="rounded-lg border bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ credit.name }}</h2>
                        <p class="mt-0.5 text-sm text-gray-600">{{ credit.jabatan ?? 'Jabatan belum tersinkron' }}</p>
                        <p v-if="credit.golongan" class="text-sm text-gray-600">{{ credit.pangkat }} ({{ credit.golongan }})<template v-if="credit.golongan_since">, sejak {{ formatDate(credit.golongan_since) }}</template></p>
                    </div>
                    <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', meta.chip]">{{ meta.label }}</span>
                </div>

                <p v-if="credit.status === 'no_data'" class="mt-4 rounded-md bg-gray-50 px-3 py-2.5 text-sm text-gray-600">
                    Data golongan dan predikat Anda belum tersinkron dari kipApp. Sinkronisasi berjalan setiap Senin pagi. Jika minggu depan masih kosong, hubungi admin.
                </p>
                <p v-else-if="credit.status === 'non_jf'" class="mt-4 rounded-md bg-gray-50 px-3 py-2.5 text-sm text-gray-600">
                    Jabatan Anda bukan jabatan fungsional, sehingga kenaikan pangkat tidak memakai Angka Kredit.
                </p>
            </section>

            <template v-if="tracked">
                <!-- Progress toward the next step -->
                <section class="rounded-lg border bg-white p-5" aria-labelledby="progres-title">
                    <h2 id="progres-title" class="text-sm font-semibold text-gray-900">
                        <template v-if="credit.kind">Menuju {{ nextStepLabel(credit) }}</template>
                        <template v-else>{{ credit.level }} adalah jenjang tertinggi</template>
                    </h2>

                    <div v-if="credit.target" class="mt-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-3xl font-semibold tabular-nums text-gray-900">
                                {{ formatAk(credit.earned) }}<span class="text-base font-normal text-gray-500"> / {{ formatAk(credit.target) }} AK</span>
                            </p>
                            <p class="text-sm tabular-nums text-gray-600">{{ creditProgress(credit).toFixed(0) }}%</p>
                        </div>
                        <div
                            class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-100"
                            role="progressbar"
                            :aria-valuenow="Math.round(creditProgress(credit))"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="`Angka Kredit ${formatAk(credit.earned)} dari ${formatAk(credit.target)}`"
                        >
                            <div class="h-full rounded-full bg-primary transition-[width]" :style="{ width: `${creditProgress(credit)}%` }" />
                        </div>

                        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-md bg-gray-50 px-3 py-2.5">
                                <dt class="text-xs text-gray-500">Masih kurang</dt>
                                <dd class="mt-0.5 font-semibold tabular-nums text-gray-900">{{ formatAk(credit.gap) }} AK</dd>
                            </div>
                            <div class="rounded-md bg-gray-50 px-3 py-2.5">
                                <dt class="text-xs text-gray-500">Perkiraan waktu</dt>
                                <dd class="mt-0.5 font-semibold text-gray-900">
                                    <template v-if="credit.gap">± {{ credit.quarters_to_go }} triwulan lagi dengan predikat Baik</template>
                                    <template v-else>AK sudah cukup</template>
                                </dd>
                            </div>
                            <div class="rounded-md bg-gray-50 px-3 py-2.5">
                                <dt class="text-xs text-gray-500">Masa kerja golongan (min. 2 tahun)</dt>
                                <dd class="mt-0.5 font-semibold text-gray-900">
                                    <template v-if="credit.eligible_from">{{ waitingForTime ? 'Terpenuhi pada' : 'Terpenuhi sejak' }} {{ formatDate(credit.eligible_from) }}</template>
                                    <template v-else>—</template>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <p v-if="credit.estimated" class="mt-3 flex gap-2 text-sm text-gray-600">
                        <CalendarClock class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" aria-hidden="true" />
                        {{ formatAk(credit.estimated) }} AK berasal dari SKP yang belum dinilai dan dihitung dengan predikat Baik.
                    </p>
                    <p class="mt-2 flex gap-2 text-sm text-gray-600">
                        <Award class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" aria-hidden="true" />
                        <template v-if="credit.ak_base_date">Dihitung dari AK awal {{ formatAk(credit.ak_base) }} (PAK s.d. {{ formatDate(credit.ak_base_date) }}), ditambah SKP sesudahnya.</template>
                        <template v-else>Dihitung dari SKP sejak {{ formatDate(credit.counted_from) }}. Angka resmi tetap mengikuti PAK.</template>
                    </p>
                </section>

                <!-- Per quarter -->
                <section class="overflow-hidden rounded-lg border bg-white" aria-labelledby="riwayat-title">
                    <h2 id="riwayat-title" class="border-b bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-900">Riwayat per triwulan</h2>
                    <Table class="text-sm">
                        <TableHeader>
                            <TableRow class="text-xs uppercase tracking-wide text-gray-500">
                                <TableHead>Triwulan</TableHead>
                                <TableHead>Predikat</TableHead>
                                <TableHead class="text-right">AK</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody class="divide-y divide-gray-100">
                            <TableRow v-for="q in credit.quarters" :key="q.label">
                                <TableCell class="font-medium text-gray-800">{{ q.label }}</TableCell>
                                <TableCell class="text-gray-700">{{ q.predikat ?? 'Belum dinilai' }}</TableCell>
                                <TableCell class="text-right tabular-nums text-gray-900">{{ formatAk(q.ak) }}</TableCell>
                                <TableCell>
                                    <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', q.final ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800']">
                                        {{ q.final ? 'Final' : 'Estimasi' }}
                                    </span>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!credit.quarters?.length">
                                <TableCell colspan="4" class="py-8 text-center text-sm text-gray-500">Belum ada SKP periodik sejak {{ formatDate(credit.counted_from) }}.</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </section>
            </template>

            <p class="flex gap-2 text-xs leading-relaxed text-gray-500">
                <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                <span>
                    AK per bulan = koefisien tahunan jenjang ÷ 12 × persentase predikat (Sangat Baik 150%, Baik 100%, Butuh Perbaikan 75%, Kurang 50%, Sangat Kurang 25%).
                    Sumber: PermenPANRB Nomor 1 Tahun 2023 Pasal 37 dan Lampiran A; PerBKN Nomor 3 Tahun 2023 Pasal 13. Data golongan dan predikat dari kipApp.
                </span>
            </p>
        </div>
    </AppLayout>
</template>
