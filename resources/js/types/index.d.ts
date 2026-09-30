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
    /** Sasaran of the Kepala's Perjanjian Kinerja this IKU measures (synced from kipApp). */
    sasaran?: string | null;
    target?: number | string | null;
    target_unit?: string | null;
    description?: string | null;
    team?: Team | null;
    can_update?: boolean;
    can_delete?: boolean;
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
    team?: Team | null;
    can_update?: boolean;
    can_delete?: boolean;
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
    has_achievement: boolean;
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
    achievement: number | null;
}

export interface TeamProjectWithMembers {
    id: number;
    name: string;
    team: { id: number; name: string } | null;
    members: TeamMember[];
    kinetik_submitted_count?: number;
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
    /** Runs past this week: claimed once per week it covers. */
    spans_weeks?: boolean;
    /** The part of the activity inside the viewed week (default claim dates). */
    week_date_start?: string;
    week_date_end?: string;
    matched_plan_id?: number | null;
    /** True when the PJ locked the recap period this activity falls in. */
    locked?: boolean;
    claim?: ActivityClaim | null;
}

export interface ActivityClaim {
    id: number;
    kip_activity_id?: number | null;
    employee_id: number;
    performance_plan_id: number;
    project_id?: number | null;
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
    project?: { id: number; name: string } | null;
}

export interface PlanOption {
    id: number;
    description: string;
    project_id: number | null;
    project_name: string;
    team_id: number | null;
    team_name: string;
    /** Projek under this RK's leader RK in kipApp, when there are several; empty = any Projek of the team. */
    project_candidates: number[];
}

export interface ProjectOption {
    id: number;
    name: string;
    team_id: number;
    is_member: boolean;
}

// ── Kinetik / Team recaps (Phase 4) ──────────────────────────────────────────

/** One member claim behind a recap row. */
export interface RecapClaimLine {
    claim_id: number;
    name: string;
    uraian: string | null;
    target: number | null;
    realization: number | null;
    target_unit: string | null;
    achievement: number | null;
    /** PJ who corrected the numbers, if any. */
    adjusted_by: string | null;
}

export interface RecapRow {
    /** "planId:projectId" — one RK within one Projek. */
    row_key: string;
    /** Row key of the merge group's lead; null when not merged. */
    /** Member claims behind this row. */
    claims?: RecapClaimLine[];
    merge_key: string | null;
    performance_plan_id: number;
    project_id: number | null;
    rk_code?: string | null;
    rk_description: string;
    uraian_aggregated?: string | null;
    uraian_items?: Array<{ name: string; uraian: string }>;
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
    // Confirmation
    is_confirmed?: boolean;
    confirmed_by?: string | null;
    // PJ paraphrase fields (read-only view for non-PJ members)
    pj_uraian?: string | null;
    pj_obstacle?: string | null;
    pj_solution?: string | null;
    pj_follow_up_plan?: string | null;
    // Inherited from previous weekly paraphrase (used to prefill monthly/quarterly)
    inherited_obstacle?: string | null;
    inherited_solution?: string | null;
    inherited_follow_up_plan?: string | null;
    // PIC employee id (for row-level paraphrase permission)
    pic_employee_id?: number | null;
    // Quarterly (FRA) only
    follow_up_evidence_url?: string | null;
    follow_up_pic?: string | null;
    follow_up_pic_employee_id?: number | null;
    follow_up_deadline?: string | null;
}

