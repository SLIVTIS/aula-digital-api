<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Elimina la vista si existiera
        DB::statement('DROP VIEW IF EXISTS user_groups');

        // Crea la vista: user_id, group_id, source_role (teacher|parent|student)
        DB::statement("
            CREATE VIEW user_groups AS
            -- Maestros asignados directamente a grupos
            SELECT
                tg.teacher_user_id AS user_id,
                tg.group_id        AS group_id,
                'teacher'          AS source_role
            FROM teacher_groups tg

            UNION

            -- Padres: por cada alumno del que es tutor, hereda los grupos del alumno
            SELECT
                sp.parent_user_id  AS user_id,
                gs.group_id        AS group_id,
                'parent'           AS source_role
            FROM student_parents sp
            JOIN group_students gs ON gs.student_id = sp.student_id

            -- Si manejas alumnos con cuenta de usuario, descomenta y ajusta la siguiente parte:
            -- UNION
            -- SELECT
            --     u.id            AS user_id,
            --     gs.group_id     AS group_id,
            --     'student'       AS source_role
            -- FROM users u
            -- JOIN group_students gs ON gs.student_id = u.student_id
            -- WHERE u.student_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS user_groups');
    }
};
