<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A pair of look-alike kegiatan ("{lower id}-{higher id}") an admin marked as not double. */
class DuplicateKegiatanDismissal extends Model
{
    protected $fillable = ['pair_key', 'user_id'];
}
