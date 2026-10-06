<script setup lang="ts">
export type RecapTextForm = { uraian: string; obstacle: string; solution: string; follow_up_plan: string };

/** The PJ's parafrase plus Permasalahan, Solusi and RTL of one recap row or merged group. */
defineProps<{
    form: RecapTextForm;
    /** Names the box for screen readers, e.g. the RK. */
    label: string;
    merged?: boolean;
}>();

const field = 'w-full rounded-md border border-gray-200 bg-white px-2 py-1.5 text-sm placeholder:text-gray-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';
</script>

<template>
    <div>
        <textarea
            v-model="form.uraian"
            :rows="merged ? 2 : 1"
            :class="[field, 'resize-y', merged ? 'border-amber-300 bg-white' : 'border-yellow-300 bg-yellow-50/40']"
            :placeholder="merged ? 'Tulis parafrase gabungan…' : 'Parafrase PJ — biarkan kosong untuk gunakan uraian di atas'"
            :aria-label="`${merged ? 'Parafrase gabungan' : 'Parafrase PJ'} untuk ${label}`"
        />
        <div class="mt-2 grid gap-2 sm:grid-cols-3">
            <label class="text-xs text-gray-500">Permasalahan
                <textarea v-model="form.obstacle" rows="2" :class="[field, 'mt-0.5', form.obstacle ? 'border-orange-300' : '']" placeholder="—" />
            </label>
            <label class="text-xs text-gray-500">Solusi
                <textarea v-model="form.solution" rows="2" :class="[field, 'mt-0.5']" placeholder="—" />
            </label>
            <label class="text-xs text-gray-500">RTL
                <textarea v-model="form.follow_up_plan" rows="2" :class="[field, 'mt-0.5', form.follow_up_plan ? 'border-orange-300' : '']" placeholder="—" />
            </label>
        </div>
    </div>
</template>
