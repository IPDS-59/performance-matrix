<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, type Component } from 'vue';
import {
    Award, BookOpen, CalendarCheck, CalendarDays, CalendarRange, ClipboardCheck, FileChartColumn, FileText, FolderKanban,
    House, LayoutGrid, LayoutList, ListChecks, ListTodo, LogOut, Menu, PanelLeftClose, PanelLeftOpen, Target, UserRound, Users, X, Zap,
} from 'lucide-vue-next';
import { useSidebarStore } from '@/stores/sidebar';
import { Notivue, Notification, push } from 'notivue';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';

const page = usePage();
const sidebar = useSidebarStore();

const user = computed(() => page.props.auth.user as { name: string; email: string; role: string; position?: string | null });
const isAdmin = computed(() => user.value.role === 'admin');
const isHead = computed(() => user.value.role === 'head');
const isStaff = computed(() => user.value.role === 'staff');
const canViewProjects = computed(() => (page.props.can as Record<string, boolean>)?.view_projects ?? false);
const canViewIndicators = computed(() => (page.props.can as Record<string, boolean>)?.view_indicators ?? false);
const canViewPlans = computed(() => (page.props.can as Record<string, boolean>)?.view_plans ?? false);
const canViewTeamCredits = computed(() => (page.props.can as Record<string, boolean>)?.view_team_credits ?? false);
const hasEmployee = computed(() => !!(page.props.auth as { has_employee?: boolean })?.has_employee);

// ── Navigation model (grouped by who uses it) ─────────────────────────────
interface NavItem { label: string; href: string; active: boolean; icon: Component; show: boolean }
interface NavSection { title?: string; items: NavItem[] }

const navSections = computed<NavSection[]>(() => {
    void page.url; // recompute active state on every visit
    const is = (name: string) => route().current(name);
    const sections: NavSection[] = [
        {
            items: [
                { label: 'Beranda', href: route('dashboard'), active: is('dashboard'), icon: House, show: true },
                { label: 'Matriks', href: route('matrix'), active: is('matrix'), icon: LayoutGrid, show: true },
            ],
        },
        {
            // Personal claim tools; the head only reads, so they stay hidden for the head.
            title: 'Kegiatan',
            items: [
                { label: 'Rekap Mingguan', href: route('weekly.index'), active: is('weekly.*'), icon: CalendarCheck, show: hasEmployee.value && !isHead.value },
                { label: isAdmin.value ? 'Kegiatan kipApp' : 'Kegiatan Saya', href: route('kip-activities.index'), active: is('kip-activities.*'), icon: ListChecks, show: isAdmin.value || (hasEmployee.value && !isHead.value) },
                { label: 'Angka Kredit Saya', href: route('credit.mine'), active: is('credit.mine'), icon: Award, show: hasEmployee.value },
            ],
        },
        {
            title: 'Rekap Tim',
            items: [
                { label: 'Review Bersama', href: route('team-recap.overview'), active: is('team-recap.overview'), icon: LayoutList, show: isHead.value || isAdmin.value },
                { label: 'Mingguan', href: route('team-recap.weekly'), active: is('team-recap.weekly'), icon: CalendarDays, show: hasEmployee.value || isHead.value || isAdmin.value },
                { label: 'Bulanan', href: route('team-recap.monthly'), active: is('team-recap.monthly'), icon: CalendarRange, show: hasEmployee.value || isHead.value || isAdmin.value },
                { label: 'Triwulanan (FRA)', href: route('team-recap.quarterly'), active: is('team-recap.quarterly'), icon: FileChartColumn, show: hasEmployee.value || isHead.value || isAdmin.value },
                { label: 'Angka Kredit Tim', href: route('credit.team'), active: is('credit.team'), icon: Award, show: canViewTeamCredits.value },
            ],
        },
        {
            title: 'Laporan',
            items: [
                { label: 'Laporan Pegawai', href: route('laporan.pegawai'), active: is('laporan.*'), icon: FileText, show: isAdmin.value || isHead.value },
                { label: 'Input Kinerja', href: route('performance.index'), active: is('performance.*'), icon: ClipboardCheck, show: isHead.value },
            ],
        },
        {
            title: 'Data Master',
            items: [
                { label: 'Tim Kerja', href: route('teams.index'), active: is('teams.*'), icon: Users, show: isAdmin.value },
                { label: 'Pegawai', href: route('employees.index'), active: is('employees.*'), icon: UserRound, show: isAdmin.value },
                { label: 'Proyek', href: route('projects.index'), active: is('projects.*'), icon: FolderKanban, show: canViewProjects.value },
                { label: 'IKU', href: route('performance-indicators.index'), active: is('performance-indicators.*'), icon: Target, show: canViewIndicators.value },
                { label: 'Rencana Kinerja (RK)', href: route('performance-plans.index'), active: is('performance-plans.*'), icon: ListTodo, show: canViewPlans.value },
                { label: 'Integrasi kipApp', href: route('kip-integration.index'), active: is('kip-integration.*'), icon: Zap, show: isAdmin.value },
            ],
        },
        {
            title: 'Bantuan',
            items: [
                { label: 'Buku Pedoman', href: route('guide'), active: is('guide'), icon: BookOpen, show: true },
            ],
        },
    ];

    return sections
        .map(section => ({ ...section, items: section.items.filter(item => item.show) }))
        .filter(section => section.items.length);
});

