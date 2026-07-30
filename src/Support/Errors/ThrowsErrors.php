<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

trait ThrowsErrors
{
    private ?ErrorFactory $localErrorFactory = null;

    protected function errorFactory(): ErrorFactory
    {
        // Единый общий ErrorFactory из контейнера — единый источник каталога ошибок,
        // а не пересборка из config в каждом классе.
        return $this->localErrorFactory ??= app(ErrorFactory::class);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function throwError(ErrorCode $code, ?string $detail = null, array $meta = []): never
    {
        $builder = $this->errorFactory()->for($code);

        if ($detail !== null) {
            $builder = $builder->detail($detail);
        }

        if ($meta !== []) {
            $builder = $builder->meta($meta);
        }

        throw new HttpErrorException($builder->build());
    }

    /**
     * Здесь был ещё throwErrorWithHeaders(): он собирал замыкание-донастройщик
     * ответа, чтобы проставить заголовки. Единственным его потребителем был отказ
     * аутентификации с `WWW-Authenticate` и `Pragma`, а эти два заголовка теперь
     * выводятся из статуса 401 в {@see ErrorResponseFactory} — одинаково для всех
     * путей, включая те, что раньше их теряли. Механизм стал не нужен.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function unauthorized(?string $detail = null, array $meta = []): never
    {
        $this->throwError(ErrorCode::UNAUTHORIZED, $detail, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function forbidden(?string $detail = null, array $meta = []): never
    {
        $this->throwError(ErrorCode::FORBIDDEN, $detail, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function internalError(string $detail, array $meta = []): never
    {
        $this->throwError(ErrorCode::INTERNAL_SERVER_ERROR, $detail, $meta);
    }
}
