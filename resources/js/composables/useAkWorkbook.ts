import * as XLSX from 'xlsx';

/** One person of the office's "Rekapan SKP" workbook: AK integrated up to 2022 and the AK of each later year. */
export interface AkRow {
    nip: string;
    name: string;
    integrasi: number | null;
    y2023: number | null;
    y2024: number | null;
    y2025: number | null;
    jumlah: number | null;
    note: string | null;
}

const clean = (v: unknown) => String(v ?? '').replace(/\s+/g, ' ').trim();

function num(v: unknown): number | null {
    if (typeof v === 'number' && Number.isFinite(v)) return Math.round(v * 1000) / 1000;
    const t = clean(v).replace(',', '.');
    return /^-?\d+(\.\d+)?$/.test(t) ? Math.round(Number(t) * 1000) / 1000 : null;
}

/** Sheet names of a workbook, so the person can pick the office sheet (the file has one per satker). */
export async function sheetNames(file: File): Promise<string[]> {
    return XLSX.read(await file.arrayBuffer(), { type: 'array', bookSheets: true }).SheetNames;
}

/**
 * Reads one sheet. Columns are found by their header label (NIP, NAMA, INTEGRASI, Jumlah, KETERANGAN) and the years by the
 * 2023/2024/2025 cells under the header, because every sheet of the file lays them out a little differently.
 */
export async function readAkWorkbook(file: File, sheet: string): Promise<AkRow[]> {
    const wb = XLSX.read(await file.arrayBuffer(), { type: 'array' });
    const ws = wb.Sheets[sheet];
    if (!ws) throw new Error(`Sheet "${sheet}" tidak ada di file.`);
    const grid = XLSX.utils.sheet_to_json<unknown[]>(ws, { header: 1, raw: true, defval: null });

    const headerAt = grid.findIndex((r) => r.some((c) => /^nip$/i.test(clean(c))));
    if (headerAt < 0) throw new Error('Baris judul dengan kolom NIP tidak ditemukan.');
    const header = grid[headerAt].map(clean);
    const col = (re: RegExp) => header.findIndex((h) => re.test(h));
    const nip = col(/^nip$/i);
    const name = col(/^nama/i);
    const integrasi = col(/^integrasi/i);
    const jumlah = col(/^jumlah$/i);
    const note = col(/^keterangan/i);

    const years: Record<number, number> = {};
    for (const row of grid.slice(headerAt, headerAt + 3)) {
        row.forEach((c, i) => {
            const y = Number(clean(c));
            if ([2023, 2024, 2025].includes(y) && years[y] === undefined) years[y] = i;
        });
    }

    return grid.slice(headerAt + 1)
        .filter((r) => clean(r[nip]) !== '' && /\d{8}/.test(clean(r[nip])))
        .map((r) => ({
            nip: clean(r[nip]).replace(/\D/g, ''),
            name: clean(r[name]),
            integrasi: integrasi >= 0 ? num(r[integrasi]) : null,
            y2023: years[2023] !== undefined ? num(r[years[2023]]) : null,
            y2024: years[2024] !== undefined ? num(r[years[2024]]) : null,
            y2025: years[2025] !== undefined ? num(r[years[2025]]) : null,
            jumlah: jumlah >= 0 ? num(r[jumlah]) : null,
            note: note >= 0 && clean(r[note]) !== '' ? clean(r[note]) : null,
        }));
}
