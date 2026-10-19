<?php

namespace App\Services\Kinetik;

use App\Kinetik\Contracts\SkpFlow;
use App\Kinetik\Sources\ApiKipWriter;
use App\Models\Employee;
use App\Models\MemberSkpStatus;
use App\Models\SkpStatus;
use App\Models\User;
use App\Notifications\KinetikNotification;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/** SkpFlow against the live kipApp, with the member's own token. */
class ApiSkpFlow implements SkpFlow
{
    /** kipApp statusskpid: 1 Sedang dibuat. Yearly 2 waits for the atasan, 6 Ditetapkan. Quarter 5 is with the atasan, 7 Dinilai. */
    private const DRAFT = 1;

    private const YEARLY_WAITING = 2;

    private const YEARLY_SET = 6;

    private const QUARTER_WAITING = 5;

    public function __construct(private readonly MemberKipToken $tokens) {}

    public function overview(User $user): array
    {
        [$writer, $pegawaiId] = $this->writer($user);
        $periodeId = (int) config('kinetik.kip.periode_id');
        $yearly = $writer->yearlySkp($pegawaiId, $periodeId);
        $quarterSkps = $writer->quarterSkps($pegawaiId, $periodeId);
        $currentQuarter = now(config('app.schedule_timezone', 'Asia/Makassar'))->quarter;
        $yearlySet = $yearly !== null && (int) ($yearly['statusskpid'] ?? 0) === self::YEARLY_SET;

        $quarters = [];
        foreach ([1, 2, 3, 4] as $q) {
            $skp = $quarterSkps[$q] ?? null;
            $statusId = $skp ? (int) ($skp['statusskpid'] ?? 0) : null;
            $rks = $skp ? $writer->rks((string) $skp['id']) : [];
            $draft = $statusId === self::DRAFT;

            $quarters[] = [
                'quarter' => $q,
                'is_current' => $q === $currentQuarter,
                'exists' => $skp !== null,
                'id' => $skp ? (string) $skp['id'] : null,
                'status_id' => $statusId,
                'status' => $skp['statusskp'] ?? null,
                'jml_rk' => count($rks),
                'jml_kegiatan' => (int) collect($rks)->sum(fn ($r) => (int) ($r['jmlkegiatan'] ?? 0)),
                'create_blocker' => $skp === null && ! $yearlySet ? 'SKP Tahunan harus Ditetapkan atasan dulu.' : null,
                'can_create' => $skp === null && $yearlySet,
                'can_add_rk' => $draft,
                'blockers' => $draft ? $this->blockers($rks) : [],
                'can_submit' => $draft && $this->blockers($rks) === [],
                'can_cancel' => $statusId === self::QUARTER_WAITING,
                'candidates' => ($skp === null && $yearlySet) || $draft
                    ? $this->candidates($writer, $pegawaiId, $periodeId, $yearly, $q, $skp)
                    : [],
            ];
        }

        $yearlyRks = $yearly && (int) ($yearly['statusskpid'] ?? 0) === self::DRAFT ? $writer->rks((string) $yearly['id']) : [];
        $yearlyStatus = $yearly ? (int) ($yearly['statusskpid'] ?? 0) : null;

        return [
            'year' => (int) config('kinetik.kip.tahun'),
            'yearly' => $yearly === null ? null : [
                'id' => (string) $yearly['id'],
                'status_id' => $yearlyStatus,
                'status' => $yearly['statusskp'] ?? null,
                'jml_rk' => (int) ($yearly['jmlrk'] ?? 0),
                'atasan' => $yearly['namaatasan'] ?? null,
                'blockers' => $yearlyStatus === self::DRAFT ? $this->blockers($yearlyRks) : [],
                'can_submit' => $yearlyStatus === self::DRAFT && $this->blockers($yearlyRks) === [],
                'can_cancel' => $yearlyStatus === self::YEARLY_WAITING,
            ],
            'quarters' => $quarters,
        ];
    }

    public function createQuarter(User $user, int $quarter, array $rkIds): void
    {
        [$writer, $pegawaiId] = $this->writer($user);
        $skp = $writer->createQuarterSkp($pegawaiId, (int) config('kinetik.kip.periode_id'), $quarter, $rkIds);
        MemberSkpStatus::remember($user->employee->id, (int) config('kinetik.kip.tahun'), $quarter, $skp['statusskpid'] ?? null, $skp['statusskp'] ?? null);
    }

    public function addRks(User $user, int $quarter, array $rkIds): void
    {
        [$writer, $pegawaiId] = $this->writer($user);
        $periodeId = (int) config('kinetik.kip.periode_id');
        $skp = $writer->quarterSkp($pegawaiId, $periodeId, $quarter) ?? throw new RuntimeException("SKP triwulan {$quarter} belum ada.");
        if ((int) ($skp['statusskpid'] ?? 0) !== self::DRAFT) {
            throw new RuntimeException('RK hanya bisa ditambah selama SKP triwulan berstatus Sedang dibuat.');
        }
        $writer->addRksToQuarterSkp($pegawaiId, $periodeId, $skp, [], $rkIds);
    }

