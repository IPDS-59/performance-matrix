import type { Team } from '@/types';

/** Teams a form popup can pick, and whether the user may pick any team (admin). */
export interface TeamPickProps {
    formTeams: Team[];
    manager: boolean;
}
