<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { ListChecks } from 'lucide-vue-next';
import type { PlanItemRow, PlanRkOption, PresensiRow } from '@/types';
import FormDialog from '@/Components/FormDialog.vue';
import InputError from '@/Components/InputError.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import PresensiPickerDialog from '@/Components/Kinetik/PresensiPickerDialog.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { type EntryFormState, dateEndAfterRangeToggle, defaultProgress, entryFormState, entryPayload, quarterBounds, todayIso } from '@/composables/usePlanEntry';

/** Add, edit or copy one kegiatan, with the same fields as kipApp plus target and satuan. Capaian and bukti dukung are filled in later, from the kegiatan's detail, when the capaian is updated. */
const props = defineProps<{
    open: boolean;
    /** The kegiatan to edit, or null to add one. */
    item: PlanItemRow | null;
    /** The kegiatan to copy (a new kegiatan filled from it), or null. */
    copyOf: PlanItemRow | null;
    rkOptions: PlanRkOption[];
    projectOptions: Array<{ id: number; name: string }>;
    /** Default start date of a new kegiatan. */
    defaultDate: string;
    /** Dates must stay in one quarter of this year. */
    skpYear: number;
}>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm(entryFormState(null, null, '', todayIso()));
const presensiOpen = ref(false);

// RK tambahan: extra work outside the team's RK gets an RK of its own, added here and then picked.
const addingRk = ref(false);
const newRk = ref('');
const newRkError = ref('');
const newRkBusy = ref(false);

async function addRk() {
    newRkBusy.value = true;
    newRkError.value = '';
    try {
        const { data } = await axios.post(route('rk-additional.store'), { description: newRk.value });
        router.reload({ only: ['rkOptions'], onSuccess: () => { pickRk(data.id); addingRk.value = false; newRk.value = ''; } });
    } catch (e) {
        const err = e as { response?: { data?: { errors?: { description?: string[] } } } };
        newRkError.value = err.response?.data?.errors?.description?.[0] ?? 'RK tambahan belum tersimpan. Coba lagi.';
    } finally {
        newRkBusy.value = false;
    }
}
// A kegiatan that was already sent only changes "Masukan ke capaian SKP", like kipApp.
const sentOnly = computed(() => !!props.item?.sent);
// The RK (and projek) can still change while the kegiatan is only in Kinetik, e.g. after an Excel import guessed it wrong.
const rkEditable = computed(() => !props.item || (!props.item.in_kipapp && props.item.status === 'planned'));

watch(() => [props.open, props.item, props.copyOf] as const, ([open, item, copyOf]) => {
    if (!open) return;
    form.clearErrors();
    Object.assign(form, entryFormState(item, copyOf, props.defaultDate, todayIso()));
    // A kegiatan filed under a copy of an RK shows (and keeps) the option that stands for that RK.
    const own = props.rkOptions.find(o => o.id === form.performance_plan_id || o.alias_ids.includes(form.performance_plan_id as number));
    if (own) form.performance_plan_id = own.id;
    externalSelected.value = null;
}, { immediate: true });

// Logging past work starts at 100 (kipApp); planning starts at 0.
watch(() => form.date_start, (date) => {
    if (!props.item) form.progres = defaultProgress(date, todayIso());
    if (!form.use_range || form.date_end < date) form.date_end = date;
});
watch(() => form.use_range, (on) => { form.date_end = dateEndAfterRangeToggle(on, form.date_start, form.date_end); });

const rk = computed(() => props.rkOptions.find(o => o.id === form.performance_plan_id) ?? null);
const needsProject = computed(() => !!rk.value && !rk.value.project_id && !rk.value.project_optional);

/** Whether an RK belongs to a projek: its own projek, or one of the projek it can be linked to. */
function rkFitsProject(option: PlanRkOption, projectId: number | null): boolean {
    return projectId === null || option.project_id === projectId || option.ketua_projects.includes(projectId) || (option.project_id === null && option.project_candidates.includes(projectId));
}

// Projek first, then only the RK of that projek. Once an RK is chosen the projek is fixed; "Ganti RK" frees it again.
// An RK of a Pusat team carries its projek name in the label, so searching the projek finds it.
// A projek of a Pusat team is no row in Kinetik, so it cannot be a project_id. It is still offered in the Projek list (value below 0, nothing
// is sent to the server) so the member finds their RK the same way: pick the projek, then its RK.
const externalSelected = ref<string | null>(null);
const externalProjects = computed(() => [...new Map(props.rkOptions.filter(o => o.external_project).map(o => [o.external_project as string, o.team_name])).entries()]
    .map(([name, team], i) => ({ value: -(i + 1), name, label: `${name} (${team ?? 'tim pusat'})` })));
