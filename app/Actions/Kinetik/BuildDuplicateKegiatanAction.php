<?php

namespace App\Actions\Kinetik;

use App\Models\PlanItem;
use App\Notifications\KinetikNotification;
use App\Services\Kinetik\KegiatanDuplicateFinder;
use Carbon\Carbon;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;

/**
 * Pairs of kegiatan of one person that start on the same day and say almost the same thing, over the last 60 days, each with
 * the one to keep (the one with more finished data or already sent), the one to delete, how, and whether an admin already
 * asked the person to act. The admin reviews them before asking; nothing here deletes anything.
 */
class BuildDuplicateKegiatanAction
{
    public const TYPE = 'duplicate_kegiatan';

    private const COUNT_CACHE = 'kinetik.duplicate_pending';

    /** Pairs no admin has asked about yet, for the sidebar badge. Cached for 10 minutes; asking clears it. */
    public function pendingCount(): int
    {
        return Cache::remember(self::COUNT_CACHE, 600, fn () => collect($this->execute())->whereNull('asked')->count());
    }

    /** @return list<array<string, mixed>> */
    public function execute(): array
    {
        $finder = new KegiatanDuplicateFinder;
        $asked = $this->asked();
        $pairs = [];

        PlanItem::with('employee:id,name')->where('status', '!=', 'cancelled')->where('date_start', '>=', now()->subDays(60)->toDateString())
            ->get()
            ->groupBy(fn (PlanItem $i) => $i->employee_id.'|'.substr((string) $i->date_start, 0, 10))
            ->each(function ($group) use ($finder, $asked, &$pairs) {
                $items = $group->values();
                for ($a = 0; $a < $items->count(); $a++) {
                    for ($b = $a + 1; $b < $items->count(); $b++) {
                        if ($finder->similarity($items[$a]->description, $items[$b]->description) < KegiatanDuplicateFinder::SAME) {
                            continue;
                        }
                        [$x, $y] = [$items[$a], $items[$b]];
                        [$keep, $drop] = ($this->weight($x) > $this->weight($y) || ($this->weight($x) === $this->weight($y) && $x->id < $y->id)) ? [$x, $y] : [$y, $x];
                        $key = min($x->id, $y->id).'-'.max($x->id, $y->id);
                        $pairs[] = [
                            'key' => $key,
                            'employee_id' => $x->employee_id,
                            'employee' => $x->employee?->name,
                            'date' => substr((string) $x->date_start, 0, 10),
                            'description' => $x->description,
                            'keep' => $this->describe($keep),
                            'drop' => $this->describe($drop),
                            'how' => $drop->kip_sent_at !== null ? 'Batalkan kirim dulu, lalu hapus' : ($drop->kip_external_id !== null ? 'Hapus di Kinetik (ikut terhapus di kipApp)' : 'Hapus di Kinetik (kipApp tidak tersentuh)'),
                            'same_rk' => $x->performance_plan_id === $y->performance_plan_id,
                            'asked' => $asked[$key] ?? null,
                        ];
                    }
                }
            });

        return collect($pairs)->sortBy([['employee', 'asc'], ['date', 'asc']])->values()->all();
    }

    /**
     * Ask the people of the chosen pairs to act: one bell notice per person, however many of their pairs were chosen.
     *
     * @param  list<string>  $keys  pair keys from execute()
     * @return int people notified
     */
    public function ask(array $keys, string $adminName): int
    {
        $chosen = collect($this->execute())->whereIn('key', $keys)->groupBy('employee_id');
        $notified = 0;

        foreach ($chosen as $employeeId => $rows) {
            $user = PlanItem::where('employee_id', $employeeId)->first()?->employee?->user;
            if ($user === null) {
                continue;
            }
            $first = $rows->first();
            $message = $rows->count() === 1
                ? 'Admin meminta Anda memeriksa kegiatan ganda "'.mb_strimwidth($first['description'], 0, 70, '…').'" pada '.Carbon::parse($first['date'])->translatedFormat('j F Y').'. Bila memang sama, hapus salah satunya.'
                : 'Admin meminta Anda memeriksa '.$rows->count().' kegiatan ganda, misalnya "'.mb_strimwidth($first['description'], 0, 60, '…').'". Bila memang sama, hapus salah satunya.';
            $user->notify(new KinetikNotification(self::TYPE, $message, route('weekly.index', ['week' => Carbon::parse($first['date'])->startOfWeek()->toDateString()]), ['pairs' => $rows->pluck('key')->all(), 'by' => $adminName]));
            $notified++;
        }
        Cache::forget(self::COUNT_CACHE);

        return $notified;
    }

    /** @return array<string, array{at: string, by: string|null}> the latest request per pair key */
    private function asked(): array
    {
        $asked = [];
        DatabaseNotification::where('data', 'like', '%"type":"'.self::TYPE.'"%')->orderBy('created_at')->get()
            ->each(function (DatabaseNotification $n) use (&$asked) {
                foreach ((array) ($n->data['pairs'] ?? []) as $key) {
                    $asked[$key] = ['at' => $n->created_at->toDateTimeString(), 'by' => $n->data['by'] ?? null];
                }
            });

        return $asked;
    }

    /** More finished data wins: sent to the atasan, linked to kipApp, a capaian, a progress. */
    private function weight(PlanItem $p): int
    {
        return ($p->kip_sent_at ? 4 : 0) + ($p->kip_external_id ? 2 : 0) + ($p->capaian !== null ? 2 : 0) + ($p->progress > 0 ? 1 : 0) + (filled($p->evidence_url) ? 1 : 0);
    }

    /** @return array<string, mixed> */
    private function describe(PlanItem $p): array
    {
        return [
            'id' => $p->id,
            'where' => $p->kip_external_id !== null ? ($p->kip_sent_at ? 'di kipApp, sudah dikirim ke atasan' : 'di kipApp') : 'hanya di Kinetik',
            'source' => $p->source,
            'progress' => (int) $p->progress,
            'has_capaian' => $p->capaian !== null,
            'has_bukti' => filled($p->evidence_url),
        ];
    }
}
