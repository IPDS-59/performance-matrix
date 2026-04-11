<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Masuk" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Masuk</h2>
            <p class="mt-1 text-sm text-gray-500">Masukkan kredensial Anda untuk melanjutkan</p>
        </div>

        <div v-if="status" class="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">
            {{ status }}
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
                    placeholder="nama@bps.go.id"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <Label for="password">Kata Sandi</Label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs text-[#1B4B8A] underline-offset-4 hover:underline"
                    >
                        Lupa kata sandi?
                    </Link>
                </div>
                <Input
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="remember"
                    type="checkbox"
                    v-model="form.remember"
                    class="h-4 w-4 rounded border-gray-300 text-[#1B4B8A] focus:ring-[#1B4B8A]"
                />
                <Label for="remember" class="text-sm font-normal text-gray-600 cursor-pointer">Ingat saya</Label>
            </div>

            <Button
                type="submit"
                class="w-full bg-[#1B4B8A] hover:bg-[#163d73]"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Memproses...' : 'Masuk' }}
            </Button>
        </form>
    </GuestLayout>
</template>
