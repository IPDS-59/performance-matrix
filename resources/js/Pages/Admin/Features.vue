<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { useDateFormat } from '@/composables/useDateFormat';
import type { FeatureFlagRow } from '@/types';

defineProps<{ flags: FeatureFlagRow[] }>();

const { formatDate } = useDateFormat();
const processing = ref(false);

type Ask = { title: string; description: string; run: () => void } | null;
const ask = ref<Ask>(null);
const askOpen = computed({ get: () => ask.value !== null, set: (v) => { if (!v) ask.value = null; } });

function send(method: 'put' | 'delete', flag: FeatureFlagRow, data: Record<string, unknown> = {}) {
    processing.value = true;
    router[method](route(method === 'put' ? 'admin.features.update' : 'admin.features.reset', flag.key), method === 'put' ? data : {}, { preserveScroll: true, onFinish: () => (processing.value = false) });
}

function toggle(flag: FeatureFlagRow) {
    const turnOn = !flag.enabled;
    // A flag that writes official kipApp data and was never run live asks first when it is turned on.
    if (turnOn && !flag.tested) {
        ask.value = {
            title: `Nyalakan "${flag.label}"`,
            description: 'Fitur ini menulis data resmi di kipApp dan belum pernah dicoba langsung. Pemakaian pertama sebaiknya didampingi dan hasilnya diperiksa di kipApp. Lanjutkan?',
            run: () => send('put', flag, { enabled: true }),
        };
        return;
    }
    send('put', flag, { enabled: turnOn });
}
</script>

<template>
    <Head title="Fitur" />
    <AppLayout>
        <template #title>Fitur</template>

        <div class="mx-auto max-w-3xl space-y-3">
            <p class="text-sm text-gray-600">Nyalakan atau matikan fitur yang bekerja dengan kipApp tanpa menyentuh server. Perubahan berlaku segera untuk semua pengguna.</p>

            <ul class="divide-y divide-gray-100 overflow-hidden rounded-lg border bg-white">
                <li v-for="flag in flags" :key="flag.key" class="flex items-start justify-between gap-4 px-4 py-4">
                    <div class="min-w-0 space-y-1">
                        <p class="text-sm font-semibold text-gray-900">
                            {{ flag.label }}
                            <span class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium" :class="flag.enabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'">{{ flag.enabled ? 'Menyala' : 'Mati' }}</span>
                            <span v-if="!flag.tested" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">Belum diuji langsung</span>
                        </p>
                        <p class="text-sm text-gray-600">{{ flag.description }}</p>
                        <p class="text-xs text-gray-500">
                            <template v-if="flag.overridden">Diatur dari halaman ini<template v-if="flag.changed_by"> oleh {{ flag.changed_by }}</template><template v-if="flag.changed_at">, {{ formatDate(flag.changed_at) }}</template>.
                                <button type="button" class="underline" :disabled="processing" @click="send('delete', flag)">Ikuti pengaturan server</button></template>
                            <template v-else>Mengikuti pengaturan server (.env).</template>
                        </p>
                    </div>
                    <Button size="sm" :variant="flag.enabled ? 'outline' : 'default'" :disabled="processing" @click="toggle(flag)">{{ flag.enabled ? 'Matikan' : 'Nyalakan' }}</Button>
                </li>
            </ul>
        </div>

        <ConfirmDialog v-model:open="askOpen" :title="ask?.title" :description="ask?.description" confirm-label="Nyalakan" @confirm="ask?.run()" />
    </AppLayout>
</template>
