<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Student extends Model
{
    protected $fillable = [
        'first_name', 'last_name', 'student_code',
    ];

    // Grupos a los que pertenece el alumno
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_students', 'student_id', 'group_id');
    }

    // Padres/tutores (usuarios con rol "parent" a nivel de aplicación)
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_parents', 'student_id', 'parent_user_id')
            ->withPivot('relationship');
    }

    // Accesor útil: nombre completo
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => "{$this->first_name} {$this->last_name}");
    }
}
