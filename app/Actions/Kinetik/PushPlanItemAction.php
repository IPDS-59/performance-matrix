<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Exceptions\KipApiException;
use App\Kinetik\PlanStatus;
use App\Kinetik\Sources\ApiKipWriter;
use App\Models\MemberSkpStatus;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\User;
use App\Notifications\KinetikNotification;
use App\Services\Kinetik\KegiatanDuplicateFinder;
use App\Services\Kinetik\MemberKipToken;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Creates the kipApp kegiatan of a plan item (progres 0) with the member's own
 * token. A failure is stored on the plan, the member is told once, and the
 * scheduler tries again on its next run until the plan's end date (Q14).
 */
class PushPlanItemAction
{
    /** What push_error starts with when the kegiatan waits for a decision on a possible duplicate. */
    public const SIMILAR_PREFIX = 'Mirip dengan kegiatan yang sudah ada di kipApp';

    public function __construct(private readonly MemberKipToken $tokens) {}

    /** The cache lock a push of this plan holds, so a double click or the hourly run cannot make a second kegiatan in kipApp. */
    public static function lockKey(PlanItem $plan): string
    {
        return 'plan-push:'.$plan->id;
    }

    /**
     * @return bool true when the plan is now in kipApp. False when it failed (push_error says why) or when another push of
     *              the same plan is still running (push_error untouched).
     */
    public function execute(PlanItem $plan, bool $force = false): bool
    {
        if ($plan->kip_external_id !== null) {
            return true;
        }

        // Only a finished kegiatan goes to kipApp: progres 100, capaian, bukti dukung. The hourly run and the buttons all stop here.
        if ($plan->missingForKip() !== []) {
            return false;
        }

        $lock = Cache::lock(self::lockKey($plan), 120);
        if (! $lock->get()) {
            return false;
        }

        try {
            // Another request may have linked the kegiatan after this one loaded the plan.
            $plan->refresh();

            return $plan->kip_external_id !== null || $this->push($plan, $force);
        } finally {
            $lock->release();
        }
    }

    private function push(PlanItem $plan, bool $force): bool
    {
        $user = $plan->employee?->user;

        try {
            // A kegiatan can be the same work as one the member already filed in kipApp: imported from a spreadsheet, or typed
            // again in Kinetik. Sending it would make a second one there, so it waits until someone says it is different
            // ("Kirim tetap"). A spreadsheet row is judged on a loose match, a kegiatan typed here only on a near-identical one.
            // A copy made on purpose with "Salin kegiatan" (parent_id) is meant to repeat the text and is never held.
            if (! $force && $plan->parent_id === null) {
                $same = (new KegiatanDuplicateFinder)->find((int) $plan->employee_id, (string) $plan->description, Carbon::parse($plan->date_start)->toDateString(), Carbon::parse($plan->date_end)->toDateString(), $plan->id, onlyInKipapp: true, minScore: $plan->source === 'import' ? KegiatanDuplicateFinder::SIMILAR : KegiatanDuplicateFinder::SAME);
                if ($same !== null) {
                    $plan->update(['push_error' => self::SIMILAR_PREFIX.' ('.$same['date'].'): "'.mb_strimwidth($same['description'], 0, 80, '…').'". Hapus kegiatan ini bila sama, atau tekan "Kirim tetap" bila berbeda.']);

                    return false;
                }
            }

            if ($user === null || $plan->employee->kip_pegawai_id === null) {
                throw new RuntimeException('Pegawai belum punya akun atau ID kipApp.');
            }

            $this->requireProject($plan);

            // Repeated finished kegiatan of the month become one: the range grows and target and capaian add up. Nothing is saved
            // before kipApp accepted the kegiatan.
            $folded = config('kinetik.kip.merge_repeated') ? $this->foldRepeated($plan) : collect();

            $writer = new ApiKipWriter($this->tokens->for($user));
            [$skpId, $rk] = $this->skpAndRk($writer, $plan);

            $activityId = $writer->createActivity([
                'skpid' => $skpId,
                'rkid' => (string) $rk['rkid'],
                'rencanakinerja' => (string) $rk['rencanakinerja'],
                'kegiatan' => $plan->description,
                ...$plan->kipFields(),
                'parentid' => $plan->parent_id ? PlanItem::whereKey($plan->parent_id)->value('kip_external_id') : null,
            ]);
            $rkId = (string) $rk['rkid'];

            $plan->update([
                'kip_external_id' => $activityId,
                'kip_skp_id' => $skpId,
                'kip_rk_id' => $rkId,
                // A finished kegiatan arrives with progres 100, so it is done; kipApp's own list shows it that way.
                'status' => PlanStatus::fromKipProgress((int) $plan->progress)->value,
                'pushed_at' => now(),
                'push_error' => null,
                'push_attempts' => $plan->push_attempts + 1,
                ...($folded->isEmpty() ? [] : $plan->only(['date_start', 'date_end', 'target', 'capaian'])),
            ]);
            $folded->each(fn (PlanItem $other) => $other->update(['status' => 'cancelled', 'merged_into_id' => $plan->id]));

            return true;
        } catch (RuntimeException|KipApiException $e) {
            $this->fail($plan, $user, $e);

            return false;
        }
    }

