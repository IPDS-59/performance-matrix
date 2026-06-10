<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import InputError from '@/Components/InputError.vue';
import { CheckCircle2, AlertTriangle, RefreshCw } from 'lucide-vue-next';
import type { KipCredential, KipIntegrationStats, KipSyncRun } from '@/types';
import { useKipSyncStore } from '@/stores/kipSync';

const props = defineProps<{
    credential: KipCredential | null;
    stats: KipIntegrationStats;
    structureRun: KipSyncRun | null;
    activityRun: KipSyncRun | null;
}>();

const kipSync = useKipSyncStore();

function pct(run: KipSyncRun | null): number {
    if (!run || !run.total) return 0;
    return Math.min(100, Math.round((run.processed / run.total) * 100));
}

function formatDateTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

const expiryLabel = computed(() => {
    if (!props.credential?.expires_at) return null;
    const ms = new Date(props.credential.expires_at).getTime() - Date.now();
    if (ms <= 0) return 'Kedaluwarsa';
    const hours = Math.floor(ms / 3_600_000);
    const mins = Math.floor((ms % 3_600_000) / 60_000);
    return `Berlaku ${hours} jam ${mins} menit lagi`;
});

// ── Forms ──────────────────────────────────────────────────────────────────

const tokenForm = useForm({ token: '' });

function saveToken() {
    tokenForm.post(route('kip-integration.token'), {
        preserveScroll: true,
        onSuccess: () => tokenForm.reset('token'),
    });
}

// ── Sync progress (chunked, no queue) — logic lives in the kipSync store ─────

const structurePct = computed(() => pct(props.structureRun));
const activityPct = computed(() => pct(props.activityRun));

// Resume any in-progress run when the page (re)loads.
onMounted(() => {
    if (props.structureRun?.status === 'running') kipSync.startStructureSync();
    if (props.activityRun?.status === 'running') kipSync.startActivitySync();
});
</script>

