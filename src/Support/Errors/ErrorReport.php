<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

/**
 * Как записать ошибку в лог: уровень, сообщение и дополнительный контекст.
 *
 * Контекст здесь — уже вычисленный массив, а не замыкание-резолвер: тот, кто
 * строит отчёт, держит исключение в руках, поэтому откладывать вычисление
 * незачем. Прежний вариант с Closure требовал машинерии привязки к контейнеру,
 * которая никогда ничего не делала (все замыкания были static).
 */
final readonly class ErrorReport
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $level,
        public string $message,
        public array $context = [],
    ) {}
}
