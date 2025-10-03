<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::create('group_students', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('student_id');

            $table->primary(['group_id', 'student_id']);

            $table->foreign('group_id')
                ->references('id')->on('groups')
                ->cascadeOnDelete();

            $table->foreign('student_id')
                ->references('id')->on('students')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_students');
    }
};
