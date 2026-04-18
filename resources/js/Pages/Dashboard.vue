<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import type { Employee, PersonalStats, TeamProgress, TrendPoint, EmployeeRankItem, TeamMember, TeamWithMembers, ProjectWithItems, TeamProjectWithMembers } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Badge } from '@/Components/ui/badge';
import { Progress } from '@/Components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { VisXYContainer, VisLine, VisArea, VisAxis, VisTooltip } from '@unovis/vue';
import { Line as UnovisLine, Area as UnovisArea } from '@unovis/ts';
import { CurveType } from '@unovis/ts';
import { useAchievementColor } from '@/composables/useAchievementColor';
import DashboardStatCards from '@/Components/Dashboard/DashboardStatCards.vue';
import DashboardTeamRanking from '@/Components/Dashboard/DashboardTeamRanking.vue';
import DashboardEmployeeRankings from '@/Components/Dashboard/DashboardEmployeeRankings.vue';
import DashboardPersonalProjects from '@/Components/Dashboard/DashboardPersonalProjects.vue';

// ── Types ──────────────────────────────────────────────────────────────────
// All dashboard types are imported from @/types.

// ── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    role: 'admin' | 'head' | 'staff';
    employee?: Employee;
    personal_stats?: PersonalStats;
    projects?: ProjectWithItems[];
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

const { achievementColor, progressVariant } = useAchievementColor();

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

// ── Team ranking data ────────────────────────────────────────────────────

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

// ── Led project helpers ──────────────────────────────────────────────────

function isProjectLeader(member: TeamMember): boolean {
    return member.pivot.role === 'leader' || member.pivot.role === 'ketua';
}

function leaderBadgeLabel(employeeId: number, teamId: number | null | undefined): string {
    if (teamId != null && teamLeaderMap.value.get(teamId) === employeeId) return 'Ketua Tim';
    return 'Ketua Proyek';
}

function ledProjectMemberCount(project: TeamProjectWithMembers): number {
    return project.members.length;
}

function ledProjectSubmittedCount(project: TeamProjectWithMembers): number {
    const reportedBySet = new Set<number>();
    for (const wi of project.work_items) {
        for (const r of wi.performance_reports) {
            if (r.reported_by !== null) reportedBySet.add(r.reported_by);
        }
    }
    return reportedBySet.size;
}

const chipScrollable = reactive<Record<number, boolean>>({});
const chipResizeObservers = new Map<number, ResizeObserver>();

function initChipScrollable(el: HTMLElement | null, projectId: number) {
    chipResizeObservers.get(projectId)?.disconnect();
    if (!el) return;
    const update = () => { chipScrollable[projectId] = el.scrollWidth > el.clientWidth; };
    update();
    const ro = new ResizeObserver(update);
    ro.observe(el);
    chipResizeObservers.set(projectId, ro);
}

// ── Admin: trend chart ──────────────────────────────────────────────────

const trendLabels = months.map(m => m.label.substring(0, 3));

interface TrendDatum { month: number; label: string; value: number | null }

const trendUnovisData = computed<TrendDatum[]>(() => {
    const dataByMonth: (number | null)[] = Array(12).fill(null);
    for (const point of props.trend ?? []) {
        dataByMonth[point.period_month - 1] = point.avg_achievement;
    }
    return dataByMonth.map((v, i) => ({ month: i, label: trendLabels[i], value: v }));
});

const trendX = (d: TrendDatum) => d.month;
const trendY = [(d: TrendDatum) => d.value ?? undefined];
const trendXTickFormat = (_tick: number, i: number) => trendUnovisData.value[i]?.label ?? '';
const trendYTickFormat = (v: number) => `${v}%`;
const trendLineColor = '#1B4B8A';
const trendAreaColor = 'rgba(27,75,138,0.08)';

const formatTrendTooltip = (d: TrendDatum) =>
    `<div style="padding:4px 8px;font-size:13px"><strong>${d.label}</strong><br/>${d.value?.toFixed(1) ?? '-'}%</div>`;

