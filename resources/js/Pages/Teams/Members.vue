<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { Employee, Team, TeamMemberWithPivot } from '@/types';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Check, ChevronsUpDown, X } from 'lucide-vue-next';
import InputError from '@/Components/InputError.vue';
import { computed, ref } from 'vue';

type MemberRow = { employee_id: number; role: 'member' | 'leader'; is_primary: boolean };

const props = defineProps<{
    team: Team & { members?: TeamMemberWithPivot[] };
    employees: Employee[];
}>();

const initialMembers = computed<MemberRow[]>(() =>
    (props.team.members ?? []).map((m) => ({
        employee_id: m.id,
        role: (m.pivot?.role ?? 'member') as 'member' | 'leader',
        is_primary: Boolean(m.pivot?.is_primary),
    }))
);

const form = useForm<{ members: MemberRow[] }>({
    members: initialMembers.value,
});

// ── Employee picker ───────────────────────────────────────────────────────

const pickerOpen = ref(false);

const selectedIds = computed(() => new Set(form.members.map((m) => m.employee_id)));

const availableEmployees = computed(() =>
    props.employees.filter((e) => !selectedIds.value.has(e.id))
);

function addMember(employee: Employee) {
    form.members.push({ employee_id: employee.id, role: 'member', is_primary: false });
    pickerOpen.value = false;
}

function removeMember(index: number) {
    form.members.splice(index, 1);
}

function employeeName(id: number): string {
    const emp = props.employees.find((e) => e.id === id);
    return emp ? (emp.display_name || emp.name) : `#${id}`;
}

function submit() {
    form.put(route('teams.members.update', props.team.id));
}
</script>

<template>
    <Head :title="`Anggota Tim — ${team.name}`" />
    <AppLayout>
        <template #title>Kelola Anggota: {{ team.name }}</template>

        <div class="max-w-2xl space-y-6">
            <div class="rounded-md border bg-white p-6">
                <form class="space-y-4" @submit.prevent="submit">

                    <!-- Member picker -->
                    <div>
                        <Label>Tambah Anggota</Label>
                        <Popover v-model:open="pickerOpen">
                            <PopoverTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    role="combobox"
                                    class="mt-1 w-full justify-between font-normal"
                                >
                                    Pilih pegawai...
                                    <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-[--radix-popover-trigger-width] p-0">
                                <Command>
                                    <CommandInput placeholder="Cari pegawai..." />
                                    <CommandList>
                                        <CommandEmpty>Tidak ada pegawai tersedia.</CommandEmpty>
                                        <CommandGroup>
                                            <CommandItem
                                                v-for="emp in availableEmployees"
                                                :key="emp.id"
                                                :value="emp.display_name || emp.name"
                                                @select="() => addMember(emp)"
                                            >
                                                {{ emp.display_name || emp.name }}
                                                <Check
                                                    v-if="selectedIds.has(emp.id)"
                                                    class="ml-auto h-4 w-4"
                                                />
                                            </CommandItem>
                                        </CommandGroup>
                                    </CommandList>
                                </Command>
                            </PopoverContent>
                        </Popover>
                    </div>

                    <!-- Member list -->
                    <div v-if="form.members.length" class="space-y-2">
                        <Label>Daftar Anggota</Label>
                        <div
                            v-for="(member, index) in form.members"
                            :key="member.employee_id"
                            class="flex items-center gap-3 rounded-md border bg-gray-50 px-3 py-2"
                        >
                            <!-- Avatar initial -->
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                {{ employeeName(member.employee_id).charAt(0).toUpperCase() }}
                            </div>

                            <!-- Name -->
                            <span class="min-w-0 flex-1 truncate text-sm">
                                {{ employeeName(member.employee_id) }}
                            </span>

                            <!-- Role select — never use empty string value (reka-ui forbids it) -->
                            <Select v-model="member.role">
                                <SelectTrigger class="h-7 w-32 text-xs">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="member">Anggota</SelectItem>
                                    <SelectItem value="leader">Ketua Tim</SelectItem>
                                </SelectContent>
                            </Select>

                            <!-- Remove -->
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7 shrink-0 text-red-500 hover:bg-red-50 hover:text-red-600"
                                @click="removeMember(index)"
                            >
                                <X class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                        <InputError :message="form.errors.members" />
                    </div>

                    <p v-else class="text-sm text-gray-400">
                        Belum ada anggota. Pilih pegawai di atas untuk menambahkan.
                    </p>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="outline" as-child>
                            <a :href="route('teams.index')">Batal</a>
                        </Button>
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
