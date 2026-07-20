<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Создание таблицы record_definitions.
 *
 * Хранит типы записей (например, 'article', 'page', 'post').
 * Каждый тип может быть связан с schema, определяющей структуру Record.
 */
return new class extends Migration
{
    /**
     * Выполнить миграцию.
     */
    public function up(): void
    {
        Schema::create('record_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('schema_id')->nullable()->constrained('schemas')->restrictOnDelete();
            $table->text('display_template')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_definitions');
    }
};
