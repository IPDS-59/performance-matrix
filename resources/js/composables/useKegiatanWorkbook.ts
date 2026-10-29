import * as XLSX from 'xlsx';

/** One kegiatan row read from a "Kegiatan Mingguan Anggota" workbook. Dates are YYYY-MM-DD or null. */
export interface WorkbookRow {
    sheet: string;
    member: string;
    project: string | null;
    date_start: string | null;
    date_end: string | null;
    time_start: string | null;
    time_end: string | null;
    description: string;
    /** NIP from the pegawai list, when one was given. */
    nip?: string | null;
    target: number | null;
    realization: number | null;
    percent: number | null;
    note: string | null;
    link: string | null;
}

/** Header label (normalised) to field. The workbook has five slightly different layouts, so columns are found by label, never by position. */
const LABELS: Array<[RegExp, keyof WorkbookRow | 'ignore']> = [
    [/^nama anggota$/, 'member'],
    [/^projek$/, 'project'],
    [/^tanggal mulai$/, 'date_start'],
    [/^tanggal selesai$/, 'date_end'],
    [/^tanggal$/, 'date_start'],
    [/^jam mulai$/, 'time_start'],
    [/^jam selesai$/, 'time_end'],
    [/^uraian( pekerjaan)?$/, 'description'],
    [/^target$/, 'target'],
    [/^realisasi$/, 'realization'],
    [/^capaian kinerja/, 'percent'],
    [/^capaian$/, 'note'],
    [/^link/, 'link'],
];

const clean = (v: unknown) => String(v ?? '').replace(/\s+/g, ' ').trim();
const pad = (n: number) => String(n).padStart(2, '0');

const MONTHS = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
const monthOf = (word: string) => MONTHS.findIndex((m) => m === word || (word.length >= 3 && m.startsWith(word.slice(0, 3)))) + 1;

function toIso(v: unknown): string | null {
    if (v instanceof Date && !Number.isNaN(v.getTime())) return `${v.getFullYear()}-${pad(v.getMonth() + 1)}-${pad(v.getDate())}`;
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(clean(v));
    return m ? `${m[1]}-${m[2]}-${m[3]}` : null;
}

/** Dates typed as text: "12 Juni", "12 Juni 2026", and ranges such as "2-3 Juni". The sheet has no year, so the deck year is used. */
function textDates(v: unknown, year: number): [string, string] | null {
    const m = /^(\d{1,2})(?:\s*[-–]\s*(\d{1,2}))?\s+([A-Za-z]+)\.?(?:\s+(\d{4}))?$/.exec(clean(v));
    const month = m ? monthOf(m[3].toLowerCase()) : 0;
    if (!m || !month) return null;
    const y = Number(m[4] ?? year);
    const iso = (d: string) => `${y}-${pad(month)}-${pad(Number(d))}`;

    return [iso(m[1]), iso(m[2] ?? m[1])];
}

function toTime(v: unknown): string | null {
    if (v instanceof Date && !Number.isNaN(v.getTime())) return `${pad(v.getHours())}:${pad(v.getMinutes())}`;
    const m = /^(\d{1,2})[:.](\d{2})/.exec(clean(v));
    return m ? `${pad(Number(m[1]))}:${m[2]}` : null;
}

function toNumber(v: unknown): number | null {
    if (typeof v === 'number') return Number.isFinite(v) ? v : null;
    const s = clean(v).replace(/%$/, '').replace(/\./g, '').replace(',', '.');
    return s !== '' && Number.isFinite(Number(s)) ? Number(s) : null;
}

