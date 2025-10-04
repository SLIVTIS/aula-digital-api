<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body_md',
        'author_user_id',
        'visibility',
        'published_at',
        'is_archived',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_archived'  => 'boolean',
    ];

    // Autor (usuario que publica)
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    // Destinatarios específicos (usuarios o grupos)
    public function targets(): HasMany
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    // Lecturas (pivot con users + read_at)
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    // Lectores como relación many-to-many
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads')
            ->withPivot('read_at');
    }

    // Alcance fulltext (requiere MySQL con soporte FULLTEXT)
    public function scopeSearch($query, string $term)
    {
        // disponible desde Laravel 9+: whereFullText
        return $query->whereFullText(['title','body_md'], $term);
    }
}
