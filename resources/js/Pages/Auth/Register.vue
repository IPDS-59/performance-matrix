<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Daftar" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Buat Akun</h2>
            <p class="mt-1 text-sm text-gray-500">Daftarkan akun baru Anda</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div class="space-y-1.5">
                <Label for="name">Nama Lengkap</Label>
                <Input
                    id="name"
                    type="text"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Nama lengkap"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="space-y-1.5">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    placeholder="nama@bps.go.id"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="space-y-1.5">
                <Label for="password">Kata Sandi</Label>
                <Input
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="space-y-1.5">
                <Label for="password_confirmation">Konfirmasi Kata Sandi</Label>
                <Input
                    id="password_confirmation"
                    type="password"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="w-full bg-[#1B4B8A] hover:bg-[#163d73]"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Memproses...' : 'Daftar' }}
            </Button>

            <p class="text-center text-sm text-gray-500">
                Sudah punya akun?
                <Link :href="route('login')" class="text-[#1B4B8A] underline-offset-4 hover:underline">
                    Masuk
                </Link>
            </p>
        </form>
    </GuestLayout>
</template>
