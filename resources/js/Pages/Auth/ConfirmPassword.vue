<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Konfirmasi Kata Sandi" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Konfirmasi Kata Sandi</h2>
            <p class="mt-2 text-sm text-gray-500">
                Ini adalah area aman. Mohon konfirmasi kata sandi Anda sebelum melanjutkan.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div class="space-y-1.5">
                <Label for="password">Kata Sandi</Label>
                <Input
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                    placeholder="••••••••"
                />
                <InputError :message="form.errors.password" />
            </div>

            <Button
                type="submit"
                class="w-full bg-[#1B4B8A] hover:bg-[#163d73]"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Memverifikasi...' : 'Konfirmasi' }}
            </Button>
        </form>
    </GuestLayout>
</template>
