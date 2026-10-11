<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { PerformanceIndicator, Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import InputError from '@/Components/InputError.vue';

const props = defineProps<{ performanceIndicator: PerformanceIndicator; teams: Team[]; isAdmin: boolean }>();

const form = useForm({
    team_id: props.performanceIndicator.team_id,
    year: props.performanceIndicator.year,
    name: props.performanceIndicator.name,
    target: (props.performanceIndicator.target ?? '') as string | number,
    target_unit: props.performanceIndicator.target_unit ?? '',
    description: props.performanceIndicator.description ?? '',
});

function submit() {
    form.put(route('performance-indicators.update', props.performanceIndicator.id));
}
</script>

<template>
    <Head title="Edit IKU" />
    <AppLayout>
        <template #title>Edit IKU</template>

        <div class="max-w-lg bg-white rounded-md border p-6">
            <form @submit.prevent="submit" class="space-y-4">
                <div v-if="isAdmin || teams.length > 1">
                    <Label for="team_id">Tim Kerja</Label>
                    <Select
                        :model-value="String(form.team_id)"
                        @update:model-value="(v) => (form.team_id = Number(v))"
                    >
                        <SelectTrigger class="mt-1 w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.team_id" />
                </div>
                <div>
                    <Label for="year">Tahun</Label>
                    <Input id="year" type="number" v-model="form.year" class="mt-1" min="2020" max="2099" />
                    <InputError :message="form.errors.year" />
                </div>
                <div>
                    <Label for="name">Nama IKU</Label>
                    <Input id="name" v-model="form.name" class="mt-1" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label for="target">Target</Label>
                        <Input id="target" type="number" step="any" v-model="form.target" class="mt-1" />
                        <InputError :message="form.errors.target" />
                    </div>
                    <div>
                        <Label for="target_unit">Satuan</Label>
                        <Input id="target_unit" v-model="form.target_unit" class="mt-1" />
                        <InputError :message="form.errors.target_unit" />
                    </div>
                </div>
                <div>
                    <Label for="description">Deskripsi</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" as-child>
                        <a :href="route('performance-indicators.index')">Batal</a>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Perbarui</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
