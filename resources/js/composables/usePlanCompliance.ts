import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import type { ComplianceTeam, ComplianceWeek } from '@/types';

/** Percentage of members who planned the week, or null when the team has no members. */
export function planningRate(week: ComplianceWeek): number | null {
    return week.members ? Math.round((week.planners / week.members) * 100) : null;
}

/** Share of plans of finished weeks that ended as done, or null when there are none. */
export function completionRate(teams: ComplianceTeam[]): number | null {
    let done = 0;
    let total = 0;
    for (const team of teams) {
        for (const w of team.weeks) {
            if (w.done === null) continue;
            done += w.done;
            total += w.done + (w.in_progress ?? 0) + (w.not_started ?? 0);
        }
    }
    return total ? Math.round((done / total) * 100) : null;
}

/** Tailwind classes for a planning-rate cell: green from 80%, amber from 50%, else red. */
export function rateClass(rate: number | null): string {
    if (rate === null) return 'bg-gray-50 text-gray-400';
    if (rate >= 80) return 'bg-green-50 text-green-800';
    if (rate >= 50) return 'bg-amber-50 text-amber-800';
    return 'bg-red-50 text-red-800';
}

export function usePlanCompliance(teams: MaybeRefOrGetter<ComplianceTeam[]>) {
    /** Office-wide planning rate of the current (last) week. */
    const thisWeekRate = computed(() => {
        const last = toValue(teams).map(t => t.weeks[t.weeks.length - 1]).filter(Boolean);
        const members = last.reduce((n, w) => n + w.members, 0);
        const planners = last.reduce((n, w) => n + w.planners, 0);
        return members ? Math.round((planners / members) * 100) : null;
    });
    const quarterCompletion = computed(() => completionRate(toValue(teams)));

    return { thisWeekRate, quarterCompletion };
}
