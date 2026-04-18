<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { Employee, Team } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { computed, ref } from 'vue';

const props = defineProps<{ team: Team; employees: Employee[] }>();

const form = useForm({
    name: props.team.name,
    code: props.team.code,
    description: props.team.description ?? '',
    is_active: props.team.is_active,
    leader_id: props.team.leader_id ?? null as number | null,
});

const leaderOpen = ref(false);

const selectedLeaderLabel = computed(() => {
    if (form.leader_id === null) return '— Belum ditentukan —';
    const emp = props.employees.find(e => e.id === form.leader_id);
    return emp ? (emp.display_name || emp.name) : '— Belum ditentukan —';
});

function submit() {
    form.put(route('teams.update', props.team.id));
}
</script>

<template>
    <Head title="Edit Tim" />
    <AppLayout>
        <template #title>Edit Tim Kerja</template>

        <div class="max-w-lg bg-white rounded-md border p-6">
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <Label for="name">Nama Tim</Label>
                    <Input id="name" v-model="form.name" class="mt-1" />
                    <InputError :message="form.errors.name" />
                </div>
                <div>
                    <Label for="code">Kode Tim</Label>
                    <Input id="code" v-model="form.code" class="mt-1 uppercase" maxlength="20" />
                    <InputError :message="form.errors.code" />
                </div>
                <div>
                    <Label for="description">Deskripsi</Label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <div>
                    <Label>Ketua Tim</Label>
                    <Popover v-model:open="leaderOpen">
                        <PopoverTrigger as-child>
                            <Button
                                variant="outline"
                                role="combobox"
                                class="mt-1 w-full justify-between font-normal"
                            >
                                {{ selectedLeaderLabel }}
                                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-[--radix-popover-trigger-width] p-0">
                            <Command>
                                <CommandInput placeholder="Cari pegawai..." />
                                <CommandList>
                                    <CommandEmpty>Tidak ada hasil.</CommandEmpty>
                                    <CommandGroup>
                                        <CommandItem value="__none__" @select="() => { form.leader_id = null; leaderOpen = false }">
                                            — Belum ditentukan —
                                            <Check v-if="form.leader_id === null" class="ml-auto h-4 w-4" />
                                        </CommandItem>
                                        <CommandItem
                                            v-for="emp in employees"
                                            :key="emp.id"
                                            :value="emp.display_name || emp.name"
                                            @select="() => { form.leader_id = emp.id; leaderOpen = false }"
                                        >
                                            {{ emp.display_name || emp.name }}
                                            <Check v-if="form.leader_id === emp.id" class="ml-auto h-4 w-4" />
                                        </CommandItem>
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                    <InputError :message="form.errors.leader_id" />
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_active" v-model="form.is_active" class="h-4 w-4" />
                    <Label for="is_active">Aktif</Label>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" as-child>
                        <a :href="route('teams.index')">Batal</a>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Perbarui</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
