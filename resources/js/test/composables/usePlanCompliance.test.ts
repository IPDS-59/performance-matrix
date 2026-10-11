import { describe, expect, it } from 'vitest';
import { completionRate, planningRate, rateClass, usePlanCompliance } from '@/composables/usePlanCompliance';
import type { ComplianceTeam, ComplianceWeek } from '@/types';

const week = (over: Partial<ComplianceWeek>): ComplianceWeek => ({
    week_start: '2026-10-05', members: 4, planners: 3, plans: 3, done: null, in_progress: null, not_started: null, ...over,
});

describe('usePlanCompliance', () => {
    it('rounds the planning rate and is null for an empty team', () => {
        expect(planningRate(week({}))).toBe(75);
        expect(planningRate(week({ members: 0, planners: 0 }))).toBeNull();
    });

    it('counts only finished weeks in the completion rate', () => {
        const teams: ComplianceTeam[] = [{
            team_id: 1, name: 'A', missing_this_week: [],
            weeks: [week({ done: 3, in_progress: 1, not_started: 0 }), week({ done: null })],
        }];
        expect(completionRate(teams)).toBe(75);
        expect(completionRate([{ ...teams[0], weeks: [week({})] }])).toBeNull();
    });

    it('sums the current week across teams', () => {
        const teams: ComplianceTeam[] = [
            { team_id: 1, name: 'A', missing_this_week: [], weeks: [week({ members: 4, planners: 4 })] },
            { team_id: 2, name: 'B', missing_this_week: [], weeks: [week({ members: 4, planners: 0 })] },
        ];
        expect(usePlanCompliance(teams).thisWeekRate.value).toBe(50);
    });

    it('colours rates by threshold', () => {
        expect(rateClass(90)).toContain('green');
        expect(rateClass(60)).toContain('amber');
        expect(rateClass(10)).toContain('red');
        expect(rateClass(null)).toContain('gray');
    });
});
