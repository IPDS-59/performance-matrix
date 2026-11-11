<?php

namespace App\Services\Kinetik;

use App\Models\KipActivity;
use App\Models\PlanItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Finds a kegiatan of the same person that is probably the same work as a given text and date: one filed in kipApp in October and
 * synced, an earlier import, or a plan. Spreadsheet rows word the same work differently and often carry no exact day, so the match
 * is by overlapping dates and similar words, not by equal text.
 */
class KegiatanDuplicateFinder
{
    /** Similarity (0 to 1) from which two texts count as the same kegiatan. */
    public const SIMILAR = 0.8;

    /** Merge threshold: same day and nearly the same words, safe enough to join two rows without asking. */
    public const SAME = 0.9;

    /**
     * @param  int|null  $exceptPlanItemId  a plan item to leave out (the one being checked)
     * @return array{id: int|null, source: string, description: string, date: string, score: float, in_kipapp: bool}|null the closest match
     */
    public function find(int $employeeId, string $description, string $dateStart, ?string $dateEnd = null, ?int $exceptPlanItemId = null, bool $onlyInKipapp = false, float $minScore = self::SIMILAR): ?array
    {
        $start = Carbon::parse($dateStart)->startOfDay();
        $end = Carbon::parse($dateEnd ?? $dateStart)->startOfDay();
        // A single day may be off by one (the sheet's day vs the day it was filed); a range must overlap.
        [$from, $to] = $start->equalTo($end) ? [$start->copy()->subDay(), $end->copy()->addDay()] : [$start, $end];

        $candidates = PlanItem::where('employee_id', $employeeId)->where('status', '!=', 'cancelled')
            ->when($exceptPlanItemId, fn ($q) => $q->where('id', '!=', $exceptPlanItemId))
            ->when($onlyInKipapp, fn ($q) => $q->whereNotNull('kip_external_id'))
            ->whereDate('date_start', '<=', $to->toDateString())->whereDate('date_end', '>=', $from->toDateString())
            ->get(['id', 'description', 'date_start', 'kip_external_id'])
            ->map(fn (PlanItem $i) => ['id' => $i->id, 'description' => (string) $i->description, 'date' => Carbon::parse($i->date_start)->toDateString(), 'in_kipapp' => $i->kip_external_id !== null, 'source' => $i->kip_external_id !== null ? 'kipapp' : 'kinetik']);

        // A kipApp kegiatan the sync has not turned into a plan item yet (no RK match, for example).
        $unmatched = KipActivity::where('employee_id', $employeeId)
            ->whereNotIn('external_id', PlanItem::whereNotNull('kip_external_id')->select('kip_external_id'))
            ->whereDate('activity_date_start', '<=', $to->toDateString())
            ->whereDate(DB::raw('coalesce(activity_date_end, activity_date_start)'), '>=', $from->toDateString())
            ->get(['id', 'description', 'activity_date_start'])
            ->map(fn (KipActivity $a) => ['id' => null, 'description' => (string) $a->description, 'date' => Carbon::parse($a->activity_date_start)->toDateString(), 'in_kipapp' => true, 'source' => 'kipapp']);

        return $candidates->concat($unmatched)
            ->map(fn (array $c) => $c + ['score' => $this->similarity($description, $c['description'])])
            ->filter(fn (array $c) => $c['score'] >= $minScore)
            ->sortByDesc('score')->first();
    }

    /** 0 to 1. The larger of the word overlap and the character similarity, on texts with case, punctuation and spacing removed. */
    public function similarity(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }
        // "Mengikuti BRS 2" and "Mengikuti BRS 5" are two kegiatan: different numbers never count as the same one.
        preg_match_all('/\d+/', $a, $na);
        preg_match_all('/\d+/', $b, $nb);
        if (array_diff($na[0], $nb[0]) !== [] || array_diff($nb[0], $na[0]) !== []) {
            return 0.0;
        }
        $wa = array_unique(explode(' ', $a));
        $wb = array_unique(explode(' ', $b));
        $jaccard = count(array_intersect($wa, $wb)) / count(array_unique(array_merge($wa, $wb)));
        similar_text(mb_substr($a, 0, 250), mb_substr($b, 0, 250), $percent);

        return round(max($jaccard, $percent / 100), 3);
    }

    /**
     * Whether two texts of the same day are one kegiatan entered twice, for the admin's list of double kegiatan. Stricter than
     * similarity(): every word one text has and the other lacks must be a typo of a word the other has (2 letters off, 5 letters
     * or longer, never a number) or a filler word. "Kabupaten Tolitoli" and "Kabupaten Sigi", "Surat Keluar" and "Surat Masuk",
     * "SPP" and "SPM" are two kegiatan, though similarity() scores them above SAME (seen on production 2026-11-11).
     */
    public function isSameKegiatan(string $a, string $b): bool
    {
        $words = fn (string $t) => array_values(array_unique(array_filter(explode(' ', self::normalize($t)), fn ($w) => $w !== '')));
        [$wa, $wb] = [$words($a), $words($b)];
        if ($wa === [] || $wb === []) {
            return false;
        }
        $onlyA = array_values(array_diff($wa, $wb));
        $onlyB = array_values(array_diff($wb, $wa));
        $typoOf = fn (string $w, array $others) => ! ctype_digit($w) && mb_strlen($w) >= 5
            && collect($others)->contains(fn (string $o) => ! ctype_digit($o) && levenshtein($w, $o) <= 2);

        foreach ([[$onlyA, $onlyB], [$onlyB, $onlyA]] as [$mine, $theirs]) {
            foreach ($mine as $w) {
                if (! in_array($w, self::FILLER, true) && ! $typoOf($w, $theirs)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** Words that do not tell two kegiatan apart. */
    private const FILLER = ['dan', 'yang', 'di', 'ke', 'dari', 'untuk', 'dalam', 'rangka', 'pada', 'kegiatan', 'dengan', 'serta', 'atau'];

    /**
     * How much of a kegiatan's wording an RK (or projek) text covers, 0 to 1: the share of the kegiatan's significant words that the
     * other text also has. Used to rank RK for a spreadsheet row that only names a projek; it is no duplicate test.
     */
    public function coverage(string $kegiatan, string $other): float
    {
        $words = fn (string $t) => collect(explode(' ', self::normalize($t)))
            ->reject(fn (string $w) => mb_strlen($w) < 4 || in_array($w, ['yang', 'dari', 'pada', 'dalam', 'untuk', 'dengan', 'sesuai', 'kegiatan', 'melakukan', 'terlaksananya', 'terselenggaranya', 'tersedianya', 'data'], true))->unique()->values();
        $mine = $words($kegiatan);
        if ($mine->isEmpty()) {
            return 0.0;
        }

        return round($mine->intersect($words($other))->count() / $mine->count(), 3);
    }

    public static function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text))));
    }
}
