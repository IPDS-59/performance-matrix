<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';

interface NotificationItem {
    id: string;
    type: string;
    message: string;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    notifications: Paginated<NotificationItem>;
    unread_count: number;
}>();

const items = ref<NotificationItem[]>(props.notifications.data);

function csrfToken(): string {
    return (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? '';
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

async function markRead(n: NotificationItem) {
    if (!n.read_at) {
        await fetch(route('notifications.read', n.id), {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        });
        n.read_at = new Date().toISOString();
    }
    const url = n.data?.url as string | undefined;
    if (url) router.visit(url);
}

async function markAllRead() {
    await fetch(route('notifications.read-all'), {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
    items.value = items.value.map(n => ({ ...n, read_at: n.read_at ?? new Date().toISOString() }));
}

async function deleteOne(id: string) {
    await fetch(route('notifications.destroy', id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
    items.value = items.value.filter(n => n.id !== id);
}

const showDeleteAllDialog = ref(false);

async function deleteAll() {
    await Promise.all(items.value.map(n => fetch(route('notifications.destroy', n.id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    })));
    items.value = [];
    showDeleteAllDialog.value = false;
}
</script>

<template>
    <Head title="Notifikasi" />
    <AppLayout>
        <template #title>Notifikasi</template>

        <div class="mx-auto max-w-2xl">
            <!-- Header actions -->
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    <template v-if="items.length">{{ notifications.total }} notifikasi</template>
                    <template v-else>Tidak ada notifikasi</template>
                </p>
                <div class="flex gap-3">
                    <button
                        v-if="items.some(n => !n.read_at)"
                        type="button"
                        class="text-xs text-primary hover:underline"
                        @click="markAllRead"
                    >
                        Tandai semua dibaca
                    </button>
                    <button
                        v-if="items.length"
                        type="button"
                        class="text-xs text-red-500 hover:underline"
                        @click="showDeleteAllDialog = true"
                    >
                        Hapus semua
                    </button>
                </div>
            </div>

            <!-- Notification list -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div v-if="!items.length" class="px-6 py-16 text-center text-sm text-gray-400">
                    Tidak ada notifikasi
                </div>
                <div v-else class="divide-y divide-gray-100">
                    <div
                        v-for="n in items"
                        :key="n.id"
                        :class="['group flex items-start gap-3 px-5 py-4 transition-colors', !n.read_at ? 'bg-blue-50' : 'hover:bg-gray-50']"
                    >
                        <!-- Unread dot -->
                        <div class="mt-1.5 shrink-0">
                            <div :class="['h-2 w-2 rounded-full', !n.read_at ? 'bg-blue-500' : 'bg-transparent']" />
                        </div>

                        <!-- Content -->
                        <div
                            :class="['flex-1 min-w-0', n.data?.url ? 'cursor-pointer' : '']"
                            @click="markRead(n)"
                        >
                            <p :class="['text-sm leading-relaxed', !n.read_at ? 'font-medium text-gray-800' : 'text-gray-600']">
                                {{ n.message }}
                            </p>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="text-xs text-gray-400">{{ formatDate(n.created_at) }}</span>
                                <span v-if="n.data?.url" class="text-xs text-primary">Lihat →</span>
                            </div>
                        </div>

                        <!-- Delete button -->
                        <button
                            type="button"
                            class="shrink-0 rounded p-1 text-gray-300 opacity-0 transition-opacity hover:bg-gray-100 hover:text-gray-500 group-hover:opacity-100"
                            title="Hapus"
                            @click="deleteOne(n.id)"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="notifications.last_page > 1" class="mt-4 flex items-center justify-center gap-1">
                <template v-for="link in notifications.links" :key="link.label">
                    <button
                        v-if="link.url"
                        type="button"
                        :class="['rounded px-3 py-1.5 text-xs transition-colors', link.active ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100']"
                        @click="router.get(link.url)"
                        v-html="link.label"
                    />
                    <span v-else class="px-2 py-1.5 text-xs text-gray-300" v-html="link.label" />
                </template>
            </div>
        </div>
    </AppLayout>

    <AlertDialog :open="showDeleteAllDialog" @update:open="(v) => { if (!v) showDeleteAllDialog = false }">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Hapus semua notifikasi?</AlertDialogTitle>
                <AlertDialogDescription>
                    Semua notifikasi akan dihapus permanen dan tidak dapat dipulihkan.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Batal</AlertDialogCancel>
                <AlertDialogAction class="bg-red-600 hover:bg-red-700 focus:ring-red-600" @click="deleteAll">
                    Hapus Semua
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
