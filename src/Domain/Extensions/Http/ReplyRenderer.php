<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Http;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Support\Errors\ErrorCode as CoreErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use Polymorph\Sdk\Errors\ErrorCode as SdkErrorCode;
use Polymorph\Sdk\Errors\ExtensionError;
use Polymorph\Sdk\Http\Reply;

/**
 * Хост-рендерер нейтрального SDK {@see Reply} и {@see ExtensionError} в HTTP-ответ
 * ядра. Расширение возвращает Reply / бросает ExtensionError; ХОСТ превращает их
 * в JsonResponse (ошибки — в ProblemJson-формат ядра через ErrorFactory).
 *
 * Контроллеры расширения оборачиваются загрузчиком маршрутов так, чтобы их
 * возвращаемый Reply проходил через render() (см. wiring в ADR/прогресс-доке).
 */
final class ReplyRenderer
{
    /**
     * SDK ErrorCode → ErrorCode ядра (для единого ProblemJson).
     * ПОЛНОТА охраняется ReplyRendererErrorMapGuardTest — каждый SDK-код обязан
     * иметь запись, иначе toPayload() тихо свалится в INTERNAL_SERVER_ERROR.
     */
    private const ERROR_MAP = [
        SdkErrorCode::BAD_REQUEST->value => CoreErrorCode::BAD_REQUEST,
        SdkErrorCode::VALIDATION_ERROR->value => CoreErrorCode::VALIDATION_ERROR,
        SdkErrorCode::UNAUTHORIZED->value => CoreErrorCode::UNAUTHORIZED,
        SdkErrorCode::FORBIDDEN->value => CoreErrorCode::FORBIDDEN,
        SdkErrorCode::NOT_FOUND->value => CoreErrorCode::NOT_FOUND,
        SdkErrorCode::CONFLICT->value => CoreErrorCode::CONFLICT,
        SdkErrorCode::TOO_MANY_REQUESTS->value => CoreErrorCode::TOO_MANY_REQUESTS,
        SdkErrorCode::SERVICE_UNAVAILABLE->value => CoreErrorCode::SERVICE_UNAVAILABLE,
        SdkErrorCode::INTERNAL_ERROR->value => CoreErrorCode::INTERNAL_SERVER_ERROR,
    ];

    public function __construct(private readonly ErrorFactory $errors) {}

    public function render(Reply $reply): JsonResponse
    {
        return new JsonResponse($reply->body, $reply->status, $reply->headers);
    }

    /**
     * ExtensionError → ProblemJson-ответ ядра. Статус берётся из SDK-кода, тело —
     * через ErrorFactory (единый формат с остальными ошибками платформы).
     */
    public function renderError(ExtensionError $error): JsonResponse
    {
        $payload = $this->toPayload($error);

        return new JsonResponse($payload->toArray(), $payload->status);
    }

    /**
     * ExtensionError → ProblemJson-payload ядра (для единого reporter/response
     * pipeline в обработчике исключений).
     */
    public function toPayload(ExtensionError $error): ErrorPayload
    {
        $coreCode = self::ERROR_MAP[$error->errorCode->value] ?? CoreErrorCode::INTERNAL_SERVER_ERROR;

        $builder = $this->errors->for($coreCode)->status($error->httpStatus());
        if ($error->detail !== null) {
            $builder = $builder->detail($error->detail);
        }
        if ($error->meta !== []) {
            $builder = $builder->meta($error->meta);
        }

        return $builder->build();
    }
}
