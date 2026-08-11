<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors\Resolvers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorReport;
use Polymorph\Platform\Support\Errors\ErrorResolution;
use Polymorph\Platform\Support\Errors\HttpErrorException;
use Polymorph\Platform\Support\Errors\ResolvesError;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;
use UnexpectedValueException;

/**
 * Перевод чужих исключений (фреймворк, Symfony, SPL) в наш ErrorPayload.
 *
 * Последний резолвер в цепочке: его ветвь `RuntimeException` — подстраховка-предок,
 * поэтому всё, у чего есть свой смысл, должно опознаваться раньше.
 *
 * Раньше это были 13 замыканий в config/errors.php. Замыкания в конфиге нельзя
 * покрыть юнит-тестом по отдельности, они невидимы статическому анализатору, и
 * порядок ключей массива молча решал, какое из них сработает: `RuntimeException`
 * стоял раньше `UnexpectedValueException` и затенял его по is_a(), из-за чего
 * UnexpectedValueException отдавал 500 вместо своих 400.
 *
 * Здесь порядок ветвей виден глазами, и наследники обязаны идти ПЕРЕД предками.
 * Свои доменные исключения тут не обрабатываются: они реализуют
 * {@see ErrorConvertible} и опознаются раньше.
 */
final class FrameworkErrorResolver implements ResolvesError
{
    public function __construct(
        private readonly ErrorFactory $factory,
    ) {}

    public function resolve(Throwable $exception, ?Request $request = null): ?ErrorResolution
    {
        // Своё исключение описывает себя само и опознаётся раньше в цепочке.
        // Проверка дублируется здесь как страховка: ветвь RuntimeException ниже —
        // предок почти всех доменных исключений, и без этого условия резолвер молча
        // превратил бы осмысленный 409 в 500, если бы его позвали напрямую.
        if ($exception instanceof ErrorConvertible) {
            return null;
        }

        // Payload и отчёт для лога решаются ОДНИМ списком ветвей, а не двумя
        // параллельными. Два списка разъезжаются: пока диагностика «Unwrapped» жила
        // отдельной таблицей instanceof, она накрывала всё, унаследованное от
        // RuntimeException, — сначала 24 доменных исключения из 30, а после переноса
        // сюда ещё и штатный Symfony-404, потому что HttpException тоже RuntimeException.
        return match (true) {
            $exception instanceof ValidationException => new ErrorResolution($this->validation($exception)),
            $exception instanceof AuthenticationException => new ErrorResolution($this->unauthenticated($request)),
            $exception instanceof AuthorizationException => new ErrorResolution($this->forbidden($exception->getMessage())),
            $exception instanceof AccessDeniedHttpException => new ErrorResolution($this->forbidden($exception->getMessage())),
            $exception instanceof NotFoundHttpException => new ErrorResolution($this->notFound($exception->getMessage())),
            $exception instanceof QueryException => $this->query($exception),
            $exception instanceof ServiceUnavailableHttpException => new ErrorResolution(
                $this->serviceUnavailable($exception),
                new ErrorReport(level: 'error', message: 'Service unavailable during API request'),
            ),
            $exception instanceof PostTooLargeException => new ErrorResolution($this->postTooLarge()),
            $exception instanceof ThrottleRequestsException => new ErrorResolution($this->tooManyRequests($exception)),

            // Подстраховка под необёрнутые SPL-исключения. Наследник ПЕРЕД предком:
            // UnexpectedValueException extends RuntimeException. Отчёт помечает
            // происхождение, чтобы такие места находились и заворачивались в доменный тип.
            $exception instanceof UnexpectedValueException => $this->unwrapped(
                $exception,
                'UnexpectedValueException',
                'warning',
                $this->badRequest('Invalid value provided', 'UnexpectedValueException'),
            ),
            $exception instanceof InvalidArgumentException => $this->unwrapped(
                $exception,
                'InvalidArgumentException',
                'warning',
                $this->badRequest($exception->getMessage(), 'InvalidArgumentException'),
            ),
            $exception instanceof RuntimeException => $this->unwrapped(
                $exception,
                'RuntimeException',
                'error',
                $this->factory->for(ErrorCode::INTERNAL_SERVER_ERROR)->build(),
            ),

            default => null,
        };
    }

