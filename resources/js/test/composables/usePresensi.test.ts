import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import { filterPresensi, loadPresensi, sortPresensi } from '@/composables/usePresensi';

vi.mock('axios');
const rows = [
    { id: '2', date: '2026-10-06', description: 'Rapat koordinasi' },
    { id: '1', date: '2026-10-02', description: 'Entri data' },
];

describe('usePresensi', () => {
    beforeEach(() => { (globalThis as { route?: unknown }).route = (name: string) => `/${name}`; });

    it('sorts oldest first and filters by date or text', () => {
        expect(sortPresensi(rows).map(r => r.id)).toEqual(['1', '2']);
        expect(filterPresensi(rows, 'rapat')).toHaveLength(1);
        expect(filterPresensi(rows, '2026-10-02')).toHaveLength(1);
        expect(filterPresensi(rows, '  ')).toHaveLength(2);
    });

    it('loads the rows sorted', async () => {
        vi.mocked(axios.get).mockResolvedValue({ data: { data: rows } });
        expect((await loadPresensi('2026-10-05')).map(r => r.id)).toEqual(['1', '2']);
    });

    it('turns a server message or a network error into an Error with text for the user', async () => {
        vi.mocked(axios.get).mockRejectedValueOnce({ response: { data: { message: 'NIP lama pegawai belum terisi' } } });
        await expect(loadPresensi('2026-10-05')).rejects.toThrow('NIP lama pegawai belum terisi');
        vi.mocked(axios.get).mockRejectedValueOnce(new Error('network'));
        await expect(loadPresensi('2026-10-05')).rejects.toThrow('belum bisa diambil');
    });
});
