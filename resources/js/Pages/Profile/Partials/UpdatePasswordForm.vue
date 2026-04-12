<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function updatePassword() {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
}
</script>

<template>
    <section>
        <h2 class="text-base font-semibold text-gray-900">Ubah Kata Sandi</h2>
        <p class="mt-1 text-sm text-gray-500">Gunakan kata sandi yang kuat dan unik untuk menjaga keamanan akun.</p>

        <form @submit.prevent="updatePassword" class="mt-5 space-y-4">
            <div>
                <Label for="current_password">Kata Sandi Saat Ini</Label>
                <Input
                    ref="currentPasswordInput"
                    id="current_password"
                    type="password"
                    v-model="form.current_password"
                    class="mt-1"
                    autocomplete="current-password"
                />
                <InputError class="mt-1" :message="form.errors.current_password" />
            </div>

            <div>
                <Label for="password">Kata Sandi Baru</Label>
                <Input
                    ref="passwordInput"
                    id="password"
                    type="password"
                    v-model="form.password"
                    class="mt-1"
                    autocomplete="new-password"
                />
                <InputError class="mt-1" :message="form.errors.password" />
            </div>

            <div>
                <Label for="password_confirmation">Konfirmasi Kata Sandi</Label>
                <Input
                    id="password_confirmation"
                    type="password"
                    v-model="form.password_confirmation"
                    class="mt-1"
                    autocomplete="new-password"
                />
                <InputError class="mt-1" :message="form.errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-3 pt-1">
                <Button type="submit" :disabled="form.processing">Perbarui Sandi</Button>
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
