<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Polymorph\Platform\Support\Errors\ErrorKernel;
use Polymorph\Platform\Support\Errors\ErrorResponseFactory;
use Polymorph\Platform\Support\Errors\ResolvesError;
use Polymorph\Sdk\Errors\ExtensionError;
use Throwable;

/**
 * Обработчик исключений HTTP-слоя: одно решение «наш ли это запрос», затем общий путь.
 *
 * Живёт здесь, а не в Bootstrap: hook фреймворка должен только делегировать, иначе в
 * нём заводится логика, которую нельзя позвать из теста. И не в Support: предикат
 * ниже знает про ошибки SDK, а Support — нижний слой.
 *
 * До этого в hook'е лежало пять ветвей: свой конвертер и свой репортер для ошибок
 * расширений, разворачивание сбоя пайплайна с подменой `$e`, отдельная ветвь для
 * HttpErrorException и раздача заголовков по типу исключения. Всё это разъехалось
 * потому, что у ErrorKernel не было шва для регистрации конвертеров — теперь есть
 * {@see ResolvesError}, и ветвление свелось к одному
 * вопросу, который к переводу исключений отношения не имеет.
 */
final class ApiErrorHandler
{
    public function __construct(
        private readonly ErrorKernel $kernel,
    ) {}

    /**
     * null — «не наш ответ», пусть фреймворк рендерит своё (HTML-страницу ошибки).
     */
    public function handle(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! self::handles($exception, $request)) {
            return null;
        }

        return ErrorResponseFactory::make($this->kernel->resolve($exception, $request));
    }

    /**
     * Ошибки расширений — всегда ProblemJson, даже вне `api/*`: SDK не знает про
     * маршруты хоста, и расширение не должно получать HTML-страницу вместо ответа.
     */
    public static function handles(Throwable $exception, Request $request): bool
    {
        return $exception instanceof ExtensionError
            || $request->expectsJson()
            || $request->is('api/*');
    }
}
