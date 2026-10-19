<?php

namespace App\Kinetik\Sources;

use App\Kinetik\Exceptions\KipApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The kipApp calls that write a member's kegiatan, with the member's own token.
 * Payloads were verified on 2026-10-08 (create, update, delete). kipApp does
 * not return the new id, so a created row is found by listing before and after.
 */
class ApiKipWriter
{
    /** statusskpid of a yearly SKP that is "Ditetapkan". */
    private const YEARLY_APPROVED = 6;

    public function __construct(#[\SensitiveParameter] private readonly string $token) {}

    /**
     * The member's SKP of one quarter, or null when it was not created yet.
     *
     * @return array<string, mixed>|null
     */
    public function quarterSkp(string $pegawaiId, int $periodeId, int $quarter): ?array
    {
        $rows = $this->rows($this->get('v1/skp', ['pegawaiid' => $pegawaiId, 'jenis' => 2]), 'skp');

        return collect($rows)->first(fn (array $r) => (int) ($r['periodeid'] ?? 0) === $periodeId && (int) ($r['periodepenilaianid'] ?? 0) === $quarter);
    }

    /**
     * Creates the member's SKP of one quarter, like kipApp's "Buat SKP Periodik": the RK of the
     * yearly SKP (which must be Ditetapkan) that the member also had in the latest quarter, or all
     * of them when there is no earlier quarter. Verified live on 2026-10-05 (POST /skp/bulanan).
     *
     * @param  list<string>|null  $rkIds  the RK of the yearly SKP to take; null picks them as described above
     * @return array<string, mixed> the new quarterly SKP
     */
    public function createQuarterSkp(string $pegawaiId, int $periodeId, int $quarter, ?array $rkIds = null): array
    {
        $rows = $this->rows($this->get('v1/skp', ['pegawaiid' => $pegawaiId, 'jenis' => 1]), 'skp tahunan');
        $yearly = collect($rows)->first(fn (array $r) => (int) ($r['periodeid'] ?? 0) === $periodeId);
        if ($yearly === null) {
            throw new RuntimeException('SKP Tahunan belum ada di kipApp, jadi SKP triwulan belum bisa dibuat.');
        }
        if ((int) ($yearly['statusskpid'] ?? 0) !== self::YEARLY_APPROVED) {
            throw new RuntimeException('SKP Tahunan belum Ditetapkan ('.($yearly['statusskp'] ?? 'status tidak diketahui').'), jadi SKP triwulan belum bisa dibuat.');
        }

        $json = $this->get('v1/skp/rk/copy/bulanan', [
            'pegawaiid' => $pegawaiId, 'periodepenilaianid' => $quarter, 'periodeid' => $periodeId,
            'unitkerjaid' => $yearly['unitkerjaid'] ?? null, 'wilayahid' => $yearly['wilayahid'] ?? null,
        ])->json('rk') ?? [];
        $candidates = collect($json)->filter(fn ($r) => is_array($r) && ! ($r['iscopied'] ?? false))->values();
        if ($candidates->isEmpty()) {
            throw new RuntimeException('SKP Tahunan belum punya RK yang bisa dipakai untuk SKP triwulan.');
        }

        if ($rkIds !== null) {
            $chosen = $candidates->filter(fn (array $r) => in_array((string) $r['id'], array_map('strval', $rkIds), true));
            if ($chosen->isEmpty()) {
                throw new RuntimeException('Pilih minimal satu RK untuk SKP triwulan.');
            }
        } else {
            $earlier = $this->latestQuarterRkNames($pegawaiId, $periodeId, $quarter);
            $chosen = $candidates->filter(fn (array $r) => in_array($this->norm($r['rencanakinerja'] ?? null), $earlier, true));
            if ($chosen->isEmpty()) {
                $chosen = $candidates;
            }
        }

        $this->send('post', 'v1/skp/bulanan', [
            'id' => (string) $yearly['id'], 'periodepenilaianid' => $quarter, 'flagmethod' => 1,
            'data' => $chosen->map(fn (array $r) => [
                'rk' => $r['id'], 'iki' => collect($r['iki'] ?? [])->pluck('id')->values()->all(), 'flagrk' => $r['flagrk'] ?? 1,
            ])->values()->all(),
        ], 'buat SKP triwulan');

        return $this->quarterSkp($pegawaiId, $periodeId, $quarter)
            ?? throw new RuntimeException('kipApp menerima permintaan SKP triwulan, tetapi SKP-nya belum terbaca. Coba lagi sebentar lagi.');
    }

    /**
     * Adds RK of the yearly SKP to a quarter SKP that already exists (kipApp's edit of a periodic SKP,
     * POST /skp/bulanan with flagmethod 2). The call takes the whole selection, so the RK already in
     * the quarter SKP are sent again together with the new ones. Returns the RK ids now in the SKP.
     *
     * @param  list<string>  $wantedTexts  RK texts to add (any spacing or case)
     * @param  list<string>|null  $rkIds  yearly RK ids to add instead of matching by text
     * @return list<array<string, mixed>> the RK of the quarter SKP after the call
     */
    public function addRksToQuarterSkp(string $pegawaiId, int $periodeId, array $skp, array $wantedTexts, ?array $rkIds = null): array
    {
        $yearly = collect($this->rows($this->get('v1/skp', ['pegawaiid' => $pegawaiId, 'jenis' => 1]), 'skp tahunan'))
            ->first(fn (array $r) => (int) ($r['periodeid'] ?? 0) === $periodeId);
        if ($yearly === null) {
            throw new RuntimeException('SKP Tahunan belum ada di kipApp, jadi RK belum bisa ditambahkan ke SKP triwulan.');
        }

        $quarter = (int) $skp['periodepenilaianid'];
        $rows = collect($this->get('v1/skp/rk/copy/bulanan', [
            'pegawaiid' => $pegawaiId, 'periodepenilaianid' => $quarter, 'periodeid' => $periodeId,
            'unitkerjaid' => $yearly['unitkerjaid'] ?? null, 'wilayahid' => $yearly['wilayahid'] ?? null, 'skpid' => $skp['id'],
        ])->json('rk') ?? [])->filter(fn ($r) => is_array($r));

        $wantedNames = array_map(fn (string $t) => $this->norm($t), $wantedTexts);
        $toAdd = $rows->filter(fn (array $r) => ! ($r['iscopied'] ?? false) && ($rkIds !== null
            ? in_array((string) $r['id'], array_map('strval', $rkIds), true)
            : in_array($this->norm($r['rencanakinerja'] ?? null), $wantedNames, true)));
        if ($toAdd->isEmpty()) {
            throw new RuntimeException('RK rencana ini tidak ada di SKP Tahunan, jadi tidak bisa ditambahkan ke SKP triwulan.');
        }

        $selection = $rows->filter(fn (array $r) => ($r['iscopied'] ?? false))->merge($toAdd);
        $this->send('post', 'v1/skp/bulanan', [
            'id' => (string) $skp['id'], 'periodepenilaianid' => $quarter, 'flagmethod' => 2,
            'data' => $selection->map(fn (array $r) => [
                'rk' => $r['id'], 'iki' => collect($r['iki'] ?? [])->pluck('id')->values()->all(), 'flagrk' => $r['flagrk'] ?? 1,
            ])->values()->all(),
        ], 'tambah RK ke SKP triwulan');

        return $this->rks((string) $skp['id']);
    }

    /**
     * The RK of a quarter SKP with the atasan's rating so far (GET /skp/penilaian/rk/periodik): rkid,
     * rencanakinerja, nilaiumpanbalik, levelumpanbalik, umpanbalik. Read from kipApp's own page, tested live
     * on a rated SKP only.
     *
     * @return list<array<string, mixed>>
     */
    public function ratingRks(string $skpId): array
    {
        return $this->rows($this->get('v1/skp/penilaian/rk/periodik', ['skpid' => $skpId]), 'penilaian rk');
    }

    /** The seven behaviours with the atasan's rating so far (GET /perilaku). @return list<array<string, mixed>> */
    public function perilaku(string $skpId): array
    {
        return $this->rows($this->get('v1/perilaku', ['skpid' => $skpId]), 'perilaku');
    }

    /** Save the atasan's feedback on one RK (POST /skp/rk/umpanbalik), read from kipApp's own form. */
    public function rateRk(string $rkId, int $nilai, int $level, ?string $komentar): void
    {
        $this->send('post', 'v1/skp/rk/umpanbalik', ['id' => $rkId, 'nilaiumpanbalik' => $nilai, 'levelumpanbalik' => $level, 'umpanbalik' => $komentar], 'nilai RK');
    }

    /** Save the atasan's feedback on one behaviour (PUT /perilaku), read from kipApp's own form. */
    public function ratePerilaku(string $skpId, int $jenisPerilakuId, int $nilai, int $level, ?string $komentar, ?string $ekspektasiKhusus = null): void
    {
        $this->send('put', 'v1/perilaku', [
            'skpid' => $skpId, 'jenisperilakuid' => $jenisPerilakuId, 'nilaiumpanbalik' => $nilai,
            'levelumpanbalik' => $level, 'umpanbalik' => $komentar, 'ekspektasikhusus' => $ekspektasiKhusus,
        ], 'nilai perilaku');
    }

    /** One SKP by its id (the header kipApp shows), or null. @return array<string, mixed>|null */
    public function skpById(string $skpId): ?array
    {
        return collect($this->rows($this->get('v1/skp', ['skpid' => $skpId]), 'skp'))->first();
    }

    /** The signed-in person's own kipApp profile (id is the pegawaiid). @return array<string, mixed> */
    public function me(): array
    {
        return (array) $this->get('v1/user', [])->json();
    }

    /** The member's yearly SKP of one period, or null. @return array<string, mixed>|null */
    public function yearlySkp(string $pegawaiId, int $periodeId): ?array
    {
        return collect($this->rows($this->get('v1/skp', ['pegawaiid' => $pegawaiId, 'jenis' => 1]), 'skp tahunan'))
            ->first(fn (array $r) => (int) ($r['periodeid'] ?? 0) === $periodeId);
    }

    /** The member's quarter SKPs of one period, keyed by quarter (1 to 4). @return array<int, array<string, mixed>> */
    public function quarterSkps(string $pegawaiId, int $periodeId): array
    {
        return collect($this->rows($this->get('v1/skp', ['pegawaiid' => $pegawaiId, 'jenis' => 2]), 'skp'))
            ->filter(fn (array $r) => (int) ($r['periodeid'] ?? 0) === $periodeId && (int) ($r['periodepenilaianid'] ?? 0) > 0)
            ->keyBy(fn (array $r) => (int) $r['periodepenilaianid'])->all();
    }

    /**
     * RK of the yearly SKP that a quarter SKP can take, from kipApp's "copy" list. With the quarter SKP id
     * the rows say which RK are already in it (iscopied).
     *
     * @param  array<string, mixed>  $yearly
     * @return list<array{id: string, rencanakinerja: string, iscopied: bool, jmliki: int}>
     */
    public function rkCandidates(string $pegawaiId, int $periodeId, array $yearly, int $quarter, ?string $quarterSkpId = null): array
    {
        $query = [
            'pegawaiid' => $pegawaiId, 'periodepenilaianid' => $quarter, 'periodeid' => $periodeId,
            'unitkerjaid' => $yearly['unitkerjaid'] ?? null, 'wilayahid' => $yearly['wilayahid'] ?? null,
        ];
        if ($quarterSkpId !== null) {
            $query['skpid'] = $quarterSkpId;
        }

        return collect($this->get('v1/skp/rk/copy/bulanan', $query)->json('rk') ?? [])
            ->filter(fn ($r) => is_array($r))
            ->map(fn (array $r) => [
                'id' => (string) $r['id'], 'rencanakinerja' => (string) ($r['rencanakinerja'] ?? ''),
                'iscopied' => (bool) ($r['iscopied'] ?? false), 'jmliki' => count($r['iki'] ?? []),
            ])->values()->all();
    }

    /**
     * Moves a SKP to another status like kipApp's buttons do (POST /skp/penetapan/kirim): flag 2 sends a
     * yearly SKP to the atasan to be set (ditetapkan), flag 5 sends a quarter SKP to be rated. Flag 5
     * verified live on 2026-10-20 (status 1 became 5, "Sedang diperiksa"). Flag 2 is read from kipApp's own
     * code only. The atasan's own steps (6 tetapkan, 7 nilai) are not offered by Kinetik.
     */
    public function submitSkp(string $skpId, int $flag): void
    {
        $this->send('post', 'v1/skp/penetapan/kirim', ['skpid' => $skpId, 'flag' => $flag], 'kirim SKP');
    }

    /** Takes a sent SKP back to "Sedang dibuat" (POST /skp/kembalikan). Verified live on 2026-10-20 for a status 5 SKP. */
    public function returnSkp(string $skpId): void
    {
        $this->send('post', 'v1/skp/kembalikan', ['id' => $skpId], 'batalkan kirim SKP');
    }

    /** RK texts of the member's newest earlier quarter of that year, normalised. @return list<string> */
    private function latestQuarterRkNames(string $pegawaiId, int $periodeId, int $quarter): array
    {
        $previous = collect($this->rows($this->get('v1/skp', ['pegawaiid' => $pegawaiId, 'jenis' => 2]), 'skp'))
            ->filter(fn (array $r) => (int) ($r['periodeid'] ?? 0) === $periodeId && (int) ($r['periodepenilaianid'] ?? 0) < $quarter)
            ->sortByDesc(fn (array $r) => (int) $r['periodepenilaianid'])->first();

        return $previous === null ? [] : collect($this->rks((string) $previous['id']))->map(fn (array $r) => $this->norm($r['rencanakinerja'] ?? null))->all();
    }

    private function norm(?string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $text)));
    }

    /** @return list<array<string, mixed>> */
    public function rks(string $skpId): array
    {
        return $this->rows($this->get('v1/skp/rk', ['skpid' => $skpId]), 'skp/rk');
    }

    /** @return list<array<string, mixed>> */
    public function activities(string $skpId): array
    {
        return $this->rows($this->get('v1/kegiatan', ['skpid' => $skpId]), 'kegiatan');
    }

    /**
     * The member's presensi activities of one period, for the "List Aktivitas" picker. Each row is
     * {date: Y-m-d, description}. Rows without a date or a description are dropped. The field names
     * are read from kipApp's own code (id, fullDate, description); `tanggal` is the fallback seen in a probe.
     *
     * @return list<array{id: string, date: string, description: string}>
     */
    public function presensi(int $periodeId, int $periodePenilaianId, string $niplama): array
    {
        $rows = $this->rows($this->get('v1/kegiatan/presensi', ['periodeid' => $periodeId, 'periodepenilaianid' => $periodePenilaianId, 'niplama' => $niplama]), 'presensi');

        return collect($rows)
            ->map(function ($r) {
                $date = is_array($r) ? substr((string) ($r['fullDate'] ?? $r['tanggal'] ?? ''), 0, 10) : '';
                $text = is_array($r) ? trim((string) ($r['description'] ?? '')) : '';

                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $text !== '' ? ['id' => (string) ($r['id'] ?? $date.$text), 'date' => $date, 'description' => $text] : null;
            })
            ->filter()->sortBy('date')->values()->all();
    }

    /**
     * Create a kegiatan and return its kegiatanperhariid.
     *
     * @param  array{skpid: string, rkid: string, rencanakinerja: string, kegiatan: string, tanggal: string, tanggalselesai: ?string, progres?: int}  $data
     */
    public function createActivity(array $data): string
    {
        $before = collect($this->activities($data['skpid']))->pluck('kegiatanperhariid')->map(fn ($id) => (string) $id);

        $this->send('post', 'v1/kegiatan', $this->payload($data), 'create kegiatan');

        $new = collect($this->activities($data['skpid']))
            ->first(fn (array $r) => ! $before->contains((string) $r['kegiatanperhariid']) && ($r['kegiatan'] ?? null) === $data['kegiatan']);

        if ($new === null) {
            throw new KipApiException('kipApp menerima kegiatan, tetapi barisnya tidak ditemukan kembali.');
        }

        return (string) $new['kegiatanperhariid'];
    }

    /**
     * Update progres, capaian and evidence. kipApp wants the full row again.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateActivity(string $id, array $data): void
    {
        $this->send('put', 'v1/kegiatan', ['id' => $id, ...$this->payload($data)], 'update kegiatan');
    }

    public function deleteActivity(string $id): void
    {
        $this->send('delete', 'v1/kegiatan', ['id' => $id], 'delete kegiatan');
    }

    /**
     * "Kirim ke atasan": the kegiatan leaves "belum dikirim". Decoded from kipApp's
     * own code, not yet verified against the live API (see spec-planning.md section 5).
     *
     * @param  list<string>  $ids  kegiatanperhariid values
     */
    public function sendActivities(array $ids): void
    {
        $this->send('post', 'v1/kegiatan/kirim', ['id' => array_values($ids)], 'kirim kegiatan');
    }

    /**
     * "Batalkan kirim". Same status as sendActivities().
     *
     * @param  list<string>  $ids
     */
    public function cancelSend(array $ids): void
    {
        $this->send('post', 'v1/kegiatan/batal', ['id' => array_values($ids)], 'batal kirim kegiatan');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        // KIP_NULL_TIMES on: null stays null, like kipApp's own form. Off: today's defaults.
        $nullTimes = (bool) config('kinetik.kip.null_times');

        return [
            'skpid' => $data['skpid'], 'rkid' => $data['rkid'], 'rencanakinerja' => $data['rencanakinerja'],
            'kegiatan' => $data['kegiatan'], 'tanggal' => $data['tanggal'],
            'tanggalselesai' => $nullTimes ? ($data['tanggalselesai'] ?? null) : $data['tanggalselesai'],
            'progres' => $data['progres'] ?? 0, 'butirid' => null,
            'jammulai' => $data['jammulai'] ?? ($nullTimes ? null : '08:00'), 'jamselesai' => $data['jamselesai'] ?? ($nullTimes ? null : '16:00'),
            'capaian' => $data['capaian'] ?? '', 'datadukung' => $data['datadukung'] ?? '',
            'iscapaianskp' => (int) ($data['iscapaianskp'] ?? 1), 'parentid' => $data['parentid'] ?? null,
        ];
    }

    /** @param  array<string, mixed>  $query */
    private function get(string $path, array $query): Response
    {
        $response = $this->client()->get($path, $query);
        if (! $response->successful()) {
            throw KipApiException::fromResponse($response, $path);
        }

        return $response;
    }

    /** @param  array<string, mixed>  $body */
    private function send(string $method, string $path, array $body, string $context): void
    {
        $response = $this->client()->{$method}($path, $body);
        if (! $response->successful()) {
            throw KipApiException::fromResponse($response, $context);
        }
        if ($response->json('status') === false) {
            throw new KipApiException("kipApp menolak {$context}: ".($response->json('message') ?: 'tanpa pesan'), $response);
        }
    }

    /** @return list<array<string, mixed>> */
    private function rows(Response $response, string $context): array
    {
        $json = $response->json();
        $rows = is_array($json) && array_is_list($json) ? $json : ($json['data'] ?? []);
        if (! is_array($rows)) {
            throw new KipApiException("kipApp mengirim data {$context} yang tidak dikenal.", $response);
        }

        return array_values($rows);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('kinetik.kip.base_url'))
            ->timeout((int) config('kinetik.kip.timeout'))
            ->acceptJson()
            ->withHeaders(['x-auth' => 'Bearer '.$this->token]);
    }
}
