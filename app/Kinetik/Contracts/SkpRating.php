<?php

namespace App\Kinetik\Contracts;

use App\Models\SkpStatus;
use App\Models\User;

/**
 * The atasan's rating of a quarter SKP, with the atasan's own kipApp token: a score and feedback per RK and per
 * behaviour (BerAKHLAK), then "selesai menilai". Behind `kinetik.kip.atasan_rating`.
 */
interface SkpRating
{
    /**
     * @return array{rks: list<array{id: string, name: string, nilai: int|null, level: int|null, umpanbalik: string|null}>, perilaku: list<array{id: int, name: string, nilai: int|null, level: int|null, umpanbalik: string|null}>}
     */
    public function load(User $atasan, SkpStatus $skp): array;

    public function saveRk(User $atasan, SkpStatus $skp, string $rkId, int $nilai, ?string $umpanBalik): void;

    public function savePerilaku(User $atasan, SkpStatus $skp, int $jenisPerilakuId, int $nilai, ?string $umpanBalik): void;

    /** Finish rating: every RK and behaviour needs a score first. The SKP becomes Dinilai. */
    public function finish(User $atasan, SkpStatus $skp): void;
}
