<?php

namespace App\Kinetik\Contracts;

use App\Models\User;

/**
 * The member's own SKP in kipApp: where it stands, how a quarter SKP is created and how a SKP is
 * sent to the atasan and taken back. The atasan's own steps (tetapkan, nilai) stay in kipApp.
 */
interface SkpFlow
{
    /**
     * @return array{
     *     year: int,
     *     yearly: array<string, mixed>|null,
     *     quarters: list<array<string, mixed>>,
     * }
     */
    public function overview(User $user): array;

    /** @param list<string> $rkIds yearly RK ids to put in the new quarter SKP */
    public function createQuarter(User $user, int $quarter, array $rkIds): void;

    /** @param list<string> $rkIds yearly RK ids to add to the existing quarter SKP */
    public function addRks(User $user, int $quarter, array $rkIds): void;

    /** @param 'yearly'|'quarter' $kind */
    public function submit(User $user, string $kind, ?int $quarter): void;

    /** Add an IKI to an RK of the member's SKP that is Sedang dibuat. @param 'yearly'|'quarter' $kind */
    public function addIki(User $user, string $kind, ?int $quarter, string $rkId, string $text): void;

    /** Remove an IKI the member added (not one copied from the atasan) from a SKP that is Sedang dibuat. @param 'yearly'|'quarter' $kind */
    public function deleteIki(User $user, string $kind, ?int $quarter, string $ikiId): void;

    /** @param 'yearly'|'quarter' $kind */
    public function cancel(User $user, string $kind, ?int $quarter): void;
}
