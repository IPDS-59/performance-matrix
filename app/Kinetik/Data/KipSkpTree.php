<?php

namespace App\Kinetik\Data;

use Illuminate\Support\Collection;

/**
 * One employee's yearly SKP: its RK (each with the RK above it in
 * rencanakinerjaatasan) and each RK's own IKI. The atasan is the next level
 * up (the Kepala for a ketua tim).
 */
readonly class KipSkpTree
{
    /**
     * @param  Collection<int, KipRkData>  $rks
     * @param  Collection<string, list<array<string, mixed>>>  $ikiByRk  rkid → IKI rows
     */
    public function __construct(
        public string $skpId,
        public ?string $atasanPegawaiId,
        public Collection $rks,
        public Collection $ikiByRk,
    ) {}

    /**
     * RK copied from the Perjanjian Kinerja (iscopypk=1): the Sasaran of a Kepala.
     *
     * @return Collection<int, KipRkData>
     */
    public function performanceAgreementRks(): Collection
    {
        return $this->rks->filter(fn (KipRkData $rk) => (int) ($rk->raw['iscopypk'] ?? 0) === 1)->values();
    }
}
