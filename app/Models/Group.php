<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    protected $fillable = [
        'name', 'grade', 'section', 'code',
    ];

     // Alumnos asignados al grupo
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'group_students', 'group_id', 'student_id');
    }

    // Maestros asignados al grupo (usuarios con rol "teacher" )
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'teacher_groups', 'group_id', 'teacher_user_id');
    }
}