    private function unwrapped(Throwable $exception, string $type, string $level, ErrorPayload $payload): ErrorResolution
    {
        return new ErrorResolution($payload, new ErrorReport(
            level: $level,
            message: sprintf('Unwrapped %s in API request', $type),
            context: [
                'reason' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ],
        ));
    }

    /**
     * detail — стабильный summary, специфика полей уезжает в meta.errors (и оттуда
     * поднимается в ключ errors при рендере). Раньше здесь бралось первое сообщение
     * валидатора, а ApiFormRequest на том же классе ошибок отдавал фиксированную
     * строку: клиент получал разный контракт в зависимости от того, от какого
     * базового FormRequest отнаследовался конкретный запрос. Значение совпадает с
     * дефолтом ApiFormRequest::validationErrorDetail(); отдельные запросы
     * переопределяют его намеренно, под свой эндпоинт.
     */
    private function validation(ValidationException $exception): ErrorPayload
    {
        return $this->factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail('Validation failed.')
            ->meta(['errors' => $exception->errors()])
            ->build();
    }

    private function unauthenticated(?Request $request): ErrorPayload
    {
        return $this->factory->for(ErrorCode::UNAUTHORIZED)
            ->meta([
                'reason' => 'missing_token',
                'message' => 'Authentication is required to access this resource.',
            ])
            ->build();
    }

    private function forbidden(string $detail): ErrorPayload
    {
        $builder = $this->factory->for(ErrorCode::FORBIDDEN);

        return trim($detail) === '' ? $builder->build() : $builder->detail($detail)->build();
    }

    private function notFound(string $detail): ErrorPayload
    {
        $builder = $this->factory->for(ErrorCode::NOT_FOUND);

        return trim($detail) === '' ? $builder->build() : $builder->detail($detail)->build();
    }

    /**
     * Доменную ошибку, брошенную внутри транзакции, Laravel оборачивает в
     * QueryException. Разворачиваем, иначе осмысленный 409 стал бы 500.
     */
    private function query(QueryException $exception): ErrorResolution
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof HttpErrorException) {
            // Не сбой БД: SQL к нему отношения не имеет, а уровень выведется из статуса.
            return new ErrorResolution($previous->payload());
        }

        return new ErrorResolution(
            $this->factory->for(ErrorCode::INTERNAL_SERVER_ERROR)->build(),
            new ErrorReport(
                level: 'error',
                message: 'Database error during API request',
                context: [
                    'sql' => $exception->getSql(),
                    'bindings' => $exception->getBindings(),
                ],
            ),
        );
    }

    private function serviceUnavailable(ServiceUnavailableHttpException $exception): ErrorPayload
    {
        $builder = $this->factory->for(ErrorCode::SERVICE_UNAVAILABLE);

        $detail = $exception->getMessage();
        if (trim($detail) !== '') {
            $builder = $builder->detail($detail);
        }

        $retryAfter = $exception->getHeaders()['Retry-After'] ?? null;
        if ($retryAfter !== null) {
            $builder = $builder->addMeta('retry_after', $retryAfter);
        }

        return $builder->build();
    }

    private function postTooLarge(): ErrorPayload
    {
        $postMaxSize = ini_get('post_max_size') ?: 'unknown';
        $uploadMaxFilesize = ini_get('upload_max_filesize') ?: 'unknown';

        return $this->factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail('The uploaded file exceeds the maximum allowed size.')
            ->meta([
                'max_size' => $postMaxSize !== 'unknown' ? $postMaxSize : $uploadMaxFilesize,
                'post_max_size' => $postMaxSize,
                'upload_max_filesize' => $uploadMaxFilesize,
                'error_type' => 'post_too_large',
            ])
            ->build();
    }

    private function tooManyRequests(ThrottleRequestsException $exception): ErrorPayload
    {
        $builder = $this->factory->for(ErrorCode::TOO_MANY_REQUESTS);
        $retryAfter = $exception->getHeaders()['Retry-After'] ?? null;

        if (is_numeric($retryAfter)) {
            $builder = $builder->addMeta('retry_after', (int) $retryAfter);
        }

        return $builder->build();
    }

    private function badRequest(string $detail, string $exceptionType): ErrorPayload
    {
        $builder = $this->factory->for(ErrorCode::BAD_REQUEST)
            ->meta(['exception_type' => $exceptionType]);

        return trim($detail) === '' ? $builder->build() : $builder->detail($detail)->build();
    }
}
