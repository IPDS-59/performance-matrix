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
    performance_reports?: PerformanceReport[];
}

export interface PerformanceReport {
    id: number;
    work_item_id: number;
    reported_by?: number | null;
    period_month: number;
    period_year: number;
    achievement_percentage: number;
    issues?: string | null;
    solutions?: string | null;
    action_plan?: string | null;
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
