<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Runtime;

use Polymorph\Platform\Support\Errors\ErrorCode;

enum Stage: string
{
    case VALIDATION = 'validation';
    case LOAD_AND_LOCK = 'load_and_lock';
    case WRITE = 'write';
    case DERIVED_WRITE = 'derived_write';

    public static function fromName(string $name): self
    {
        return self::from($name);
    }

    /**
     * Канонический порядок выполнения стадий.
     * Единственное место, определяющее порядок — не дублируется в PipelineDefinition.
     *
     * @return Stage[]
     */
    public static function orderedList(): array
    {
        return [
            self::VALIDATION,
            self::LOAD_AND_LOCK,
            self::WRITE,
            self::DERIVED_WRITE,
        ];
    }

    public function isBlocking(): bool
    {
        return match ($this) {
            self::VALIDATION,
            self::LOAD_AND_LOCK,
            self::WRITE => true,
            self::DERIVED_WRITE => false,
        };
    }

    public function defaultErrorCode(): ErrorCode
    {
        return match ($this) {
            self::VALIDATION => ErrorCode::VALIDATION_ERROR,
            self::LOAD_AND_LOCK => ErrorCode::CONFLICT,
            self::WRITE,
            self::DERIVED_WRITE => ErrorCode::INTERNAL_SERVER_ERROR,
        };
    }
}
