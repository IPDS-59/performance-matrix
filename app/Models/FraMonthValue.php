<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FraMonthValue extends Model
{
    protected $fillable = ['fra_indicator_id', 'month', 'x', 'y', 'updated_by'];

    protected $casts = ['x' => 'float', 'y' => 'float'];
}
