import type { TeamMember, TeamProjectWithMembers } from '@/types';

export function isProjectLeader(member: TeamMember): boolean {
    return member.pivot.role === 'leader' || member.pivot.role === 'ketua';
}

export function leaderBadgeLabel(
    employeeId: number,
    teamId: number | null | undefined,
    teamLeaderMap: Map<number, number>,
): string {
    if (teamId != null && teamLeaderMap.get(teamId) === employeeId) return 'Ketua Tim';
    return 'Ketua Proyek';
}

export function ledProjectMemberCount(project: TeamProjectWithMembers): number {
    return project.members.length;
}

export function ledProjectSubmittedCount(project: TeamProjectWithMembers): number {
    const reportedBySet = new Set<number>();
    for (const wi of project.work_items) {
        for (const r of wi.performance_reports) {
            if (r.reported_by !== null) reportedBySet.add(r.reported_by);
        }
    }
    return reportedBySet.size;
}
