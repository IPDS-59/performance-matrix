<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { RecapLockState, RecapPeriodType } from '@/types';
import { Button } from '@/Components/ui/button';
import { Lock, LockOpen } from 'lucide-vue-next';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps<{
    teamId: number | null;
    lock: RecapLockState | null;
    canLock: boolean;
    period: { period_type: RecapPeriodType; period_year: number; period_month?: number; period_quarter?: number; week_start?: string };
}>();

const { formatDate } = useDateFormat();
const saving = ref(false);

function toggle() {
    saving.value = true;
    router.post(route('team-recap.lock'), {
        team_id: props.teamId,
        ...props.period,
        locked: !props.lock,
    }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
}
</script>

<template>
    <div
        v-if="teamId && (lock || canLock)"
        id="kunci-rekap"
        :class="['mb-4 flex scroll-mt-4 flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-2.5 text-sm', lock ? 'border-amber-200 bg-amber-50 text-amber-800' : 'bg-white text-gray-600']"
    >
        <span class="inline-flex items-center gap-2">
            <component :is="lock ? Lock : LockOpen" class="h-4 w-4" />
            <template v-if="lock">
                Rekap dikunci<template v-if="lock.locked_by"> oleh {{ lock.locked_by }}</template><template v-if="lock.locked_at"> pada {{ formatDate(lock.locked_at) }}</template>. Rekap tidak dapat diubah.
            </template>
            <template v-else>Kunci rekap sebelum rapat agar isinya tidak berubah.</template>
        </span>
        <Button v-if="canLock" size="sm" :variant="lock ? 'outline' : 'default'" :disabled="saving" @click="toggle">
            {{ lock ? 'Buka kunci' : 'Kunci rekap' }}
        </Button>
    </div>
</template>
