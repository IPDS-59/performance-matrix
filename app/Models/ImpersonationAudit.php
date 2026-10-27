<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One write an admin made while acting as another user. */
class ImpersonationAudit extends Model
{
    protected $fillable = ['admin_id', 'user_id', 'method', 'route', 'path', 'status'];
}
