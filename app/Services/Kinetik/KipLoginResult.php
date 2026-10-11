<?php

namespace App\Services\Kinetik;

use Carbon\CarbonImmutable;

final class KipLoginResult
{
    public function __construct(
        #[\SensitiveParameter] public readonly string $token,
        public readonly CarbonImmutable $expiresAt,
    ) {}
}
