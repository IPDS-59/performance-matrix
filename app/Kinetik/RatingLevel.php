<?php

namespace App\Kinetik;

/** The level kipApp derives from a score (GET /umpanbalik/nilai): RK 99+ and 80+, behaviour 91+ and 80+. */
final class RatingLevel
{
    public static function forRk(int $nilai): int
    {
        return $nilai >= 99 ? 3 : ($nilai >= 80 ? 2 : 1);
    }

    public static function forPerilaku(int $nilai): int
    {
        return $nilai >= 91 ? 3 : ($nilai >= 80 ? 2 : 1);
    }
}
