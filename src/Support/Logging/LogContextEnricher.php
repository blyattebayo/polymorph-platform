<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Logging;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Polymorph\Platform\SharedKernel\Identity\CurrentActorResolver;

final class LogContextEnricher
{
    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function enrich(array $context): array
    {
        $request = $this->request();
        $automatic = array_filter([
            'request_id' => $this->requestId($request),
            'correlation_id' => $this->correlationId($request),
            'trace_id' => $context['trace_id'] ?? $this->traceId($request),
            'user_id' => $context['user_id'] ?? $this->userId(),
            'route' => $this->routeName($request),
            'action' => $this->routeAction($request),
            'plugin_id' => $context['plugin_id'] ?? $this->pluginId($request),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $context = array_merge($automatic, $context);

        return $this->withExceptionMetadata($context);
    }

    private function request(): ?Request
    {
        if (! $this->app->bound('request')) {
            return null;
        }

        $request = $this->app->make('request');

        return $request instanceof Request ? $request : null;
    }

    private function requestId(?Request $request): ?string
    {
        return $this->firstString($request, ['X-Request-ID', 'X-Request-Id', 'X-Correlation-ID', 'X-Correlation-Id'], [
            'request_id',
            'requestId',
            'request-id',
            'X-Request-ID',
        ]);
    }

    private function correlationId(?Request $request): ?string
    {
        return $this->firstString($request, ['X-Correlation-ID', 'X-Correlation-Id'], [
            'correlation_id',
            'correlationId',
        ]);
    }

    private function traceId(?Request $request): ?string
    {
        return $this->firstString($request, ['X-Trace-ID', 'X-Trace-Id'], [
            'trace_id',
            'traceId',
        ]);
    }

    private function userId(): int|string|null
    {
        try {
            return $this->app->make(CurrentActorResolver::class)->authIdentifier();
        } catch (\Throwable) {
            return null;
        }
    }

    private function routeName(?Request $request): ?string
    {
        $route = $request?->route();

        return $route instanceof Route ? $route->getName() : null;
    }

    private function routeAction(?Request $request): ?string
    {
        $route = $request?->route();
        if (! $route instanceof Route) {
            return null;
        }

        $action = $route->getActionName();

        return $action !== 'Closure' ? $action : null;
    }

    private function pluginId(?Request $request): ?string
    {
        $route = $request?->route();
        if (! $route instanceof Route) {
            return null;
        }

        $parameter = $route->parameter('plugin') ?? $route->parameter('plugin_id') ?? $route->parameter('pluginId');
        if (is_string($parameter) && $parameter !== '') {
            return $parameter;
        }

        $name = $route->getName();
        if (is_string($name) && preg_match('/(?:^|\.)plugins\.([a-z0-9_]+)/i', $name, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $attributes
     */
    private function firstString(?Request $request, array $headers, array $attributes): ?string
    {
        if ($request === null) {
            return null;
        }

        foreach ($headers as $header) {
            $value = $request->headers->get($header);
            if (is_array($value)) {
                $value = reset($value) ?: null;
            }
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        foreach ($attributes as $attribute) {
            $value = $request->attributes->get($attribute);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function withExceptionMetadata(array $context): array
    {
        $exception = $context['exception'] ?? null;
        if (! $exception instanceof \Throwable) {
            return $context;
        }

        // КРИТИЧНО: заменяем ЖИВОЙ объект исключения на ограниченное представление.
        // Иначе formatter (JsonFormatter) при сериализации уходит в объектный граф
        // трейса — а для исключений из scoped-плагинов он бывает гигантским/цикличным,
        // что валит запрос в OOM/«max call stack» ПРЯМО в Monolog и маскирует реальную
        // ошибку. Здесь: плоские метаданные + трейс как ОБРЕЗАННАЯ строка (без живых
        // объектов и args), размер записи детерминированно ограничен.
        $context['exception'] = [
            'class' => $exception::class,
            'message' => $this->truncate($exception->getMessage(), 2000),
            'code' => $exception->getCode(),
            'file' => $exception->getFile().':'.$exception->getLine(),
            'trace' => $this->truncate($exception->getTraceAsString(), 8000),
        ];

        return array_merge([
            'exception_class' => $exception::class,
            'exception_message' => $this->truncate($exception->getMessage(), 2000),
            'exception_code' => $exception->getCode(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
        ], $context);
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max).'…[truncated]' : $value;
    }
}
