<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaTarget extends Model
{
    protected $fillable = [
        'media_id',
        'target_type',
        'group_id',
        'user_id',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'media_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
