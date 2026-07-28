<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorResponseFactory;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Контроллер для обработки 404 ошибок (fallback маршрут).
 *
 * Обрабатывает все запросы, которые не совпали с другими маршрутами.
 * Логирует информацию о запросе и возвращает соответствующий ответ
 * (JSON для API, HTML для веб-запросов).
 */
final class FallbackController extends Controller
{
    public function __construct(
        private readonly ErrorFactory $errorFactory,
        private readonly AppLogger $logger,
    ) {}

    /**
     * Обработать fallback запрос (404).
     *
     * Логирует информацию о запросе и возвращает ответ в зависимости
     * от типа запроса (JSON для API, HTML для веб).
     *
     * Намеренно принимает базовый Request, а не FormRequest: fallback ловит
     * произвольный мусорный трафик, включая битые тела запросов, и не должен
     * запускать на нём машинерию валидации до входа в контроллер.
     *
     * @return Response|View|JsonResponse Ответ
     */
    public function __invoke(Request $request): Response|View|JsonResponse
    {
        $this->logger->event('routing.fallback.not_found', [
            'path' => $request->path(),
            'method' => $request->method(),
            'referer' => $request->header('referer'),
            'accept' => $request->header('accept'),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);

        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            $payload = $this->errorFactory->for(ErrorCode::NOT_FOUND)
                ->detail('The requested resource was not found.')
                ->meta(['path' => $request->path()])
                ->build();

            return ErrorResponseFactory::make($payload);
        }

        return response()->view('errors.404', [
            'path' => $request->path(),
        ], Response::HTTP_NOT_FOUND);
    }
}
