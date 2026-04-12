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
    target: number;
    target_unit: string;
    performance_reports: Array<{
        id: number;
        realization: number;
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

const monthLabel = computed(() => months.find(m => m.value === props.filters.month)?.label ?? '');

// Group projects by team
const projectsByTeam = computed(() => {
    const groups: Record<number, { teamId: number; teamName: string; projects: ProjectWithItems[] }> = {};
    for (const p of props.projects) {
        const tid = p.team_id;
        const tname = p.team?.name ?? 'Tim Tidak Diketahui';
        if (!groups[tid]) groups[tid] = { teamId: tid, teamName: tname, projects: [] };
        groups[tid].projects.push(p);
    }
    return Object.values(groups).sort((a, b) => a.teamName.localeCompare(b.teamName));
});

// Form state
type ItemForm = {
    work_item_id: number;
    realization: number;
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

const seededIds = new Set<number>();

function getItem(workItemId: number): ItemForm {
    const existing = form.items.find(i => i.work_item_id === workItemId);
    if (existing) return existing;

    if (!seededIds.has(workItemId)) {
        seededIds.add(workItemId);
        let report: WorkItemWithReports['performance_reports'][number] | undefined;
        outer: for (const p of props.projects) {
            for (const wi of p.work_items) {
                if (wi.id === workItemId) {
                    report = wi.performance_reports[0];
                    break outer;
                }
            }
        }
        form.items.push({
            work_item_id: workItemId,
            realization: report?.realization ?? 0,
            issues: report?.issues ?? '',
            solutions: report?.solutions ?? '',
            action_plan: report?.action_plan ?? '',
        });
    }
    return form.items.find(i => i.work_item_id === workItemId)!;
}

function getWorkItem(workItemId: number): WorkItemWithReports | undefined {
    for (const p of props.projects) {
        const wi = p.work_items.find(w => w.id === workItemId);
        if (wi) return wi;
    }
}

function computePct(workItemId: number): number {
    const wi = getWorkItem(workItemId);
    if (!wi || !wi.target || Number(wi.target) <= 0) return 0;
    const item = getItem(workItemId);
    return Math.min(100, (Number(item.realization) / Number(wi.target)) * 100);
}

function progressColor(pct: number): string {
    if (pct >= 80) return '[&>div]:bg-green-500';
    if (pct >= 50) return '[&>div]:bg-yellow-500';
    return '[&>div]:bg-red-500';
}

function pctColor(pct: number): string {
    if (pct >= 80) return 'text-green-600';
    if (pct >= 50) return 'text-yellow-500';
    return 'text-red-500';
}

function projectAvgPct(project: ProjectWithItems): number {
    if (!project.work_items.length) return 0;
    const pcts = project.work_items.map(wi => computePct(wi.id));
    return pcts.reduce((s, v) => s + v, 0) / pcts.length;
}

function submit() {
    form.post(route('performance.batch'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Input Kinerja" />
    <AppLayout>
        <template #title>
            Input Kinerja — {{ employee.display_name || employee.name }}
        </template>

        <!-- Period filters -->
        <div class="mb-6 flex flex-wrap items-center gap-3">
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
                    <SelectItem v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</SelectItem>
                </SelectContent>
            </Select>
            <span class="ml-auto text-sm text-gray-500">
                Periode: <strong>{{ monthLabel }} {{ filters.year }}</strong>
            </span>
        </div>

        <div v-if="!projects.length" class="py-16 text-center text-gray-400">
            <p class="font-medium">Tidak ada proyek untuk periode ini.</p>
            <p class="mt-1 text-sm">Anda belum ditugaskan ke proyek aktif tahun {{ filters.year }}.</p>
        </div>

        <form v-else @submit.prevent="submit" class="space-y-8">
            <!-- Grouped by team -->
            <div v-for="group in projectsByTeam" :key="group.teamId" class="space-y-3">
                <!-- Team header -->
                <div class="flex items-center gap-3">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-primary">
                        {{ group.teamName }}
                    </h2>
                    <span class="h-px flex-1 bg-primary/20"></span>
                    <Badge variant="outline" class="text-xs">
                        {{ group.projects.length }} proyek
                    </Badge>
                </div>

                <!-- Projects accordion within team -->
                <Accordion type="multiple" class="space-y-2">
                    <AccordionItem
                        v-for="project in group.projects"
                        :key="project.id"
                        :value="String(project.id)"
                        class="rounded-lg border bg-white shadow-sm"
                    >
                        <AccordionTrigger class="px-4 py-3 hover:no-underline">
                            <div class="flex min-w-0 flex-1 items-center gap-3 pr-2">
                                <span class="min-w-0 flex-1 truncate text-left font-medium text-gray-800">
                                    {{ project.name }}
                                </span>
                                <div class="flex shrink-0 items-center gap-2">
                                    <div class="hidden w-24 sm:block">
                                        <Progress
                                            :model-value="projectAvgPct(project)"
                                            :class="['h-1.5', progressColor(projectAvgPct(project))]"
                                        />
                                    </div>
                                    <span :class="['text-sm font-bold', pctColor(projectAvgPct(project))]">
                                        {{ projectAvgPct(project).toFixed(0) }}%
                                    </span>
                                </div>
                            </div>
                        </AccordionTrigger>
                        <AccordionContent class="px-4 pb-4">
                            <div class="space-y-4">
                                <div
                                    v-for="wi in project.work_items"
                                    :key="wi.id"
                                    class="rounded-md border border-gray-100 bg-gray-50 p-4"
                                >
                                    <p class="mb-4 text-sm font-semibold text-gray-700">
                                        {{ wi.number }}. {{ wi.description }}
                                    </p>

                                    <!-- Target + Realization -->
                                    <div class="mb-4 rounded-md border border-gray-200 bg-white p-3">
                                        <div class="mb-2 flex items-center justify-between">
                                            <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Progres Capaian</span>
                                            <span :class="['text-base font-bold', pctColor(computePct(wi.id))]">
                                                {{ computePct(wi.id).toFixed(1) }}%
                                            </span>
                                        </div>
                                        <Progress
                                            :model-value="computePct(wi.id)"
                                            :class="['mb-3 h-2', progressColor(computePct(wi.id))]"
                                        />
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <Label class="text-xs text-gray-500">
                                                    Target ({{ wi.target_unit }})
                                                </Label>
                                                <div class="mt-1 flex items-center gap-2">
                                                    <span class="rounded bg-gray-100 px-2.5 py-1.5 text-sm font-semibold text-gray-700">
                                                        {{ Number(wi.target).toLocaleString('id') }}
                                                    </span>
                                                    <span class="text-xs text-gray-400">{{ wi.target_unit }}</span>
                                                </div>
                                            </div>
                                            <div>
                                                <Label class="text-xs text-gray-500">
                                                    Realisasi ({{ wi.target_unit }})
                                                </Label>
                                                <div class="mt-1 flex items-center gap-2">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        v-model="getItem(wi.id).realization"
                                                        class="w-28"
                                                    />
                                                    <span class="text-xs text-gray-400">{{ wi.target_unit }}</span>
                                                </div>
                                                <InputError :message="form.errors[`items.${form.items.findIndex(i => i.work_item_id === wi.id)}.realization`]" />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Notes grid -->
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div>
                                            <Label class="text-xs text-gray-500">Kendala</Label>
                                            <Textarea
                                                v-model="getItem(wi.id).issues"
                                                rows="3"
                                                class="mt-1 text-sm"
                                                placeholder="(opsional)"
                                            />
                                        </div>
                                        <div>
                                            <Label class="text-xs text-gray-500">Solusi</Label>
                                            <Textarea
                                                v-model="getItem(wi.id).solutions"
                                                rows="3"
                                                class="mt-1 text-sm"
                                                placeholder="(opsional)"
                                            />
                                        </div>
                                        <div>
                                            <Label class="text-xs text-gray-500">Rencana Tindak Lanjut</Label>
                                            <Textarea
                                                v-model="getItem(wi.id).action_plan"
                                                rows="3"
                                                class="mt-1 text-sm"
                                                placeholder="(opsional)"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>
            </div>

            <!-- Submit -->
            <div class="sticky bottom-4 flex justify-end">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="px-8 shadow-lg"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Laporan Kinerja' }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