export interface RecapSegment {
    project_id: number | null;
    project_name: string;
    /** The ketua tim's RK this Projek hangs under. */
    leader_rk?: string | null;
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

export type RecapPeriodType = 'week' | 'month' | 'quarter';

/** One team member's input for the week (GET team-recap.weekly `members`). */
export interface MemberCompleteness {
    employee_id: number;
    name: string;
    total: number;
    saved: number;
    status: 'complete' | 'partial' | 'empty' | 'no_activity';
}

/** One team in the all-teams overview (GET team-recap.overview). */
export interface OverviewTeam {
    id: number;
    name: string;
    rows: number;
    confirmed: number;
    avg_achievement: number | null;
    locked: boolean;
    /** Weekly only */
    members_active: number | null;
    members_complete: number | null;
    /** Team PJ (ketua tim). */
    leader: string | null;
    /** Catatan Pimpinan on the whole team for this period. */
    note: string | null;
    projects: OverviewProject[];
}

/** One project inside an overview team row. `id` null = rows without a Projek. */
export interface OverviewProject {
    id: number | null;
    name: string;
    /** Ketua / PIC of the project. */
    leader: string | null;
    members: number | null;
    rows: number;
    avg_achievement: number | null;
    /** Catatan Pimpinan on this project for this period. */
    note: string | null;
}

/** Review Bersama status of a capaian. `none` = no data yet. */
export type ReviewStatus = 'achieved' | 'progress' | 'low' | 'none';

/** One step of the "Siap rapat" checklist. */
export interface ChecklistStep {
    label: string;
    done: boolean;
    detail: string;
    /** id of the page section this step points to */
    target?: string;
}

/** PJ lock on one team recap period (null = open). */
export interface RecapLockState {
    locked_at: string | null;
    locked_by: string | null;
}

/** One team in the office-wide export (GET team-recap.export). */
export interface RecapExportTeam {
    team_name: string;
    segments: RecapSegment[];
    /** Weekly only: evidence URLs keyed by type. */
    evidences: Partial<Record<TeamRecapEvidence['type'], string[]>>;
}

export interface RecapExport {
    period_type: RecapPeriodType;
    week_start: string | null;
    teams: RecapExportTeam[];
}

export interface TeamOption {
    id: number;
    name: string;
}

export interface WeeklyTeamNote {
    id: number;
    team_id: number;
    week_start: string;
    uraian: string | null;
    obstacle: string | null;
    solution: string | null;
    follow_up_plan: string | null;
}

export interface KipSyncRun {
    id: number;
    status: string;
    total: number | null;
    processed: number;
    summary: Record<string, unknown>;
    message?: string | null;
}

export interface KipCredential {
    account_nip?: string | null;
    account_name?: string | null;
    expires_at: string | null;
    is_expired: boolean;
    is_expiring_soon?: boolean;
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
    /** Employees with golongan from kipApp (Angka Kredit). */
    careers_synced: number;
    ratings_synced: number;
}

export interface KipActivityRow {
    id: number;
    employee_id: number;
    employee_name: string;
    nip_lama?: string | null;
    date_start: string;
    description?: string | null;
    rk_name?: string | null;
    progress?: number | null;
    is_claimed: boolean;
    evidence_url?: string | null;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
    links: PaginationLink[];
}

// ── Angka Kredit ─────────────────────────────────────────────────────────────

/** ready: AK and 2 years done · ak_ready: AK done, waiting for 2 years · near: at most 2 quarters at Baik to go. */
export type CreditStatus = 'ready' | 'ak_ready' | 'near' | 'progress' | 'top' | 'no_data' | 'non_jf';

export interface CreditQuarter {
    label: string;
    ak: number;
    predikat: string | null;
    /** false = at least one month has no predikat yet; counted as Baik. */
    final: boolean;
    /** How the AK was computed, e.g. "Ahli Muda: 3 bln × 25 ÷ 12 × 150%". */
    formula: string;
}

/** Angka Kredit progress of one employee (CreditCalculator). Fields after `status` are absent for no_data / non_jf. */
export interface CreditSummary {
    employee_id: number;
    name: string;
    jabatan: string | null;
    golongan: string | null;
    pangkat: string | null;
    status: CreditStatus;
    level?: string;
    coefficient?: number;
    /** pangkat = next golongan in the jenjang; jenjang = next jenjang; null at the top. */
    kind?: 'pangkat' | 'jenjang' | null;
    next_label?: string | null;
    target?: number | null;
    earned?: number;
    /** Part of `earned` from SKPs that are not rated yet. */
    estimated?: number;
    gap?: number | null;
    quarters_to_go?: number | null;
    counted_from?: string;
    golongan_since?: string | null;
    eligible_from?: string | null;
    ak_base?: number | null;
    ak_base_date?: string | null;
    /** pegawai = entered by the employee, not checked; admin = checked against the PAK. */
    ak_base_source?: 'pegawai' | 'admin' | null;
    quarters?: CreditQuarter[];
}