const rkChoices = computed(() => props.rkOptions
    .filter(o => (externalSelected.value ? o.external_project === externalSelected.value : rkFitsProject(o, form.project_id)))
    .map(o => ({ value: o.id, label: o.external_project ? `${o.description} (Projek: ${o.external_project})` : o.description })));
const projectChoices = computed(() => {
    const chosen = rk.value;
    if (chosen?.external_project) return externalProjects.value.filter(p => p.name === chosen.external_project).map(p => ({ value: p.value, label: p.label }));
    if (chosen?.project_id) return props.projectOptions.filter(p => p.id === chosen.project_id).map(p => ({ value: p.id, label: p.name }));
    const ids = [...(chosen?.project_candidates ?? []), ...(chosen?.ketua_projects ?? [])];
    const own = props.projectOptions.filter(p => !ids.length || ids.includes(p.id)).map(p => ({ value: p.id, label: p.name }));
    return chosen ? own : [...own, ...externalProjects.value.map(p => ({ value: p.value, label: p.label }))];
});
/** What the Projek select shows: the real projek, or the Pusat projek of the chosen RK (or the one picked first). */
const projectModel = computed(() => {
    if (form.project_id !== null) return form.project_id;
    const name = rk.value?.external_project ?? externalSelected.value;
    return name ? (externalProjects.value.find(p => p.name === name)?.value ?? null) : null;
});
const projectLocked = computed(() => (!!props.item && !rkEditable.value) || (form.performance_plan_id !== null && (form.project_id !== null || !!rk.value?.external_project)));
const bounds = computed(() => quarterBounds(props.skpYear, form.date_start));

function pickRk(value: number | null) {
    form.performance_plan_id = value;
    externalSelected.value = props.rkOptions.find(o => o.id === value)?.external_project ?? null;
    form.team_id = rk.value?.team_id ?? null;
    // An RK that belongs to one projek brings it along; otherwise the projek chosen before stays if the RK fits it.
    if (rk.value?.project_id) form.project_id = rk.value.project_id;
    else if (rk.value?.project_suggested) form.project_id = rk.value.project_suggested;
    else if (rk.value && !rkFitsProject(rk.value, form.project_id)) form.project_id = null;
    if (rk.value && !form.target_unit) form.target_unit = rk.value.target_unit ?? '';
}

function pickProject(value: number | null) {
    if (value !== null && value < 0) {
        // A Pusat projek: no project_id, only its RK are listed.
        externalSelected.value = externalProjects.value.find(p => p.value === value)?.name ?? null;
        form.project_id = null;
        if (rk.value && rk.value.external_project !== externalSelected.value) {
            form.performance_plan_id = null;
            form.team_id = null;
        }

        return;
    }
    externalSelected.value = null;
    form.project_id = value;
    // Another projek shows its own RK: an RK that does not fit it is cleared.
    if (rk.value && !rkFitsProject(rk.value, value)) {
        form.performance_plan_id = null;
        form.team_id = null;
    }
}

/** Clears the RK so the projek can be changed; the projek chosen stays until the user picks another. */
function changeRk() {
    form.performance_plan_id = null;
    form.team_id = null;
}

function applyPresensi(row: PresensiRow) {
    form.description = row.description;
    form.use_range = false;
    form.date_start = row.date;
    form.date_end = row.date;
}

function save() {
    form.transform((data: EntryFormState) => (sentOnly.value ? { is_capaian_skp: data.is_capaian_skp } : entryPayload(data)));
    const options = { preserveScroll: true, onSuccess: () => emit('update:open', false) };
    if (props.item) form.patch(route('plan-items.update', props.item.id), options);
    else form.post(route('plan-items.store'), options);
}
</script>

