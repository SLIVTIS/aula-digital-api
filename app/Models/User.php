<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage; // <-- agrega

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'avatar_path'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        // 'email',
    ];

    /**
     * Para que el JSON incluya automáticamente el URL del avatar.
     */
    protected $appends = ['avatar_url']; // <-- agrega

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    // Helper: URL público del avatar (o null si no hay)
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) return null;
        // Ajusta el disco si usas otro distinto a 'public'
        return Storage::disk('public')->url($this->avatar_path);
    }

    // Atajos de rol...
    public function isRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }
    public function isAdmin(): bool   { return $this->isRole('admin'); }
    public function isTeacher(): bool { return $this->isRole('teacher'); }
    public function isParent(): bool  { return $this->isRole('parent'); }
}
