<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    intent: 'login' | 'connect';
}>();

const form = useForm({ otp: '' });

const submit = () => {
    form.post(route('kip.otp.store'), { onFinish: () => form.reset('otp') });
};
</script>

<template>
    <GuestLayout>
        <Head title="Kode OTP" />

        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Masukkan kode OTP</h2>
            <p class="mt-1 text-sm text-gray-600">
                Akun SSO BPS Anda memakai kode sekali pakai. Buka aplikasi autentikator di ponsel Anda, lalu masukkan kode 6 digit yang tampil.
                Kode berganti setiap 30 detik.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div class="space-y-1.5">
                <Label for="otp">Kode OTP</Label>
                <Input
                    id="otp"
                    v-model="form.otp"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="8"
                    required
                    autofocus
                />
                <InputError :message="form.errors.otp" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Memeriksa…' : 'Lanjutkan' }}
            </Button>
        </form>

        <p class="mt-6 text-center text-sm">
            <Link :href="route(intent === 'login' ? 'login' : 'kip.connect')" class="text-gray-500 underline-offset-4 hover:underline">
                Kembali
            </Link>
        </p>
    </GuestLayout>
</template>
