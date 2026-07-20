<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Tx;

use Illuminate\Support\Facades\DB;

/**
 * Тонкая обёртка над транзакциями БД — единая точка для запуска работы в
 * транзакции и регистрации after-commit колбэков в рамках PipelineCore.
 *
 * Делегирует нативному Laravel DB, который уже корректно:
 *  - обрабатывает вложенные транзакции через SAVEPOINT;
 *  - откладывает after-commit колбэки до коммита САМОЙ ВНЕШНЕЙ реальной
 *    транзакции, отслеживая фактический DB::transactionLevel();
 *  - очищает отложенные колбэки при rollback.
 *
 * Ранее здесь жил самописный CommitCallbackQueue со счётчиком глубины,
 * расцепленным с реальным уровнем транзакции БД. Если runInTransaction()
 * оборачивали во внешний DB::transaction(), очередь считала себя «внешней»
 * (depth === 1) и выполняла колбэки ДО фактического commit; при откате
 * внешней транзакции побочные эффекты уже были применены. Машинерия была
 * вдобавок мёртвой (afterCommit() не вызывался ниоткуда) и удалена в пользу
 * нативного механизма.
 */
final class TransactionManager
{
    /**
     * Выполнить функцию в транзакции БД.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function runInTransaction(callable $fn): mixed
    {
        return DB::transaction($fn);
    }

    /**
     * Зарегистрировать колбэк на выполнение после коммита внешней транзакции.
     *
     * Вне транзакции колбэк выполняется немедленно (нативное поведение DB).
     */
    public function afterCommit(callable $callback): void
    {
        DB::afterCommit($callback);
    }

    /**
     * Находимся ли мы сейчас внутри транзакции БД.
     */
    public function inTransaction(): bool
    {
        return DB::transactionLevel() > 0;
    }
}
