import { defineStore } from 'pinia';
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import type { KipSyncRun } from '@/types';

/**
 * Drives the chunked (no-queue) kipApp structure sync: each request syncs one
 * team, so the loop here keeps re-posting until the server marks the run done.
 * The authoritative run state lives in the Inertia page props (`structureRun`),
 * updated after every step; this store only owns the loop/progress flag.
 */
export const useKipSyncStore = defineStore('kipSync', () => {
    const structureSyncing = ref(false);

    function structureRun(): KipSyncRun | null {
        return (usePage().props.structureRun as KipSyncRun | null) ?? null;
    }

    function stepStructure() {
        router.post(
            route('kip-integration.sync-structure'),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    if (structureRun()?.status === 'running') {
                        stepStructure();
                    } else {
                        structureSyncing.value = false;
                    }
                },
                onError: () => {
                    structureSyncing.value = false;
                },
            },
        );
    }

    function startStructureSync() {
        if (structureSyncing.value) return;
        structureSyncing.value = true;
        stepStructure();
    }

    return { structureSyncing, startStructureSync };
});
