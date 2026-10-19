<script setup lang="ts">
import FormDialog from '@/Components/FormDialog.vue';
import InputError from '@/Components/InputError.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { useKipStructure } from '@/composables/useKipStructure';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{ teamId: number | null }>();
const model = defineModel<string | null>({ default: null });

const { rkKetua, ikiJpt, loading, error, reload } = useKipStructure(() => props.teamId);

const creating = ref(false);
const form = useForm({ team_id: props.teamId as number | null, text: '', iki_jpt_id: '' });

function create() {
    form.team_id = props.teamId;
    form.post(route('kip.rk-ketua.store'), {
        preserveScroll: true,
        onSuccess: () => {
            creating.value = false;
            form.reset('text', 'iki_jpt_id');
            reload();
        },
    });
}
</script>

<template>
    <div class="space-y-1.5">
        <Label>RK Ketua di kipApp</Label>
        <div class="flex gap-2">
            <Select :model-value="model ?? undefined" :disabled="teamId === null || loading" @update:model-value="(v) => (model = v as string)">
                <SelectTrigger class="w-full" aria-label="RK Ketua di kipApp"><SelectValue :placeholder="loading ? 'Memuat dari kipApp…' : 'Pilih RK Ketua'" /></SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="r in rkKetua" :key="r.id" :value="r.id">{{ r.text }}</SelectItem>
                </SelectContent>
            </Select>
            <Button type="button" variant="outline" :disabled="teamId === null" @click="creating = true">RK Ketua baru</Button>
        </div>
        <p v-if="error" class="text-xs text-amber-700">{{ error }}</p>
        <p v-else class="text-xs text-gray-500">Proyek dibuat di kipApp di bawah RK Ketua ini. Kosongkan bila hanya disimpan di Kinetik.</p>

        <FormDialog v-model:open="creating" title="RK Ketua baru" description="Dibuat di kipApp dengan akun Anda. Ketua tim ini menjadi pemilik RK." submit-label="Buat di kipApp" :processing="form.processing" @submit="create">
            <div>
                <Label for="rk-ketua-text">Rencana kinerja ketua</Label>
                <Input id="rk-ketua-text" v-model="form.text" class="mt-1" />
                <InputError :message="form.errors.text" />
            </div>
            <div>
                <Label>IKI JPT</Label>
                <Select v-model="form.iki_jpt_id">
                    <SelectTrigger class="mt-1 w-full" aria-label="IKI JPT"><SelectValue placeholder="Pilih IKI JPT" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="i in ikiJpt" :key="i.id" :value="i.id">{{ i.text }}</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.iki_jpt_id" />
            </div>
        </FormDialog>
    </div>
</template>
