<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use RuntimeException;

/**
 * Исключение с уже готовым payload'ом: бросается там, где ответ известен точно
 * (валидация FormRequest, CSRF, маппер сбоев пайплайна, хелперы ThrowsErrors).
 *
 * Реализует ErrorConvertible, поэтому опознаётся общей цепочкой резолверов и не
 * требует отдельной ветви у вызывающего — раньше под него была своя ветвь в
 * HostBootstrap со своим вызовом репортера.
 *
 * Здесь был ещё слот `Closure(JsonResponse):JsonResponse` для донастройки ответа.
 * Его единственным потребителем был ThrowsErrors::throwErrorWithHeaders(), который
 * строил замыкание, ставящее заголовки в цикле, — то есть замыкание существовало,
 * чтобы протащить `array<string, string>`. А оба заголовка, которые так протаскивали
 * (`WWW-Authenticate` и `Pragma` для 401), теперь выводятся из статуса в
 * {@see ErrorResponseFactory}, одинаково для всех путей. Слот стал ненужным.
 */
final class HttpErrorException extends RuntimeException implements ErrorConvertible
{
    public function __construct(
        private readonly ErrorPayload $payload,
    ) {
        parent::__construct($payload->detail);
    }

    public function payload(): ErrorPayload
    {
        return $this->payload;
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $this->payload;
    }
}
