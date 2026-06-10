<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import InputError from '@/Components/InputError.vue';
import { CheckCircle2, AlertTriangle, RefreshCw } from 'lucide-vue-next';

interface Credential {
    account_nip: string | null;
    account_name: string | null;
    expires_at: string | null;
    is_expired: boolean;
    is_expiring_soon: boolean;
    updated_at: string | null;
    updated_by: string | null;
}

const props = defineProps<{
    credential: Credential | null;
    stats: {
        employees_with_nip: number;
        employees_total: number;
        activities_synced: number;
        last_fetched_at: string | null;
        teams_synced: number;
        projects_synced: number;
    };
}>();

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

const syncForm = useForm({});

function syncAll() {
    syncForm.post(route('kip-integration.sync'), { preserveScroll: true });
}

const structureForm = useForm({});

function syncStructure() {
    structureForm.post(route('kip-integration.sync-structure'), { preserveScroll: true });
}
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
                        <Button variant="outline" :disabled="syncForm.processing" @click="syncAll">
                            <RefreshCw :class="['mr-1 h-4 w-4', syncForm.processing ? 'animate-spin' : '']" />
                            {{ syncForm.processing ? 'Menyinkronkan…' : 'Sinkronkan' }}
                        </Button>
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
                        <Button variant="outline" :disabled="structureForm.processing" @click="syncStructure">
                            <RefreshCw :class="['mr-1 h-4 w-4', structureForm.processing ? 'animate-spin' : '']" />
                            {{ structureForm.processing ? 'Menyinkronkan…' : 'Sinkronkan' }}
                        </Button>
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
                    </dl>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
