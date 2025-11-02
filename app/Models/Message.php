<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Message extends Model
{
    protected $table = 'messages';
    public $timestamps = false;

    protected $fillable = [
        'thread_id',
        'sender_user_id',
        'body_md',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /** Usuarios que leyeron este mensaje (con pivot read_at) */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_reads', 'message_id', 'user_id')
            ->withPivot('read_at');
    }
}

