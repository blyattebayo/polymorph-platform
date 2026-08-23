<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Opaque token не должен повторяться после delete/recreate, поэтому
        // ревизия берётся из глобальной sequence, а не из счётчика строки.
        DB::statement(
            'CREATE SEQUENCE ui_config_revision_seq AS bigint MINVALUE 1 MAXVALUE 9007199254740991 NO CYCLE',
        );

        Schema::create('ui_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('namespace', 64);
            $table->string('key', 191);
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->unsignedBigInteger('revision');
            $table->json('document');
            $table->timestamps();
        });

        // migrate:fresh удаляет таблицы, но не standalone sequences. Ownership
        // привязывает lifecycle sequence к fresh-only baseline таблицы.
        DB::statement('ALTER SEQUENCE ui_config_revision_seq OWNED BY ui_configs.revision');
        DB::statement('CREATE UNIQUE INDEX uq_ui_configs_global ON ui_configs (namespace, key) WHERE user_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_ui_configs_user ON ui_configs (namespace, key, user_id) WHERE user_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('ui_configs');
    }
};
