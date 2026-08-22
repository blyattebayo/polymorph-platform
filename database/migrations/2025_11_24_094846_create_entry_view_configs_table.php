<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_view_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_definition_id')
                ->constrained('record_definitions')
                ->restrictOnDelete();
            $table->foreignId('schema_id')
                ->constrained('schemas')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->json('document');
            $table->timestamps();

            $table->unique(
                ['record_definition_id', 'schema_id'],
                'uq_entry_view_configs_record_definition_schema',
            );
            $table->index('schema_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_view_configs');
    }
};
