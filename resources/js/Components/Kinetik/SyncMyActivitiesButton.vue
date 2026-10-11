<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

const syncing = ref(false);

function sync() {
    router.post(route('my-sync'), {}, {
        preserveScroll: true,
        onStart: () => (syncing.value = true),
        onFinish: () => (syncing.value = false),
    });
}
</script>

<template>
    <Button type="button" variant="outline" size="sm" :disabled="syncing" title="Tarik kegiatan dan RK terbaru Anda dari kipApp" @click="sync">
        <RefreshCw :class="['mr-1.5 h-4 w-4', syncing ? 'animate-spin' : '']" aria-hidden="true" />
        {{ syncing ? 'Menyinkronkan…' : 'Sinkronkan data saya' }}
    </Button>
</template>
