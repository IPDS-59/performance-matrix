<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirming = ref(false);
const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({ password: '' });

function startConfirm() {
    confirming.value = true;
    nextTick(() => passwordInput.value?.focus());
}

function deleteUser() {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
}

function closeModal() {
    confirming.value = false;
    form.clearErrors();
    form.reset();
}
</script>

<template>
    <section class="space-y-4">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Hapus Akun</h2>
            <p class="mt-1 text-sm text-gray-500">
                Setelah akun dihapus, semua data akan hilang secara permanen dan tidak dapat dipulihkan.
            </p>
        </div>

        <Button variant="destructive" @click="startConfirm">Hapus Akun</Button>

        <div v-if="confirming" class="mt-2 rounded-md border border-red-200 bg-red-50 p-4 space-y-3">
            <p class="text-sm font-medium text-red-800">
                Yakin ingin menghapus akun? Masukkan kata sandi untuk konfirmasi.
            </p>
            <div>
                <Label for="del_password" class="sr-only">Kata Sandi</Label>
                <Input
                    ref="passwordInput"
                    id="del_password"
                    type="password"
                    v-model="form.password"
                    placeholder="Kata sandi"
                    @keyup.enter="deleteUser"
                />
                <InputError class="mt-1" :message="form.errors.password" />
            </div>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" @click="closeModal">Batal</Button>
                <Button variant="destructive" size="sm" :disabled="form.processing" @click="deleteUser">
                    Hapus Akun
                </Button>
            </div>
        </div>
    </section>
</template>
