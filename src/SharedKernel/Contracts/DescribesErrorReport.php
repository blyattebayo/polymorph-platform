<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Contracts;

use Polymorph\Platform\Support\Errors\ErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorReport;
use Throwable;

/**
 * Исключение, которое само задаёт, как его писать в лог.
 *
 * Нужно там, где уровень по статусу неверен: ошибка конфигурации JWT отдаёт
 * клиенту обычные 500, но для оператора это `critical` — сервис не сможет
 * выпустить ни один токен, пока её не починят.
 *
 * Объявление живёт на исключении, а не в таблице внутри Support, потому что это
 * доменное знание. Иначе Support пришлось бы импортировать доменные классы
 * (зависимость вверх), и добавление своего уровня требовало бы правки нижнего слоя.
 */
interface DescribesErrorReport extends Throwable
{
    public function errorReport(ErrorPayload $payload): ErrorReport;
}
