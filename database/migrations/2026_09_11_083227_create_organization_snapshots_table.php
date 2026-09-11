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
        Schema::create('organization_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->timestamp('captured_at');
            $table->string('name');
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count');
            $table->unsignedInteger('reviews_count');
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'captured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_snapshots');
    }
};
