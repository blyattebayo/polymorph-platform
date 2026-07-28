<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Маршруты больше не хранятся в БД.
 *
 * Ядро описано нативным Laravel в platform/routes, расширения приезжают
 * объектами SDK. Таблица route_nodes и весь движок, который её читал, удалены,
 * поэтому строки в ней уже ни на что не влияют — но остаются лежать и путать.
 *
 * dropIfExists, а не drop: на чистой установке миграций, создававших таблицу,
 * уже нет, и она никогда не появлялась.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('route_nodes');
    }

    /**
     * Отката нет: восстановить пустую таблицу можно, а вот движок, который
     * придавал ей смысл, — нет. Пустая таблица создала бы иллюзию отката.
     */
    public function down(): void
    {
        //
    }
};
