<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'name',
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size',
    ];

    protected $appends = ['url', 'size_formatted'];

    // ==================== RELATIONSHIPS ====================

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==================== ACCESSORS ====================

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getSizeFormattedAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function getIconAttribute(): string
    {
        return match (true) {
            str_starts_with($this->mime_type, 'image/') => 'mdi-file-image',
            str_starts_with($this->mime_type, 'video/') => 'mdi-file-video',
            str_starts_with($this->mime_type, 'audio/') => 'mdi-file-music',
            str_contains($this->mime_type, 'pdf') => 'mdi-file-pdf-box',
            str_contains($this->mime_type, 'word') => 'mdi-file-word',
            str_contains($this->mime_type, 'excel') || str_contains($this->mime_type, 'spreadsheet') => 'mdi-file-excel',
            str_contains($this->mime_type, 'zip') || str_contains($this->mime_type, 'archive') => 'mdi-folder-zip',
            default => 'mdi-file-document',
        };
    }

    // ==================== HELPER METHODS ====================

    public function delete(): bool
    {
        Storage::disk($this->disk)->delete($this->path);
        return parent::delete();
    }
}
