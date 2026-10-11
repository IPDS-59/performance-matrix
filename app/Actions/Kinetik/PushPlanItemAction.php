<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Exceptions\KipApiException;
use App\Kinetik\Sources\ApiKipWriter;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\User;
use App\Notifications\KinetikNotification;
use App\Services\Kinetik\MemberKipToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Creates the kipApp kegiatan of a plan item (progres 0) with the member's own
 * token. A failure is stored on the plan, the member is told once, and the
 * scheduler tries again on its next run until the plan's end date (Q14).
 */
class PushPlanItemAction
{
    public function __construct(private readonly MemberKipToken $tokens) {}

    /** @return bool true when the plan is now in kipApp */
    public function execute(PlanItem $plan): bool
    {
        if ($plan->kip_external_id !== null) {
            return true;
        }

        $user = $plan->employee?->user;

        try {
            if ($user === null || $plan->employee->kip_pegawai_id === null) {
                throw new RuntimeException('Pegawai belum punya akun atau ID kipApp.');
            }

            $writer = new ApiKipWriter($this->tokens->for($user));
            [$skpId, $rk] = $this->skpAndRk($writer, $plan);

            $plan->update([
                'kip_external_id' => $writer->createActivity([
                    'skpid' => $skpId,
                    'rkid' => (string) $rk['rkid'],
                    'rencanakinerja' => (string) $rk['rencanakinerja'],
                    'kegiatan' => $plan->description,
                    'tanggal' => Carbon::parse($plan->date_start)->toDateString(),
                    'tanggalselesai' => Carbon::parse($plan->date_end)->toDateString(),
                ]),
                'kip_skp_id' => $skpId,
                'kip_rk_id' => (string) $rk['rkid'],
                'status' => 'pushed',
                'pushed_at' => now(),
                'push_error' => null,
                'push_attempts' => $plan->push_attempts + 1,
            ]);

            return true;
        } catch (RuntimeException|KipApiException $e) {
            $this->fail($plan, $user, $e);

            return false;
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
        if ($skp === null) {
            throw new RuntimeException("SKP triwulan {$date->quarter} belum dibuat di kipApp. Buat SKP triwulan dulu.");
        }

        $rk = $this->matchRk($writer->rks((string) $skp['id']), $plan->performancePlan);
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

        $url = route('weekly-plan.index', ['team' => $plan->team_id, 'week' => Carbon::parse($plan->date_start)->toDateString()]);

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
