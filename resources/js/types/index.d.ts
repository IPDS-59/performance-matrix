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

export interface ProjectOption {
    id: number;
    name: string;
    team_id: number;
    is_member: boolean;
}

// ── Kinetik / Team recaps (Phase 4) ──────────────────────────────────────────

export interface RecapRow {
    /** "planId:projectId" — one RK within one Projek. */
    row_key: string;
    performance_plan_id: number;
    project_id: number | null;
    rk_code?: string | null;
    rk_description: string;
    uraian_aggregated?: string | null;
    /** Numbers: the PJ's own for the RK, else the members' plan items summed. */
    target: number;
    realization: number;
    achievement: number | null;
    target_unit?: string | null;
    contributors: string[];
    /** Week/month: locked by the PJ. Quarter: signed off. */
    is_confirmed?: boolean;
    confirmed_by?: string | null;
    /** The PJ's paraphrase. */
    pj_uraian?: string | null;
    is_overridden: boolean;
    pic_employee_id?: number | null;
    // Quarterly (FRA) only
    obstacle: string | null;
    solution: string | null;
    follow_up_plan: string | null;
    follow_up_evidence_url?: string | null;
    follow_up_pic?: string | null;
    follow_up_pic_employee_id?: number | null;
    follow_up_deadline?: string | null;
}

/** What one member wrote for an RK in a week or month, from their plan items. */
export interface RkEntry {
    employee_id: number;
    name: string;
    /** One sentence each: the uraian capaian, else the kegiatan. */
    lines: string[];
    items: number;
    target: number | null;
    capaian: number | null;
    unit: string | null;
}

/** The PJ's saved paraphrase, target and capaian for an RK and period. */
export interface RkRecapState {
    paraphrase: string | null;
    target: number | null;
    target_unit: string | null;
    capaian: number | null;
    locked: boolean;
    locked_by: string | null;
}

/** One RK of the team in a week or month (GET team-recap.weekly / monthly `panels`). */
export interface RkPanel {
    performance_plan_id: number;
    rk_description: string;
    project_id: number | null;
    project_name: string | null;
    pic_employee_id: number | null;
    entry_count: number;
    entries: RkEntry[];
    recap: RkRecapState | null;
    default_unit: string | null;
    /** Monthly only: the weekly paraphrases of the month. */
    weeks: Array<{ label: string; start: string; paraphrase: string }>;
}

export interface RecapSegment {
    project_id: number | null;
    project_name: string;
    /** The ketua tim's RK this Projek hangs under. */
    leader_rk?: string | null;
    rows: RecapRow[];
}

