<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';
import type { Employee, PersonalStats, TeamProgress, TrendPoint, EmployeeRankItem, TeamWithMembers, ProjectWithItems, TeamProjectWithMembers } from '@/types';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import StaffDashboard from '@/Components/Dashboard/StaffDashboard.vue';
import HeadDashboard from '@/Components/Dashboard/HeadDashboard.vue';
import AdminDashboard from '@/Components/Dashboard/AdminDashboard.vue';

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    role: 'admin' | 'head' | 'staff';
    employee?: Employee;
    personal_stats?: PersonalStats;
    projects?: ProjectWithItems[];
    kinetik_plan_cards?: ProjectWithItems[];
    team_projects?: TeamProjectWithMembers[];
    teams?: TeamWithMembers[];
    project_leaders_by_team?: Record<number, number[]>;
    team_progress?: Record<string, TeamProgress>;
    org_avg?: number;
    trend?: TrendPoint[];
    top_employees_by_projects?: EmployeeRankItem[];
    top_employees_by_achievement?: EmployeeRankItem[];
    filters: { year: number; month: number };
}>();

// ── Filters ────────────────────────────────────────────────────────────────

const year = ref(props.filters.year);
const month = ref(props.filters.month);

function applyFilters() {
    router.get(route('dashboard'), { year: year.value, month: month.value }, { preserveState: true });
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

// ── Auto-scroll to current user in ranking lists ─────────────────────────

onMounted(() => {
    nextTick(() => {
        document.querySelectorAll<HTMLElement>('[data-current-user]').forEach(el => {
            const container = el.closest('.overflow-y-auto');
            if (container) {
                el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        });
    });
});

// ── Shared derived data ──────────────────────────────────────────────────

const teamList = computed(() => {
    if (!props.teams || !props.team_progress) return [];
    return props.teams.map(t => ({
        ...t,
        avg: props.team_progress![t.id]?.avg_achievement ?? 0,
        count: props.team_progress![t.id]?.report_count ?? 0,
    })).sort((a, b) => b.avg - a.avg);
});

const teamLeaderMap = computed(() => {
    const map = new Map<number, number>();
    props.teams?.forEach(t => { if (t.leader_id) map.set(t.id, t.leader_id); });
    return map;
});
</script>

<template>
    <Head title="Beranda" />
    <AppLayout>
        <template #title>Beranda — {{ monthLabel }} {{ filters.year }}</template>

        <!-- Period filters -->
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <Select v-model="month" @update:modelValue="applyFilters">
                <SelectTrigger class="w-40">
                    <SelectValue placeholder="Bulan" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="m in months" :key="m.value" :value="m.value">
                        {{ m.label }}
                    </SelectItem>
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
        </div>

        <StaffDashboard
            v-if="role === 'staff'"
            :employee="employee"
            :personal-stats="personal_stats"
            :projects="projects ?? []"
            :kinetik-plan-cards="kinetik_plan_cards ?? []"
            :team-projects="team_projects ?? []"
            :team-list="teamList"
            :team-leader-map="teamLeaderMap"
            :project-leaders-by-team="project_leaders_by_team"
            :top-employees-by-projects="top_employees_by_projects ?? []"
            :top-employees-by-achievement="top_employees_by_achievement ?? []"
            :period-label="`${monthLabel} ${filters.year}`"
        />
        <HeadDashboard
            v-else-if="role === 'head'"
            :employee="employee"
            :personal-stats="personal_stats"
            :projects="projects ?? []"
            :team-list="teamList"
            :project-leaders-by-team="project_leaders_by_team"
            :top-employees-by-projects="top_employees_by_projects ?? []"
            :top-employees-by-achievement="top_employees_by_achievement ?? []"
            :period-label="`${monthLabel} ${filters.year}`"
            :month-label="monthLabel"
        />
        <AdminDashboard
            v-else
            :org-avg="org_avg ?? 0"
            :team-list="teamList"
            :project-leaders-by-team="project_leaders_by_team"
            :top-employees-by-projects="top_employees_by_projects ?? []"
            :top-employees-by-achievement="top_employees_by_achievement ?? []"
            :trend="trend ?? []"
            :year="filters.year"
            :months="months"
            :month-label="monthLabel"
        />
    </AppLayout>
</template>
