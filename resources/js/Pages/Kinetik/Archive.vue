<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import * as XLSX from 'xlsx';
import axios from 'axios';
import { Download } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';

const props = defineProps<{ isAdmin: boolean; ownEmployeeId: number | null; employees: Array<{ value: number; label: string }>; years: number[] }>();

const employeeId = ref<number | null>(props.ownEmployeeId);
const year = ref<number>(props.years[0]);
const busy = ref(false);
const error = ref('');
const done = ref('');

async function download() {
    if (!employeeId.value) return;
    busy.value = true; error.value = ''; done.value = '';
    try {
        const { data } = await axios.get(route('archive.data'), { params: { employee_id: employeeId.value, year: year.value } });
        const keg = XLSX.utils.json_to_sheet(data.kegiatan.map((k: Record<string, unknown>) => ({
            Tim: k.tim, Projek: k.projek, 'Rencana Kinerja': k.rk, 'RK Atasan': k.rk_atasan, Kegiatan: k.kegiatan, 'Tanggal Mulai': k.mulai, 'Tanggal Selesai': k.selesai,
            Target: k.target, Satuan: k.satuan, 'Progres (%)': k.progres, 'Uraian Capaian': k.uraian_capaian, 'Bukti Dukung': k.bukti, 'ID kipApp': k.id_kipapp,
        })));
        const rk = XLSX.utils.json_to_sheet(data.rk.map((r: Record<string, unknown>) => ({ 'Rencana Kinerja': r.rk, 'RK Atasan': r.rk_atasan })));
        const book = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(book, keg, 'Kegiatan');
        XLSX.utils.book_append_sheet(book, rk, 'RK');
        XLSX.writeFile(book, `arsip-kipapp-${String(data.employee.name).replace(/[^\w]+/g, '-')}-${year.value}.xlsx`);
        done.value = `${data.kegiatan.length} kegiatan dan ${data.rk.length} RK diunduh.`;
    } catch {
        error.value = 'Arsip belum bisa dibuat. Coba lagi, atau laporkan lewat tombol Laporkan masalah.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head title="Arsip kipApp" />
    <AppLayout>
        <template #title>Arsip kipApp</template>
        <div class="max-w-2xl space-y-4 rounded-xl border bg-white p-5">
            <p class="text-sm text-gray-700">Unduh riwayat kegiatan dan RK Anda dari Kinetik sebagai file Excel. File ini menyimpan catatan kerja Anda di kipApp (tim, projek, RK, kegiatan, capaian, bukti dukung), supaya tetap Anda pegang ketika pencatatan pindah ke e-Kinerja BKN.</p>
            <div class="flex flex-wrap items-end gap-4">
                <div v-if="isAdmin" class="min-w-64 space-y-1.5">
                    <Label>Pegawai</Label>
                    <SearchableSelect v-model="employeeId" :options="employees" placeholder="Pilih pegawai" search-placeholder="Cari pegawai…" />
                </div>
                <div class="space-y-1.5">
                    <Label for="arc-year">Tahun</Label>
                    <select id="arc-year" v-model.number="year" class="h-9 rounded-md border px-3 text-sm"><option v-for="y in years" :key="y" :value="y">{{ y }}</option></select>
                </div>
                <Button :disabled="!employeeId || busy" @click="download"><Download class="mr-1.5 h-4 w-4" aria-hidden="true" /> {{ busy ? 'Menyiapkan…' : 'Unduh Excel' }}</Button>
            </div>
            <p v-if="done" class="text-sm text-green-700" role="status">{{ done }}</p>
            <p v-if="error" class="text-sm text-red-700" role="alert">{{ error }}</p>
            <p class="text-xs text-gray-600">Kinetik belum bisa mengirim data ke e-Kinerja secara otomatis. BKN belum membuka akses resmi untuk aplikasi lain. File ini dipakai untuk mengisi atau memeriksa e-Kinerja secara manual, dan sebagai arsip.</p>
        </div>
    </AppLayout>
</template>
