<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reads', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('read_at')->useCurrent();

            $table->primary(['message_id', 'user_id']);

            $table->foreign('message_id')
                ->references('id')->on('messages')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reads');
    }
};