    public function submit(User $user, string $kind, ?int $quarter): void
    {
        [$writer, $pegawaiId] = $this->writer($user);
        [$skp, $flag] = $this->target($writer, $pegawaiId, $kind, $quarter, true);
        $blockers = $this->blockers($writer->rks((string) $skp['id']));
        if ($blockers !== []) {
            throw new RuntimeException('SKP belum bisa dikirim: '.implode('; ', $blockers));
        }
        $writer->submitSkp((string) $skp['id'], $flag);
        $this->remember($user, $kind, $quarter, $writer, $pegawaiId, sent: true);
    }

    public function cancel(User $user, string $kind, ?int $quarter): void
    {
        [$writer, $pegawaiId] = $this->writer($user);
        [$skp] = $this->target($writer, $pegawaiId, $kind, $quarter, false);
        $writer->returnSkp((string) $skp['id']);
        $this->remember($user, $kind, $quarter, $writer, $pegawaiId);
    }

    /** @return array{0: ApiKipWriter, 1: string} */
    private function writer(User $user): array
    {
        $employee = $user->employee;
        if ($employee === null || $employee->kip_pegawai_id === null) {
            throw new RuntimeException('Akun ini belum terhubung ke data pegawai kipApp.');
        }

        return [new ApiKipWriter($this->tokens->for($user)), (string) $employee->kip_pegawai_id];
    }

    /**
     * The SKP an action is about, checked against its status, and the flag to send.
     *
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function target(ApiKipWriter $writer, string $pegawaiId, string $kind, ?int $quarter, bool $submitting): array
    {
        $periodeId = (int) config('kinetik.kip.periode_id');
        if ($kind === 'yearly') {
            $skp = $writer->yearlySkp($pegawaiId, $periodeId) ?? throw new RuntimeException('SKP Tahunan belum ada di kipApp.');
            $needs = $submitting ? self::DRAFT : self::YEARLY_WAITING;
            $flag = 2;
        } else {
            $skp = $writer->quarterSkps($pegawaiId, $periodeId)[(int) $quarter] ?? throw new RuntimeException("SKP triwulan {$quarter} belum ada di kipApp.");
            $needs = $submitting ? self::DRAFT : self::QUARTER_WAITING;
            $flag = 5;
        }
        if ((int) ($skp['statusskpid'] ?? 0) !== $needs) {
            throw new RuntimeException('Status SKP sekarang "'.($skp['statusskp'] ?? '?').'", jadi langkah ini tidak bisa dilakukan.');
        }

        return [$skp, $flag];
    }

    /** Keep the local rows in step with kipApp after a send or a take back, and tell the atasan about a send. */
    private function remember(User $user, string $kind, ?int $quarter, ApiKipWriter $writer, string $pegawaiId, bool $sent = false): void
    {
        $periodeId = (int) config('kinetik.kip.periode_id');
        $tahun = (int) config('kinetik.kip.tahun');
        $skp = $kind === 'yearly' ? $writer->yearlySkp($pegawaiId, $periodeId) : ($writer->quarterSkps($pegawaiId, $periodeId)[(int) $quarter] ?? null);
        if ($skp === null) {
            return;
        }

        if ($kind === 'quarter') {
            MemberSkpStatus::remember($user->employee->id, $tahun, (int) $quarter, $skp['statusskpid'] ?? null, $skp['statusskp'] ?? null);
        }
        $row = SkpStatus::remember($user->employee->id, $tahun, $kind, $kind === 'yearly' ? 0 : (int) $quarter, $skp);

        if ($sent) {
            $this->tellAtasan($user, $row);
        }
    }

    private function tellAtasan(User $member, SkpStatus $row): void
    {
        $atasan = $row->atasan_pegawai_id !== null
            ? Employee::where('kip_pegawai_id', $row->atasan_pegawai_id)->first()?->user
            : null;
        $recipients = $atasan !== null ? collect([$atasan]) : User::role('head')->get();
        $name = $member->employee->display_name ?? $member->employee->name;
        $what = $row->kind === 'yearly' ? 'untuk ditetapkan' : 'untuk dinilai';

        Notification::send($recipients, new KinetikNotification('skp_sent', "{$name} mengirim {$row->label()} {$what}.", route('skp.approvals')));
    }

    /**
     * What kipApp's own "send" checks: at least one RK, and every RK has an IKI.
     *
     * @param  list<array<string, mixed>>  $rks
     * @return list<string>
     */
    private function blockers(array $rks): array
    {
        if ($rks === []) {
            return ['belum ada RK'];
        }

        return collect($rks)->filter(fn (array $r) => (int) ($r['jmliki'] ?? 0) === 0)
            ->map(fn (array $r) => 'RK "'.mb_substr((string) ($r['rencanakinerja'] ?? ''), 0, 60).'" belum punya IKI')->values()->all();
    }

    /**
     * @param  array<string, mixed>|null  $yearly
     * @param  array<string, mixed>|null  $skp
     * @return list<array{id: string, rencanakinerja: string, in_skp: bool, jmliki: int}>
     */
    private function candidates(ApiKipWriter $writer, string $pegawaiId, int $periodeId, ?array $yearly, int $quarter, ?array $skp): array
    {
        if ($yearly === null) {
            return [];
        }

        return collect($writer->rkCandidates($pegawaiId, $periodeId, $yearly, $quarter, $skp ? (string) $skp['id'] : null))
            ->map(fn (array $r) => ['id' => $r['id'], 'rencanakinerja' => $r['rencanakinerja'], 'in_skp' => $r['iscopied'], 'jmliki' => $r['jmliki']])
            ->all();
    }
}
