<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Сделать route_nodes.action_type nullable.
 *
 * Колонка была объявлена NOT NULL, хотя action_type осмыслен только для
 * kind='route': у групп его нет и быть не может. Из-за этого любое создание
 * группы через админ-API падало с 23502, а PUT со сменой kind route->group
 * (cleanFieldsOnKindChange зануляет action_type) давал 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Колонка объявлена как enum -> в Postgres это varchar + CHECK,
        // поэтому меняем nullability напрямую (Doctrine DBAL не требуется).
        DB::statement('ALTER TABLE route_nodes ALTER COLUMN action_type DROP NOT NULL');
    }

    public function down(): void
    {
        // Группы, созданные после миграции, не имеют action_type — вернуть
        // NOT NULL можно только проставив им значение-заглушку.
        DB::statement("UPDATE route_nodes SET action_type = 'controller' WHERE action_type IS NULL");
        DB::statement('ALTER TABLE route_nodes ALTER COLUMN action_type SET NOT NULL');
    }
};
