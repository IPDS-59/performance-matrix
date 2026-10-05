<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What the PJ asks one member to focus on in one week. */
class WeeklyFocus extends Model
{
    protected $table = 'weekly_focuses';

    protected $fillable = ['team_id', 'employee_id', 'week_start', 'body', 'created_by'];
}
