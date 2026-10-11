<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { ArrowRight, BookOpen, Lightbulb, Printer } from 'lucide-vue-next';
import AnnotatedShot from '@/Components/Guide/AnnotatedShot.vue';
import { GUIDE_FAQ, GUIDE_FLOWS, GUIDE_OVERVIEW, type GuideRole } from '@/data/guide';

const props = defineProps<{ defaultRole: GuideRole }>();

const active = ref<GuideRole>(props.defaultRole);

// The weekly cycle from kipApp to the meeting, in the order work happens.
const CYCLE = [
    { who: 'Anggota', what: 'Input kegiatan harian di kipApp' },
    { who: 'Kinetik', what: 'Tarik data kipApp setiap pagi, atau saat Anda menekan Sinkronkan data saya' },
    { who: 'Anggota', what: 'Klaim kegiatan di Rekap Mingguan' },
    { who: 'PJ', what: 'Rapat tim, ringkasan, bukti, kunci' },
    { who: 'Pimpinan', what: 'Review Bersama dan Catatan Pimpinan' },
];

function print() {
    window.print();
}
</script>

<template>
    <Head title="Buku Pedoman" />
    <AppLayout>
        <template #title>Buku Pedoman</template>

        <div class="mx-auto max-w-4xl">
            <!-- Cover / intro -->
            <section class="mb-6 rounded-lg border bg-white p-5 sm:p-6 print:border-0 print:p-0">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary print:hidden">
                            <BookOpen class="h-5 w-5" aria-hidden="true" />
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 print:text-2xl">Buku Pedoman Kinetik</h2>
                            <p class="mt-1 text-sm text-gray-600">Kinerja Terintegrasi, BPS Provinsi Sulawesi Tengah. Alur kerja untuk setiap pengguna.</p>
                        </div>
                    </div>
                    <Button variant="outline" size="sm" class="print:hidden" @click="print">
                        <Printer class="mr-1.5 h-4 w-4" />
                        Cetak / simpan PDF
                    </Button>
                </div>

                <dl class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div v-for="item in GUIDE_OVERVIEW" :key="item.who" class="rounded-md bg-gray-50 px-3 py-2.5 print:border">
                        <dt class="text-sm font-semibold text-gray-800">{{ item.who }}</dt>
                        <dd class="mt-0.5 text-sm leading-snug text-gray-600">{{ item.what }}</dd>
                    </div>
                </dl>
            </section>

            <!-- Weekly cycle -->
            <section class="mb-6 rounded-lg border bg-white p-5 sm:p-6 print:break-inside-avoid" aria-labelledby="siklus-title">
                <h2 id="siklus-title" class="text-base font-semibold text-gray-900">Siklus mingguan</h2>
                <p class="mt-1 text-sm text-gray-600">Kinetik menggantikan tiga spreadsheet: Kegiatan Mingguan Anggota, Rapat Mingguan dan Rapat Bulanan.</p>
                <ol class="mt-4 grid gap-2 sm:grid-cols-5">
                    <li v-for="(step, i) in CYCLE" :key="i" class="relative rounded-md border px-3 py-2.5">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-primary">{{ i + 1 }}. {{ step.who }}</span>
                        <span class="mt-0.5 block text-sm leading-snug text-gray-700">{{ step.what }}</span>
                    </li>
                </ol>
            </section>

            <!-- Role tabs (screen only; print shows every role) -->
            <div class="sticky top-0 z-10 -mx-4 mb-4 bg-gray-50/95 px-4 py-2 backdrop-blur sm:mx-0 sm:px-0 print:hidden">
                <div class="flex gap-1 overflow-x-auto rounded-md border bg-white p-1" role="tablist" aria-label="Pilih peran">
                    <button
                        v-for="flow in GUIDE_FLOWS"
                        :key="flow.role"
                        type="button"
                        role="tab"
                        :aria-selected="active === flow.role"
                        :class="[
                            'shrink-0 rounded px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                            active === flow.role ? 'bg-primary text-primary-foreground' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
                        ]"
                        @click="active = flow.role"
                    >
                        {{ flow.label }}
                    </button>
                </div>
            </div>

            <!-- One flow per role -->
            <section
                v-for="flow in GUIDE_FLOWS"
                :key="flow.role"
                :class="[active === flow.role ? 'block' : 'hidden', 'mb-6 print:block print:break-before-page']"
                :aria-labelledby="`alur-${flow.role}`"
            >
                <div class="mb-4">
                    <h2 :id="`alur-${flow.role}`" class="text-lg font-semibold text-gray-900">Alur {{ flow.label }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ flow.summary }}</p>
                    <p v-if="flow.replaces" class="mt-1 text-xs text-gray-500">Menggantikan: {{ flow.replaces }}</p>
                </div>

                <ol class="space-y-3">
                    <li v-for="(step, i) in flow.steps" :key="step.title" class="flex gap-3 rounded-lg border bg-white p-4 print:break-inside-avoid">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground tabular-nums" aria-hidden="true">{{ i + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <h3 class="text-sm font-semibold text-gray-900">{{ step.title }}</h3>
                                <Link
                                    v-if="step.route"
                                    :href="route(step.route)"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline print:hidden"
                                >
                                    Buka halaman <ArrowRight class="h-3 w-3" aria-hidden="true" />
                                </Link>
                            </div>
                            <p v-if="step.where" class="mt-0.5 text-xs text-gray-500">{{ step.where }}</p>
                            <ul class="mt-2 list-disc space-y-1 pl-4 text-sm leading-relaxed text-gray-700 marker:text-gray-400">
                                <li v-for="line in step.body" :key="line">{{ line }}</li>
                            </ul>
                            <AnnotatedShot v-if="step.shot" :shot="step.shot" />
                            <p v-if="step.tip" class="mt-3 flex gap-2 rounded-md bg-amber-50 px-3 py-2 text-sm leading-snug text-amber-900">
                                <Lightbulb class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                <span>{{ step.tip }}</span>
                            </p>
                        </div>
                    </li>
                </ol>
            </section>

            <!-- FAQ -->
            <section class="mb-6 print:break-before-page" aria-labelledby="faq-title">
                <h2 id="faq-title" class="mb-3 text-lg font-semibold text-gray-900">Pertanyaan umum</h2>
                <div class="divide-y rounded-lg border bg-white">
                    <details v-for="item in GUIDE_FAQ" :key="item.q" class="group px-4 py-3 print:open" open>
                        <summary class="cursor-pointer list-none text-sm font-medium text-gray-900 marker:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                            {{ item.q }}
                        </summary>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ item.a }}</p>
                    </details>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
