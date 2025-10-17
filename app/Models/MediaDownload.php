<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaDownload extends Model
{
    protected $fillable = [
        'media_id',
        'user_id',
        'downloaded_at',
        'ip_address',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'media_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}