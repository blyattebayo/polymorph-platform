<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Validation;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class DataValidationException extends \DomainException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    /** @param list<ValidationIssue> $issues */
    public function __construct(private readonly array $issues)
    {
        parent::__construct($issues[0]->message ?? 'Data validation failed.');
    }

    /** @return list<ValidationIssue> */
    public function issues(): array
    {
        return $this->issues;
    }

    /** @return list<array<string, mixed>> */
    public function errors(): array
    {
        return array_map(static fn (ValidationIssue $issue): array => $issue->toArray(), $this->issues);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::VALIDATION_ERROR;
    }

    public function errorMeta(): array
    {
        return ['reason' => 'data_validation_failed', 'issues' => $this->errors()];
    }

    public static function one(
        string $code,
        string $message,
        string $fieldPath,
        ?string $occurrence = null,
        array $meta = [],
    ): self {
        return new self([new ValidationIssue($code, $message, $fieldPath, $occurrence, $meta)]);
    }
}
