<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaItem extends Model
{
    protected $fillable = [
        'uploader_user_id',
        'title',
        'description',
        'file_path',
        'mime_type',
        'file_size_bytes',
        'checksum_sha256',
        'scope',
    ];

    // Relaciones
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_user_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(MediaTarget::class, 'media_id');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(MediaDownload::class, 'media_id');
    }

    /**
     * Scope para filtrar lo visible para un usuario.
     * Reglas:
     *  - scope = 'all'
     *  - uploader = usuario
     *  - target directo al usuario
     *  - target por grupo si pertenece a algún grupo asignado
     * Asume una tabla pivot 'group_user' (user_id, group_id).
     */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $q, \App\Models\User $user): \Illuminate\Database\Eloquent\Builder
    {
        return $q->where(function ($qq) use ($user) {
            $qq->where('scope', 'all')
            ->orWhere('uploader_user_id', $user->id)
            ->orWhereHas('targets', function ($t) use ($user) {
                $t->where('target_type', 'user')
                    ->where('user_id', $user->id);
            })
            ->orWhereHas('targets', function ($t) use ($user) {
                $t->where('target_type', 'group')
                    ->whereIn('group_id', function ($sub) use ($user) {
                        $sub->select('group_id')
                            ->from('user_groups')          // <— la vista
                            ->where('user_id', $user->id);
                    });
            });
        });
    }
}
