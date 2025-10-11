<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notifications';

    // No manejamos updated_at y created_at por Eloquent
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'payload_json',
        'is_read',
        'created_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'is_read'      => 'boolean',
        'created_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Scope: solo no leídas */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /** Scope: por tipo exacto */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
