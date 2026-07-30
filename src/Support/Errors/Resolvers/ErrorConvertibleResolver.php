<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors\Resolvers;

use Illuminate\Http\Request;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorResolution;
use Polymorph\Platform\Support\Errors\ResolvesError;
use Throwable;

/**
 * Исключение, которое умеет описать себя само. Первый резолвер в цепочке.
 *
 * Идёт первым потому, что почти все доменные исключения унаследованы от
 * RuntimeException, а у FrameworkErrorResolver есть ветвь-подстраховка на этот
 * предок: пропусти его вперёд — и осмысленный 409 стал бы общим 500.
 *
 * Сюда же попадает HttpErrorException: он носит готовый payload, поэтому
 * реализует тот же контракт и не требует отдельной ветви у вызывающего.
 */
final class ErrorConvertibleResolver implements ResolvesError
{
    public function __construct(
        private readonly ErrorFactory $factory,
    ) {}

    public function resolve(Throwable $exception, ?Request $request = null): ?ErrorResolution
    {
        if (! $exception instanceof ErrorConvertible) {
            return null;
        }

        // Отчёт не задаём: уровень выведется из статуса, а исключение, которому
        // этого мало, поднимает себе уровень через DescribesErrorReport.
        return new ErrorResolution($exception->toError($this->factory));
    }
}