const trendTooltipTriggers = computed(() => ({
    [UnovisLine.selectors.line]: formatTrendTooltip,
    [UnovisArea.selectors.area]: formatTrendTooltip,
}));
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

        <!-- ── STAFF view ────────────────────────────────────────────────── -->
        <template v-if="role === 'staff'">
            <div v-if="!employee" class="py-20 text-center text-gray-400">
                <p class="text-lg font-medium">Akun Belum Terhubung</p>
                <p class="mt-1 text-sm">Hubungi administrator untuk menghubungkan akun ke data pegawai.</p>
            </div>
            <template v-else>
                <!-- Welcome card -->
                <div class="mb-6 flex items-center gap-4 rounded-lg border bg-white p-5 shadow-sm">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xl font-bold text-primary">
                        {{ (employee.display_name || employee.name).charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">{{ employee.display_name || employee.name }}</p>
                        <p class="text-sm text-gray-500">{{ monthLabel }} {{ filters.year }}</p>
                    </div>
                </div>

                <!-- Tabs only when team lead -->
                <template v-if="personal_stats?.is_team_lead">
                    <Tabs default-value="personal" class="w-full">
                        <TabsList class="mb-6">
                            <TabsTrigger value="personal">Kinerja Saya</TabsTrigger>
                            <TabsTrigger value="team">
                                Tim yang Saya Pimpin
                                <Badge variant="secondary" class="ml-2 text-xs">{{ team_projects?.length ?? 0 }}</Badge>
                            </TabsTrigger>
                        </TabsList>
                        <TabsContent value="personal">
                            <DashboardStatCards v-if="personal_stats" :stats="personal_stats" />

                            <h2 class="mt-10 mb-4 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                                <span class="h-px flex-1 bg-primary/20"></span>
                                Perbandingan Kinerja Tim
                                <span class="h-px flex-1 bg-primary/20"></span>
                            </h2>
                            <DashboardTeamRanking
                                :team-list="teamList"
                                :project-leaders-by-team="project_leaders_by_team"
                            />

                            <h2 class="mt-10 mb-4 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                                <span class="h-px flex-1 bg-primary/20"></span>
                                Peringkat Pegawai
                                <span class="h-px flex-1 bg-primary/20"></span>
                            </h2>
                            <DashboardEmployeeRankings
                                :top-by-projects="top_employees_by_projects ?? []"
                                :top-by-achievement="top_employees_by_achievement ?? []"
                                :current-employee-id="employee?.id"
                            />

                            <div class="mt-10">
                                <DashboardPersonalProjects :projects="projects ?? []" />
                            </div>
                        </TabsContent>
                        <TabsContent value="team">
                            <div class="space-y-4">
                                <Card v-for="ledProject in team_projects" :key="ledProject.id" class="overflow-hidden">
                                    <CardHeader class="pb-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle class="text-sm font-semibold text-gray-800">{{ ledProject.name }}</CardTitle>
                                                <p v-if="ledProject.team" class="mt-0.5 text-xs text-gray-500">{{ ledProject.team.name }}</p>
                                            </div>
                                            <div class="shrink-0 text-right text-xs text-gray-500">
                                                <span class="font-medium text-gray-700">{{ ledProjectSubmittedCount(ledProject) }}</span>
                                                <span class="text-gray-400"> / {{ ledProjectMemberCount(ledProject) }}</span>
                                                <p class="text-gray-400">sudah input</p>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent class="pt-0">
                                        <div class="flex items-center gap-2">
                                            <template v-for="member in ledProject.members" :key="'lead-'+member.id">
                                                <div
                                                    v-if="isProjectLeader(member)"
                                                    class="flex shrink-0 items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs text-amber-800"
                                                >
                                                    <span class="text-amber-500" :aria-label="leaderBadgeLabel(member.id, ledProject.team?.id)">&#9733;</span>
                                                    <span>{{ member.display_name || member.name }}</span>
                                                    <Badge class="ml-0.5 h-4 bg-amber-500 px-1.5 text-[10px] text-white hover:bg-amber-500">{{ leaderBadgeLabel(member.id, ledProject.team?.id) }}</Badge>
                                                </div>
                                            </template>
                                            <span
                                                v-if="ledProject.members.some(m => isProjectLeader(m)) && ledProject.members.some(m => !isProjectLeader(m))"
                                                class="h-6 w-px shrink-0 bg-gray-200"
                                            />
                                            <div v-if="ledProject.members.some(m => !isProjectLeader(m))" class="relative min-w-0 flex-1">
                                                <div
                                                    class="flex gap-2 overflow-x-auto [&::-webkit-scrollbar]:hidden [scrollbar-width:none]"
                                                    :ref="(el) => initChipScrollable(el as HTMLElement | null, ledProject.id)"
                                                >
                                                    <template v-for="member in ledProject.members" :key="member.id">
                                                        <div
                                                            v-if="!isProjectLeader(member)"
                                                            class="flex shrink-0 items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs text-gray-700"
                                                        >
                                                            <span>{{ member.display_name || member.name }}</span>
                                                        </div>
                                                    </template>
                                                </div>
                                                <div v-if="chipScrollable[ledProject.id]" class="pointer-events-none absolute inset-y-0 right-0 flex items-center bg-gradient-to-l from-white via-white/70 to-transparent pl-6 pr-1">
                                                    <svg class="h-4 w-4 animate-bounce-x text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </TabsContent>
                    </Tabs>
                </template>
                <template v-else>
                    <!-- Non-lead staff: direct content -->
                    <DashboardStatCards v-if="personal_stats" :stats="personal_stats" />

                    <h2 class="mt-10 mb-4 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                        <span class="h-px flex-1 bg-primary/20"></span>
                        Perbandingan Kinerja Tim
                        <span class="h-px flex-1 bg-primary/20"></span>
                    </h2>
                    <DashboardTeamRanking
                        :team-list="teamList"
                        :project-leaders-by-team="project_leaders_by_team"
                    />

                    <h2 class="mt-10 mb-4 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                        <span class="h-px flex-1 bg-primary/20"></span>
                        Peringkat Pegawai
                        <span class="h-px flex-1 bg-primary/20"></span>
                    </h2>
                    <DashboardEmployeeRankings
                        :top-by-projects="top_employees_by_projects ?? []"
                        :top-by-achievement="top_employees_by_achievement ?? []"
                        :current-employee-id="employee?.id"
                    />

                    <DashboardPersonalProjects :projects="projects ?? []" />
                </template>
            </template>
        </template>

        <!-- ── HEAD view ──────────────────────────────────────────────────── -->
        <template v-else-if="role === 'head'">
            <template v-if="employee && personal_stats">
                <div class="mb-6 flex items-center gap-4 rounded-lg border bg-white p-5 shadow-sm">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xl font-bold text-primary">
                        {{ (employee.display_name || employee.name).charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">{{ employee.display_name || employee.name }}</p>
                        <p class="text-sm text-gray-500">{{ monthLabel }} {{ filters.year }}</p>
                    </div>
                </div>
                <DashboardStatCards :stats="personal_stats" />
            </template>

            <div class="mb-4 flex items-center gap-3">
                <h2 class="text-base font-semibold text-gray-800">Ringkasan Tim</h2>
                <span class="h-px flex-1 bg-gray-200"></span>
            </div>

            <div v-if="!teamList.length" class="py-16 text-center text-gray-400">
                <p class="font-medium">Belum ada data laporan untuk periode ini.</p>
                <p class="mt-1 text-sm">Staf dapat memasukkan laporan kinerja bulan {{ monthLabel }}.</p>
            </div>

            <template v-else>
                <DashboardTeamRanking
                    :team-list="teamList"
                    :project-leaders-by-team="project_leaders_by_team"
                    :month-label="monthLabel"
                    :chart-col-span="3"
                    :rank-col-span="2"
                />

                <h2 class="mt-10 mb-4 flex items-center gap-2 text-sm font-semibold text-primary uppercase tracking-wide">
                    <span class="h-px flex-1 bg-primary/20"></span>
                    Peringkat Pegawai
                    <span class="h-px flex-1 bg-primary/20"></span>
                </h2>
                <DashboardEmployeeRankings
                    :top-by-projects="top_employees_by_projects ?? []"
                    :top-by-achievement="top_employees_by_achievement ?? []"
                />
            </template>

            <DashboardPersonalProjects v-if="(projects ?? []).length" :projects="projects ?? []" heading="Proyek Saya" />
        </template>

        <!-- ── ADMIN view ──────────────────────────────────────────────────── -->
        <template v-else>
            <div class="mb-6 grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-xs font-medium text-gray-500 uppercase tracking-wide">Rata-rata Organisasi</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p :class="['text-3xl font-bold', achievementColor(org_avg ?? 0)]">{{ (org_avg ?? 0).toFixed(1) }}%</p>
                        <Progress :model-value="org_avg ?? 0" class="mt-2 h-1.5" :indicator-class="progressVariant(org_avg ?? 0)" />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-xs font-medium text-gray-500 uppercase tracking-wide">Tim Aktif</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-gray-800">{{ teamList.length }}</p>
                        <p class="mt-1 text-xs text-gray-400">tim dengan laporan</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-xs font-medium text-gray-500 uppercase tracking-wide">Tim Capaian &#8805; 80%</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-green-600">{{ teamList.filter(t => t.avg >= 80).length }}</p>
                        <p class="mt-1 text-xs text-gray-400">dari {{ teamList.length }} tim</p>
                    </CardContent>
                </Card>
            </div>

            <div v-if="!teamList.length" class="py-16 text-center text-gray-400">
                <p class="font-medium">Belum ada data laporan untuk periode ini.</p>
                <p class="mt-1 text-sm">Staf dapat memasukkan laporan kinerja bulan {{ monthLabel }}.</p>
            </div>

            <template v-else>
                <DashboardTeamRanking
                    :team-list="teamList"
                    :project-leaders-by-team="project_leaders_by_team"
                    :month-label="monthLabel"
                    :chart-col-span="3"
                    :rank-col-span="2"
                />

                <Card class="mt-6">
                    <CardHeader>
                        <CardTitle class="text-base">Tren Capaian 12 Bulan — {{ filters.year }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="h-52">
                            <VisXYContainer :data="trendUnovisData" :yDomain="[0, 100]" :style="{ height: '100%' }">
                                <VisArea :x="trendX" :y="trendY" :color="trendAreaColor" :curveType="CurveType.MonotoneX" :opacity="1" />
                                <VisLine :x="trendX" :y="trendY" :color="trendLineColor" :curveType="CurveType.MonotoneX" />
                                <VisAxis type="x" :tickFormat="trendXTickFormat" :numTicks="12" :gridLine="false" />
                                <VisAxis type="y" :tickFormat="trendYTickFormat" />
                                <VisTooltip :triggers="trendTooltipTriggers" />
                            </VisXYContainer>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </template>
    </AppLayout>
</template>
