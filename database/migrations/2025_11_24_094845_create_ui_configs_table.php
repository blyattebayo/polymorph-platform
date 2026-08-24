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
            // Вид конфига закодирован в ключе (`entry_view:12`, `table:records.posts`),
            // поэтому отдельной колонки namespace нет.
            $table->string('key', 191);
            $table->string('domain', 16);
            // Автор общей конфигурации — тот, кто её последним записал; у личной
            // он же владелец и часть идентичности.
            $table->foreignId('author_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->unsignedBigInteger('revision');
            $table->json('config');
            $table->timestamps();
        });

        // migrate:fresh удаляет таблицы, но не standalone sequences. Ownership
        // привязывает lifecycle sequence к fresh-only baseline таблицы.
        DB::statement('ALTER SEQUENCE ui_config_revision_seq OWNED BY ui_configs.revision');
        DB::statement("ALTER TABLE ui_configs ADD CONSTRAINT ck_ui_configs_domain CHECK (domain IN ('global', 'user'))");
        // Личная конфигурация принадлежит автору: строка без него — не адрес, а
        // дырка, которую уникальный индекс с NULL к тому же не поймает.
        DB::statement("ALTER TABLE ui_configs ADD CONSTRAINT ck_ui_configs_personal_author CHECK (domain <> 'user' OR author_id IS NOT NULL)");
        // Идентичность зависит от домена: общая конфигурация одна на ключ, личная —
        // одна на ключ и автора. Автор общей в идентичность не входит.
        DB::statement("CREATE UNIQUE INDEX uq_ui_configs_global ON ui_configs (key) WHERE domain = 'global'");
        DB::statement("CREATE UNIQUE INDEX uq_ui_configs_user ON ui_configs (key, author_id) WHERE domain = 'user'");
    }

    public function down(): void
    {
        Schema::dropIfExists('ui_configs');
    }
};
