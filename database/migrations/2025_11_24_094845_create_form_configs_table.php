<?php

declare(strict_types=1);

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
        Schema::create('form_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_definition_id')
                ->constrained('record_definitions')
                ->restrictOnDelete();
            $table->foreignId('schema_id')
                ->constrained('schemas')
                ->cascadeOnDelete();
            $table->json('config_json')->nullable(false);
            $table->timestamps();

            // Составной уникальный ключ
            $table->unique(['record_definition_id', 'schema_id'], 'uq_form_configs_record_definition_schema');

            // Индексы для быстрого поиска
            $table->index('record_definition_id');
            $table->index('schema_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_configs');
    }
};
