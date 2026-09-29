<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { CopyPlus } from 'lucide-vue-next';

const props = defineProps<{
    /** team_id and the period fields for team-recap.prefill. */
    payload: Record<string, string | number | null>;
    /** "mingguan" on the monthly page, "bulanan" on the quarterly page. */
    source: 'mingguan' | 'bulanan';
}>();

const emit = defineEmits<{ done: [] }>();

const running = ref(false);

function prefill() {
    running.value = true;
    router.post(route('team-recap.prefill'), props.payload, {
        preserveScroll: true,
        onSuccess: () => emit('done'),
        onFinish: () => (running.value = false),
    });
}
</script>

<template>
    <Button
        size="sm"
        variant="outline"
        :disabled="running"
        :title="`Salin Permasalahan, Solusi dan RTL dari rekap ${source} ke kolom yang masih kosong. Teks yang sudah ada tidak diubah.`"
        @click="prefill"
    >
        <CopyPlus class="mr-1.5 h-4 w-4" />
        {{ running ? 'Mengisi…' : `Isi dari ${source}` }}
    </Button>
</template>
