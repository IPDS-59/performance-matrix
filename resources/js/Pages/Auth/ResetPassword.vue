<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Atur Ulang Kata Sandi" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Atur Ulang Kata Sandi</h2>
            <p class="mt-1 text-sm text-gray-500">Buat kata sandi baru untuk akun Anda</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div class="space-y-1.5">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="space-y-1.5">
                <Label for="password">Kata Sandi Baru</Label>
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
                class="w-full"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Memproses...' : 'Atur Ulang Kata Sandi' }}
            </Button>
        </form>
    </GuestLayout>
</template>
