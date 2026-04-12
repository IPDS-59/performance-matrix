<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useSidebarStore } from '@/stores/sidebar';
import { Notivue, Notification, push } from 'notivue';

const page = usePage();
const sidebar = useSidebarStore();

const user = computed(() => page.props.auth.user as { name: string; email: string; role: string });
const isAdmin = computed(() => user.value.role === 'admin');
const isHead = computed(() => user.value.role === 'head');
const isStaff = computed(() => user.value.role === 'staff');

// ── Notifications ─────────────────────────────────────────────────────────
const unreadCount = ref(0);
const notifications = ref<Array<{ id: string; type: string; message: string; read_at: string | null; created_at: string }>>([]);
const showDropdown = ref(false);

async function fetchNotifications() {
    try {
        const res = await fetch(route('notifications.index'), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        notifications.value = data.notifications;
        unreadCount.value = data.unread_count;
    } catch {}
}

async function markAllRead() {
    await fetch(route('notifications.read-all'), { method: 'PATCH', headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? '' } });
    unreadCount.value = 0;
    notifications.value = notifications.value.map(n => ({ ...n, read_at: new Date().toISOString() }));
}

function toggleDropdown() {
    showDropdown.value = !showDropdown.value;
    if (showDropdown.value) fetchNotifications();
}

let pollInterval: ReturnType<typeof setInterval> | null = null;
let removeSuccessListener: (() => void) | null = null;

onMounted(() => {
    fetchNotifications();
    pollInterval = setInterval(fetchNotifications, 60_000);

    removeSuccessListener = router.on('success', (event: { detail: { page: { props: unknown } } }) => {
        const flash = (event.detail.page.props as Record<string, unknown>).flash as Record<string, string> | undefined;
        if (flash?.success) push.success(flash.success);
        if (flash?.error) push.error(flash.error);
    });
});

onUnmounted(() => {
    if (pollInterval !== null) clearInterval(pollInterval);
    removeSuccessListener?.();
});
</script>

