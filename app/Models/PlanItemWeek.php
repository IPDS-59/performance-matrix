<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The realisasi of one week of a kegiatan that runs for several weeks. */
class PlanItemWeek extends Model
{
    protected $fillable = ['plan_item_id', 'week_start', 'capaian', 'progress', 'kendala'];

    protected function casts(): array
    {
        return ['week_start' => 'date:Y-m-d', 'capaian' => 'decimal:2'];
    }

    /** @return BelongsTo<PlanItem, $this> */
    public function planItem(): BelongsTo
    {
        return $this->belongsTo(PlanItem::class);
    }
}
