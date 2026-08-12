<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Http;

use Illuminate\Http\Request;
use Polymorph\Platform\Support\Errors\ErrorResolution;
use Polymorph\Platform\Support\Errors\ResolvesError;
use Polymorph\Sdk\Errors\ExtensionError;
use Throwable;

/**
 * Ошибка расширения (SDK V2) в общей цепочке разбора исключений.
 *
 * Живёт в домене Extensions, а не в Support: перевод опирается на карту кодов SDK
 * внутри {@see ReplyRenderer}, и тащить её в нижний слой было бы зависимостью вверх.
 * Ровно из-за отсутствия этого шва обработка ExtensionError раньше лежала отдельной
 * ветвью в HTTP bootstrap — со своим вызовом репортера и своей раздачей заголовков.
 *
 * Обязан идти в цепочке ПЕРЕД FrameworkErrorResolver: ExtensionError унаследован от
 * RuntimeException, и его подстраховочная ветвь превратила бы его в общий 500.
 */
final class ExtensionErrorResolver implements ResolvesError
{
    public function __construct(
        private readonly ReplyRenderer $renderer,
    ) {}

    public function resolve(Throwable $exception, ?Request $request = null): ?ErrorResolution
    {
        if (! $exception instanceof ExtensionError) {
            return null;
        }

        return new ErrorResolution($this->renderer->toPayload($exception));
    }
}
