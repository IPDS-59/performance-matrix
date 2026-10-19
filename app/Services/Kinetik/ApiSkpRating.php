<?php

namespace App\Services\Kinetik;

use App\Kinetik\Contracts\SkpRating;
use App\Kinetik\RatingLevel;
use App\Models\SkpStatus;
use App\Models\User;
use RuntimeException;

/** SkpRating against the live kipApp. Read from kipApp's own pages and never run live: see config atasan_rating. */
class ApiSkpRating implements SkpRating
{
    private const FINISH_FLAG = 7;

    public function __construct(private readonly ApiSkpApproval $approval) {}

    public function load(User $atasan, SkpStatus $skp): array
    {
        $writer = $this->approval->checked($atasan, $this->quarter($skp));

        return [
            'rks' => array_map(fn (array $r) => [
                'id' => (string) $r['rkid'], 'name' => (string) ($r['rencanakinerja'] ?? ''),
                'nilai' => $this->int($r['nilaiumpanbalik'] ?? null), 'level' => $this->int($r['levelumpanbalik'] ?? null),
                'umpanbalik' => $r['umpanbalik'] ?? null,
            ], $writer->ratingRks($skp->kip_skp_id)),
            'perilaku' => array_map(fn (array $r) => [
                'id' => (int) $r['jenisperilakuid'], 'name' => (string) ($r['jenisperilaku'] ?? ''),
                'nilai' => $this->int($r['nilaiumpanbalik'] ?? null), 'level' => $this->int($r['levelumpanbalik'] ?? null),
                'umpanbalik' => $r['umpanbalik'] ?? null,
            ], $writer->perilaku($skp->kip_skp_id)),
        ];
    }

    public function saveRk(User $atasan, SkpStatus $skp, string $rkId, int $nilai, ?string $umpanBalik): void
    {
        $this->approval->checked($atasan, $this->quarter($skp))->rateRk($rkId, $nilai, RatingLevel::forRk($nilai), $umpanBalik);
    }

    public function savePerilaku(User $atasan, SkpStatus $skp, int $jenisPerilakuId, int $nilai, ?string $umpanBalik): void
    {
        $this->approval->checked($atasan, $this->quarter($skp))
            ->ratePerilaku($skp->kip_skp_id, $jenisPerilakuId, $nilai, RatingLevel::forPerilaku($nilai), $umpanBalik);
    }

    public function finish(User $atasan, SkpStatus $skp): void
    {
        $writer = $this->approval->checked($atasan, $this->quarter($skp));
        $rated = fn (array $rows) => $rows !== [] && collect($rows)->every(fn ($r) => $r['nilaiumpanbalik'] !== null && $r['nilaiumpanbalik'] !== '');
        if (! $rated($writer->ratingRks($skp->kip_skp_id)) || ! $rated($writer->perilaku($skp->kip_skp_id))) {
            throw new RuntimeException('Beri nilai ke semua RK dan semua perilaku dulu sebelum menyelesaikan penilaian.');
        }

        $writer->submitSkp($skp->kip_skp_id, self::FINISH_FLAG);
        $this->approval->refresh($writer, $skp);
    }

    private function quarter(SkpStatus $skp): SkpStatus
    {
        if ($skp->kind !== 'quarter') {
            throw new RuntimeException('Hanya SKP triwulan yang dinilai.');
        }

        return $skp;
    }

    private function int(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }
}
