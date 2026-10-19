<script setup lang="ts">
import type { SkpEvidenceRow } from '@/types';

defineProps<{ rows: SkpEvidenceRow[] }>();

const num = (n: number) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(n);
</script>

<template>
    <p v-if="!rows.length" class="text-sm text-gray-500">Belum ada kegiatan pegawai ini di Kinetik pada triwulan ini.</p>
    <table v-else class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                <th class="py-1 pr-3 font-medium">RK</th>
                <th class="px-3 py-1 text-right font-medium">Kegiatan</th>
                <th class="px-3 py-1 text-right font-medium">Selesai</th>
                <th class="px-3 py-1 text-right font-medium">Terkirim</th>
                <th class="px-3 py-1 text-right font-medium">Capaian / target</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 align-top">
            <tr v-for="r in rows" :key="r.rk">
                <td class="py-1.5 pr-3 text-gray-900">
                    {{ r.rk }}
                    <span v-for="c in r.catatan" :key="c" class="mt-0.5 block text-xs text-gray-500">“{{ c }}”</span>
                </td>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ r.kegiatan }}</td>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ r.selesai }}</td>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ r.terkirim }}</td>
                <td class="px-3 py-1.5 text-right tabular-nums">
                    <template v-if="r.target !== null || r.capaian !== null">{{ r.capaian !== null ? num(r.capaian) : '—' }} / {{ r.target !== null ? num(r.target) : '—' }} {{ r.unit }}</template>
                    <template v-else>—</template>
                </td>
            </tr>
        </tbody>
    </table>
</template>
