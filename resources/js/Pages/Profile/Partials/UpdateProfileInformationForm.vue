<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <h2 class="text-base font-semibold text-gray-900">Informasi Profil</h2>
        <p class="mt-1 text-sm text-gray-500">Perbarui nama dan alamat email akun Anda.</p>

        <form @submit.prevent="form.patch(route('profile.update'))" class="mt-5 space-y-4">
            <div>
                <Label for="name">Nama</Label>
                <Input id="name" v-model="form.name" class="mt-1" required autocomplete="name" autofocus />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <div>
                <Label for="email">Email</Label>
                <Input id="email" type="email" v-model="form.email" class="mt-1" required autocomplete="email" />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && !user.email_verified_at">
                <p class="text-sm text-gray-600">
                    Email Anda belum terverifikasi.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="text-primary underline hover:opacity-80"
                    >
                        Kirim ulang verifikasi.
                    </Link>
                </p>
                <p v-if="status === 'verification-link-sent'" class="mt-1 text-sm text-green-600">
                    Link verifikasi telah dikirim ke email Anda.
                </p>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <Button type="submit" :disabled="form.processing">Simpan</Button>
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm text-green-600">Tersimpan.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
