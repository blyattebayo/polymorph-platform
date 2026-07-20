<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Runtime;

use Polymorph\Platform\Support\Errors\ErrorCode;
use Throwable;

class StepResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorMessage = null,
        public readonly ?ErrorCode $errorCode = null,
        public readonly ?string $errorTitle = null,
        public readonly array $metadata = [],
        public readonly ?Throwable $cause = null,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function success(array $metadata = []): self
    {
        return new self(true, null, null, null, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  Throwable|null  $cause  Исходное исключение, если шаг поймал его и
     *                                 конвертировал в доменный сбой — сохраняется для логов/трассировки,
     *                                 чтобы не терять тип и стек (HTTP-ответу отдаётся только errorCode).
     */
    public static function failure(
        string $error,
        array $metadata = [],
        ?ErrorCode $errorCode = null,
        ?string $errorTitle = null,
        ?Throwable $cause = null,
    ): self {
        return new self(false, $error, $errorCode, $errorTitle, $metadata, $cause);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            success: $this->success,
            errorMessage: $this->errorMessage,
            errorCode: $this->errorCode,
            errorTitle: $this->errorTitle,
            metadata: array_replace_recursive($this->metadata, $metadata),
            cause: $this->cause,
        );
    }
}