<template>
    <FormDialog
        :open="open"
        :title="item ? 'Ubah kegiatan' : copyOf ? 'Salin kegiatan' : 'Tambah kegiatan'"
        :description="sentOnly ? 'Kegiatan ini sudah dikirim ke atasan. Hanya “Masukan ke capaian SKP” yang bisa diubah.' : 'Kegiatan dikirim ke kipApp pada tanggal mulainya, atau saat Anda menekan Kirim ke kipApp.'"
        :processing="form.processing"
        @update:open="(v: boolean) => emit('update:open', v)"
        @submit="save"
    >
        <fieldset :disabled="sentOnly" class="min-w-0 space-y-3 border-0 p-0">
            <div class="space-y-1.5">
                <Label>Projek</Label>
                <SearchableSelect :model-value="projectModel" :options="projectChoices" :placeholder="needsProject ? 'Pilih projek' : 'Pilih projek (opsional)'" search-placeholder="Cari projek…" :empty-label="needsProject ? undefined : '— Tanpa projek —'" :disabled="projectLocked" @update:model-value="pickProject" />
                <p v-if="projectLocked && !item" class="text-xs text-gray-600">Projek terkunci karena RK sudah dipilih. Tekan "Ganti RK" untuk memilih projek lain.</p>
                <p v-if="rk?.external_project" class="text-xs text-gray-600">Projek di kipApp: {{ rk.external_project }} ({{ rk.team_name }}). Projek tim pusat tidak disimpan sebagai projek di Kinetik; kegiatan tetap dikirim ke RK ini di kipApp.</p>
                <InputError :message="form.errors.project_id" />
            </div>
            <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                    <Label>Rencana Kinerja (RK)</Label>
                    <Button v-if="form.performance_plan_id !== null && rkEditable" type="button" variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="changeRk">Ganti RK</Button>
                </div>
                <SearchableSelect :model-value="form.performance_plan_id" :options="rkChoices" :placeholder="form.project_id !== null ? 'Pilih RK projek ini' : 'Pilih RK'" search-placeholder="Cari RK…" :disabled="!rkEditable" @update:model-value="pickRk" />
                <InputError :message="form.errors.performance_plan_id" />
                <template v-if="!item">
                    <button v-if="!addingRk" type="button" class="text-xs text-primary underline" @click="addingRk = true">Pekerjaan di luar RK tim? Tambah RK tambahan</button>
                    <div v-else class="space-y-1.5 rounded-md border border-dashed p-2">
                        <Label for="new-rk" class="text-xs">RK tambahan baru (minimal 10 karakter)</Label>
                        <Input id="new-rk" v-model="newRk" placeholder="Deskripsi rencana kinerja" />
                        <p v-if="newRkError" class="text-xs text-red-700" role="alert">{{ newRkError }}</p>
                        <div class="flex gap-2">
                            <Button type="button" size="sm" :disabled="newRkBusy || newRk.trim().length < 10" @click="addRk">{{ newRkBusy ? 'Menyimpan…' : 'Simpan RK tambahan' }}</Button>
                            <Button type="button" size="sm" variant="ghost" @click="addingRk = false">Batal</Button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                    <Label for="pi-desc">Kegiatan</Label>
                    <Button type="button" variant="outline" size="sm" class="h-7 gap-1 px-2 text-xs" @click="presensiOpen = true"><ListChecks class="h-3.5 w-3.5" aria-hidden="true" /> List Aktivitas</Button>
                </div>
                <Textarea id="pi-desc" v-model="form.description" :rows="3" placeholder="Deskripsi Kegiatan" required />
                <InputError :message="form.errors.description" />
            </div>

            <div class="flex flex-wrap gap-4 text-sm">
                <label class="flex items-center gap-1.5"><input v-model="form.use_range" type="checkbox" /> Gunakan periode tanggal</label>
                <label class="flex items-center gap-1.5"><input v-model="form.use_time" type="checkbox" /> Gunakan jam</label>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <Label for="pi-start">{{ form.use_range ? 'Tanggal mulai' : 'Tanggal' }}</Label>
                    <Input id="pi-start" v-model="form.date_start" type="date" :min="bounds.min" :max="bounds.max" required />
                </div>
                <div v-if="form.use_range" class="space-y-1.5">
                    <Label for="pi-end">Tanggal selesai</Label>
                    <Input id="pi-end" v-model="form.date_end" type="date" :min="form.date_start" :max="bounds.max" required />
                </div>
            </div>
            <div v-if="form.use_time" class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5"><Label for="pi-ts">Jam mulai</Label><Input id="pi-ts" v-model="form.time_start" type="time" step="900" required /></div>
                <div class="space-y-1.5"><Label for="pi-te">Jam selesai</Label><Input id="pi-te" v-model="form.time_end" type="time" step="900" :min="form.time_start" required /></div>
            </div>
            <InputError :message="form.errors.date_end || form.errors.time_end || form.errors.date_start" />

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5"><Label for="pi-target">Target (angka)</Label><Input id="pi-target" v-model="form.target" type="number" min="0" step="any" /></div>
                <div class="space-y-1.5"><Label for="pi-unit">Satuan</Label><Input id="pi-unit" v-model="form.target_unit" placeholder="blok" /></div>
            </div>
            <InputError :message="form.errors.target" />
            <p class="text-xs text-gray-600">Progres, capaian, bukti dukung, dan uraian diisi nanti di form Realisasi pada detail kegiatan.</p>
        </fieldset>

        <label class="flex items-center gap-1.5 text-sm"><input v-model="form.is_capaian_skp" type="checkbox" /> Masukan ke capaian SKP</label>
        <InputError :message="form.errors.is_capaian_skp || form.errors.parent_id" />

        <PresensiPickerDialog v-model:open="presensiOpen" :date="form.date_start" @pick="applyPresensi" />
    </FormDialog>
</template>
