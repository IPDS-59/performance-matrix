<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A page tour a user has finished or closed. */
class TourCompletion extends Model
{
    protected $fillable = ['user_id', 'tour_key'];
}
