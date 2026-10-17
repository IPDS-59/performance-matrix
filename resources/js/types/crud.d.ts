import type { Team } from '@/types';

/** Teams a form popup can pick, and whether the user may pick any team (admin). */
export interface TeamPickProps {
    formTeams: Team[];
    manager: boolean;
}

/** A project a RK popup can attach to. */
export interface ProjectChoice {
    id: number;
    name: string;
    year: number;
    team_id: number;
}
