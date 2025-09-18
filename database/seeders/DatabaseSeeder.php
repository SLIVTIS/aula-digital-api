<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        Role::updateOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrador']
        );

        Role::updateOrCreate(
            ['slug' => 'teacher'],
            ['name' => 'Maestro']
        );

        Role::updateOrCreate(
            ['slug' => 'parent'],
            ['name' => 'Padre de familia']
        );

        // Buscar el id del rol admin
        $adminRoleId = Role::where('slug', 'admin')->value('id');

        // Crear el usuario Admin con ese rol
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@ejemplo.com',
            'password' => Hash::make('Aula_d1g1t4l'),
            'role_id' => $adminRoleId,
        ]);
    }
}
