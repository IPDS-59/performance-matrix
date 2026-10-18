<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import OrgTree from '@/Components/Kinetik/OrgTree.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useOrgTree } from '@/composables/useOrgTree';
import type { OrganizationProps } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Search, X } from 'lucide-vue-next';

const props = defineProps<OrganizationProps>();
const { query, teams, selected, selectedId, isOpen, toggle, setAll, select } = useOrgTree(() => props.tree);
</script>

<template>
    <Head title="Struktur Organisasi" />
    <AppLayout>
        <template #title>Struktur Organisasi</template>

        <p class="mb-3 text-sm text-gray-600">
            Satuan kerja, kepala, dan tim kerja beserta anggotanya. Gulir daftar: satuan kerja dan tim yang sedang dilihat tetap terlihat di atas. Satu pegawai bisa ada di beberapa tim.
        </p>

        <div class="mb-3 flex flex-wrap items-center gap-2">
            <div class="relative w-full max-w-sm">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" aria-hidden="true" />
                <Input v-model="query" class="pl-9" placeholder="Cari nama pegawai atau tim" aria-label="Cari nama pegawai atau tim" />
            </div>
            <Button type="button" variant="outline" size="sm" @click="setAll(true)">Buka semua tim</Button>
            <Button type="button" variant="outline" size="sm" @click="setAll(false)">Tutup semua tim</Button>
        </div>

        <div v-if="selected" class="mb-3 flex items-start gap-2 rounded-md border border-orange-300 bg-orange-50 px-3 py-2 text-sm" role="status">
            <p class="flex-1"><b>{{ selected.person.name }}</b>
                <span v-if="selected.person.position" class="text-gray-600"> ({{ selected.person.position }})</span>
                ada di {{ selected.teams.length }} tim: {{ selected.teams.join(', ') }}.</p>
            <button type="button" class="shrink-0 text-gray-500 hover:text-gray-800" aria-label="Batalkan pilihan" @click="select(selectedId as number)"><X class="h-4 w-4" aria-hidden="true" /></button>
        </div>

        <div v-if="!teams.length" class="rounded-md border border-dashed bg-gray-50 py-10 text-center text-sm text-gray-500">Tidak ada pegawai atau tim yang cocok.</div>
        <div v-else class="max-h-[calc(100vh-17rem)] overflow-y-auto rounded-xl border bg-white">
            <OrgTree :tree="tree" :teams="teams" :selected-id="selectedId" :is-open="isOpen" @toggle="toggle" @select="select" />
        </div>
    </AppLayout>
</template>
