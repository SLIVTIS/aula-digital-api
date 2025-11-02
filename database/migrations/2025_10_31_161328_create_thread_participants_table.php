<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thread_participants', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('user_id');

            $table->primary(['thread_id', 'user_id']);

            $table->foreign('thread_id')
                ->references('id')->on('threads')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_participants');
    }
};
