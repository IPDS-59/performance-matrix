/** Unsent form values kept in the browser, so closing a dialog by accident does not lose what was typed. */
const PREFIX = 'kinetik.draft.';
const MAX_AGE_MS = 24 * 60 * 60 * 1000;

interface Stored<T> { savedAt: number; data: T }

export function saveDraft<T>(key: string, data: T, now = Date.now()): void {
    try {
        localStorage.setItem(PREFIX + key, JSON.stringify({ savedAt: now, data } satisfies Stored<T>));
    } catch {
        // storage full or blocked: the draft is a convenience, never an error
    }
}

/** The saved values, or null when there are none or they are older than a day. */
export function loadDraft<T>(key: string, now = Date.now()): T | null {
    try {
        const raw = localStorage.getItem(PREFIX + key);
        if (!raw) return null;
        const stored = JSON.parse(raw) as Stored<T>;
        if (now - stored.savedAt > MAX_AGE_MS) {
            clearDraft(key);
            return null;
        }
        return stored.data;
    } catch {
        return null;
    }
}

export function clearDraft(key: string): void {
    try {
        localStorage.removeItem(PREFIX + key);
    } catch {
        // see saveDraft
    }
}