/** The PJ's narrative for one Projek of a month or quarter. */
export interface RecapSummaryText {
    body: string | null;
    obstacle: string | null;
    solution: string | null;
    follow_up_plan: string | null;
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
    is_sent: boolean;
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

export interface CreditQuarterOverride {
    reason: string;
    by: string | null;
    /** pegawai = the person edited it; admin = an admin did. */
    role: 'pegawai' | 'admin';
    at: string | null;
}

export interface CreditQuarter {
    /** "2026-3" = TW III 2026. */
    key: string;
    label: string;
    /** The AK that counts: the manual one when `override` is set. */
    ak: number;
    /** What the calculation says, kept next to a manual AK. */
    computed_ak: number;
    override: CreditQuarterOverride | null;
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

/** One week of a month, or one month of a quarter, shown read-only on the recap. */
export interface RecapSection {
    label: string;
    start: string;
    end: string;
    segments: RecapSegment[];
}

/** One kegiatan of the signed-in member on "Rekap Mingguan". Target and capaian are numbers. */
export interface PlanItemRow {
    id: number;
    team_id: number;
    description: string;
    performance_plan_id: number;
    rk_name: string | null;
    project_id: number | null;
    date_start: string;
    date_end: string;
    /** "HH:MM" for a kegiatan with a clock time (one day), else null. */
    time_start: string | null;
    time_end: string | null;
    target: number | null;
    target_unit: string | null;
    capaian: number | null;
    /** Uraian capaian: the text sent to kipApp. */
    capaian_note: string | null;
    evidence_url: string | null;
    progress: number;
    status: 'planned' | 'pushed' | 'in_progress' | 'done' | 'cancelled';
    source: 'member' | 'pj' | 'rtl';
    /** Why the last push to kipApp failed; only while the plan is still unsent. */
    push_error: string | null;
    in_kipapp: boolean;
    /** Sent to the atasan in kipApp ("Kirim ke atasan"). */
    sent: boolean;
    /** Date (YYYY-MM-DD) it was sent, else null. */
    sent_at: string | null;
    /** kipApp "Masukan ke capaian SKP". */
    is_capaian_skp: boolean;
    /** False until the start date: kipApp takes no future date, so it cannot be pushed yet. */
    startable: boolean;
    /** What the settings and the SKP status allow for a kegiatan that is already in kipApp. */
    can_edit: boolean;
    can_delete: boolean;
    can_send: boolean;
    /** Short reason when the quarter SKP is locked in kipApp. */
    lock_reason: string | null;
}

/** One row of the kipApp presensi list ("List Aktivitas"). */
export interface PresensiRow {
    id: string;
    /** YYYY-MM-DD */
    date: string;
    description: string;
}

export interface FraQuarter {
    allocation_value: number;
    realization_value: number;
    capaian_quarter: number | null;
    capaian_year: number | null;
}

export interface FraQuarterInput {
    allocation_x: number | null;
    allocation_y: number | null;
    realization_x: number | null;
    realization_y: number | null;
    obstacle: string | null;
    solution: string | null;
    follow_up: string | null;
    pic: string | null;
    deadline: string | null;
    evidence_url: string | null;
    previous_follow_up_url: string | null;
}

export interface FraIndicatorRow {
    id: number;
    sort_order: number;
    tujuan: string | null;
    sasaran_code: string;
    sasaran_name: string | null;
    code: string;
    name: string;
    kind: 'IKU' | 'Proksi';
    period_type: string;
    unit_type: 'percent' | 'value';
    /** A value row that the sheet marks as "%". */
    percent_label: boolean;
    unit: string | null;
    x_label: string | null;
    y_label: string | null;
    target_x: number | null;
    target_y: number | null;
    owner_team_id: number | null;
    target_value: number;
    quarters: Record<number, FraQuarter>;
    values: Record<number, FraQuarterInput>;
    normalized: number | null;
    correction: number;
    final: number | null;
}

export interface FraSummary {
    quarter: number;
    sakip_score: number | null;
    sakip_predicate: string;
    iku_capaian_quarter: number | null;
    iku_capaian_year: number | null;
    sasaran: Array<{ code: string; name: string | null; capaian_year: number | null }>;
    nko: number | null;
    pko_predicate: string | null;
}

export interface FraProps {
    indicators: FraIndicatorRow[];
    summary: FraSummary;
    year: number;
    quarter: number;
    teams: Array<{ id: number; name: string }>;
    isAdmin: boolean;
    editableIds: number[];
}

export interface ComplianceWeek {
    week_start: string;
    members: number;
    planners: number;
    plans: number;
    /** Outcome counts of plans; null while the week is still open. */
    done: number | null;
    in_progress: number | null;
    not_started: number | null;
}

export interface ComplianceTeam {
    team_id: number;
    name: string;
    weeks: ComplianceWeek[];
    missing_this_week: string[];
}

export interface PlanComplianceProps {
    weeks: string[];
    teams: ComplianceTeam[];
}

export interface PlanRkOption {
    id: number;
    description: string;
    team_id: number;
    team_name: string | null;
    project_id: number | null;
    project_candidates: number[];
    project_optional: boolean;
    target_unit: string | null;
}

export interface MemberWeekProps {
    employee: { id: number; name: string } | null;
    view: 'months' | 'weeks' | 'detail';
    periods: PeriodRow[];
    year: number;
    month: number | null;
    /** The "Kirim ke atasan" buttons are shown only when the setting is on. */
    canSendToAtasan: boolean;
    /** The SKP year (kinetik.kip.tahun): dates of a kegiatan must stay in one quarter of it. */
    skpYear: number;
    weekStart?: string;
    weekEnd?: string;
    lastWeekStart?: string;
    lastWeekEnd?: string;
    prevWeek?: string;
    nextWeek?: string;
    lastWeek?: PlanItemRow[];
    thisWeek?: PlanItemRow[];
    /** The PJ's focus for this member this week. */
    focus?: string | null;
    rkOptions?: PlanRkOption[];
    projectOptions?: Array<{ id: number; name: string }>;
    /** False until the member synced their RK list from kipApp. */
    rkSynced?: boolean;
}


/** One row of a period list (a month or a week of the recap). */
export interface PeriodRow {
    key: number | string;
    label: string;
    activities: number;
    written: number;
    locked: number;
    /** True for the month or week that contains today. */
    current: boolean;
}

/** A member's plan item for one week, as the PJ sees it (read only). */
export interface TeamWeekPlan {
    id: number;
    employee_id: number;
    employee_name: string;
    description: string;
    rk_name: string | null;
    date_start: string;
    date_end: string;
    target: number | null;
    target_unit: string | null;
    capaian: number | null;
    capaian_note: string | null;
    evidence_url: string | null;
    status: 'planned' | 'pushed' | 'in_progress' | 'done' | 'cancelled';
    in_kipapp: boolean;
    sent: boolean;
}

/** One group of things that need the head's or PJ's attention. */
export interface AttentionGroup {
    key: string;
    title: string;
    hint: string;
    severity: 'high' | 'medium' | 'info';
    count: number;
    samples: string[];
    route: string;
}

export interface OrgPerson {
    id: number;
    name: string;
    position: string | null;
}

export interface OrgTeam {
    id: number;
    name: string;
    leader: (OrgPerson & { role: 'leader' }) | null;
    members: (OrgPerson & { role: 'member' })[];
}

export interface OrgTreeData {
    satker: string;
    head: OrgPerson | null;
    employee_count: number;
    teams: OrgTeam[];
}

export interface OrganizationProps {
    tree: OrgTreeData;
}

/** A yearly RK that a quarter SKP can take (SKP Saya). */
export interface SkpRkCandidate {
    id: string;
    rencanakinerja: string;
    /** Already in the quarter SKP. */
    in_skp: boolean;
    jmliki: number;
}

export interface SkpQuarter {
    quarter: 1 | 2 | 3 | 4;
    is_current: boolean;
    exists: boolean;
    id: string | null;
    status_id: number | null;
    status: string | null;
    jml_rk: number;
    jml_kegiatan: number;
    create_blocker: string | null;
    can_create: boolean;
    can_add_rk: boolean;
    blockers: string[];
    can_submit: boolean;
    can_cancel: boolean;
    candidates: SkpRkCandidate[];
}

export interface SkpYearly {
    id: string;
    status_id: number | null;
    status: string | null;
    jml_rk: number;
    atasan: string | null;
    blockers: string[];
    can_submit: boolean;
    can_cancel: boolean;
}

export interface SkpOverview {
    year: number;
    yearly: SkpYearly | null;
    quarters: SkpQuarter[];
}

/** One SKP that waits for the atasan (Persetujuan SKP). */
export interface SkpApprovalRow {
    id: number;
    employee: string;
    label: string;
    kind: 'yearly' | 'quarter';
    status: string | null;
    jml_rk: number;
    atasan: string | null;
    since: string | null;
    /** The signed-in person is this SKP's atasan. */
    mine: boolean;
    evidence: SkpEvidenceRow[];
}

/** What the member did under one RK in the quarter, from Kinetik's own data. */
export interface SkpEvidenceRow {
    rk: string;
    kegiatan: number;
    selesai: number;
    terkirim: number;
    target: number | null;
    capaian: number | null;
    unit: string | null;
    catatan: string[];
}

/** The atasan's score so far on one RK or behaviour. */
export interface SkpRatingRow {
    id: string | number;
    name: string;
    nilai: number | null;
    level: number | null;
    umpanbalik: string | null;
}