    /**
     * The other finished, unsent kegiatan of the same person and RK with the same words in the same month. The plan takes their dates
     * (in memory only), and their target and capaian are added to it. Returns the kegiatan that fold into it.
     *
     * @return Collection<int, PlanItem>
     */
    private function foldRepeated(PlanItem $plan): Collection
    {
        $start = Carbon::parse($plan->date_start);
        $finder = new KegiatanDuplicateFinder;
        $same = PlanItem::where('employee_id', $plan->employee_id)->where('performance_plan_id', $plan->performance_plan_id)
            ->where('status', '!=', 'cancelled')->whereNull('kip_external_id')->where('id', '!=', $plan->id)
            ->whereDate('date_start', '>=', $start->copy()->startOfMonth()->toDateString())->whereDate('date_start', '<=', $start->copy()->endOfMonth()->toDateString())
            ->get()
            ->filter(fn (PlanItem $o) => $o->missingForKip() === [] && $finder->similarity((string) $o->description, (string) $plan->description) >= KegiatanDuplicateFinder::SAME)
            ->values();
        if ($same->isEmpty()) {
            return $same;
        }

        $all = $same->push($plan);
        $plan->date_start = $all->min(fn (PlanItem $o) => Carbon::parse($o->date_start)->toDateString());
        $plan->date_end = $all->max(fn (PlanItem $o) => Carbon::parse($o->date_end)->toDateString());
        if ($all->contains(fn (PlanItem $o) => $o->target !== null)) {
            $plan->target = (float) $all->sum(fn (PlanItem $o) => (float) $o->target);
        }
        $plan->capaian = (float) $all->sum(fn (PlanItem $o) => (float) $o->capaian);

        return $same->reject(fn (PlanItem $o) => $o->is($plan))->values();
    }

