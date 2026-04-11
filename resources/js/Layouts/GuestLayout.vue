<script setup lang="ts">
</script>

<template>
    <div class="flex min-h-screen bg-[#1B4B8A]">
        <!-- Left branding panel -->
        <div class="hidden lg:flex lg:w-2/5 flex-col items-center justify-center px-12 text-white">
            <img
                src="/images/bps-sulteng-logo.svg"
                alt="BPS Sulteng"
                class="h-32 w-32 mb-8"
            />
            <h1 class="text-3xl font-bold text-center leading-tight">Matriks Kinerja</h1>
            <p class="mt-3 text-base text-white/70 text-center">
                BPS Provinsi Sulawesi Tengah
            </p>
        </div>

        <!-- Right form panel -->
        <div class="flex flex-1 flex-col items-center justify-center px-6 py-12 bg-gray-50">
            <!-- Mobile logo -->
            <div class="flex flex-col items-center mb-8 lg:hidden">
                <img
                    src="/images/bps-sulteng-logo.svg"
                    alt="BPS Sulteng"
                    class="h-16 w-16 mb-3"
                />
                <p class="text-sm font-semibold text-[#1B4B8A]">BPS Provinsi Sulawesi Tengah</p>
            </div>

            <div class="w-full max-w-md">
                <slot />
            </div>
        </div>
    </div>
</template>
