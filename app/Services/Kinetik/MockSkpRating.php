<?php

namespace App\Services\Kinetik;

use App\Kinetik\Contracts\SkpRating;
use App\Kinetik\RatingLevel;
use App\Models\PlanItem;
use App\Models\SkpStatus;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/** SkpRating for KIP_SOURCE=mock: scores live in the cache, the RK come from the member's own plans. */
class MockSkpRating implements SkpRating
{
    private const BEHAVIOURS = [1 => 'Berorientasi Pelayanan', 2 => 'Akuntabel', 3 => 'Kompeten', 4 => 'Harmonis', 5 => 'Loyal', 6 => 'Adaptif', 7 => 'Kolaboratif'];

    public function load(User $atasan, SkpStatus $skp): array
    {
        $saved = Cache::get($this->key($skp), ['rk' => [], 'perilaku' => []]);
        $names = PlanItem::where('employee_id', $skp->employee_id)->with('performancePlan:id,description')->get()
            ->pluck('performancePlan.description', 'performance_plan_id')->filter();

        return [
            'rks' => $names->map(fn ($name, $id) => ['id' => (string) $id, 'name' => $name, 'nilai' => null, 'level' => null, 'umpanbalik' => null, ...($saved['rk'][$id] ?? [])])->values()->all(),
            'perilaku' => collect(self::BEHAVIOURS)->map(fn ($name, $id) => ['id' => $id, 'name' => $name, 'nilai' => null, 'level' => null, 'umpanbalik' => null, ...($saved['perilaku'][$id] ?? [])])->values()->all(),
        ];
    }

    public function saveRk(User $atasan, SkpStatus $skp, string $rkId, int $nilai, ?string $umpanBalik): void
    {
        $this->store($skp, 'rk', $rkId, ['nilai' => $nilai, 'level' => RatingLevel::forRk($nilai), 'umpanbalik' => $umpanBalik]);
    }

    public function savePerilaku(User $atasan, SkpStatus $skp, int $jenisPerilakuId, int $nilai, ?string $umpanBalik): void
    {
        $this->store($skp, 'perilaku', $jenisPerilakuId, ['nilai' => $nilai, 'level' => RatingLevel::forPerilaku($nilai), 'umpanbalik' => $umpanBalik]);
    }

    public function finish(User $atasan, SkpStatus $skp): void
    {
        $all = $this->load($atasan, $skp);
        if (collect([...$all['rks'], ...$all['perilaku']])->contains(fn ($r) => $r['nilai'] === null) || $all['rks'] === []) {
            throw new RuntimeException('Beri nilai ke semua RK dan semua perilaku dulu sebelum menyelesaikan penilaian.');
        }
        $skp->update(['status_id' => 7, 'status_text' => 'Dinilai', 'status_changed_at' => now()]);
    }

    private function store(SkpStatus $skp, string $group, string|int $id, array $row): void
    {
        $saved = Cache::get($this->key($skp), ['rk' => [], 'perilaku' => []]);
        $saved[$group][$id] = $row;
        Cache::put($this->key($skp), $saved, now()->addDay());
    }

    private function key(SkpStatus $skp): string
    {
        return "mock-rating:{$skp->id}";
    }
}
