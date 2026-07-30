<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

use Illuminate\Http\Request;
use Throwable;

/**
 * Единственная точка превращения исключения в ErrorPayload.
 *
 * Схема одна и видна целиком: спросить резолверы по порядку, первый опознавший
 * выигрывает, не опознал никто — 500. На любом пути ровно один вызов репортера.
 *
 * Порядок резолверов задаёт тот, кто собирает ядро (сервис-провайдер приложения), и
 * он важен: последний в цепочке — FrameworkErrorResolver с подстраховочной ветвью
 * `RuntimeException`, предком почти всего. Провайдер здесь намеренно не назван даже
 * комментарием: Support — нижний слой, вверх он не смотрит, в том числе ссылками.
 *
 * Прежде ядро знало только про две вещи — ErrorConvertible и фреймворковый маппер, —
 * а конвертеры, которые нельзя было захардкодить в Support (ошибки SDK расширений,
 * сбои пайплайна), оставались ветвлениями в HostBootstrap, каждое со своим вызовом
 * репортера и своей раздачей заголовков. До этого конвертеры регистрировались
 * замыканиями в config/errors.php: реестр был, но нетестируемый и с приоритетом по
 * порядку ключей массива.
 */
final class ErrorKernel
{
    /**
     * @var list<ResolvesError>
     */
    private readonly array $resolvers;

    public function __construct(
        private readonly ErrorFactory $factory,
        private readonly ErrorReportPolicy $policy,
        private readonly ErrorReporter $reporter,
        ResolvesError ...$resolvers,
    ) {
        $this->resolvers = $resolvers;
    }

    public function resolve(Throwable $throwable, ?Request $request = null): ErrorPayload
    {
        foreach ($this->resolvers as $resolver) {
            $resolution = $resolver->resolve($throwable, $request);

            if (! $resolution instanceof ErrorResolution) {
                continue;
            }

            return $this->reported(
                $throwable,
                $resolution->payload,
                $resolution->report ?? $this->policy->for($throwable, $resolution->payload),
            );
        }

        $payload = $this->factory->for(ErrorCode::INTERNAL_SERVER_ERROR)->build();

        return $this->reported($throwable, $payload, $this->policy->forUnhandled($throwable, $payload));
    }

    private function reported(Throwable $throwable, ErrorPayload $payload, ErrorReport $report): ErrorPayload
    {
        $this->reporter->report($throwable, $payload, $report);

        return $payload;
    }
}
