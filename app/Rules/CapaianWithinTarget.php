<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Capaian is what was done against the target, so it cannot be more than the target. More than 100% (a team showed 102.5%) comes
 * from a target set too low: the person raises the target first. The target is the one sent next to the capaian, else the stored one.
 */
class CapaianWithinTarget implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly ?float $storedTarget = null) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }
        $sent = data_get($this->data, preg_replace('/capaian$/', 'target', $attribute));
        $target = is_numeric($sent) ? (float) $sent : $this->storedTarget;

        if ($target !== null && $target > 0 && (float) $value > $target + 1e-9) {
            $fail('Capaian ('.rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').') melebihi target ('.rtrim(rtrim(number_format($target, 2, ',', '.'), '0'), ',').'). Naikkan targetnya dulu bila pekerjaannya memang lebih banyak.');
        }
    }
}
