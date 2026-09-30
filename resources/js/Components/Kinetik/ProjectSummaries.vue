<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { RecapSection, RecapSegment } from '@/types';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { CopyPlus } from 'lucide-vue-next';
import { draftProjectSummary, projectKey, summaryProjects } from '@/composables/useRecapSummary';

/**
 * "Ringkasan Bulanan/Triwulanan": one narrative per Projek, written by the PJ,
 * drafted from the weeks (or months) of the period.
 */
const props = defineProps<{
    title: string;
    /** "4 minggu" / "3 bulan", for the pre-fill button. */
    sourceLabel: string;
    segments: RecapSegment[];
    sections: RecapSection[];
    summaries: Record<string, string>;
    canManage: boolean;
    /** team_id and the period fields for team-recap.summary. */
    payload: Record<string, string | number | null>;
}>();

const projects = computed(() => summaryProjects(props.segments, props.sections));
const drafts = ref<Record<string, string>>({});
const saving = ref<string | null>(null);

watch(() => props.summaries, (s) => {
    drafts.value = Object.fromEntries(projects.value.map(p => [projectKey(p.id), s[projectKey(p.id)] ?? '']));
}, { immediate: true });

function prefill() {
    for (const p of projects.value) {
        const key = projectKey(p.id);
        if (!drafts.value[key]?.trim()) drafts.value[key] = draftProjectSummary(p.id, props.sections);
    }
}

function save(projectId: number | null) {
    const key = projectKey(projectId);
    saving.value = key;
    router.post(route('team-recap.summary'), { ...props.payload, project_id: projectId, body: drafts.value[key] ?? '' }, {
        preserveScroll: true,
        onFinish: () => (saving.value = null),
    });
}
</script>

<template>
    <section class="mb-6 overflow-hidden rounded-md border bg-white" :aria-label="title">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b bg-gray-50 px-4 py-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-800">{{ title }}</h2>
                <p class="text-xs text-gray-500">Satu ringkasan per projek untuk rapat.</p>
            </div>
            <Button v-if="canManage && projects.length" size="sm" variant="outline" title="Isi ringkasan yang masih kosong dari rekap di atas" @click="prefill">
                <CopyPlus class="mr-1.5 h-4 w-4" /> Isi dari {{ sourceLabel }}
            </Button>
        </div>

        <p v-if="!projects.length" class="px-4 py-6 text-center text-sm text-gray-500">Belum ada rekap pada periode ini.</p>

        <div v-for="p in projects" :key="projectKey(p.id)" class="border-b px-4 py-3 last:border-b-0">
            <label :for="`summary-${projectKey(p.id)}`" class="text-sm font-medium text-gray-800">{{ p.name }}</label>
            <template v-if="canManage">
                <Textarea :id="`summary-${projectKey(p.id)}`" v-model="drafts[projectKey(p.id)]" :rows="3" class="mt-1 text-sm" placeholder="Ringkasan capaian, kendala dan tindak lanjut projek ini" />
                <div class="mt-1.5 flex justify-end">
                    <Button size="sm" :disabled="saving === projectKey(p.id) || drafts[projectKey(p.id)] === (summaries[projectKey(p.id)] ?? '')" @click="save(p.id)">
                        {{ saving === projectKey(p.id) ? 'Menyimpan…' : 'Simpan' }}
                    </Button>
                </div>
            </template>
            <p v-else class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ summaries[projectKey(p.id)] || '—' }}</p>
        </div>
    </section>
</template>
