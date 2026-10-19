<?php

namespace App\Models;

use App\Kinetik\FeatureFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An admin's on/off choice for one kipApp feature flag. See {@see FeatureFlags}. */
class FeatureFlag extends Model
{
    protected $fillable = ['key', 'enabled', 'changed_by'];

    protected $casts = ['enabled' => 'boolean'];

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
