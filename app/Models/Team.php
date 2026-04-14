<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function annualPlans(): HasMany
    {
        return $this->hasMany(TeamAnnualPlan::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function teamHistories(): HasMany
    {
        return $this->hasMany(EmployeeTeamHistory::class);
    }
}
