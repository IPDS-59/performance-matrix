<script setup lang="ts">
import FormDialog from '@/Components/FormDialog.vue';
import InputError from '@/Components/InputError.vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm({ title: '', description: '', page_url: '' });

watch(() => props.open, (open) => {
    if (!open) return;
    form.reset();
    form.clearErrors();
    form.page_url = window.location.href;
});

function send() {
    form.post(route('bug-reports.store'), { preserveScroll: true, onSuccess: () => emit('update:open', false) });
}
</script>

<template>
    <FormDialog :open="open" title="Laporkan masalah" description="Ceritakan apa yang terjadi. Laporan ini sampai ke admin beserta halaman yang sedang Anda buka." submit-label="Kirim laporan" :processing="form.processing" @update:open="(v: boolean) => emit('update:open', v)" @submit="send">
        <div class="space-y-1.5">
            <Label for="bug-title">Ringkasan masalah</Label>
            <Input id="bug-title" v-model="form.title" placeholder="Misalnya: projek tidak muncul setelah memilih RK" />
            <InputError :message="form.errors.title" />
        </div>
        <div class="space-y-1.5">
            <Label for="bug-desc">Apa yang Anda lakukan dan apa yang terjadi</Label>
            <Textarea id="bug-desc" v-model="form.description" rows="5" placeholder="Langkah yang Anda lakukan, hasil yang Anda harapkan, dan pesan yang muncul." />
            <InputError :message="form.errors.description" />
        </div>
        <p class="break-all text-xs text-gray-600">Halaman: {{ form.page_url }}</p>
    </FormDialog>
</template>
