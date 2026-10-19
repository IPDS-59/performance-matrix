<?php

namespace App\Services\Kinetik;

use App\Kinetik\Contracts\StructureFlow;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;

/** StructureFlow for KIP_SOURCE=mock: nothing leaves the app, ids are made up so the screens can be tried. */
class MockStructureFlow implements StructureFlow
{
    public function rkKetua(User $user, Team $team): array
    {
        return [['id' => 'mock-rk-1', 'text' => 'Terlaksananya statistik sosial yang berkualitas'], ['id' => 'mock-rk-2', 'text' => 'Terselenggaranya layanan data']];
    }

    public function ikiJpt(User $user): array
    {
        return [['id' => 'mock-iki-1', 'text' => 'Persentase data berkualitas'], ['id' => 'mock-iki-2', 'text' => 'Indeks kepuasan pengguna data']];
    }

    public function createRkKetua(User $user, Team $team, string $text, string $ikiJptId): string
    {
        return 'mock-'.Str::random(6);
    }

    public function pushProject(User $user, Project $project, string $rkKetuaId): void
    {
        $project->forceFill(['kip_external_id' => 'mock-proyek-'.$project->id])->save();
    }

    public function pushPlan(User $user, PerformancePlan $plan): void
    {
        $plan->forceFill(['kip_rkproyek_id' => 'mock-rkp-'.$plan->id])->save();
    }
}
