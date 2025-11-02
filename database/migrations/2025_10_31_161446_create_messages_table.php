<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('sender_user_id');
            $table->mediumText('body_md');
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('thread_id')
                ->references('id')->on('threads')
                ->onDelete('cascade');

            $table->foreign('sender_user_id')
                ->references('id')->on('users')
                ->onDelete('restrict');

            $table->index('thread_id');
            $table->index('sender_user_id');

            // Requiere MySQL 5.7+ / 8.0+ con InnoDB
            $table->fullText('body_md');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
