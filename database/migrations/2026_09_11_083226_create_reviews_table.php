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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source_review_id');
            $table->string('author_name');
            $table->text('author_avatar_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('text')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'source_review_id']);
            $table->index(['organization_id', 'published_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
