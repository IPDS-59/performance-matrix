import { defineStore } from 'pinia';
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import type { KipSyncRun } from '@/types';

/**
 * Drives the chunked (no-queue) kipApp syncs: each request handles one unit
 * (a team for structure, a batch of employees for activities), so the loop here
 * keeps re-posting until the server marks the run done. The authoritative run
 * state lives in the Inertia page props, updated after every step; this store
 * only owns the loop/progress flags.
 */
export const useKipSyncStore = defineStore('kipSync', () => {
    const structureSyncing = ref(false);
    const activitySyncing = ref(false);

    function runProp(key: 'structureRun' | 'activityRun'): KipSyncRun | null {
        return (usePage().props[key] as KipSyncRun | null) ?? null;
    }

    function loop(routeName: string, propKey: 'structureRun' | 'activityRun', flag: typeof structureSyncing) {
        router.post(
            route(routeName),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    if (runProp(propKey)?.status === 'running') {
                        loop(routeName, propKey, flag);
                    } else {
                        flag.value = false;
                    }
                },
                onError: () => {
                    flag.value = false;
                },
            },
        );
    }

    function startStructureSync() {
        if (structureSyncing.value) return;
        structureSyncing.value = true;
        loop('kip-integration.sync-structure', 'structureRun', structureSyncing);
    }

    function startActivitySync() {
        if (activitySyncing.value) return;
        activitySyncing.value = true;
        loop('kip-integration.sync', 'activityRun', activitySyncing);
    }

    return { structureSyncing, activitySyncing, startStructureSync, startActivitySync };
});
