<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { MessageSquarePlus, Pencil } from 'lucide-vue-next';

const props = defineProps<{
    note: string | null;
    canWrite: boolean;
    /** Screen-reader context, e.g. the team or project name. */
    subject: string;
    /** team_id, project_id and the period fields for the store endpoint. */
    payload: Record<string, string | number | null>;
}>();

const editing = ref(false);
const draft = ref('');
const saving = ref(false);

function start() {
    draft.value = props.note ?? '';
    editing.value = true;
}

function save() {
    saving.value = true;
    router.post(route('team-recap.leadership-note'), { ...props.payload, body: draft.value }, {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
        onFinish: () => (saving.value = false),
    });
}
</script>

<template>
    <form v-if="editing" class="space-y-1.5" @submit.prevent="save">
        <Textarea
            v-model="draft"
            rows="3"
            maxlength="2000"
            class="min-w-[14rem] text-sm"
            :aria-label="`Catatan pimpinan untuk ${subject}`"
            autofocus
            @keydown.esc="editing = false"
        />
        <div class="flex gap-1.5">
            <Button type="submit" size="sm" class="h-7 px-2.5 text-xs" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
            <Button type="button" size="sm" variant="ghost" class="h-7 px-2.5 text-xs" @click="editing = false">Batal</Button>
        </div>
    </form>

    <div v-else-if="note" class="group flex items-start gap-1.5">
        <p class="whitespace-pre-line text-sm leading-snug text-gray-800">{{ note }}</p>
        <button
            v-if="canWrite"
            type="button"
            class="shrink-0 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            :aria-label="`Ubah catatan pimpinan untuk ${subject}`"
            @click="start"
        >
            <Pencil class="h-3.5 w-3.5" />
        </button>
    </div>

    <button
        v-else-if="canWrite"
        type="button"
        class="inline-flex items-center gap-1 rounded text-xs font-medium text-gray-500 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
        :aria-label="`Tulis catatan pimpinan untuk ${subject}`"
        @click="start"
    >
        <MessageSquarePlus class="h-3.5 w-3.5" aria-hidden="true" /> Tulis catatan
    </button>

    <span v-else class="text-xs text-gray-400">—</span>
</template>
