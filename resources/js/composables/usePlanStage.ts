import type { PlanItemRow } from '@/types';

/** The same keys as the PHP enum App\Kinetik\PlanStage: the server derives the stage, this file gives it words, a color and the next action. */
export type PlanStageKey = 'planned' | 'in_kipapp' | 'in_progress' | 'done' | 'sent' | 'cancelled';
export type PlanTone = 'neutral' | 'info' | 'warning' | 'success' | 'done';
export type PlanAction = 'push' | 'complete' | 'send' | 'unsend' | null;

export interface PlanStage {
    key: PlanStageKey;
    label: string;
    tone: PlanTone;
    /** The one thing the member does next, or null when nothing is left. */
    action: PlanAction;
    actionLabel: string | null;
    /** 0 to 4: how far along the chain the plan is (planned, in kipApp, in progress, done, sent). */
    step: number;
}

/**
 * Where a plan item stands, in the words of the kipApp flow. With `sendEnabled` off, the
 * "Kirim ke atasan" step shows no action: it is not tested against kipApp yet. "Kirim ke kipApp"
 * creates the kegiatan; "Kirim ke atasan" sends it for rating. Two different
 * steps, so two different labels.
 */
type StageInput = Pick<PlanItemRow, 'status' | 'in_kipapp' | 'sent'> & { stage?: PlanStageKey };

export function planStage(plan: StageInput, sendEnabled = true): PlanStage {
    const stage = stageOf(plan);
    const sendStep = stage.action === 'send' || stage.action === 'unsend';

    return sendEnabled || !sendStep ? stage : { ...stage, action: null, actionLabel: null };
}

const STAGES: Record<PlanStageKey, PlanStage> = {
    planned: { key: 'planned', label: 'Direncanakan', tone: 'neutral', action: 'push', actionLabel: 'Kirim ke kipApp', step: 0 },
    in_kipapp: { key: 'in_kipapp', label: 'Ada di kipApp', tone: 'info', action: 'complete', actionLabel: 'Tandai selesai', step: 1 },
    in_progress: { key: 'in_progress', label: 'Berjalan', tone: 'warning', action: 'complete', actionLabel: 'Tandai selesai', step: 2 },
    done: { key: 'done', label: 'Selesai, belum dikirim', tone: 'success', action: 'send', actionLabel: 'Kirim ke atasan', step: 3 },
    sent: { key: 'sent', label: 'Terkirim ke atasan', tone: 'done', action: 'unsend', actionLabel: 'Batalkan kirim', step: 4 },
    cancelled: { key: 'cancelled', label: 'Dibatalkan', tone: 'neutral', action: null, actionLabel: null, step: 0 },
};

/** The stage the server sent, or the same rule worked out here for rows that carry none. */
function stageOf(plan: StageInput): PlanStage {
    if (plan.stage) return STAGES[plan.stage];
    if (plan.sent) return STAGES.sent;
    if (plan.status === 'done') return STAGES.done;
    if (plan.status === 'in_progress') return STAGES.in_progress;
    if (plan.in_kipapp || plan.status === 'pushed') return STAGES.in_kipapp;

    return STAGES.planned;
}

export const toneClass: Record<PlanTone, string> = {
    neutral: 'bg-gray-100 text-gray-700',
    info: 'bg-blue-50 text-blue-800',
    warning: 'bg-amber-50 text-amber-800',
    success: 'bg-green-50 text-green-800',
    done: 'bg-emerald-100 text-emerald-900',
};
