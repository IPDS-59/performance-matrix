<?php

namespace App\Http\Controllers;

use App\Kinetik\Contracts\StructureFlow;
use App\Kinetik\Exceptions\KipApiException;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** RK Ketua, proyek and RK Anggota made in Kinetik, created in kipApp too. Off unless `kinetik.kip.structure_write`. */
class KipStructureController extends Controller
{
    public function __construct(private readonly StructureFlow $flow) {}

    public static function enabled(): bool
    {
        return (bool) config('kinetik.kip.structure_write');
    }

    /** The error to show when a push to kipApp fails, or null when it worked. The local row is kept either way. */
    public static function attempt(callable $do): ?string
    {
        try {
            $do();
        } catch (RuntimeException|KipApiException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /** What the RK Ketua form needs for one tim: the RK Ketua that exist and the IKI JPT to hang a new one on. */
    public function options(Request $request): JsonResponse
    {
        $team = $this->ledTeam($request, $request->integer('team_id'));

        $error = self::attempt(function () use ($request, $team, &$data) {
            $data = ['rkKetua' => $this->flow->rkKetua($request->user(), $team), 'ikiJpt' => $this->flow->ikiJpt($request->user())];
        });

        return response()->json($error === null ? $data : ['rkKetua' => [], 'ikiJpt' => [], 'error' => $error]);
    }

    public function storeRkKetua(Request $request): RedirectResponse
    {
        $v = $request->validate(['team_id' => 'required|exists:teams,id', 'text' => 'required|string|max:500', 'iki_jpt_id' => 'required|string']);
        $team = $this->ledTeam($request, $v['team_id']);

        $error = self::attempt(fn () => $this->flow->createRkKetua($request->user(), $team, $v['text'], $v['iki_jpt_id']));

        return $error ? back()->with('error', $error) : back()->with('success', 'RK Ketua dibuat di kipApp.');
    }

    public function pushProject(Request $request, Project $project): RedirectResponse
    {
        abort_unless(self::enabled(), 404);
        $this->authorize('update', $project);
        $v = $request->validate(['rk_ketua_id' => 'required|string']);

        $error = self::attempt(fn () => $this->flow->pushProject($request->user(), $project, $v['rk_ketua_id']));

        return $error ? back()->with('error', $error) : back()->with('success', 'Proyek dibuat di kipApp.');
    }

    public function pushPlan(Request $request, PerformancePlan $performancePlan): RedirectResponse
    {
        abort_unless(self::enabled(), 404);
        $this->authorize('update', $performancePlan);

        $error = self::attempt(fn () => $this->flow->pushPlan($request->user(), $performancePlan));

        return $error ? back()->with('error', $error) : back()->with('success', 'RK Anggota dibuat di kipApp.');
    }

    /** The tim must be led by the signed-in person, or the person manages projects. */
    private function ledTeam(Request $request, int $teamId): Team
    {
        abort_unless(self::enabled(), 404);
        /** @var User $user */
        $user = $request->user();
        $team = Team::findOrFail($teamId);
        abort_unless($user->hasPermissionTo('manage-projects') || ($user->employee && $team->leader_id === $user->employee->id), 403);

        return $team;
    }
}
