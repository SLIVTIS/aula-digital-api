<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'avatar_path'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relación: cada usuario pertenece a un rol
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    // Helper
    public function isRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }

    // Atajos
    public function isAdmin(): bool   { return $this->isRole('admin'); }
    public function isTeacher(): bool { return $this->isRole('teacher'); }
    public function isParent(): bool  { return $this->isRole('parent'); }
}
