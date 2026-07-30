<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

/**
 * Исключение при попытке создать схему с дублирующимся кодом.
 *
 * Свойство названо $schemaCode, а НЕ $code: у Exception уже есть protected $code,
 * и промоушен `private readonly string $code` делал класс незагружаемым
 * («Cannot redeclare non-readonly property Exception::$code as readonly») —
 * то есть любое обращение к этому исключению падало фаталом вместо конфликта.
 */
class DuplicateSchemaCodeException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly string $schemaCode,
    ) {
        parent::__construct(
            "Schema with code '{$schemaCode}' already exists. Use a different unique code."
        );
    }

    public static function create(string $schemaCode): self
    {
        return new self($schemaCode);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::CONFLICT;
    }

    public function errorMeta(): array
    {
        // Ключ schema_code, как в DuplicateFieldPathException: один и тот же смысл
        // должен читаться одинаково в обоих конфликтах схемы.
        return [
            'schema_code' => $this->schemaCode,
        ];
    }
}