/** Rows of every team sheet. Sheets without a "Nama Anggota" header (Proyek, Sheet2) are skipped. */
export function parseKegiatanRows(cells: Record<string, unknown[][]>, year = new Date().getFullYear()): WorkbookRow[] {
    const out: WorkbookRow[] = [];

    for (const [sheet, grid] of Object.entries(cells)) {
        let columns = new Map<number, keyof WorkbookRow>();
        let spans = new Map<keyof WorkbookRow, number[]>();
        const isHeader = (row: unknown[]) => row.some((c) => clean(c).toLowerCase() === 'nama anggota');
        const readHeader = (row: unknown[]) => {
            columns = new Map();
            row.forEach((cell, i) => {
                const hit = LABELS.find(([re]) => re.test(clean(cell).toLowerCase()));
                if (hit && hit[1] !== 'ignore' && ![...columns.values()].includes(hit[1])) columns.set(i, hit[1]);
            });
            // A header such as "Uraian" is often merged over two columns: the text sits under the second one.
            const starts = [...columns.keys()].sort((a, b) => a - b);
            spans = new Map(starts.map((c, k) => [columns.get(c)!, Array.from({ length: (starts[k + 1] ?? row.length) - c }, (_, n) => c + n)]));
        };
        const cell = (row: unknown[], field: keyof WorkbookRow) => row[(spans.get(field) ?? [-1])[0]];
        const text = (row: unknown[], field: keyof WorkbookRow) => {
            for (const i of spans.get(field) ?? []) {
                const v = clean(row[i]);
                if (v && !/^-?[\d.,]+$/.test(v)) return v; // a lone number is the row numbering, not the text
            }
            return '';
        };

        let member = '';
        let project: string | null = null;
        for (const row of grid) {
            if (isHeader(row)) {
                readHeader(row); // also a header repeated further down the sheet
                member = '';
                project = null;
                continue;
            }
            if (!columns.size) continue;
            if (typeof row[1] === 'number' && row[1] < 0) continue; // the "-1, -2, ..." column numbering row

            const name = clean(cell(row, 'member'));
            if (name) {
                member = name;
                project = null;
            }
            const proj = text(row, 'project');
            if (proj) project = proj;

            const description = text(row, 'description');
            if (!member || !description) continue;

            const raw = cell(row, 'date_start');
            const range = textDates(raw, year);
            const start = range?.[0] ?? toIso(raw);
            out.push({
                sheet,
                member,
                project,
                date_start: start,
                date_end: range?.[1] ?? toIso(cell(row, 'date_end')) ?? start,
                time_start: toTime(cell(row, 'time_start')),
                time_end: toTime(cell(row, 'time_end')),
                description,
                target: toNumber(cell(row, 'target')),
                realization: toNumber(cell(row, 'realization')),
                percent: toNumber(cell(row, 'percent')),
                note: text(row, 'note') || null,
                link: text(row, 'link') || null,
            });
        }
    }

    return out;
}

/** Reads an uploaded .xlsx in the browser, so the server never has to parse spreadsheets. */
export async function readKegiatanWorkbook(file: File, year: number): Promise<WorkbookRow[]> {
    const book = XLSX.read(await file.arrayBuffer(), { cellDates: true });
    const cells: Record<string, unknown[][]> = {};
    for (const name of book.SheetNames) cells[name] = XLSX.utils.sheet_to_json(book.Sheets[name], { header: 1, raw: true, defval: null }) as unknown[][];

    return parseKegiatanRows(cells, year);
}

/** The "Daftar Pegawai" export: NIP BPS, NIP, Nama. */
export interface PegawaiNip { name: string; nip: string; nip_bps: string }

export function parsePegawaiRows(grid: unknown[][]): PegawaiNip[] {
    const at = grid.findIndex((r) => r.some((c) => clean(c).toLowerCase() === 'nama'));
    if (at < 0) return [];
    const head = grid[at].map((c) => clean(c).toLowerCase());
    const [iNip, iBps, iName] = [head.indexOf('nip'), head.indexOf('nip bps'), head.indexOf('nama')];

    return grid.slice(at + 1)
        .map((r) => ({ name: clean(r[iName]), nip: clean(r[iNip]), nip_bps: clean(r[iBps]) }))
        .filter((p) => p.name && (p.nip || p.nip_bps));
}

export async function readPegawaiList(file: File): Promise<PegawaiNip[]> {
    const book = XLSX.read(await file.arrayBuffer());
    return parsePegawaiRows(XLSX.utils.sheet_to_json(book.Sheets[book.SheetNames[0]], { header: 1, raw: false, defval: null }) as unknown[][]);
}

/** Letters and digits only, so "S. Tr. Stat" and "S.Tr.Stat." are the same text. */
const squash = (name: string) => name.toLowerCase().replace(/[^a-z0-9]/g, '');
/** The name without titles: before the comma, minus words with a dot or short all-capital words (SST, SE). */
function bare(name: string): string {
    const words = name.split(',')[0].trim().split(/\s+/).filter(Boolean);
    return words.filter((w, i) => (i === 0 ? !/^(dr|drs|ir|prof|h|hj)\.?$/i.test(w) : !(w.includes('.') || (w === w.toUpperCase() && w.length <= 5 && /^[A-Za-z]+$/.test(w))))).join(' ').toLowerCase();
}

/** Puts the NIP of the pegawai list on each row: by the whole name first, then by the name without titles when that is unique. */
export function attachNip(rows: WorkbookRow[], pegawai: PegawaiNip[]): WorkbookRow[] {
    const full = new Map<string, PegawaiNip[]>();
    const short = new Map<string, PegawaiNip[]>();
    for (const p of pegawai) {
        full.set(squash(p.name), [...(full.get(squash(p.name)) ?? []), p]);
        short.set(bare(p.name), [...(short.get(bare(p.name)) ?? []), p]);
    }
    return rows.map((r) => {
        const hit = [full.get(squash(r.member)), short.get(bare(r.member))].find((c) => c?.length === 1)?.[0];
        return { ...r, nip: hit ? (hit.nip || hit.nip_bps) : null };
    });
}
