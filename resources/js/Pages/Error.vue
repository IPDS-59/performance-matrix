<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    status: number;
}>();

const title = computed(() => {
    const titles: Record<number, string> = {
        401: 'Tidak Terautentikasi',
        403: 'Akses Ditolak',
        404: 'Halaman Tidak Ditemukan',
        419: 'Sesi Kedaluwarsa',
        429: 'Terlalu Banyak Permintaan',
        500: 'Kesalahan Server',
        503: 'Layanan Tidak Tersedia',
    };
    return titles[props.status] ?? 'Terjadi Kesalahan';
});

const description = computed(() => {
    const descriptions: Record<number, string> = {
        401: 'Anda perlu masuk untuk mengakses halaman ini.',
        403: 'Anda tidak memiliki izin untuk mengakses halaman ini.',
        404: 'Halaman yang Anda cari tidak ditemukan.',
        419: 'Sesi Anda telah kedaluwarsa. Silakan muat ulang halaman.',
        429: 'Terlalu banyak permintaan. Silakan coba lagi nanti.',
        500: 'Terjadi kesalahan pada server. Silakan coba lagi nanti.',
        503: 'Layanan sedang tidak tersedia. Silakan coba lagi nanti.',
    };
    return descriptions[props.status] ?? 'Terjadi kesalahan yang tidak diketahui.';
});
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4">
        <div class="w-full max-w-md text-center">
            <img
                src="/images/bps-sulteng-logo.svg"
                alt="BPS Sulteng"
                class="mx-auto mb-8 h-16 w-16"
            />

            <p class="text-6xl font-bold text-[#1B4B8A]">{{ status }}</p>

            <h1 class="mt-4 text-xl font-semibold text-gray-800">{{ title }}</h1>

            <p class="mt-2 text-sm text-gray-500">{{ description }}</p>

            <div class="mt-8 flex justify-center gap-3">
                <Link
                    :href="route('dashboard')"
                    class="inline-flex items-center rounded-md bg-[#1B4B8A] px-4 py-2 text-sm font-medium text-white hover:bg-[#163d73] transition-colors"
                >
                    Ke Beranda
                </Link>
                <button
                    type="button"
                    @click="() => window.history.back()"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    Kembali
                </button>
            </div>
        </div>
    </div>
</template>
