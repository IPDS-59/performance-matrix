<script setup lang="ts">
import FormDialog from '@/Components/FormDialog.vue';
import KipRkKetuaPicker from '@/Components/Kinetik/KipRkKetuaPicker.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{ projectId: number | null; teamId: number | null }>();
const open = defineModel<boolean>('open', { required: true });

const rkKetuaId = ref<string | null>(null);
const processing = ref(false);
watch(open, (v) => v && (rkKetuaId.value = null));

function submit() {
    if (props.projectId === null || rkKetuaId.value === null) return;
    processing.value = true;
    router.post(route('projects.push', props.projectId), { rk_ketua_id: rkKetuaId.value }, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <FormDialog v-model:open="open" title="Kirim proyek ke kipApp" description="Proyek dibuat di kipApp dengan akun Anda, lengkap dengan anggotanya." submit-label="Buat di kipApp" :processing="processing" @submit="submit">
        <KipRkKetuaPicker v-model="rkKetuaId" :team-id="teamId" />
    </FormDialog>
</template>
