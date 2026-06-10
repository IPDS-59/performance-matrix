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
    members?: TeamMemberWithPivot[];
}

export interface Employee {
    id: number;
    name: string;
    full_name?: string | null;
    display_name?: string | null;
    employee_number?: string | null;
    nip_lama?: string | null;
    nip_baru?: string | null;
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

export interface PerformanceIndicator {
    id: number;
    team_id: number;
    year: number;
    code?: string | null;
    name: string;
    target?: number | string | null;
    target_unit?: string | null;
    description?: string | null;
    team?: Team | null;
}

export interface PerformancePlan {
    id: number;
    project_id: number;
    code?: string | null;
    description: string;
    target?: number | string | null;
    target_unit?: string | null;
    period_type: 'year' | 'quarter';
    period?: number | null;
    pic_employee_id?: number | null;
    project?: Project | null;
    pic?: Employee | null;
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

export interface TeamMemberPivot {
    role: 'member' | 'leader';
    is_primary: boolean;
    started_at?: string | null;
    ended_at?: string | null;
}

export interface TeamMemberWithPivot extends Employee {
    pivot: TeamMemberPivot;
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

// ── Kinetik / Weekly Scrapper types ──────────────────────────────────────────

export interface KipActivity {
    id: number;
    employee_id: number;
    external_id: string;
    description: string;
    activity_date_start: string;
    activity_date_end?: string | null;
    time_start?: string | null;
    time_end?: string | null;
    evidence_url?: string | null;
    rk_name?: string | null;
    is_claimed: boolean;
    claim?: ActivityClaim | null;
}

export interface ActivityClaim {
    id: number;
    kip_activity_id?: number | null;
    employee_id: number;
    performance_plan_id: number;
    work_item_id?: number | null;
    target?: string | number | null;
    realization?: string | number | null;
    achievement?: string | number | null;
    target_unit?: string | null;
    obstacle?: string | null;
    solution?: string | null;
    follow_up_plan?: string | null;
    activity_date_start: string;
    activity_date_end?: string | null;
    start_time?: string | null;
    end_time?: string | null;
    evidence_url?: string | null;
    status: 'draft' | 'saved';
    week_start: string;
    performance_plan?: PerformancePlan & { project?: { name: string; team?: { name: string } | null } | null } | null;
    kip_activity?: KipActivity | null;
}

export interface PlanOption {
    id: number;
    description: string;
    project_name: string;
    team_name: string;
}

// ── Kinetik / Team recaps (Phase 4) ──────────────────────────────────────────

export interface RecapRow {
    performance_plan_id: number;
    rk_code?: string | null;
    rk_description: string;
    target: number;
    realization: number;
    achievement: number | null;
    target_unit?: string | null;
    obstacle: string | null;
    solution: string | null;
    follow_up_plan: string | null;
    obstacle_aggregated: string | null;
    solution_aggregated: string | null;
    follow_up_aggregated: string | null;
    is_overridden: boolean;
    contributors: string[];
    // Quarterly (FRA) only
    follow_up_evidence_url?: string | null;
    follow_up_pic?: string | null;
    follow_up_pic_employee_id?: number | null;
    follow_up_deadline?: string | null;
}

export interface RecapSegment {
    project_id: number | null;
    project_name: string;
    rows: RecapRow[];
}

export interface TeamRecapEvidence {
    id: number;
    team_id: number;
    project_id?: number | null;
    period_type: string;
    week_start?: string | null;
    type: 'notula' | 'photo' | 'attendance';
    title?: string | null;
    url: string;
    uploaded_by?: number | null;
}

export interface TeamOption {
    id: number;
    name: string;
}

// ── Kinetik: kipApp integration ──────────────────────────────────────────────

export interface KipCredential {
    account_nip: string | null;
    account_name: string | null;
    expires_at: string | null;
    is_expired: boolean;
    is_expiring_soon: boolean;
    updated_at: string | null;
    updated_by: string | null;
}

export interface KipIntegrationStats {
    employees_with_nip: number;
    employees_total: number;
    activities_synced: number;
    last_fetched_at: string | null;
    teams_synced: number;
    projects_synced: number;
}

export interface KipSyncRun {
    id: number;
    status: 'running' | 'completed' | 'failed';
    total: number;
    processed: number;
    summary: Record<string, number>;
    message: string | null;
}
