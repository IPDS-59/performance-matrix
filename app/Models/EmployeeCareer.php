<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCareer extends Model
{
    protected $fillable = [
        'employee_id',
        'jabatan',
        'golongan',
        'pangkat',
        'golongan_since',
        'level_since',
        'level_start_golongan',
        'ak_base',
        'ak_base_date',
        'synced_at',
    ];

    protected $casts = [
        'golongan_since' => 'date',
        'level_since' => 'date',
        'ak_base' => 'float',
        'ak_base_date' => 'date',
        'synced_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
