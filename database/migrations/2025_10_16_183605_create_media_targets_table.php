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
       Schema::create('media_targets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('media_id')
                  ->constrained('media_items')
                  ->cascadeOnDelete();

            $table->enum('target_type', ['group','user']);
            $table->foreignId('group_id')->nullable()
                  ->constrained('groups')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->timestamps();

            $table->index('media_id');
            $table->index(['target_type', 'group_id']);
            $table->index(['target_type', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_targets');
    }
};
