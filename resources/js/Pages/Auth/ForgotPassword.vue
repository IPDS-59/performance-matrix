<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Lupa Kata Sandi" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Lupa Kata Sandi?</h2>
            <p class="mt-2 text-sm text-gray-500">
                Masukkan alamat email Anda dan kami akan mengirimkan tautan untuk mengatur ulang kata sandi.
            </p>
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

            <Button
                type="submit"
                class="w-full"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Mengirim...' : 'Kirim Tautan Reset' }}
            </Button>
        </form>
    </GuestLayout>
</template>
