/**
 * Helpers that speed up weekly claim entry. Most kipApp activities are one
 * finished task with no obstacle, so members can fill them in one click and
 * save every ready activity at once.
 */

export const NO_PROJECT = 'none';

export interface QuickClaimFields {
    performance_plan_id: number | null;
    project_id: string;
    target: string;
    realization: string;
    target_unit: string;
    obstacle: string;
}

/**
 * Fill only the empty fields. kipApp has no numeric target or realisasi for a
 * kegiatan, only its progres (0–100%), so a kegiatan counts as 1: realisasi is
 * its progres, the unit is the RK's IKI unit when known, else "Kegiatan".
 */
export function quickFill<T extends QuickClaimFields>(form: T, from: { progress?: number | null; unit?: string | null } = {}): void {
    const progress = from.progress ?? 100;
    if (form.target === '') form.target = '1';
    if (form.realization === '') form.realization = String(Math.round(Math.min(Math.max(progress, 0), 100)) / 100);
    if (form.target_unit === '') form.target_unit = from.unit?.trim() || 'Kegiatan';
    if (form.obstacle.trim() === '') form.obstacle = '-';
}

/**
 * A claim can be saved once it has an RK, target (> 0), realisasi, satuan, a
 * Kendala, and a Projek unless the RK has none.
 */
export function isReadyToSave(form: QuickClaimFields, projectRequired = false): boolean {
    return form.performance_plan_id !== null
        && Number(form.target) > 0
        && form.realization.trim() !== ''
        && form.target_unit.trim() !== ''
        && form.obstacle.trim() !== ''
        && (!projectRequired || form.project_id !== NO_PROJECT);
}

/** Server payload: the "no project" sentinel becomes null. */
export function toClaimPayload<T extends QuickClaimFields>(data: T): Omit<T, 'project_id'> & { project_id: number | null } {
    return { ...data, project_id: data.project_id === NO_PROJECT ? null : Number(data.project_id) };
}

/** Map bulk errors ("claims.2.obstacle") back to per-form errors, by batch position. */
export function splitBulkErrors(errors: Record<string, string>): Record<number, Record<string, string>> {
    const out: Record<number, Record<string, string>> = {};
    for (const [key, message] of Object.entries(errors)) {
        const match = key.match(/^claims\.(\d+)\.(.+)$/);
        if (!match) continue;
        const index = Number(match[1]);
        (out[index] ??= {})[match[2]] = message;
    }
    return out;
}

// Words that describe any result, not which project it belongs to.
const GENERIC_WORDS = new Set([
    'terlaksananya', 'tersedianya', 'terselenggaranya', 'meningkatnya', 'terwujudnya', 'tercapainya', 'terlaksana',
    'yang', 'dan', 'dalam', 'rangka', 'untuk', 'dengan', 'pada', 'serta', 'atau', 'terkait', 'kegiatan', 'sesuai',
    'berkualitas', 'handal', 'bermanfaat', 'tepat', 'waktu', 'baik', 'dukungan', 'penyelenggaraan', 'pelaksanaan',
]);

function words(text: string): string[] {
    return (text.toLowerCase().match(/[a-z0-9]+/g) ?? []).filter(w => w.length > 2 && !GENERIC_WORDS.has(w));
}

/**
 * kipApp links an RK to a team, not to a project, but the RK text names the
 * project ("Terlaksananya Pengelolaan Jaringan dan Internet ..." belongs to
 * "Pengelolaan Jaringan dan Internet"). Returns the project whose name words
 * appear in the RK text, only when one project clearly wins; the member's own
 * projects win a tie.
 */
export function suggestProject<P extends { id: number; name: string; is_member?: boolean }>(rkText: string, projects: P[]): P | null {
    const rk = new Set(words(rkText));
    const scored = projects
        .map(project => {
            const name = words(project.name);
            const score = name.length ? name.filter(w => rk.has(w)).length / name.length : 0;
            return { project, score: score + (project.is_member ? 0.001 : 0) };
        })
        .filter(s => s.score >= 0.6)
        .sort((a, b) => b.score - a.score);

    if (!scored.length) return null;
    if (scored.length > 1 && Math.abs(scored[0].score - scored[1].score) < 0.001) return null;

    return scored[0].project;
}
