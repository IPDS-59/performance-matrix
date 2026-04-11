<script setup lang="ts">
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verifikasi Email" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Verifikasi Email</h2>
            <p class="mt-2 text-sm text-gray-500">
                Terima kasih telah mendaftar! Sebelum memulai, mohon verifikasi alamat email Anda
                dengan mengklik tautan yang telah kami kirimkan. Jika Anda tidak menerima emailnya,
                kami akan dengan senang hati mengirimkan yang baru.
            </p>
        </div>

        <div
            v-if="verificationLinkSent"
            class="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800"
        >
            Tautan verifikasi baru telah dikirimkan ke alamat email yang Anda daftarkan.
        </div>

        <div class="flex items-center justify-between gap-4">
            <Button
                type="button"
                class="bg-[#1B4B8A] hover:bg-[#163d73]"
                :disabled="form.processing"
                @click="submit"
            >
                {{ form.processing ? 'Mengirim...' : 'Kirim Ulang Email Verifikasi' }}
            </Button>

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="text-sm text-gray-500 underline-offset-4 hover:underline"
            >
                Keluar
            </Link>
        </div>
    </GuestLayout>
</template>
