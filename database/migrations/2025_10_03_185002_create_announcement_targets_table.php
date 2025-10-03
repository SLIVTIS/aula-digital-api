<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
      public function up(): void
    {
        Schema::create('announcement_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();

            $table->enum('target_type', ['group','user']);
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            // Índices
            $table->index('announcement_id');
            $table->index(['target_type', 'group_id']);
            $table->index(['target_type', 'user_id']);

            // FKs
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // CHECK constraint.
        DB::statement("
            ALTER TABLE announcement_targets
            ADD CONSTRAINT chk_announcement_targets_type
            CHECK (
                (target_type = 'group' AND group_id IS NOT NULL AND user_id IS NULL) OR
                (target_type = 'user'  AND user_id  IS NOT NULL AND group_id IS NULL)
            )
        ");
    }

    public function down(): void
    {
        // Quitar constraint si el motor lo soporta (no falla si no existe)
        try {
            DB::statement("ALTER TABLE announcement_targets DROP CONSTRAINT chk_announcement_targets_type");
        } catch (\Throwable $e) {}

        Schema::dropIfExists('announcement_targets');
    }
};