    /** Same rule as the entry form: an RK that belongs to a projek must not go to kipApp without one. */
    private function requireProject(PlanItem $plan): void
    {
        $rk = $plan->performancePlan;
        if ($rk !== null && $rk->project_id === null && $plan->project_id === null && ! (new LinkPlansToProjectsAction)->projectOptionalFor($rk, $plan->employee)) {
            throw new RuntimeException('RK ini belum punya projek. Pilih projek pada kegiatan ini, atau sinkronkan struktur supaya RK terhubung ke projeknya.');
        }
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function skpAndRk(ApiKipWriter $writer, PlanItem $plan): array
    {
        $date = Carbon::parse($plan->date_start);
        if ($date->year !== (int) config('kinetik.kip.tahun')) {
            throw new RuntimeException("Tahun {$date->year} belum didukung untuk pengiriman ke kipApp.");
        }

        $skp = $writer->quarterSkp((string) $plan->employee->kip_pegawai_id, (int) config('kinetik.kip.periode_id'), $date->quarter);
        // A new quarter has no SKP until someone creates it, so create it here, like kipApp's "Buat SKP Periodik".
        $skp ??= $writer->createQuarterSkp((string) $plan->employee->kip_pegawai_id, (int) config('kinetik.kip.periode_id'), $date->quarter);

        MemberSkpStatus::remember($plan->employee_id, $date->year, $date->quarter, $skp['statusskpid'] ?? null, $skp['statusskp'] ?? null);

        $rk = $this->matchRk($writer->rks((string) $skp['id']), $plan->performancePlan);
        if ($rk === null && $plan->performancePlan?->is_additional && $plan->performancePlan->kip_external_id === null) {
            // An RK tambahan made in Kinetik exists nowhere in kipApp yet. kipApp's own "Add Rencana Kinerja SKP" form creates it
            // with POST v1/skp/rk (jenis 2 = Tambahan); the same call is made here and the new row is read back for its rkid
            // (run live on 2026-11-08: create, IKI, kegiatan under it, delete).
            if (! config('kinetik.kip.rk_tambahan_write')) {
                throw new RuntimeException('RK tambahan ini belum ada di kipApp. Tambahkan RK-nya di kipApp (Tambah rencana kinerja baru), lalu sinkronkan.');
            }
            $writer->createAdditionalRk((string) $skp['id'], $plan->performancePlan->description);
            $rk = $this->matchRk($writer->rks((string) $skp['id']), $plan->performancePlan)
                ?? throw new RuntimeException('kipApp menerima RK tambahannya, tetapi RK itu tidak ditemukan kembali di SKP.');
            $plan->performancePlan->forceFill(['kip_external_id' => (string) $rk['rkid']])->save();
        }
        if ($rk === null && $plan->performancePlan !== null) {
            // The quarter SKP only has the RK its owner picked. Add this plan's RK from the yearly SKP, like kipApp's edit of a periodic SKP.
            $rk = $this->matchRk($writer->addRksToQuarterSkp((string) $plan->employee->kip_pegawai_id, (int) config('kinetik.kip.periode_id'), $skp, [$plan->performancePlan->description]), $plan->performancePlan);
        }
        if ($rk === null) {
            throw new RuntimeException('RK rencana ini tidak ditemukan di SKP triwulan kipApp.');
        }

        return [(string) $skp['id'], $rk];
    }

    /**
     * @param  list<array<string, mixed>>  $rks
     * @return array<string, mixed>|null
     */
    private function matchRk(array $rks, ?PerformancePlan $plan): ?array
    {
        if ($plan === null) {
            return null;
        }
        $norm = fn (?string $t) => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $t)));

        return collect($rks)->first(fn (array $r) => $plan->kip_external_id !== null && (string) $r['rkid'] === (string) $plan->kip_external_id)
            ?? collect($rks)->first(fn (array $r) => $norm($r['rencanakinerja'] ?? null) === $norm($plan->description));
    }

    private function fail(PlanItem $plan, ?User $user, \Throwable $e): void
    {
        $attempts = $plan->push_attempts + 1;
        $plan->update(['push_attempts' => $attempts, 'push_error' => mb_substr($e->getMessage(), 0, 500)]);

        $url = route('weekly.index', ['week' => Carbon::parse($plan->date_start)->toDateString()]);

        // One notice for the first failure only.
        if ($attempts === 1) {
            $user?->notify(new KinetikNotification('plan_push_failed', 'Rencana "'.mb_substr($plan->description, 0, 80).'" belum terkirim ke kipApp: '.$e->getMessage(), $url));
        }

        // A token problem also goes to the admins, once a day per member.
        if ($e instanceof RuntimeException && $user !== null && Cache::add("plan-push-token:{$user->id}:".now()->toDateString(), true, now()->addDay())) {
            User::permission('manage-kip-integration')->get()->each(fn (User $admin) => $admin->notify(
                new KinetikNotification('plan_push_failed_admin', "{$user->name}: rencana belum terkirim ke kipApp. {$e->getMessage()}", $url),
            ));
        }
    }
}
