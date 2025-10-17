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
        Schema::create('media_downloads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('media_id')
                  ->constrained('media_items')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->timestamp('downloaded_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index('media_id');
            $table->index('user_id');
            $table->index('downloaded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_downloads');
    }
};
