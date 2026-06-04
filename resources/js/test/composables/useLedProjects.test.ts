import { describe, it, expect } from 'vitest';
import {
    isProjectLeader,
    leaderBadgeLabel,
    ledProjectMemberCount,
    ledProjectSubmittedCount,
} from '@/composables/useLedProjects';
import type { TeamMember, TeamProjectWithMembers } from '@/types';

// ── Factories ─────────────────────────────────────────────────────────────────

function makeMember(id: number, role: string): TeamMember {
    return {
        id,
        name: `Employee ${id}`,
        display_name: null,
        is_active: true,
        pivot: { role },
    } as TeamMember;
}

function makeProject(
    members: TeamMember[],
    reportedByValues: (number | null)[][],
): TeamProjectWithMembers {
    return {
        id: 1,
        name: 'Test Project',
        team: { id: 10, name: 'Team A' },
        members,
        work_items: reportedByValues.map((reporters, wiIdx) => ({
            id: wiIdx + 1,
            description: `Work item ${wiIdx + 1}`,
            target: 100,
            target_unit: 'unit',
            performance_reports: reporters.map((reportedBy, rIdx) => ({
                id: rIdx + 1,
                realization: 80,
                achievement_percentage: 80,
                reported_by: reportedBy,
                reporter: null,
            })),
        })),
    };
}

// ── isProjectLeader ───────────────────────────────────────────────────────────

describe('isProjectLeader', () => {
    it('returns true for role "leader"', () => {
        expect(isProjectLeader(makeMember(1, 'leader'))).toBe(true);
    });

    it('returns true for role "ketua"', () => {
        expect(isProjectLeader(makeMember(1, 'ketua'))).toBe(true);
    });

    it('returns false for role "member"', () => {
        expect(isProjectLeader(makeMember(1, 'member'))).toBe(false);
    });

    it('returns false for role "anggota"', () => {
        expect(isProjectLeader(makeMember(1, 'anggota'))).toBe(false);
    });
});

// ── leaderBadgeLabel ─────────────────────────────────────────────────────────

describe('leaderBadgeLabel', () => {
    it('returns "Ketua Tim" when employee is the team leader', () => {
        const map = new Map([[10, 5]]);
        expect(leaderBadgeLabel(5, 10, map)).toBe('Ketua Tim');
    });

    it('returns "Ketua Proyek" when employee is not the team leader', () => {
        const map = new Map([[10, 5]]);
        expect(leaderBadgeLabel(9, 10, map)).toBe('Ketua Proyek');
    });

    it('returns "Ketua Proyek" when teamId is null', () => {
        const map = new Map([[10, 5]]);
        expect(leaderBadgeLabel(5, null, map)).toBe('Ketua Proyek');
    });

    it('returns "Ketua Proyek" when teamId is undefined', () => {
        const map = new Map([[10, 5]]);
        expect(leaderBadgeLabel(5, undefined, map)).toBe('Ketua Proyek');
    });

    it('returns "Ketua Proyek" when map has no entry for the team', () => {
        const map = new Map<number, number>();
        expect(leaderBadgeLabel(5, 10, map)).toBe('Ketua Proyek');
    });
});

// ── ledProjectMemberCount ─────────────────────────────────────────────────────

describe('ledProjectMemberCount', () => {
    it('returns number of members', () => {
        const project = makeProject([makeMember(1, 'leader'), makeMember(2, 'member')], []);
        expect(ledProjectMemberCount(project)).toBe(2);
    });

    it('returns 0 for empty members', () => {
        const project = makeProject([], []);
        expect(ledProjectMemberCount(project)).toBe(0);
    });
});

// ── ledProjectSubmittedCount ─────────────────────────────────────────────────

describe('ledProjectSubmittedCount', () => {
    it('counts distinct reporters across all work items', () => {
        const project = makeProject(
            [makeMember(1, 'leader'), makeMember(2, 'member'), makeMember(3, 'member')],
            [
                [1, 2],   // work item 1: reporters 1 and 2
                [2, 3],   // work item 2: reporters 2 and 3
            ],
        );
        // Distinct set: {1, 2, 3}
        expect(ledProjectSubmittedCount(project)).toBe(3);
    });

    it('does not double-count the same reporter', () => {
        const project = makeProject([], [[1], [1], [1]]);
        expect(ledProjectSubmittedCount(project)).toBe(1);
    });

    it('ignores null reported_by', () => {
        const project = makeProject([], [[null, 1, null]]);
        expect(ledProjectSubmittedCount(project)).toBe(1);
    });

    it('returns 0 when no reports exist', () => {
        const project = makeProject([], []);
        expect(ledProjectSubmittedCount(project)).toBe(0);
    });

    it('returns 0 when all reported_by are null', () => {
        const project = makeProject([], [[null, null]]);
        expect(ledProjectSubmittedCount(project)).toBe(0);
    });
});
