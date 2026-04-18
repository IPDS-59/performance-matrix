export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'head' | 'staff';
    email_verified_at?: string;
}

export interface Team {
    id: number;
    name: string;
    code: string;
    description?: string | null;
    is_active: boolean;
    leader_id?: number | null;
}

export interface Employee {
    id: number;
    name: string;
    full_name?: string | null;
    display_name?: string | null;
    employee_number?: string | null;
    position?: string | null;
    office?: string | null;
    team_id?: number | null;
    team?: Team | null;
    user_id?: number | null;
    is_active: boolean;
}

export interface Project {
    id: number;
    team_id: number;
    leader_id?: number | null;
    name: string;
    description?: string | null;
    objective?: string | null;
    kpi?: string | null;
    status: 'active' | 'completed' | 'cancelled';
    year: number;
    team?: Team | null;
    leader?: Employee | null;
    members?: (Employee & { pivot: { role: string } })[];
}

export interface WorkItem {
    id: number;
    project_id: number;
    number: number;
    description: string;
    target: number;
    target_unit: string;
    performance_reports?: PerformanceReport[];
}

export interface PerformanceReport {
    id: number;
    work_item_id: number;
    reported_by?: number | null;
    period_month: number;
    period_year: number;
    realization: number;
    achievement_percentage: number;
    issues?: string | null;
    solutions?: string | null;
    action_plan?: string | null;
}

export interface ReviewEvent {
    id: number;
    action: 'submitted' | 'resubmitted' | 'approved' | 'rejected';
    note: string | null;
    created_at: string;
    actor: { id: number; name: string } | null;
}

// ── Dashboard types ───────────────────────────────────────────────────────

export interface PersonalStats {
    teams_count: number;
    projects_count: number;
    items_count: number;
    avg_achievement: number;
    is_team_lead: boolean;
}

export interface TeamProgress {
    team_id: number;
    avg_achievement: number;
    report_count: number;
}

export interface TrendPoint {
    period_month: number;
    avg_achievement: number;
}

export interface EmployeeRankItem {
    id: number;
    name: string;
    display_name: string | null;
    project_count?: number;
    leader_count?: number;
    member_count?: number;
    avg_achievement?: number;
}

export interface TeamMember extends Employee {
    pivot: { role: string };
}

export interface TeamWithMembers extends Team {
    employees?: TeamMember[];
}

export interface TeamRankItem extends TeamWithMembers {
    avg: number;
    count: number;
}

export interface ProjectWithItems {
    id: number;
    team_id: number;
    name: string;
    team?: { id: number; name: string } | null;
    work_items: Array<{
        id: number;
        description: string;
        performance_reports: Array<{ achievement_percentage: number }>;
    }>;
}

export interface TeamProjectWithMembers {
    id: number;
    name: string;
    team: { id: number; name: string } | null;
    members: TeamMember[];
    work_items: Array<{
        id: number;
        description: string;
        target: number;
        target_unit: string;
        performance_reports: Array<{
            id: number;
            realization: number;
            achievement_percentage: number;
            reported_by: number | null;
            reporter: { id: number; name: string; display_name: string | null } | null;
        }>;
    }>;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash?: {
        success?: string;
        error?: string;
    };
};
