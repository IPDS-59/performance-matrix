import { beforeEach, describe, expect, it } from 'vitest';
import { clearDraft, loadDraft, saveDraft } from '@/composables/useFormDraft';

describe('useFormDraft', () => {
    beforeEach(() => localStorage.clear());

    it('returns what was saved', () => {
        saveDraft('k', { description: 'Rapat koordinasi' });
        expect(loadDraft('k')).toEqual({ description: 'Rapat koordinasi' });
    });

    it('returns null when nothing was saved or it was cleared', () => {
        expect(loadDraft('k')).toBeNull();
        saveDraft('k', { a: 1 });
        clearDraft('k');
        expect(loadDraft('k')).toBeNull();
    });

    it('drops a draft older than a day', () => {
        saveDraft('k', { a: 1 }, 1000);
        expect(loadDraft('k', 1000 + 25 * 60 * 60 * 1000)).toBeNull();
        expect(localStorage.getItem('kinetik.draft.k')).toBeNull();
    });

    it('keeps drafts of different keys apart', () => {
        saveDraft('plan-item.new', { a: 1 });
        saveDraft('plan-item.7', { a: 2 });
        expect(loadDraft('plan-item.7')).toEqual({ a: 2 });
    });
});
