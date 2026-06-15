<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\SyncKipActivitiesAction;
use App\Actions\Kinetik\SyncKipStructureAction;
use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Contracts\KipStructureSource;
use App\Kinetik\KipTokenInfo;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\KipCredential;
use App\Models\KipSyncRun;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class KipIntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $credential = KipCredential::current();

        return Inertia::render('Kinetik/KipIntegration', [
            'credential' => $credential ? [
                'account_nip' => $credential->account_nip,
                'account_name' => $credential->account_name,
                'expires_at' => $credential->expires_at?->toIso8601String(),
                'is_expired' => $credential->isExpired(),
                'is_expiring_soon' => $credential->isExpiringSoon(),
                'updated_at' => $credential->updated_at?->toIso8601String(),
                'updated_by' => $credential->updatedBy?->name,
            ] : null,
            'stats' => [
                'employees_with_nip' => Employee::where('is_active', true)->whereNotNull('nip_lama')->count(),
                'employees_total' => Employee::where('is_active', true)->count(),
                'activities_synced' => KipActivity::count(),
                'last_fetched_at' => ($at = KipActivity::max('fetched_at')) ? \Carbon\Carbon::parse($at)->toIso8601String() : null,
                'teams_synced' => Team::whereNotNull('kip_external_id')->count(),
                'projects_synced' => Project::whereNotNull('kip_external_id')->count(),
            ],
            'structureRun' => $this->runPayload(
                KipSyncRun::active('structure') ?? KipSyncRun::where('type', 'structure')->latest('id')->first()
            ),
            'activityRun' => $this->runPayload(
                KipSyncRun::active('activities') ?? KipSyncRun::where('type', 'activities')->latest('id')->first()
            ),
        ]);
    }

    public function storeToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:8192'],
        ]);

        $token = KipTokenInfo::normalize($validated['token']);
        $info = KipTokenInfo::fromToken($token);

        KipCredential::create([
            'token' => $token,
            'account_nip' => $info->nip,
            'account_name' => $info->email,
            'expires_at' => $info->expiresAt,
            'updated_by' => $request->user()->id,
        ]);

        $when = $info->expiresAt ? ' Berlaku hingga '.$info->expiresAt->translatedFormat('d M Y H:i').'.' : '';

        return back()->with('success', 'Token kipApp berhasil disimpan.'.$when);
    }

    /**
     * One chunk of the activity sync: processes a small batch of employees per
     * request so each stays under the 30s limit (no queue worker). The browser
     * calls this repeatedly until the run completes.
     */
    public function syncAll(
        Request $request,
        KipActivitySource $source,
        SyncKipActivitiesAction $action,
    ): RedirectResponse {
        if (KipCredential::current() === null && empty(config('kinetik.kip.token'))) {
            return back()->with('error', 'Belum ada token kipApp. Simpan token terlebih dahulu.');
        }

        $run = KipSyncRun::active('activities');

        try {
            if ($run === null) {
                $employeeIds = Employee::where('is_active', true)
                    ->whereNotNull('nip_lama')
                    ->pluck('id')->all();

                $run = KipSyncRun::create([
                    'type' => 'activities',
                    'status' => empty($employeeIds) ? 'completed' : 'running',
                    'total' => count($employeeIds),
                    'processed' => 0,
                    'pending' => $employeeIds,
                    'summary' => ['activities' => 0],
                    'user_id' => $request->user()->id,
                    'finished_at' => empty($employeeIds) ? now() : null,
                ]);
            }

            $pending = $run->pending ?? [];

            if ($run->status === 'running' && ! empty($pending)) {
                $chunk = max(1, (int) config('kinetik.kip.activity_chunk', 5));
                $batch = array_splice($pending, 0, $chunk);

                $employees = Employee::whereIn('id', $batch)->get();
                $upserted = $action->execute($source, $employees);

                $summary = $run->summary ?? [];
                $summary['activities'] = ($summary['activities'] ?? 0) + $upserted;

                $run->pending = $pending;
                $run->processed = $run->processed + count($batch);
                $run->summary = $summary;

                if (empty($pending)) {
                    $run->status = 'completed';
                    $run->finished_at = now();
                }

                $run->save();
            }
        } catch (Throwable $e) {
            $run?->update(['status' => 'failed', 'message' => $e->getMessage(), 'finished_at' => now()]);

            return back()->with('error', 'Sinkronisasi gagal: '.$e->getMessage());
        }

        return back();
    }

    /**
     * One chunk of the structure sync: syncs a single team per request so each
     * stays well under the 30s execution limit (no queue worker required). The
     * browser calls this repeatedly until the run completes.
     */
    public function syncStructure(
        Request $request,
        KipStructureSource $source,
        SyncKipStructureAction $action,
    ): RedirectResponse {
        if (KipCredential::current() === null && empty(config('kinetik.kip.token'))) {
            return back()->with('error', 'Belum ada token kipApp. Simpan token terlebih dahulu.');
        }

        $run = KipSyncRun::active('structure');

        try {
            if ($run === null) {
                $teamIds = $source->fetchTeams()
                    ->map(fn ($t) => $t->externalId)->filter()->unique()->values()->all();

                $run = KipSyncRun::create([
                    'type' => 'structure',
                    'status' => empty($teamIds) ? 'completed' : 'running',
                    'total' => count($teamIds),
                    'processed' => 0,
                    'pending' => $teamIds,
                    'summary' => [],
                    'user_id' => $request->user()->id,
                    'finished_at' => empty($teamIds) ? now() : null,
                ]);
            }

            $pending = $run->pending ?? [];

            if ($run->status === 'running' && ! empty($pending)) {
                $teamId = (string) array_shift($pending);
                $counts = $action->syncTeam($source, $teamId);

                $summary = $run->summary ?? [];
                foreach ($counts as $key => $value) {
                    $summary[$key] = ($summary[$key] ?? 0) + $value;
                }

                $run->pending = $pending;
                $run->processed = $run->processed + 1;
                $run->summary = $summary;

                if (empty($pending)) {
                    $run->status = 'completed';
                    $run->finished_at = now();
                }

                $run->save();
            }
        } catch (Throwable $e) {
            $run?->update(['status' => 'failed', 'message' => $e->getMessage(), 'finished_at' => now()]);

            return back()->with('error', 'Sinkronisasi struktur gagal: '.$e->getMessage());
        }

        return back();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runPayload(?KipSyncRun $run): ?array
    {
        if ($run === null) {
            return null;
        }

        return [
            'id' => $run->id,
            'status' => $run->status,
            'total' => $run->total,
            'processed' => $run->processed,
            'summary' => $run->summary ?? [],
            'message' => $run->message,
        ];
    }
}
