<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyRecapRowClaim extends Model
{
    public $timestamps = false;

    protected $fillable = ['weekly_recap_row_id', 'activity_claim_id'];
}
