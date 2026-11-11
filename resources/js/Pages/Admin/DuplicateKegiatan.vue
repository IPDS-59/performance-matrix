<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Badge } from '@/Components/ui/badge';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { useDateFormat } from '@/composables/useDateFormat';

interface Side { id: number; where: string; source: string; progress: number; has_capaian: boolean; has_bukti: boolean }
interface Pair {
    key: string; employee_id: number; employee: string | null; date: string; description: string;
    keep: Side; drop: Side; how: string; same_rk: boolean; asked: { at: string; by: string | null } | null;
}

const props = defineProps<{ pairs: Pair[] }>();
const { formatDate } = useDateFormat();

const picked = ref<string[]>([]);
const busy = ref(false);
const filter = ref<number | null>(null);
const personChoices = computed(() => [...new Map(props.pairs.map(p => [p.employee_id, p.employee ?? '-'])).entries()].map(([id, name]) => ({ value: id, label: `${name} (${props.pairs.filter(x => x.employee_id === id).length})` })));
const shown = computed(() => props.pairs.filter(p => filter.value === null || p.employee_id === filter.value));
const notAsked = computed(() => shown.value.filter(p => !p.asked).map(p => p.key));
const allPicked = computed(() => shown.value.length > 0 && shown.value.every(p => picked.value.includes(p.key)));

function toggle(key: string, on: boolean) { picked.value = on ? [...new Set([...picked.value, key])] : picked.value.filter(k => k !== key); }
function toggleAll(on: boolean) { const keys = shown.value.map(p => p.key); picked.value = on ? [...new Set([...picked.value, ...keys])] : picked.value.filter(k => !keys.includes(k)); }
function ask(keys: string[]) {
    if (!keys.length || busy.value) return;
    busy.value = true;
    router.post(route('admin.duplicate-kegiatan.ask'), { keys }, { preserveScroll: true, onSuccess: () => { picked.value = picked.value.filter(k => !keys.includes(k)); }, onFinish: () => { busy.value = false; } });
}
// "Bukan ganda" asks first: the pair leaves the list for good.
const dismissing = ref<string[]>([]);
function dismiss() {
    const keys = dismissing.value;
    if (!keys.length || busy.value) return;
    busy.value = true;
    router.post(route('admin.duplicate-kegiatan.dismiss'), { keys }, { preserveScroll: true, onSuccess: () => { picked.value = picked.value.filter(k => !keys.includes(k)); dismissing.value = []; }, onFinish: () => { busy.value = false; } });
}
const side = (s: Side) => `#${s.id} · ${s.where}${s.has_capaian ? ' · ada capaian' : ''}${s.has_bukti ? ' · ada bukti' : ''}`;
</script>

<template>
    <Head title="Kegiatan ganda" />
    <AppLayout>
        <template #title>Kegiatan ganda</template>
        <p class="mb-3 text-sm text-gray-700">
            Dua kegiatan satu pegawai pada hari yang sama dengan kata-kata yang sama, paling-paling beda salah ketik, 60 hari terakhir. Tinjau dulu: bisa jadi memang dua pekerjaan yang mirip. Tandai "Bukan ganda" bila berbeda, supaya tidak muncul lagi. Tombol minta tindakan hanya mengirim pemberitahuan lewat lonceng ke pegawainya; admin tidak menghapus apa pun di sini.
            <Link :href="route('admin.data-integrity')" class="font-medium text-primary hover:underline">Kembali ke Integritas data</Link>
        </p>

        <div v-if="!pairs.length" class="rounded-lg border border-dashed bg-white py-10 text-center text-sm text-gray-600">Tidak ada kegiatan ganda.</div>

        <template v-else>
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <span class="text-sm text-gray-700">Pegawai</span>
                <div class="w-64"><SearchableSelect :model-value="filter" :options="personChoices" placeholder="Semua pegawai" search-placeholder="Cari pegawai…" :empty-label="`Semua (${$n(pairs.length)})`" @update:model-value="(v: number | null) => (filter = v)" /></div>
                <Button variant="outline" size="sm" :disabled="busy || !picked.length" @click="ask(picked)">Minta tindakan ({{ $n(picked.length) }})</Button>
                <Button variant="outline" size="sm" :disabled="busy || !notAsked.length" @click="ask(notAsked)">Minta semua yang belum diminta ({{ $n(notAsked.length) }})</Button>
                <Button variant="ghost" size="sm" :disabled="busy || !picked.length" @click="dismissing = [...picked]">Bukan ganda ({{ $n(picked.length) }})</Button>
            </div>

            <div class="overflow-x-auto rounded-lg border bg-white">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b bg-gray-50 text-gray-600">
                            <th class="w-8 px-3 py-2"><Checkbox :model-value="allPicked" aria-label="Pilih semua" @update:model-value="(v: boolean | 'indeterminate') => toggleAll(v === true)" /></th>
                            <th class="px-2 py-2 font-medium">Pegawai</th>
                            <th class="px-2 py-2 font-medium">Tanggal</th>
                            <th class="px-2 py-2 font-medium">Kegiatan</th>
                            <th class="px-2 py-2 font-medium">Disarankan dipertahankan</th>
                            <th class="px-2 py-2 font-medium">Disarankan dihapus</th>
                            <th class="px-2 py-2 font-medium">Status</th>
                            <th class="px-2 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in shown" :key="p.key" class="border-b last:border-0 align-top">
                            <td class="px-3 py-2"><Checkbox :model-value="picked.includes(p.key)" :aria-label="`Pilih ${p.description}`" @update:model-value="(v: boolean | 'indeterminate') => toggle(p.key, v === true)" /></td>
                            <td class="px-2 py-2 font-medium">{{ p.employee }}</td>
                            <td class="px-2 py-2 whitespace-nowrap tabular-nums">{{ formatDate(p.date, { day: 'numeric', month: 'short' }) }}</td>
                            <td class="max-w-xs px-2 py-2">{{ p.description }}<Badge v-if="!p.same_rk" variant="secondary" class="ml-1">RK beda</Badge></td>
                            <td class="px-2 py-2">{{ side(p.keep) }}</td>
                            <td class="px-2 py-2">{{ side(p.drop) }}<span class="block text-gray-500">{{ p.how }}</span></td>
                            <td class="px-2 py-2 whitespace-nowrap">
                                <span v-if="p.asked" class="text-green-700">Diminta {{ formatDate(p.asked.at, { day: 'numeric', month: 'short' }) }}<span v-if="p.asked.by"> oleh {{ p.asked.by }}</span></span>
                                <span v-else class="text-gray-500">Belum diminta</span>
                            </td>
                            <td class="px-2 py-2">
                                <div class="flex gap-1">
                                    <Button variant="outline" size="sm" :disabled="busy" @click="ask([p.key])">{{ p.asked ? 'Minta lagi' : 'Minta tindakan' }}</Button>
                                    <Button variant="ghost" size="sm" :disabled="busy" @click="dismissing = [p.key]">Bukan ganda</Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <ConfirmDialog
            :open="dismissing.length > 0"
            title="Bukan kegiatan ganda"
            :description="`Tandai ${dismissing.length} pasangan sebagai dua pekerjaan yang berbeda? Pasangan ini tidak akan muncul lagi di daftar.`"
            confirm-label="Ya, bukan ganda"
            cancel-label="Batal"
            variant="warning"
            @update:open="(v: boolean) => { if (!v) dismissing = []; }"
            @confirm="dismiss"
        />
    </AppLayout>
</template>
