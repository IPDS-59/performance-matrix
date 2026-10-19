<?php

namespace App\Kinetik\Contracts;

use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

/**
 * Creating the planning structure in kipApp from Kinetik, with the signed-in tim leader's own token: an RK Ketua
 * of a tim, a proyek under it, and the RK Anggota of a member in that proyek. Behind `kinetik.kip.structure_write`.
 */
interface StructureFlow
{
    /** @return list<array{id: string, text: string}> the RK Ketua already in kipApp for this tim */
    public function rkKetua(User $user, Team $team): array;

    /** @return list<array{id: string, text: string}> the "IKI JPT" proyek an RK Ketua can hang on */
    public function ikiJpt(User $user): array;

    /** Create an RK Ketua for the tim, led by the tim leader. Returns its kipApp id. */
    public function createRkKetua(User $user, Team $team, string $text, string $ikiJptId): string;

    /** Create the project in kipApp under an RK Ketua and store its proyekid on the project. */
    public function pushProject(User $user, Project $project, string $rkKetuaId): void;

    /** Create the RK Anggota (the plan's PIC in the plan's project) and store its rkproyekid. */
    public function pushPlan(User $user, PerformancePlan $plan): void;
}
