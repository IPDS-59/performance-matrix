<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { Lock, LockOpen, Search } from 'lucide-vue-next';
import type { RkPanel } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import InputError from '@/Components/InputError.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { appendSentence, filterEntries } from '@/composables/useRkRecap';
import { achievementPercent, withUnit } from '@/composables/useAchievement';

/**
 * One RK in a week or month. Left: what the members wrote (read only). Right: what the PJ
 * writes, and one target, satuan and capaian for the whole RK. No text is filled in for the PJ.
 */
const props = defineProps<{
    panel: RkPanel;
    teamId: number;
    periodType: 'week' | 'month';
    /** Any date of the period; the server takes the Monday or the 1st. */
    periodStart: string;
    /** The PJ or the RK's PIC. */
    canEdit: boolean;
}>();

// The head reads figures and status; the PJ's paraphrase is a working text for the team, not for the head.
const isHead = usePage().props.auth.user.role === 'head';

const form = useForm({ paraphrase: '', target: '' as string | number, target_unit: '', capaian: '' as string | number });
watch(() => [props.panel.performance_plan_id, props.panel.recap] as const, () => {
    form.clearErrors();
    form.paraphrase = props.panel.recap?.paraphrase ?? '';
    form.target = props.panel.recap?.target ?? '';
    form.target_unit = props.panel.recap?.target_unit ?? props.panel.default_unit ?? '';
    form.capaian = props.panel.recap?.capaian ?? '';
}, { immediate: true });

const dialogOpen = ref(false);
const locked = computed(() => props.panel.recap?.locked === true);
const editable = computed(() => props.canEdit && !locked.value);

// Reading state lives in the browser for this meeting; nothing is stored.
const read = reactive(new Set<number>());
const open = reactive(new Set<number>());
const query = ref('');
const onlyUnread = ref(false);
const person = ref<number | null>(null);

const shown = computed(() => filterEntries(props.panel.entries, { query: query.value, onlyUnread: onlyUnread.value, person: person.value, read }));
const readCount = computed(() => props.panel.entries.filter(e => read.has(e.employee_id)).length);

function toggle(id: number) {
    if (open.has(id)) open.delete(id); else open.add(id);
    read.add(id);
}

function insert(line: string) {
    if (editable.value) form.paraphrase = appendSentence(form.paraphrase, line);
}

const key = () => ({ team_id: props.teamId, performance_plan_id: props.panel.performance_plan_id, period_type: props.periodType, period_start: props.periodStart });

function save(lock = false) {
    form.transform((data: Record<string, any>) => ({
        ...key(),
        paraphrase: data.paraphrase || null,
        target: data.target === '' ? null : data.target,
        target_unit: data.target_unit || null,
        capaian: data.capaian === '' ? null : data.capaian,
        lock,
    })).post(route('team-recap.rk.store'), { preserveScroll: true, onSuccess: () => (dialogOpen.value = false) });
}

function unlock() {
    router.post(route('team-recap.rk.unlock'), key(), { preserveScroll: true });
}

const percent = computed(() => achievementPercent(form.capaian === '' ? null : Number(form.capaian), form.target === '' ? null : Number(form.target)));
</script>

