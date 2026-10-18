import { describe, expect, it } from 'vitest';
import { useOrgTree } from '@/composables/useOrgTree';
import type { OrgTreeData } from '@/types';

const person = (id: number, name: string) => ({ id, name, position: null });
const tree: OrgTreeData = {
    satker: 'BPS Provinsi Sulawesi Tengah',
    head: person(1, 'Kepala'),
    employee_count: 3,
    teams: [
        { id: 10, name: 'KEUANGAN', leader: { ...person(2, 'Ketua Uang'), role: 'leader' }, members: [{ ...person(3, 'Budi Santoso'), role: 'member' }] },
        { id: 11, name: 'UMUM', leader: { ...person(4, 'Ketua Umum'), role: 'leader' }, members: [{ ...person(3, 'Budi Santoso'), role: 'member' }] },
    ],
};

describe('useOrgTree', () => {
    it('keeps every team without a search and narrows to matches with one', () => {
        const org = useOrgTree(tree);
        expect(org.teams.value).toHaveLength(2);
        org.query.value = 'budi';
        expect(org.teams.value.map((t) => t.name)).toEqual(['KEUANGAN', 'UMUM']);
        expect(org.teams.value[0].leader).toBeNull();
        org.query.value = 'umum';
        expect(org.teams.value.map((t) => t.name)).toEqual(['UMUM']);
    });

    it('knows a person sits in several teams and toggles the pick', () => {
        const org = useOrgTree(tree);
        org.select(3);
        expect(org.selected.value?.teams).toEqual(['KEUANGAN', 'UMUM']);
        org.select(3);
        expect(org.selected.value).toBeNull();
    });

    it('collapses and opens teams, and always opens them while searching', () => {
        const org = useOrgTree(tree);
        org.setAll(false);
        expect(org.isOpen(10)).toBe(false);
        org.toggle(10);
        expect(org.isOpen(10)).toBe(true);
        org.setAll(false);
        org.query.value = 'x';
        expect(org.isOpen(10)).toBe(true);
    });
});
