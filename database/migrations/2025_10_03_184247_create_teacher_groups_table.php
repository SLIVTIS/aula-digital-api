<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::create('teacher_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('teacher_user_id');

            $table->primary(['group_id', 'teacher_user_id']);

            $table->foreign('group_id')
                ->references('id')->on('groups')
                ->cascadeOnDelete();

            $table->foreign('teacher_user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_groups');
    }
};
