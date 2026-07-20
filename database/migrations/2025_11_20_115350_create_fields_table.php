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
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schema_id')->constrained('schemas')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('fields')->cascadeOnDelete();
            $table->string('name');
            $table->string('full_path');
            $table->string('type'); // string, text, int, float, bool, datetime, json, ref, media
            $table->string('cardinality')->default('one'); // one, many
            $table->boolean('is_indexed')->default(false);
            $table->boolean('is_system')->default(false);
            $table->json('validation_rules')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index(['schema_id', 'full_path']);
            $table->index(['schema_id', 'parent_id']);
            $table->index('type');
            $table->index('is_indexed');
            $table->index('is_system');
            $table->unique(['schema_id', 'full_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
