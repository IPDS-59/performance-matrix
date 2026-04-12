<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReportAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_report_id',
        'type',
        'file_path',
        'file_name',
        'mime_type',
        'url',
        'title',
        'status',
        'reviewed_by',
        'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    protected $appends = ['display_url'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(PerformanceReport::class, 'performance_report_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }

    public function getDisplayUrlAttribute(): ?string
    {
        if ($this->type === 'link') {
            return $this->url;
        }

        if ($this->file_path) {
            return route('report-attachments.download', $this);
        }

        return null;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function deleteFile(): void
    {
        if ($this->type === 'file' && $this->file_path) {
            Storage::disk('local')->delete($this->file_path);
        }
    }
}
