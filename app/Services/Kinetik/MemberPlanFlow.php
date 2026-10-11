<?php

namespace App\Services\Kinetik;

/**
 * Where a member stands in the kipApp planning chain, from data Kinetik already
 * holds (synced RK and kegiatan, plan items). It never guesses what it cannot
 * see: the SKP Tahunan and its approval are not read, so they are not a step.
 *
 * Order in kipApp: RK Anggota, SKP Tahunan approved, SKP Periodik per quarter,
 * kegiatan, send to the atasan.
 */
class MemberPlanFlow
{
    public const DONE = 'done';

    public const TODO = 'todo';

    public const BLOCKED = 'blocked';

    public const UNKNOWN = 'unknown';

    /**
     * @param  list<array{in_kipapp: bool, push_error: ?string, status: string}>  $plans  this week, cancelled left out
     * @return list<array{key: string, label: string, state: string, hint: string}>
     */
    public function steps(int $rkCount, int $activityCount, int $unsentCount, array $plans): array
    {
        $inKipapp = count(array_filter($plans, fn ($p) => $p['in_kipapp']));
        $skpMissing = (bool) array_filter($plans, fn ($p) => str_contains((string) $p['push_error'], 'SKP triwulan'));
        $failed = count(array_filter($plans, fn ($p) => $p['push_error'] !== null && ! $p['in_kipapp']));

        return [
            $this->step('rk', 'RK Anggota', $rkCount > 0 ? self::DONE : self::BLOCKED, $rkCount > 0
                ? "{$rkCount} RK tersinkron dari kipApp."
                : 'Belum ada RK tersinkron. Buat di kipApp (Rencana Kinerja > RK Anggota), lalu jalankan sinkronisasi.'),
            $this->step('skp', 'SKP triwulan', match (true) {
                $skpMissing => self::BLOCKED,
                $inKipapp > 0 || $activityCount > 0 => self::DONE,
                default => self::UNKNOWN,
            }, match (true) {
                $skpMissing => 'SKP triwulan belum dibuat. Di kipApp: Perencanaan Kinerja > Periodik > Add, pilih triwulan dan RK. Butuh SKP Tahunan yang sudah disetujui kepala.',
                $inKipapp > 0 || $activityCount > 0 => 'SKP triwulan ada: kegiatan sudah bisa dicatat.',
                default => 'Belum terlihat dari data yang ada. Akan jelas saat rencana dikirim ke kipApp.',
            }),
            $this->step('plan', 'Rencana minggu ini', $plans ? self::DONE : self::TODO, $plans
                ? count($plans).' rencana minggu ini.'
                : 'Belum ada rencana untuk minggu ini.'),
            $this->step('kipapp', 'Ada di kipApp', match (true) {
                ! $plans => self::UNKNOWN,
                $failed > 0 => self::BLOCKED,
                $inKipapp === count($plans) => self::DONE,
                default => self::TODO,
            }, match (true) {
                ! $plans => 'Menunggu rencana.',
                $failed > 0 => "{$failed} rencana belum terkirim ke kipApp. Lihat alasannya di rencana itu.",
                $inKipapp === count($plans) => 'Semua rencana sudah menjadi kegiatan di kipApp.',
                default => 'Rencana dikirim otomatis pada tanggal mulainya, atau kirim sekarang.',
            }),
            $this->step('sent', 'Terkirim ke atasan', $activityCount === 0 ? self::UNKNOWN : ($unsentCount === 0 ? self::DONE : self::TODO),
                match (true) {
                    $activityCount === 0 => 'Belum ada kegiatan triwulan ini.',
                    $unsentCount === 0 => 'Semua kegiatan triwulan ini sudah dikirim ke atasan.',
                    default => "{$unsentCount} kegiatan belum dikirim ke atasan.",
                }),
        ];
    }

    /** @return array{key: string, label: string, state: string, hint: string} */
    private function step(string $key, string $label, string $state, string $hint): array
    {
        return ['key' => $key, 'label' => $label, 'state' => $state, 'hint' => $hint];
    }
}
