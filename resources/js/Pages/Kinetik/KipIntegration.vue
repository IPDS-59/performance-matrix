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
</script>

<template>
    <Head title="Integrasi kipApp" />
    <AppLayout>
        <template #title>Integrasi kipApp</template>

        <div class="max-w-2xl space-y-6">
            <!-- Token status -->
            <div class="rounded-lg border bg-white p-6">
                <h2 class="mb-1 text-base font-semibold text-gray-900">Status Token</h2>
                <p class="mb-4 text-sm text-gray-500">
                    Token admin kipApp dipakai untuk menarik kegiatan harian seluruh pegawai. Token berlaku ±24 jam,
                    perbarui bila kedaluwarsa.
                </p>

                <div v-if="!credential" class="flex items-center gap-2 rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                    <AlertTriangle class="h-4 w-4 shrink-0" />
                    Belum ada token tersimpan. Tempelkan token kipApp di bawah.
                </div>

                <div v-else class="space-y-2">
                    <div class="flex items-center gap-2 text-sm" :class="credential.is_expired ? 'text-red-600' : 'text-green-700'">
                        <component :is="credential.is_expired ? AlertTriangle : CheckCircle2" class="h-4 w-4 shrink-0" />
                        <span class="font-medium">{{ credential.is_expired ? 'Token kedaluwarsa' : 'Token aktif' }}</span>
                        <span v-if="expiryLabel" class="text-gray-500">· {{ expiryLabel }}</span>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-2 pt-2 text-sm">
                        <div>
                            <dt class="text-xs text-gray-400">Akun (NIP Lama)</dt>
                            <dd class="font-mono text-gray-800">{{ credential.account_nip ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">Email</dt>
                            <dd class="text-gray-800">{{ credential.account_name ?? '—' }}</dd>
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
                    Login ke kipApp sebagai admin unit kerja, buka DevTools → Network, salin nilai header
                    <code class="rounded bg-gray-100 px-1">x-auth</code> (boleh dengan atau tanpa "Bearer ").
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

            <!-- Centralized sync -->
            <div class="rounded-lg border bg-white p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="mb-1 text-base font-semibold text-gray-900">Sinkronisasi Terpusat</h2>
                        <p class="text-sm text-gray-500">Tarik kegiatan harian kipApp untuk semua pegawai aktif yang memiliki NIP Lama.</p>
                    </div>
                    <Button variant="outline" :disabled="syncForm.processing" @click="syncAll">
                        <RefreshCw :class="['mr-1 h-4 w-4', syncForm.processing ? 'animate-spin' : '']" />
                        {{ syncForm.processing ? 'Menyinkronkan…' : 'Sinkronkan Semua' }}
                    </Button>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-gray-400">Pegawai (punya NIP)</dt>
                        <dd class="text-gray-800">{{ stats.employees_with_nip }} / {{ stats.employees_total }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400">Kegiatan tersinkron</dt>
                        <dd class="text-gray-800">{{ stats.activities_synced }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-xs text-gray-400">Terakhir ditarik</dt>
                        <dd class="text-gray-800">{{ formatDateTime(stats.last_fetched_at) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </AppLayout>
</template>
