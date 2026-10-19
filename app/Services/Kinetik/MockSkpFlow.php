<?php

namespace App\Services\Kinetik;

use App\Kinetik\Contracts\SkpFlow;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/** SkpFlow for KIP_SOURCE=mock: demo data kept in the cache, so the page can be tried and photographed without kipApp. */
class MockSkpFlow implements SkpFlow
{
    private const RKS = [
        ['id' => '9001', 'rencanakinerja' => 'Terlaksananya pengolahan survei yang tepat waktu'],
        ['id' => '9002', 'rencanakinerja' => 'Tersedianya publikasi statistik daerah yang berkualitas'],
        ['id' => '9003', 'rencanakinerja' => 'Terlaksananya layanan data dan informasi sesuai SOP'],
        ['id' => '9004', 'rencanakinerja' => 'Terlaksananya pengelolaan website dan jaringan yang andal'],
    ];

    public function overview(User $user): array
    {
        $state = $this->state($user);
        $quarters = [];
        foreach ([1, 2, 3, 4] as $q) {
            $row = $state['quarters'][$q] ?? null;
            $draft = $row !== null && $row['status_id'] === 1;
            $inSkp = $row['rks'] ?? [];
            $quarters[] = [
                'quarter' => $q,
                'is_current' => $q === $state['current'],
                'exists' => $row !== null,
                'id' => $row ? "mock-q{$q}" : null,
                'status_id' => $row['status_id'] ?? null,
                'status' => $row['status'] ?? null,
                'jml_rk' => count($inSkp),
                'jml_kegiatan' => $row['kegiatan'] ?? 0,
                'create_blocker' => null,
                'can_create' => $row === null,
                'can_add_rk' => $draft,
                'blockers' => [],
                'can_submit' => $draft,
                'can_cancel' => $row !== null && $row['status_id'] === 5,
                'candidates' => $row === null || $draft
                    ? array_map(fn (array $r) => [...$r, 'in_skp' => in_array($r['id'], $inSkp, true), 'jmliki' => 1], self::RKS)
                    : [],
            ];
        }

        return [
            'year' => (int) config('kinetik.kip.tahun'),
            'yearly' => ['id' => 'mock-y', 'status_id' => 6, 'status' => 'Ditetapkan', 'jml_rk' => 4, 'atasan' => 'Atasan Contoh', 'blockers' => [], 'can_submit' => false, 'can_cancel' => false],
            'quarters' => $quarters,
        ];
    }

    public function createQuarter(User $user, int $quarter, array $rkIds): void
    {
        if ($rkIds === []) {
            throw new RuntimeException('Pilih minimal satu RK untuk SKP triwulan.');
        }
        $state = $this->state($user);
        $state['quarters'][$quarter] = ['status_id' => 1, 'status' => 'Sedang dibuat', 'rks' => array_values($rkIds), 'kegiatan' => 0];
        $this->save($user, $state);
    }

    public function addRks(User $user, int $quarter, array $rkIds): void
    {
        $state = $this->state($user);
        $state['quarters'][$quarter]['rks'] = array_values(array_unique([...$state['quarters'][$quarter]['rks'], ...$rkIds]));
        $this->save($user, $state);
    }

    public function submit(User $user, string $kind, ?int $quarter): void
    {
        $this->setStatus($user, (int) $quarter, 5, 'Sedang diperiksa');
    }

    public function cancel(User $user, string $kind, ?int $quarter): void
    {
        $this->setStatus($user, (int) $quarter, 1, 'Sedang dibuat');
    }

    private function setStatus(User $user, int $quarter, int $id, string $text): void
    {
        $state = $this->state($user);
        $state['quarters'][$quarter] = [...$state['quarters'][$quarter], 'status_id' => $id, 'status' => $text];
        $this->save($user, $state);
    }

    /** @return array<string, mixed> */
    private function state(User $user): array
    {
        return Cache::get("mock-skp:{$user->id}", [
            'current' => 4,
            'quarters' => [
                1 => ['status_id' => 7, 'status' => 'Dinilai', 'rks' => ['9001', '9002', '9003'], 'kegiatan' => 41],
                2 => ['status_id' => 7, 'status' => 'Dinilai', 'rks' => ['9001', '9002', '9003', '9004'], 'kegiatan' => 38],
                3 => ['status_id' => 7, 'status' => 'Dinilai', 'rks' => ['9001', '9002', '9003'], 'kegiatan' => 44],
            ],
        ]);
    }

    /** @param array<string, mixed> $state */
    private function save(User $user, array $state): void
    {
        Cache::forever("mock-skp:{$user->id}", $state);
    }
}