<template>
    <Head title="Integrasi kipApp" />
    <AppLayout>
        <template #title>Integrasi kipApp</template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left column: token status + paste -->
            <div class="space-y-6 lg:col-span-1">
                <!-- Token status -->
                <div class="rounded-lg border bg-white p-6">
                    <h2 class="mb-1 text-base font-semibold text-gray-900">Status Token</h2>
                    <p class="mb-4 text-sm text-gray-500">
                        Token admin kipApp dipakai untuk menarik data seluruh pegawai. Berlaku ±24 jam.
                    </p>

                    <div v-if="!credential" class="flex items-center gap-2 rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                        <AlertTriangle class="h-4 w-4 shrink-0" />
                        Belum ada token tersimpan. Tempelkan token di bawah.
                    </div>

                    <div v-else class="space-y-3">
                        <div class="flex items-center gap-2 text-sm" :class="credential.is_expired ? 'text-red-600' : credential.is_expiring_soon ? 'text-amber-600' : 'text-green-700'">
                            <component :is="credential.is_expired || credential.is_expiring_soon ? AlertTriangle : CheckCircle2" class="h-4 w-4 shrink-0" />
                            <span class="font-medium">{{ credential.is_expired ? 'Token kedaluwarsa' : 'Token aktif' }}</span>
                        </div>
                        <p v-if="expiryLabel" class="text-xs text-gray-500">{{ expiryLabel }}</p>
                        <div v-if="credential.is_expiring_soon && !credential.is_expired" class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            Token akan segera kedaluwarsa — perbarui agar sinkronisasi tidak gagal.
                        </div>
                        <dl class="space-y-2 border-t pt-3 text-sm">
                            <div>
                                <dt class="text-xs text-gray-400">Akun (NIP Lama)</dt>
                                <dd class="font-mono text-gray-800">{{ credential.account_nip ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-400">Email</dt>
                                <dd class="truncate text-gray-800">{{ credential.account_name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-400">Kedaluwarsa</dt>
                                <dd class="text-gray-800">{{ formatDateTime(credential.expires_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-400">Diperbarui</dt>
                                <dd class="text-gray-800">{{ formatDateTime(credential.updated_at) }}<span v-if="credential.updated_by"> · {{ credential.updated_by }}</span></dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Paste token -->
                <div class="rounded-lg border bg-white p-6">
                    <h2 class="mb-1 text-base font-semibold text-gray-900">{{ credential ? 'Perbarui Token' : 'Simpan Token' }}</h2>
                    <p class="mb-4 text-sm text-gray-500">
                        Login kipApp sebagai admin → DevTools → Network → salin header
                        <code class="rounded bg-gray-100 px-1">x-auth</code>.
                    </p>
                    <form class="space-y-3" @submit.prevent="saveToken">
                        <div>
                            <Label for="kip-token">Token x-auth</Label>
                            <textarea
                                id="kip-token"
                                v-model="tokenForm.token"
                                rows="4"
                                class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                placeholder="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
                            />
                            <InputError :message="tokenForm.errors.token" />
                        </div>
                        <div class="flex justify-end">
                            <Button type="submit" :disabled="tokenForm.processing || !tokenForm.token">Simpan Token</Button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right column: sync actions -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Centralized activity sync -->
                <div class="rounded-lg border bg-white p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="mb-1 text-base font-semibold text-gray-900">Sinkronisasi Kegiatan</h2>
                            <p class="text-sm text-gray-500">Tarik kegiatan harian kipApp untuk semua pegawai aktif yang memiliki NIP Lama.</p>
                        </div>
                        <Button variant="outline" :disabled="kipSync.activitySyncing" @click="kipSync.startActivitySync()">
                            <RefreshCw :class="['mr-1 h-4 w-4', kipSync.activitySyncing ? 'animate-spin' : '']" />
                            {{ kipSync.activitySyncing ? 'Menyinkronkan…' : 'Sinkronkan' }}
                        </Button>
                    </div>

                    <!-- Progress -->
                    <div v-if="kipSync.activitySyncing || activityRun?.status === 'running'" class="mt-4">
                        <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                            <span>Menyinkronkan pegawai… {{ activityRun?.processed ?? 0 }} / {{ activityRun?.total ?? 0 }}</span>
                            <span class="font-medium text-gray-700">{{ activityPct }}%</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-blue-600 transition-all duration-300" :style="{ width: activityPct + '%' }" />
                        </div>
                    </div>

                    <div v-else-if="activityRun?.status === 'failed'" class="mt-4 flex items-start gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                        <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        Sinkronisasi gagal: {{ activityRun.message }}
                    </div>

                    <div v-else-if="activityRun?.status === 'completed'" class="mt-4 flex items-start gap-2 text-xs text-green-700">
                        <CheckCircle2 class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        <span>Selesai: {{ activityRun.summary.activities ?? 0 }} kegiatan diperbarui untuk {{ activityRun.total }} pegawai.</span>
                    </div>

                    <dl class="mt-5 grid grid-cols-3 gap-4 text-sm">
                        <div class="rounded-md bg-gray-50 p-3">
                            <dt class="text-xs text-gray-400">Pegawai (punya NIP)</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">{{ stats.employees_with_nip }} <span class="text-sm font-normal text-gray-400">/ {{ stats.employees_total }}</span></dd>
                        </div>
                        <div class="rounded-md bg-gray-50 p-3">
                            <dt class="text-xs text-gray-400">Kegiatan tersinkron</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">{{ stats.activities_synced }}</dd>
                        </div>
                        <div class="rounded-md bg-gray-50 p-3">
                            <dt class="text-xs text-gray-400">Terakhir ditarik</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-700">{{ formatDateTime(stats.last_fetched_at) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Structure sync -->
                <div class="rounded-lg border bg-white p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="mb-1 text-base font-semibold text-gray-900">Sinkronisasi Struktur</h2>
                            <p class="text-sm text-gray-500">Tarik Tim, Projek, dan keanggotaan dari kipApp. Pegawai dicocokkan via NIP Lama.</p>
                        </div>
                        <Button variant="outline" :disabled="kipSync.structureSyncing" @click="kipSync.startStructureSync()">
                            <RefreshCw :class="['mr-1 h-4 w-4', kipSync.structureSyncing ? 'animate-spin' : '']" />
                            {{ kipSync.structureSyncing ? 'Menyinkronkan…' : 'Sinkronkan' }}
                        </Button>
                    </div>

                    <!-- Progress -->
                    <div v-if="kipSync.structureSyncing || structureRun?.status === 'running'" class="mt-4">
                        <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                            <span>Menyinkronkan tim… {{ structureRun?.processed ?? 0 }} / {{ structureRun?.total ?? 0 }}</span>
                            <span class="font-medium text-gray-700">{{ structurePct }}%</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-blue-600 transition-all duration-300" :style="{ width: structurePct + '%' }" />
                        </div>
                    </div>

                    <div v-else-if="structureRun?.status === 'failed'" class="mt-4 flex items-start gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                        <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        Sinkronisasi gagal: {{ structureRun.message }}
                    </div>

                    <div v-else-if="structureRun?.status === 'completed'" class="mt-4 flex items-start gap-2 text-xs text-green-700">
                        <CheckCircle2 class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        <span>Selesai: {{ structureRun.summary.teams ?? 0 }} tim, {{ structureRun.summary.projects ?? 0 }} projek,
                            {{ structureRun.summary.employees_created ?? 0 }} pegawai baru, {{ structureRun.summary.users_created ?? 0 }} akun login.</span>
                    </div>

                    <dl class="mt-5 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                        <div class="rounded-md bg-gray-50 p-3">
                            <dt class="text-xs text-gray-400">Tim tersinkron</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">{{ stats.teams_synced }}</dd>
                        </div>
                        <div class="rounded-md bg-gray-50 p-3">
                            <dt class="text-xs text-gray-400">Projek tersinkron</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">{{ stats.projects_synced }}</dd>
                        </div>
                        <div class="rounded-md bg-gray-50 p-3">
                            <dt class="text-xs text-gray-400">Total pegawai</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">{{ stats.employees_total }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
