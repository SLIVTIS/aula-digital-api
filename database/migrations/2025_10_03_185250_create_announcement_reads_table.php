<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('read_at')->useCurrent();

            $table->primary(['announcement_id', 'user_id']);

            $table->foreign('announcement_id')
                ->references('id')->on('announcements')->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
    }
};