<template>
    <div class="flex h-screen bg-gray-50">
        <!-- Sidebar -->
        <aside
            :class="sidebar.isOpen ? 'w-64' : 'w-16'"
            class="flex flex-col bg-[#1B4B8A] text-white transition-all duration-200 ease-in-out"
        >
            <!-- Logo area -->
            <div class="flex h-16 items-center justify-between px-4">
                <Link
                    v-if="sidebar.isOpen"
                    :href="route('dashboard')"
                    class="flex items-center gap-2 font-semibold text-sm leading-tight"
                >
                    <img
                        src="/images/bps-sulteng-logo.svg"
                        alt="BPS Sulteng"
                        class="h-8 w-8 rounded object-contain bg-white p-0.5"
                    />
                    <span class="truncate">Matriks Kinerja</span>
                </Link>
                <button
                    @click="sidebar.toggle()"
                    class="rounded p-1 hover:bg-white/20 transition-colors"
                    aria-label="Toggle sidebar"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </button>
            </div>

            <!-- Nav links -->
            <nav class="flex-1 space-y-1 px-2 py-4">
                <!-- Dashboard (all) -->
                <Link
                    :href="route('dashboard')"
                    :class="route().current('dashboard') ? 'bg-white/20' : 'hover:bg-white/10'"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10"/>
                    </svg>
                    <span v-if="sidebar.isOpen">Beranda</span>
                </Link>

                <!-- Matrix (admin + head) -->
                <Link
                    v-if="isAdmin || isHead"
                    :href="route('matrix')"
                    :class="route().current('matrix') ? 'bg-white/20' : 'hover:bg-white/10'"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    <span v-if="sidebar.isOpen">Matriks</span>
                </Link>

                <!-- Performance entry (staff + head) -->
                <Link
                    v-if="isStaff || isHead"
                    :href="route('performance.index')"
                    :class="route().current('performance.*') ? 'bg-white/20' : 'hover:bg-white/10'"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span v-if="sidebar.isOpen">Input Kinerja</span>
                </Link>

                <!-- Reports (head + admin) -->
                <template v-if="isAdmin || isHead">
                    <div v-if="sidebar.isOpen" class="mt-4 px-3 text-xs font-semibold text-white/50 uppercase tracking-wider">
                        Laporan
                    </div>
                    <Link
                        :href="route('laporan.pegawai')"
                        :class="route().current('laporan.*') ? 'bg-white/20' : 'hover:bg-white/10'"
                        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    >
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span v-if="sidebar.isOpen">Laporan Pegawai</span>
                    </Link>
                </template>

                <!-- Admin section -->
                <template v-if="isAdmin">
                    <div v-if="sidebar.isOpen" class="mt-4 px-3 text-xs font-semibold text-white/50 uppercase tracking-wider">
                        Manajemen Data
                    </div>
                    <Link
                        :href="route('teams.index')"
                        :class="route().current('teams.*') ? 'bg-white/20' : 'hover:bg-white/10'"
                        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    >
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                        </svg>
                        <span v-if="sidebar.isOpen">Tim Kerja</span>
                    </Link>
                    <Link
                        :href="route('employees.index')"
                        :class="route().current('employees.*') ? 'bg-white/20' : 'hover:bg-white/10'"
                        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    >
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span v-if="sidebar.isOpen">Pegawai</span>
                    </Link>
                    <Link
                        :href="route('projects.index')"
                        :class="route().current('projects.*') ? 'bg-white/20' : 'hover:bg-white/10'"
                        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    >
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <span v-if="sidebar.isOpen">Proyek</span>
                    </Link>
                </template>
            </nav>

            <!-- User footer -->
            <div class="border-t border-white/20 p-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/20 text-xs font-semibold uppercase">
                        {{ user.name.charAt(0) }}
                    </div>
                    <div v-if="sidebar.isOpen" class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ user.name }}</p>
                        <p class="truncate text-xs text-white/60">{{ user.role }}</p>
                    </div>
                </div>
                <div v-if="sidebar.isOpen" class="mt-2 flex gap-2">
                    <Link
                        :href="route('profile.edit')"
                        class="flex-1 rounded py-1 text-center text-xs text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                    >
                        Profil
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="flex-1 rounded py-1 text-center text-xs text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                    >
                        Keluar
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex flex-1 flex-col min-w-0 overflow-hidden">
            <!-- Top bar -->
            <header class="flex h-16 items-center justify-between bg-white border-b border-gray-200 px-6">
                <h1 class="text-lg font-semibold text-gray-800">
                    <slot name="title" />
                </h1>
                <div class="flex items-center gap-3 text-sm text-gray-500">
                    <span class="hidden sm:inline">BPS Provinsi Sulawesi Tengah</span>

                    <!-- Notification bell -->
                    <div class="relative">
                        <button
                            type="button"
                            class="relative flex h-9 w-9 items-center justify-center rounded-full hover:bg-gray-100 transition-colors"
                            aria-label="Notifikasi"
                            @click="toggleDropdown"
                        >
                            <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 00-5-5.916V4a1 1 0 10-2 0v1.084A6 6 0 006 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <span v-if="unreadCount > 0" class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white leading-none">
                                {{ unreadCount > 9 ? '9+' : unreadCount }}
                            </span>
                        </button>

                        <!-- Click-away backdrop -->
                        <div v-if="showDropdown" class="fixed inset-0 z-40" @click="showDropdown = false" />

                        <!-- Dropdown -->
                        <div
                            v-if="showDropdown"
                            class="absolute right-0 top-11 z-50 w-80 rounded-lg border bg-white shadow-lg"
                        >
                            <div class="flex items-center justify-between border-b px-4 py-3">
                                <span class="text-sm font-semibold text-gray-800">Notifikasi</span>
                                <button v-if="unreadCount > 0" type="button" class="text-xs text-primary hover:underline" @click="markAllRead">
                                    Tandai semua dibaca
                                </button>
                            </div>
                            <div class="max-h-80 overflow-y-auto divide-y divide-gray-100">
                                <div v-if="!notifications.length" class="px-4 py-8 text-center text-sm text-gray-400">
                                    Tidak ada notifikasi
                                </div>
                                <div
                                    v-for="n in notifications"
                                    :key="n.id"
                                    :class="['px-4 py-3 text-xs hover:bg-gray-50 transition-colors', !n.read_at ? 'bg-blue-50' : '']"
                                >
                                    <p :class="['leading-relaxed', !n.read_at ? 'font-medium text-gray-800' : 'text-gray-600']">{{ n.message }}</p>
                                    <p class="mt-1 text-gray-400">{{ new Date(n.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Notivue toast container -->
            <Notivue v-slot="item">
                <Notification :item="item" />
            </Notivue>

            <!-- Page content -->
            <main class="flex-1 overflow-auto p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
