<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BugReport extends Model
{
    public const STATUSES = ['baru' => 'Baru', 'diproses' => 'Sedang diperiksa', 'selesai' => 'Selesai', 'ditolak' => 'Bukan masalah'];

    protected $fillable = ['user_id', 'title', 'description', 'page_url', 'user_agent', 'status', 'admin_note'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
