<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Tx;

use Illuminate\Support\Facades\DB;

/**
 * Тонкая обёртка над транзакциями БД — единая точка для запуска pipeline work.
 *
 * Делегирует нативному Laravel DB, который обрабатывает вложенные транзакции через SAVEPOINT.
 */
final class TransactionManager
{
    /**
     * Выполнить функцию в транзакции БД.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public function runInTransaction(callable $fn): mixed
    {
        return DB::transaction($fn);
    }

    /**
     * Находимся ли мы сейчас внутри транзакции БД.
     */
    public function inTransaction(): bool
    {
        return DB::transactionLevel() > 0;
    }
}