// Labels are visible when the desktop sidebar is expanded, and always in the mobile drawer.
const showLabels = computed(() => sidebar.isOpen || sidebar.mobileOpen);

// ── Notifications ─────────────────────────────────────────────────────────
const unreadCount = ref(0);
const notifications = ref<Array<{ id: string; type: string; message: string; data: Record<string, unknown>; read_at: string | null; created_at: string }>>([]);
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

function csrfToken(): string {
    return (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? '';
}

async function handleNotificationClick(n: { id: string; data: Record<string, unknown>; read_at: string | null }) {
    if (!n.read_at) {
        await fetch(route('notifications.read', n.id), {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        });
        n.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
    showDropdown.value = false;
    const url = n.data?.url as string | undefined;
    if (url) router.visit(url);
}

const deleteNotifDialogOpen = ref(false);
const deleteNotifTarget = ref<{ id: string; read_at: string | null } | null>(null);

function confirmDeleteNotif(n: { id: string; read_at: string | null }, event: MouseEvent) {
    event.stopPropagation();
    deleteNotifTarget.value = n;
    deleteNotifDialogOpen.value = true;
}

async function executeDeleteNotif() {
    const n = deleteNotifTarget.value;
    if (!n) return;
    deleteNotifDialogOpen.value = false;
    deleteNotifTarget.value = null;
    await fetch(route('notifications.destroy', n.id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
    if (!n.read_at) unreadCount.value = Math.max(0, unreadCount.value - 1);
    notifications.value = notifications.value.filter(x => x.id !== n.id);
}

let pollInterval: ReturnType<typeof setInterval> | null = null;
let removeSuccessListener: (() => void) | null = null;
let removeNavigateListener: (() => void) | null = null;

function closeDrawerOnEscape(event: KeyboardEvent) {
    if (event.key === 'Escape') sidebar.closeMobile();
}

onMounted(() => {
    fetchNotifications();
    pollInterval = setInterval(() => {
        if (document.visibilityState === 'visible') fetchNotifications();
    }, 60_000);

    removeNavigateListener = router.on('navigate', () => sidebar.closeMobile());
    window.addEventListener('keydown', closeDrawerOnEscape);

    removeSuccessListener = router.on('success', (event: { detail: { page: { props: unknown } } }) => {
        const flash = (event.detail.page.props as Record<string, unknown>).flash as Record<string, string> | undefined;
        if (flash?.success) push.success(flash.success);
        if (flash?.error) push.error(flash.error);
    });
});

onUnmounted(() => {
    if (pollInterval !== null) clearInterval(pollInterval);
    removeSuccessListener?.();
    removeNavigateListener?.();
    window.removeEventListener('keydown', closeDrawerOnEscape);
});
</script>

<template>
    <div class="flex h-screen bg-gray-50 print:block print:h-auto print:bg-white">
        <!-- Mobile drawer backdrop -->
        <div
            v-if="sidebar.mobileOpen"
            class="fixed inset-0 z-40 bg-gray-900/40 lg:hidden print:hidden"
            aria-hidden="true"
            @click="sidebar.closeMobile()"
        />

        <!-- Sidebar: off-canvas drawer below lg, collapsible rail on desktop -->
        <aside
            :class="[
                sidebar.mobileOpen ? 'translate-x-0' : '-translate-x-full',
                sidebar.isOpen ? 'lg:w-64' : 'lg:w-16',
            ]"
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col print:hidden bg-[#1B4B8A] text-white transition-[transform,width] duration-200 ease-out lg:static lg:translate-x-0"
            aria-label="Navigasi utama"
        >
            <!-- Logo area -->
            <div class="flex h-16 shrink-0 items-center justify-between gap-2 px-4">
                <Link
                    v-if="showLabels"
                    :href="route('dashboard')"
                    class="flex min-w-0 items-center gap-2 text-sm font-semibold leading-tight"
                >
                    <img
                        src="/images/bps-sulteng-logo.svg"
                        alt="BPS Sulteng"
                        class="h-8 w-8 shrink-0 rounded bg-white object-contain p-0.5"
                    />
                    <span class="truncate">Kinetik</span>
                </Link>
                <button
                    type="button"
                    class="hidden rounded p-1.5 transition-colors hover:bg-white/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 lg:block"
                    :aria-label="sidebar.isOpen ? 'Ciutkan menu' : 'Lebarkan menu'"
                    :aria-expanded="sidebar.isOpen"
                    @click="sidebar.toggle()"
                >
                    <PanelLeftClose v-if="sidebar.isOpen" class="h-5 w-5" />
                    <PanelLeftOpen v-else class="h-5 w-5" />
                </button>
                <button
                    type="button"
                    class="rounded p-1.5 transition-colors hover:bg-white/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 lg:hidden"
                    aria-label="Tutup menu"
                    @click="sidebar.closeMobile()"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Nav links -->
            <nav class="flex-1 overflow-y-auto px-2 pb-4">
                <div v-for="(section, index) in navSections" :key="section.title ?? 'main'" :class="index > 0 ? 'mt-5' : 'mt-1'">
                    <p
                        v-if="section.title && showLabels"
                        class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-white/55"
                    >
                        {{ section.title }}
                    </p>
                    <div v-else-if="section.title" class="mx-3 mb-2 border-t border-white/15" aria-hidden="true" />
                    <div class="space-y-0.5">
                        <Link
                            v-for="item in section.items"
                            :key="item.href"
                            :href="item.href"
                            :aria-current="item.active ? 'page' : undefined"
                            :title="showLabels ? undefined : item.label"
                            :class="[
                                'group relative flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70',
                                item.active ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10 hover:text-white',
                            ]"
                        >
                            <span v-if="item.active" class="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-white" aria-hidden="true" />
                            <component :is="item.icon" class="h-[18px] w-[18px] shrink-0" aria-hidden="true" />
                            <span v-if="showLabels" class="truncate">{{ item.label }}</span>
                            <span v-else class="sr-only">{{ item.label }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- User footer -->
            <div class="shrink-0 border-t border-white/15 p-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/20 text-xs font-semibold uppercase">
                        {{ user.name.charAt(0) }}
                    </div>
                    <div v-if="showLabels" class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ user.name }}</p>
                        <p class="truncate text-xs text-white/65">{{ user.position || user.role }}</p>
                    </div>
                </div>
                <div v-if="showLabels" class="mt-2 flex gap-2">
                    <Link
                        :href="route('profile.edit')"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded py-1.5 text-xs text-white/75 transition-colors hover:bg-white/10 hover:text-white"
                    >
                        <UserRound class="h-3.5 w-3.5" aria-hidden="true" />
                        Profil
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded py-1.5 text-xs text-white/75 transition-colors hover:bg-white/10 hover:text-white"
                    >
                        <LogOut class="h-3.5 w-3.5" aria-hidden="true" />
                        Keluar
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex flex-1 flex-col min-w-0 overflow-hidden print:overflow-visible">
            <!-- Top bar -->
            <header class="flex h-16 shrink-0 print:hidden items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 sm:px-6">
                <div class="flex min-w-0 items-center gap-2">
                    <button
                        type="button"
                        class="-ml-1 rounded-md p-2 text-gray-600 transition-colors hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary lg:hidden"
                        aria-label="Buka menu"
                        :aria-expanded="sidebar.mobileOpen"
                        @click="sidebar.openMobile()"
                    >
                        <Menu class="h-5 w-5" />
                    </button>
                    <h1 class="truncate text-base font-semibold text-gray-800 sm:text-lg">
                        <slot name="title" />
                    </h1>
                </div>
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
                            class="absolute right-0 top-11 z-50 w-[min(20rem,calc(100vw-2rem))] rounded-lg border bg-white shadow-lg"
                        >
                            <div class="flex items-center justify-between border-b px-4 py-3">
                                <span class="text-sm font-semibold text-gray-800">Notifikasi</span>
                                <button v-if="unreadCount > 0" type="button" class="text-xs text-primary hover:underline" @click="markAllRead">
                                    Tandai semua dibaca
                                </button>
                            </div>
                            <div class="divide-y divide-gray-100">
                                <div v-if="!notifications.length" class="px-4 py-8 text-center text-sm text-gray-400">
                                    Tidak ada notifikasi
                                </div>
                                <div
                                    v-for="n in notifications"
                                    :key="n.id"
                                    :class="['group relative px-4 py-3 text-xs transition-colors', !n.read_at ? 'bg-blue-50' : '', n.data?.url ? 'cursor-pointer hover:bg-primary/5' : 'hover:bg-gray-50']"
                                    @click="handleNotificationClick(n)"
                                >
                                    <button
                                        type="button"
                                        class="absolute right-2 top-2 hidden h-5 w-5 items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 group-hover:flex"
                                        @click="confirmDeleteNotif(n, $event)"
                                        title="Hapus"
                                    >
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                    <p :class="['leading-relaxed pr-4', !n.read_at ? 'font-medium text-gray-800' : 'text-gray-600']">{{ n.message }}</p>
                                    <div class="mt-1 flex items-center justify-between gap-2">
                                        <p class="text-gray-400">{{ new Date(n.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}</p>
                                        <span v-if="n.data?.url" class="text-[10px] text-primary">Lihat →</span>
                                    </div>
                                </div>
                            </div>
                            <div class="border-t px-4 py-2 text-center">
                                <Link
                                    :href="route('notifications.page')"
                                    class="text-xs text-primary hover:underline"
                                    @click="showDropdown = false"
                                >
                                    Lihat semua notifikasi
                                </Link>
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
            <main class="flex-1 overflow-auto p-4 sm:p-6 print:overflow-visible print:p-0">
                <slot />
            </main>
        </div>
    </div>

    <!-- Delete single notification confirmation -->
    <AlertDialog :open="deleteNotifDialogOpen" @update:open="deleteNotifDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Hapus notifikasi ini?</AlertDialogTitle>
                <AlertDialogDescription>
                    Notifikasi ini akan dihapus permanen.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Batal</AlertDialogCancel>
                <AlertDialogAction class="bg-red-600 hover:bg-red-700 focus:ring-red-600" @click="executeDeleteNotif">
                    Hapus
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
