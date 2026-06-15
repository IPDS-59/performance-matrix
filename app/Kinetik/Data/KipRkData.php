<?php

namespace App\Kinetik\Data;

readonly class KipRkData
{
    public function __construct(
        public string $externalId,
        public string $name,
        public ?string $teamExternalId,
        public ?float $target,
        public ?string $targetUnit,
        public ?string $skpStatus,
        public array $raw,
    ) {}

    /**
     * Build from a `/v1/skp/rk` row plus the parsed target from its IKI text.
     *
     * @param  ?string  $skpStatus  Status label from the parent SKP (e.g. "Ditetapkan")
     */
    public static function fromApiRow(array $row, ?string $ikiText = null, ?string $skpStatus = null): self
    {
        [$target, $unit] = self::parseTarget($ikiText);

        return new self(
            externalId: (string) ($row['rkid'] ?? ''),
            name: (string) ($row['rencanakinerja'] ?? ''),
            teamExternalId: isset($row['timkerjaid']) ? (string) $row['timkerjaid'] : null,
            target: $target,
            targetUnit: $unit,
            skpStatus: $skpStatus,
            raw: $row,
        );
    }

    /**
     * Parse the inline target from an IKI text, e.g.
     *   "... : 100%"            -> [100.0, "%"]
     *   "... Sebanyak 4 dokumen" -> [4.0, "dokumen"]
     *   "... sebesar 100 persen" -> [100.0, "persen"]
     *
     * Takes the last number-with-unit in the string (the target trails the
     * indicator name); returns [null, null] when no numeric target is present.
     *
     * @return array{0: ?float, 1: ?string}
     */
    public static function parseTarget(?string $iki): array
    {
        if ($iki === null || trim($iki) === '') {
            return [null, null];
        }

        if (! preg_match_all('/(\d+(?:[.,]\d+)?)\s*(%|[A-Za-z]+)?/u', $iki, $matches, PREG_SET_ORDER)) {
            return [null, null];
        }

        // Prefer the last number that has a unit; otherwise the last number.
        $withUnit = null;
        $any = null;
        foreach ($matches as $m) {
            if (($m[1] ?? '') === '') {
                continue;
            }
            $any = $m;
            if (! empty($m[2])) {
                $withUnit = $m;
            }
        }

        $chosen = $withUnit ?? $any;
        if ($chosen === null) {
            return [null, null];
        }

        $number = (float) str_replace(',', '.', $chosen[1]);
        $unit = ($chosen[2] ?? '') !== '' ? $chosen[2] : null;

        return [$number, $unit];
    }
}
