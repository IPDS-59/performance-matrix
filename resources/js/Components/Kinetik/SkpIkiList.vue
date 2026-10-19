<script setup lang="ts">
import { ref } from 'vue';
import { Trash2 } from 'lucide-vue-next';
import type { SkpRkWithIki } from '@/types';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';

/** The RK of a SKP that is Sedang dibuat with their IKI. The member adds what is missing, like kipApp's IKI form. */
defineProps<{ rks: SkpRkWithIki[]; canEdit: boolean; processing: boolean }>();
const emit = defineEmits<{ add: [rkId: string, text: string, done: () => void]; remove: [ikiId: string] }>();

// kipApp refuses an IKI shorter than 10 characters.
const MIN = 10;
const open = ref<string | null>(null);
const text = ref('');

function start(id: string) {
    open.value = open.value === id ? null : id;
    text.value = '';
}

function save(id: string) {
    emit('add', id, text.value.trim(), () => { open.value = null; text.value = ''; });
}
</script>

<template>
    <details class="mt-3 rounded-md border border-gray-200 bg-gray-50/60" :open="rks.some((r) => !r.iki.length)">
        <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-gray-800">RK dan IKI ({{ rks.length }} RK)</summary>
        <ul class="divide-y divide-gray-200 border-t border-gray-200">
            <li v-for="rk in rks" :key="rk.id" class="space-y-1.5 px-3 py-2.5">
                <p class="text-sm font-medium text-gray-900">{{ rk.name }}</p>
                <p v-if="!rk.iki.length" class="text-xs text-amber-800">Belum punya IKI. SKP baru bisa dikirim setelah tiap RK punya IKI.</p>
                <ul v-else class="space-y-1">
                    <li v-for="i in rk.iki" :key="i.id" class="flex items-start justify-between gap-2 text-sm text-gray-700">
                        <span>{{ i.text }}</span>
                        <button v-if="canEdit && i.own" type="button" class="shrink-0 text-gray-400 hover:text-red-600" :disabled="processing" :aria-label="`Hapus IKI: ${i.text}`" @click="emit('remove', i.id)"><Trash2 class="h-4 w-4" aria-hidden="true" /></button>
                    </li>
                </ul>
                <template v-if="canEdit">
                    <Button v-if="open !== rk.id" type="button" size="sm" variant="outline" @click="start(rk.id)">Tambah IKI</Button>
                    <form v-else class="space-y-2" @submit.prevent="save(rk.id)">
                        <Textarea v-model="text" rows="2" :aria-label="`IKI untuk ${rk.name}`" placeholder="Tulis IKI beserta targetnya, misalnya: Persentase entri data selesai tepat waktu 95 persen" />
                        <p class="text-xs" :class="text.trim().length >= MIN ? 'text-gray-500' : 'text-gray-500'">Minimal {{ MIN }} huruf. Target ikut ditulis di teksnya, seperti di kipApp.</p>
                        <div class="flex gap-2">
                            <Button type="submit" size="sm" :disabled="processing || text.trim().length < MIN">Simpan IKI</Button>
                            <Button type="button" size="sm" variant="ghost" @click="open = null">Batal</Button>
                        </div>
                    </form>
                </template>
            </li>
        </ul>
    </details>
</template>
