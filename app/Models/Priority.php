<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Priority extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'color',
        'level',
        'icon',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];


    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }


    public function scopeOrdered($query)
    {
        return $query->orderBy('level', 'desc');
    }
}
