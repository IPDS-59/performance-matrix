import type { PlanItemRow } from '@/types';

/** The fields of the kegiatan form. `use_range` and `use_time` are the two independent kipApp checkboxes. */
export interface EntryFormState {
    team_id: number | null;
    performance_plan_id: number | null;
    project_id: number | null;
    description: string;
    use_range: boolean;
    use_time: boolean;
    date_start: string;
    date_end: string;
    time_start: string;
    time_end: string;
    target: string | number;
    target_unit: string;
    capaian: string | number;
    /** Not `progress`: useForm has its own `progress` (upload progress) that would overwrite it. Sent as `progress`. */
    progres: string | number;
    evidence_url: string;
    capaian_note: string;
    is_capaian_skp: boolean;
    parent_id: number | null;
}

/** Today as YYYY-MM-DD in the browser's time zone. */
export function todayIso(now: Date = new Date()): string {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/** Logging past work starts at 100 like kipApp; planning starts at 0. */
export function defaultProgress(date: string, today: string): number {
    return date && date < today ? 100 : 0;
}

/** First and last day of the quarter that holds `date`, inside `year`. Used as min and max of the date inputs. */
export function quarterBounds(year: number, date: string): { min: string; max: string } {
    const month = /^\d{4}-(\d{2})/.exec(date)?.[1];
    const quarter = month ? Math.floor((Number(month) - 1) / 3) : 0;
    const first = quarter * 3 + 1;
    const lastDay = new Date(year, first + 2, 0).getDate();
    const pad = (n: number) => String(n).padStart(2, '0');
    return { min: `${year}-${pad(first)}-01`, max: `${year}-${pad(first + 2)}-${pad(lastDay)}` };
}

/**
 * Starting values of the form: empty (new), a kegiatan (edit) or a copy of one (adds parent_id). A copy is a new kegiatan,
 * so it starts without the capaian, link and note of the original: those are filled in later, when the capaian is updated.
 */
export function entryFormState(item: PlanItemRow | null, copyOf: PlanItemRow | null, defaultDate: string, today: string): EntryFormState {
    const source = item ?? copyOf;
    const result = item; // capaian, bukti dukung and its note belong to the kegiatan being updated, never to a new one
    const start = source?.date_start ?? defaultDate;
    const end = source?.date_end ?? start;
    return {
        team_id: source?.team_id ?? null,
        performance_plan_id: source?.performance_plan_id ?? null,
        project_id: source?.project_id ?? null,
        description: source?.description ?? '',
        use_range: end !== start,
        use_time: !!source?.time_start,
        date_start: start,
        date_end: end,
        time_start: source?.time_start ?? '08:00',
        time_end: source?.time_end ?? '16:00',
        target: source?.target ?? '',
        target_unit: source?.target_unit ?? '',
        capaian: result?.capaian ?? '',
        progres: source ? source.progress : defaultProgress(start, today),
        evidence_url: result?.evidence_url ?? '',
        capaian_note: result?.capaian_note ?? '',
        is_capaian_skp: source?.is_capaian_skp ?? true,
        parent_id: copyOf && !item ? copyOf.id : null,
    };
}

/** What goes to the server. Without the range checkbox the end is the start; without the time checkbox there is no time. */
export function entryPayload(data: EntryFormState): Record<string, unknown> {
    return {
        team_id: data.team_id,
        performance_plan_id: data.performance_plan_id,
        project_id: data.project_id,
        description: data.description,
        date_start: data.date_start,
        date_end: data.use_range ? data.date_end : data.date_start,
        time_start: data.use_time ? data.time_start : null,
        time_end: data.use_time ? data.time_end : null,
        target: data.target === '' ? null : data.target,
        target_unit: data.target_unit || null,
        capaian: data.capaian === '' ? null : data.capaian,
        progress: data.progres === '' ? 0 : data.progres,
        evidence_url: data.evidence_url || null,
        capaian_note: data.capaian_note || null,
        is_capaian_skp: data.is_capaian_skp,
        parent_id: data.parent_id,
    };
}

/** Turning the range off keeps the first date, like kipApp. */
export function dateEndAfterRangeToggle(useRange: boolean, start: string, end: string): string {
    return useRange ? (end >= start ? end : start) : start;
}
