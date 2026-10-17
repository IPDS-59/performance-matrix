import { describe, expect, it } from 'vitest';
import { appendSentence, filterEntries, panelState } from '@/composables/useRkRecap';
import type { RkEntry } from '@/types';

const entry = (id: number, name: string, lines: string[]): RkEntry => ({ employee_id: id, name, lines, items: 1, target: null, capaian: null, unit: null });
const entries = [entry(1, 'Bayu', ['Menyusun skema tabel.']), entry(2, 'Sukma', ['Membuat halaman rekap.']), entry(3, 'Ical', ['Sinkron kegiatan.'])];

describe('filterEntries', () => {
    const base = { query: '', onlyUnread: false, person: null, read: new Set<number>() };

    it('keeps everything without a filter', () => {
        expect(filterEntries(entries, base)).toHaveLength(3);
    });

    it('searches name and sentences, ignoring case', () => {
        expect(filterEntries(entries, { ...base, query: 'HALAMAN' }).map(e => e.name)).toEqual(['Sukma']);
        expect(filterEntries(entries, { ...base, query: 'bayu' }).map(e => e.name)).toEqual(['Bayu']);
    });

    it('hides read entries when asked', () => {
        expect(filterEntries(entries, { ...base, onlyUnread: true, read: new Set([1, 3]) }).map(e => e.name)).toEqual(['Sukma']);
    });

    it('shows one person', () => {
        expect(filterEntries(entries, { ...base, person: 3 }).map(e => e.name)).toEqual(['Ical']);
    });
});

describe('appendSentence', () => {
    it('starts the paraphrase or continues it', () => {
        expect(appendSentence('', ' Satu. ')).toBe('Satu.');
        expect(appendSentence('Satu.', 'Dua.')).toBe('Satu. Dua.');
    });
});

describe('panelState', () => {
    it('tells locked, written and empty apart', () => {
        const recap = (over: object) => ({ recap: { paraphrase: null, target: null, target_unit: null, capaian: null, locked: false, locked_by: null, ...over } });
        expect(panelState({ recap: null })).toBe('empty');
        expect(panelState(recap({ paraphrase: 'x' }))).toBe('written');
        expect(panelState(recap({ paraphrase: 'x', locked: true }))).toBe('locked');
    });
});
