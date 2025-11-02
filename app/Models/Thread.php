<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends Model
{
    protected $table = 'threads';
    public $timestamps = false;

    protected $fillable = [
        'subject',
        'is_one_to_one',
    ];

    protected $casts = [
        'is_one_to_one' => 'bool',
        'created_at' => 'datetime',
    ];

    /** Participantes del hilo */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'thread_participants', 'thread_id', 'user_id');
    }

    /** Mensajes del hilo */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }

    /** Scope: hilos donde participa un usuario */
    public function scopeForUser($query, int $userId)
    {
        return $query->whereExists(function ($q) use ($userId) {
            $q->selectRaw(1)
              ->from('thread_participants as tp')
              ->whereColumn('tp.thread_id', 'threads.id')
              ->where('tp.user_id', $userId);
        });
    }

    /** Buscar (o crear) un hilo 1:1 entre dos usuarios */
    public static function firstOneToOneBetween(int $userA, int $userB): ?self
    {
        return static::query()
            ->where('is_one_to_one', true)
            ->whereExists(function ($q) use ($userA) {
                $q->selectRaw(1)->from('thread_participants as tpa')
                  ->whereColumn('tpa.thread_id', 'threads.id')
                  ->where('tpa.user_id', $userA);
            })
            ->whereExists(function ($q) use ($userB) {
                $q->selectRaw(1)->from('thread_participants as tpb')
                  ->whereColumn('tpb.thread_id', 'threads.id')
                  ->where('tpb.user_id', $userB);
            })
            ->first();
    }
}

