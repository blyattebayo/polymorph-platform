<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Создание таблицы records.
 *
 * Хранит записи контента различных типов (record_definitions).
 * Включает индексы для оптимизации запросов опубликованных записей.
 */
return new class extends Migration {
    /**
     * Выполнить миграцию.
     */
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_definition_id')->constrained('record_definitions')->restrictOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('data_json');
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('updated_at', 'idx_records_updated_at');
            $table->index(['deleted_at', 'updated_at'], 'idx_records_deleted_updated');
            $table->index(['record_definition_id', 'updated_at'], 'idx_records_record_definition_updated');
        });

        // GIN для containment-запросов (@>) по полям data_json (Query API, S1).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX idx_records_data_gin ON records USING gin (data_json jsonb_path_ops)');
        }
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};