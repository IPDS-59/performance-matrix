<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FraMonthValue extends Model
{
    protected $fillable = ['fra_indicator_id', 'month', 'x', 'y', 'obstacle', 'solution', 'follow_up', 'pic', 'deadline', 'evidence_url', 'previous_follow_up_url', 'updated_by'];

    protected $casts = ['x' => 'float', 'y' => 'float'];
}
