<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One RK of a member's yearly kipApp SKP. */
class EmployeeRk extends Model
{
    protected $fillable = ['employee_id', 'kip_rk_id', 'name', 'leader_rk', 'team_kip_id', 'year'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
