<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->mediumText('body_md');
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('visibility', ['all','groups','users'])->default('all');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            // FULLTEXT index para búsquedas por palabra
            $table->fullText(['title', 'body_md'], 'ft_ann_title_body');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
