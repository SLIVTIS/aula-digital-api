<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_parents', function (Blueprint $table) {
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('parent_user_id');
            $table->string('relationship', 40)->nullable();

            $table->primary(['student_id', 'parent_user_id']);

            $table->foreign('student_id')
                ->references('id')->on('students')
                ->cascadeOnDelete();

            $table->foreign('parent_user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_parents');
    }
};
