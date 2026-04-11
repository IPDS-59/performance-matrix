<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { Employee, Project } from '@/types';
import { ref, computed } from 'vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/Components/ui/accordion';
import { Progress } from '@/Components/ui/progress';
import InputError from '@/Components/InputError.vue';

interface WorkItemWithReports {
    id: number;
    number: number;
    description: string;
    performance_reports: Array<{
        id: number;
        achievement_percentage: number;
        issues: string | null;
        solutions: string | null;
        action_plan: string | null;
    }>;
}

interface ProjectWithItems extends Project {
    work_items: WorkItemWithReports[];
}

const props = defineProps<{
    employee: Pick<Employee, 'id' | 'name' | 'display_name'>;
    projects: ProjectWithItems[];
    filters: { year: number; month: number };
}>();

const year = ref(props.filters.year);
const month = ref(props.filters.month);

function applyFilters() {
    router.get(route('performance.index'), { year: year.value, month: month.value }, { preserveState: true });
}

const months = [
    { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' }, { value: 4, label: 'April' },
    { value: 5, label: 'Mei' }, { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' }, { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' }, { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' }, { value: 12, label: 'Desember' },
];

// Form state for batch submission
type ItemForm = {
    work_item_id: number;
    achievement_percentage: number;
    issues: string;
    solutions: string;
    action_plan: string;
};

const form = useForm<{
    period_month: number;
    period_year: number;
    items: ItemForm[];
}>({
    period_month: props.filters.month,
    period_year: props.filters.year,
    items: [],
});

// Initialize form items from existing reports
const itemMap = computed(() => {
    const map: Record<number, ItemForm> = {};
    props.projects.forEach(project => {
        project.work_items.forEach(wi => {
            const report = wi.performance_reports[0];
            map[wi.id] = {
                work_item_id: wi.id,
                achievement_percentage: report?.achievement_percentage ?? 0,
                issues: report?.issues ?? '',
                solutions: report?.solutions ?? '',
                action_plan: report?.action_plan ?? '',
            };
        });
    });
    return map;
});

function getItem(workItemId: number): ItemForm {
    const existing = form.items.find(i => i.work_item_id === workItemId);
    if (!existing) {
        const fromMap = itemMap.value[workItemId];
        form.items.push(fromMap ?? {
            work_item_id: workItemId,
            achievement_percentage: 0,
            issues: '',
            solutions: '',
            action_plan: '',
        });
    }
    return form.items.find(i => i.work_item_id === workItemId)!;
}

function progressColor(pct: number): string {
    if (pct >= 80) return '[&>div]:bg-green-500';
    if (pct >= 50) return '[&>div]:bg-yellow-500';
    return '[&>div]:bg-red-500';
}

function submit() {
    form.post(route('performance.batch'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Input Kinerja" />
    <AppLayout>
        <template #title>Input Kinerja Bulanan</template>

        <!-- Filters -->
        <div class="mb-6 flex items-center gap-3">
            <Select v-model="month" @update:modelValue="applyFilters">
                <SelectTrigger class="w-40">
                    <SelectValue placeholder="Bulan" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="year" @update:modelValue="applyFilters">
                <SelectTrigger class="w-28">
                    <SelectValue placeholder="Tahun" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="y in [2025, 2026, 2027]" :key="y" :value="y">{{ y }}</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div v-if="!projects.length" class="py-16 text-center text-gray-400">
            Tidak ada proyek yang ditugaskan untuk periode ini.
        </div>

        <form v-else @submit.prevent="submit" class="space-y-4">
            <Accordion type="multiple" class="space-y-3">
                <AccordionItem
                    v-for="project in projects"
                    :key="project.id"
                    :value="String(project.id)"
                    class="rounded-md border bg-white px-4"
                >
                    <AccordionTrigger class="py-3">
                        <div class="flex items-center gap-3 text-left">
                            <Badge variant="outline" class="shrink-0 text-xs">{{ project.team?.name }}</Badge>
                            <span class="font-medium">{{ project.name }}</span>
                        </div>
                    </AccordionTrigger>
                    <AccordionContent>
                        <div class="space-y-6 pb-4">
                            <div
                                v-for="wi in project.work_items"
                                :key="wi.id"
                                class="rounded-md border border-gray-100 bg-gray-50 p-4"
                            >
                                <p class="mb-3 text-sm font-medium text-gray-700">
                                    {{ wi.number }}. {{ wi.description }}
                                </p>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <Label>Capaian (%)</Label>
                                        <div class="mt-1 flex items-center gap-3">
                                            <Input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                v-model="getItem(wi.id).achievement_percentage"
                                                class="w-28"
                                            />
                                            <Progress
                                                :model-value="Number(getItem(wi.id).achievement_percentage)"
                                                :class="['flex-1', progressColor(Number(getItem(wi.id).achievement_percentage))]"
                                            />
                                        </div>
                                        <InputError :message="form.errors[`items.${form.items.findIndex(i => i.work_item_id === wi.id)}.achievement_percentage`]" />
                                    </div>
                                    <div>
                                        <Label>Kendala</Label>
                                        <Textarea
                                            v-model="getItem(wi.id).issues"
                                            rows="2"
                                            class="mt-1"
                                            placeholder="(opsional)"
                                        />
                                    </div>
                                    <div>
                                        <Label>Solusi</Label>
                                        <Textarea
                                            v-model="getItem(wi.id).solutions"
                                            rows="2"
                                            class="mt-1"
                                            placeholder="(opsional)"
                                        />
                                    </div>
                                    <div>
                                        <Label>Rencana Tindak Lanjut</Label>
                                        <Textarea
                                            v-model="getItem(wi.id).action_plan"
                                            rows="2"
                                            class="mt-1"
                                            placeholder="(opsional)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </AccordionContent>
                </AccordionItem>
            </Accordion>

            <div class="flex justify-end pt-2">
                <Button type="submit" :disabled="form.processing" class="bg-[#1B4B8A] hover:bg-[#163d70]">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Laporan' }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
