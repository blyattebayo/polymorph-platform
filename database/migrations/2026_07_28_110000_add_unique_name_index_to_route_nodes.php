<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Уникальность имени клиентского маршрута на уровне БД.
 *
 * Проверка в RouteNodeService — классический TOCTOU: два параллельных запроса
 * читают «имя свободно» и оба вставляют строку. Индекс частичный:
 * - name IS NOT NULL — безымянных узлов может быть сколько угодно;
 * - deleted_at IS NULL — мягко удалённый узел не должен вечно занимать имя.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX route_nodes_name_unique
             ON route_nodes (name)
             WHERE name IS NOT NULL AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS route_nodes_name_unique');
    }
};
