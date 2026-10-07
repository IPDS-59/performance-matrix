<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { RecapSection, RecapSegment, RecapSummaryText } from '@/types';
import { draftProjectSummary, projectKey, summaryProjects } from '@/composables/useRecapSummary';

/**
 * "Ringkasan Bulanan/Triwulanan": one entry per Projek, written by the PJ,
 * drafted from the weeks (or months) of the period, as in the mockup.
 */
const props = defineProps<{
    title: string;
    /** "4 minggu" / "3 bulan", for the pre-fill button. */
    sourceLabel: string;
    segments: RecapSegment[];
    sections: RecapSection[];
    summaries: Record<string, RecapSummaryText>;
    canManage: boolean;
    /** team_id and the period fields for team-recap.summary. */
    payload: Record<string, string | number | null>;
}>();

type Draft = { body: string; obstacle: string; solution: string; follow_up_plan: string };
const FIELDS = ['body', 'obstacle', 'solution', 'follow_up_plan'] as const;

const projects = computed(() => summaryProjects(props.segments, props.sections));
const drafts = ref<Record<string, Draft>>({});
const saving = ref<string | null>(null);

const draftOf = (s: Partial<RecapSummaryText> | undefined): Draft => ({ body: s?.body ?? '', obstacle: s?.obstacle ?? '', solution: s?.solution ?? '', follow_up_plan: s?.follow_up_plan ?? '' });
const isSaved = (key: string) => !!props.summaries[key];
const unchanged = (key: string) => FIELDS.every(f => drafts.value[key]?.[f] === (props.summaries[key]?.[f] ?? ''));

watch(() => props.summaries, (s) => {
    drafts.value = Object.fromEntries(projects.value.map(p => [projectKey(p.id), draftOf(s[projectKey(p.id)])]));
}, { immediate: true });

/** Fill only what is still empty, so what the PJ wrote stays. */
function prefill() {
    for (const p of projects.value) {
        const key = projectKey(p.id);
        const draft = draftProjectSummary(p.id, props.sections);
        for (const f of FIELDS) if (!drafts.value[key][f].trim()) drafts.value[key][f] = draft[f];
    }
}

function save(projectId: number | null, clear = false) {
    const key = projectKey(projectId);
    saving.value = key;
    router.post(route('team-recap.summary'), { ...props.payload, project_id: projectId, ...(clear ? { body: '', obstacle: '', solution: '', follow_up_plan: '' } : drafts.value[key]) }, {
        preserveScroll: true,
        onFinish: () => (saving.value = null),
    });
}

const field = 'w-full rounded border bg-white px-2 py-1.5 text-xs placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';
const label = 'mb-1 block text-[10px] font-medium text-gray-500';
</script>

<template>
    <section class="mb-6" :aria-label="title">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-gray-900">{{ title }}</h2>
                <p class="text-xs text-gray-400">Satu entri per projek, untuk rapat dengan pimpinan.</p>
            </div>
            <button
                v-if="canManage && projects.length"
                type="button"
                class="rounded border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 transition-colors hover:bg-indigo-100 focus-visible:ring-2 focus-visible:ring-primary"
                title="Isi bagian yang masih kosong dari rekap di atas"
                @click="prefill"
            >
                Pre-fill dari {{ sourceLabel }} ↑
            </button>
        </div>

        <p v-if="!projects.length" class="rounded-md border border-dashed bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">Belum ada rekap pada periode ini.</p>

        <div class="space-y-4">
            <article v-for="p in projects" :key="projectKey(p.id)" class="overflow-hidden rounded-md border bg-white">
                <header class="flex items-start justify-between gap-3 border-b bg-amber-50/40 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Projek</p>
                        <h3 class="text-sm font-bold text-gray-900">{{ p.name }}</h3>
                        <p v-if="p.leaderRk" class="mt-0.5 text-[11px] text-indigo-500">RK Ketua: {{ p.leaderRk }}</p>
                    </div>
                    <button v-if="canManage && isSaved(projectKey(p.id))" type="button" class="text-[11px] text-gray-400 hover:text-red-600 focus-visible:ring-2 focus-visible:ring-primary" :disabled="saving === projectKey(p.id)" @click="save(p.id, true)">Hapus</button>
                </header>

                <div v-if="canManage" class="space-y-3 px-4 py-3">
                    <label class="block text-xs font-medium text-gray-600">Uraian Kegiatan
                        <textarea v-model="drafts[projectKey(p.id)].body" rows="4" :class="[field, 'mt-1 resize-y border-yellow-300 text-sm']" placeholder="Ringkasan capaian projek ini selama periode" />
                    </label>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label :class="label">Permasalahan<textarea v-model="drafts[projectKey(p.id)].obstacle" rows="3" :class="[field, 'border-gray-200']" placeholder="—" /></label>
                        <label :class="label">Solusi<textarea v-model="drafts[projectKey(p.id)].solution" rows="3" :class="[field, 'border-gray-200']" placeholder="—" /></label>
                        <label :class="label">RTL<textarea v-model="drafts[projectKey(p.id)].follow_up_plan" rows="3" :class="[field, 'border-gray-200']" placeholder="—" /></label>
                    </div>
                    <div class="flex justify-end">
                        <button
                            type="button"
                            class="rounded bg-gray-800 px-4 py-1.5 text-xs font-medium text-white transition-colors hover:bg-gray-900 focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="saving === projectKey(p.id) || unchanged(projectKey(p.id))"
                            @click="save(p.id)"
                        >
                            {{ saving === projectKey(p.id) ? 'Menyimpan…' : 'Simpan' }}
                        </button>
                    </div>
                </div>

                <div v-else class="space-y-3 px-4 py-3 text-sm">
                    <div><p class="text-xs font-medium text-gray-500">Uraian Kegiatan</p><p class="whitespace-pre-line text-gray-800">{{ summaries[projectKey(p.id)]?.body || '—' }}</p></div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div><p class="text-xs font-medium text-gray-500">Permasalahan</p><p class="whitespace-pre-line text-gray-800">{{ summaries[projectKey(p.id)]?.obstacle || '—' }}</p></div>
                        <div><p class="text-xs font-medium text-gray-500">Solusi</p><p class="whitespace-pre-line text-gray-800">{{ summaries[projectKey(p.id)]?.solution || '—' }}</p></div>
                        <div><p class="text-xs font-medium text-gray-500">RTL</p><p class="whitespace-pre-line text-gray-800">{{ summaries[projectKey(p.id)]?.follow_up_plan || '—' }}</p></div>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
