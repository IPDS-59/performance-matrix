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
    kinetikSubmittedCount?: number,
): TeamProjectWithMembers {
    return {
        id: 1,
        name: 'Test Project',
        team: { id: 10, name: 'Team A' },
        members,
        kinetik_submitted_count: kinetikSubmittedCount,
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
        const project = makeProject([makeMember(1, 'leader'), makeMember(2, 'member')]);
        expect(ledProjectMemberCount(project)).toBe(2);
    });

    it('returns 0 for empty members', () => {
        const project = makeProject([]);
        expect(ledProjectMemberCount(project)).toBe(0);
    });
});

// ── ledProjectSubmittedCount ─────────────────────────────────────────────────

describe('ledProjectSubmittedCount', () => {
    it('returns kinetik_submitted_count', () => {
        const project = makeProject(
            [makeMember(1, 'leader'), makeMember(2, 'member'), makeMember(3, 'member')],
            3,
        );
        expect(ledProjectSubmittedCount(project)).toBe(3);
    });

    it('returns 0 when kinetik_submitted_count is undefined', () => {
        const project = makeProject([]);
        expect(ledProjectSubmittedCount(project)).toBe(0);
    });

    it('returns 0 when kinetik_submitted_count is 0', () => {
        const project = makeProject([], 0);
        expect(ledProjectSubmittedCount(project)).toBe(0);
    });
});
