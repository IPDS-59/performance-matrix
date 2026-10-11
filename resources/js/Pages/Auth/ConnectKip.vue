<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useDateFormat } from '@/composables/useDateFormat';

defineProps<{
    username: string;
    deadline: string | null;
}>();

const { formatDate } = useDateFormat();
const form = useForm({ password: '' });

const submit = () => {
    form.post(route('kip.connect.store'), { onFinish: () => form.reset('password') });
};
</script>

<template>
    <GuestLayout>
        <Head title="Hubungkan akun SSO" />

        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Hubungkan akun SSO BPS</h2>
            <p class="mt-1 text-sm text-gray-600">
                Mulai sekarang Anda masuk dengan kata sandi SSO BPS. Masukkan kata sandi tersebut satu kali agar Kinetik dapat terhubung ke kipApp atas nama Anda.
                Kata sandi disimpan terenkripsi sesuai MoU dan tidak pernah ditampilkan kembali.
            </p>
            <p v-if="deadline" class="mt-2 text-sm text-gray-600">
                Kata sandi bawaan hanya berlaku sampai {{ formatDate(deadline) }}.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div class="space-y-1.5">
                <Label for="username">Username SSO</Label>
                <Input id="username" :model-value="username" readonly class="bg-gray-50" />
            </div>

            <div class="space-y-1.5">
                <Label for="password">Kata sandi SSO BPS</Label>
                <Input id="password" type="password" v-model="form.password" required autofocus autocomplete="current-password" />
                <InputError :message="form.errors.password" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Memeriksa…' : 'Hubungkan' }}
            </Button>
        </form>

        <p class="mt-6 text-center text-sm">
            <Link :href="route('logout')" method="post" as="button" class="text-gray-500 underline-offset-4 hover:underline">Keluar</Link>
        </p>
    </GuestLayout>
</template>
