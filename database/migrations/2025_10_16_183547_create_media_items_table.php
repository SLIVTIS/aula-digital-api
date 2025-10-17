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
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploader_user_id')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->string('title', 180);
            $table->text('description')->nullable();

            // Ruta dentro de storage/app (o disco configurado)
            $table->string('file_path', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes');

            $table->char('checksum_sha256', 64)->nullable();

            $table->enum('scope', ['all','groups','users'])->default('all');

            $table->timestamps();
            $table->index('uploader_user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_items');
    }
};