<template>
    <section class="rounded-lg border bg-white" :aria-label="panel.rk_description">
        <div class="flex flex-wrap items-center gap-3 border-b px-4 py-3">
            <h2 class="text-sm font-semibold">
                {{ panel.rk_description }}
                <span class="font-normal text-gray-600">{{ panel.project_name ? `${panel.project_name} · ` : '' }}{{ panel.entry_count }} kegiatan dari {{ panel.entries.length }} anggota</span>
            </h2>
            <Button v-if="!isHead" class="ml-auto" size="sm" :variant="panel.recap?.paraphrase || !canEdit ? 'outline' : 'default'" @click="dialogOpen = true">
                {{ locked || !canEdit ? 'Lihat parafrase' : panel.recap?.paraphrase ? 'Ubah parafrase' : 'Isi parafrase' }}
            </Button>
        </div>

        <!-- Summary of what the PJ wrote -->
        <div :class="['grid gap-3 border-b px-4 py-3 text-sm', isHead ? '' : 'sm:grid-cols-[1fr_auto]']">
            <div v-if="!isHead">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-600">Parafrase PJ</div>
                <p v-if="panel.recap?.paraphrase" class="mt-1 whitespace-pre-line text-gray-900">{{ panel.recap.paraphrase }}</p>
                <p v-else class="mt-1 text-gray-600">{{ canEdit ? 'Belum ada parafrase. Tekan “Isi parafrase”.' : 'Belum ada parafrase. PJ tim atau PIC RK yang mengisinya.' }}</p>
            </div>
            <dl class="grid grid-cols-[auto_auto] gap-x-4 gap-y-0.5 tabular-nums sm:text-right">
                <dt class="text-gray-600">Target RK</dt><dd>{{ withUnit(panel.recap?.target ?? null, panel.recap?.target_unit ?? panel.default_unit ?? null) }}</dd>
                <dt class="text-gray-600">Capaian RK</dt><dd>{{ withUnit(panel.recap?.capaian ?? null, panel.recap?.target_unit ?? panel.default_unit ?? null) }}<template v-if="achievementPercent(panel.recap?.capaian ?? null, panel.recap?.target ?? null) !== null"> ({{ achievementPercent(panel.recap?.capaian ?? null, panel.recap?.target ?? null) }}%)</template></dd>
                <dt class="text-gray-600">Status</dt><dd>{{ locked ? 'Terkunci' : 'Belum dikunci' }}</dd>
            </dl>
        </div>

        <!-- Members: short table, click for the full lines -->
        <Table>
            <TableHeader><TableRow><TableHead>Anggota</TableHead><TableHead>Kegiatan</TableHead><TableHead>Target</TableHead><TableHead>Capaian</TableHead></TableRow></TableHeader>
            <TableBody>
                <TableRow v-for="e in panel.entries" :key="e.employee_id" class="cursor-pointer" tabindex="0" @click="dialogOpen = true; person = e.employee_id" @keydown.enter="dialogOpen = true; person = e.employee_id">
                    <TableCell class="whitespace-nowrap font-medium">{{ e.name }}</TableCell>
                    <TableCell class="max-w-sm truncate text-gray-600">{{ e.lines[0] }}</TableCell>
                    <TableCell class="tabular-nums">{{ withUnit(e.target, e.unit) }}</TableCell>
                    <TableCell class="tabular-nums">{{ withUnit(e.capaian, e.unit) }}<template v-if="achievementPercent(e.capaian, e.target) !== null"> ({{ achievementPercent(e.capaian, e.target) }}%)</template></TableCell>
                </TableRow>
            </TableBody>
        </Table>
        <p v-if="!panel.entries.length" class="px-4 py-6 text-center text-sm text-gray-600">Belum ada kegiatan anggota untuk RK ini.</p>

        <Dialog :open="dialogOpen" @update:open="(v: boolean) => { dialogOpen = v; if (!v) person = null; }">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle>Parafrase: {{ panel.rk_description }}</DialogTitle>
                    <DialogDescription>Baca kontribusi anggota di kiri, lalu tulis parafrase di kanan. Tidak ada isi otomatis.</DialogDescription>
                </DialogHeader>
                <div class="grid overflow-hidden rounded-lg border lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
                    <div class="min-w-0 border-b bg-gray-50/60 p-4 lg:border-b-0 lg:border-r">
                        <div class="mb-2 flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-gray-600">
                            <span>Kontribusi anggota</span><span class="tabular-nums">Dibaca {{ readCount }}/{{ panel.entries.length }}</span>
                        </div>
                        <div class="relative mb-2">
                            <Search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-500" aria-hidden="true" />
                            <Input v-model="query" class="pl-9" placeholder="Cari nama atau isi kegiatan" aria-label="Cari anggota" />
                        </div>
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-600">
                            <label class="flex items-center gap-1.5"><input v-model="onlyUnread" type="checkbox" class="h-4 w-4" /> Belum dibaca saja</label>
                            <Select v-if="panel.entries.length > 1" :model-value="person === null ? 'all' : String(person)" @update:model-value="(v) => (person = v === 'all' ? null : Number(v))">
                                <SelectTrigger class="h-8 w-44 text-xs" aria-label="Pilih anggota"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua anggota</SelectItem>
                                    <SelectItem v-for="e in panel.entries" :key="e.employee_id" :value="String(e.employee_id)">{{ e.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <span>Menampilkan {{ shown.length }} dari {{ panel.entries.length }}</span>
                        </div>

                        <details v-if="panel.weeks.length" class="mb-2 rounded-lg border bg-white">
                            <summary class="cursor-pointer px-3 py-2 text-sm font-semibold">Parafrase mingguan bulan ini ({{ panel.weeks.length }})</summary>
                            <ul class="space-y-1.5 px-3 pb-3 text-sm">
                                <li v-for="w in panel.weeks" :key="w.start" class="flex items-start gap-2">
                                    <span class="flex-1"><span class="font-medium">{{ w.label }}:</span> {{ w.paraphrase }}</span>
                                    <Button size="sm" variant="outline" class="h-7 text-xs" :disabled="!editable" @click="insert(w.paraphrase)">Sisipkan</Button>
                                </li>
                            </ul>
                        </details>

                        <ul class="max-h-80 space-y-2 overflow-y-auto pr-1">
                            <li v-for="e in shown" :key="e.employee_id" class="rounded-lg border bg-white">
                                <button type="button" class="flex w-full items-baseline gap-2 px-3 py-2 text-left text-sm" :aria-expanded="open.has(e.employee_id)" @click="toggle(e.employee_id)">
                                    <span class="whitespace-nowrap font-semibold">{{ e.name }}<span v-if="read.has(e.employee_id)" class="ml-1 text-green-700" aria-label="sudah dibaca">✓</span></span>
                                    <span v-if="!open.has(e.employee_id)" class="min-w-0 flex-1 truncate text-gray-600">{{ e.lines[0] }}</span>
                                </button>
                                <div v-if="open.has(e.employee_id)" class="space-y-1.5 px-3 pb-3 text-sm">
                                    <div v-for="line in e.lines" :key="line" class="flex items-start gap-2">
                                        <span class="flex-1">{{ line }}</span>
                                        <Button size="sm" variant="outline" class="h-7 text-xs" :disabled="!editable" @click="insert(line)">Sisipkan</Button>
                                    </div>
                                    <p class="text-xs tabular-nums text-gray-700">
                                        Target {{ withUnit(e.target, e.unit) }} · Capaian {{ withUnit(e.capaian, e.unit) }}
                                        <template v-if="achievementPercent(e.capaian, e.target) !== null">({{ achievementPercent(e.capaian, e.target) }}%)</template>
                                    </p>
                                    <p class="text-xs text-gray-600">Diisi {{ e.name.split(' ')[0] }} sendiri. PJ tidak mengubahnya.</p>
                                </div>
                            </li>
                        </ul>
                        <p v-if="!shown.length" class="py-6 text-center text-sm text-gray-600">{{ panel.entries.length ? 'Tidak ada anggota yang cocok.' : 'Belum ada kegiatan anggota untuk RK ini.' }}</p>
                    </div>

                    <form class="min-w-0 space-y-3 p-4" @submit.prevent="save(false)">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-600">Parafrase PJ</div>
                        <Textarea v-model="form.paraphrase" :rows="6" :disabled="!editable" placeholder="Tulis parafrase sendiri. Pakai “Sisipkan” di kiri untuk mengambil satu kalimat." />
                        <InputError :message="form.errors.paraphrase" />
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1.5"><Label for="rk-t">Target RK</Label><Input id="rk-t" v-model="form.target" type="number" min="0" step="any" :disabled="!editable" /></div>
                            <div class="space-y-1.5"><Label for="rk-u">Satuan</Label><Input id="rk-u" v-model="form.target_unit" :disabled="!editable" /></div>
                            <div class="space-y-1.5"><Label for="rk-c">Capaian RK</Label><Input id="rk-c" v-model="form.capaian" type="number" min="0" step="any" :disabled="!editable" /></div>
                        </div>
                        <InputError :message="form.errors.target || form.errors.capaian || form.errors.target_unit" />
                        <p v-if="percent !== null" class="text-sm tabular-nums text-gray-700">Capaian {{ percent }}% dari target.</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button type="submit" :disabled="!editable || form.processing">Simpan</Button>
                            <Button v-if="!locked" type="button" variant="outline" :disabled="!editable || form.processing" @click="save(true)"><Lock class="mr-1.5 h-4 w-4" aria-hidden="true" /> Simpan dan kunci</Button>
                            <Button v-else-if="canEdit" type="button" variant="outline" @click="unlock"><LockOpen class="mr-1.5 h-4 w-4" aria-hidden="true" /> Buka kunci</Button>
                            <span class="ml-auto text-sm text-gray-600">{{ locked ? `Terkunci${panel.recap?.locked_by ? ` oleh ${panel.recap.locked_by}` : ''}` : canEdit ? 'Belum dikunci' : 'Hanya PJ atau PIC RK yang dapat mengubah' }}</span>
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    </section>
</template>
