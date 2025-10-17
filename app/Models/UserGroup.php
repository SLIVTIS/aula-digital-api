<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGroup extends Model
{
    protected $table = 'user_groups';
    public $timestamps = false;
    public $incrementing = false;
    protected $fillable = []; // solo lectura

}
