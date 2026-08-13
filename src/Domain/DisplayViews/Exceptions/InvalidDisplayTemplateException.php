<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DisplayViews\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\TemplateEngine\Core\Errors\LexerException;
use Polymorph\Platform\TemplateEngine\Core\Errors\ParserException;
use Polymorph\Platform\TemplateEngine\Core\Errors\ValidationException as TemplateValidationException;
use RuntimeException;
use Throwable;

final class InvalidDisplayTemplateException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly int $recordDefinitionId,
        private readonly string $reason,
        private readonly ?int $spanStart = null,
        private readonly ?int $spanEnd = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Display template is invalid.', 0, $previous);
    }

    public static function forRecordDefinition(int $recordDefinitionId, Throwable $cause): self
    {
        $reason = trim($cause->getMessage());

        return new self(
            $recordDefinitionId,
            $reason !== '' ? $reason : 'The template cannot be compiled.',
            match (true) {
                $cause instanceof LexerException => $cause->position,
                $cause instanceof ParserException => $cause->token?->position,
                $cause instanceof TemplateValidationException => $cause->spanStart,
                default => null,
            },
            match (true) {
                $cause instanceof LexerException => $cause->position + 1,
                $cause instanceof ParserException => $cause->token === null
                    ? null
                    : $cause->token->position + max(1, $cause->token->length),
                $cause instanceof TemplateValidationException => $cause->spanEnd,
                default => null,
            },
            $cause,
        );
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::VALIDATION_ERROR;
    }

    public function errorMeta(): array
    {
        $meta = [
            'errors' => [
                'display_template' => [$this->reason],
            ],
            'record_definition_id' => $this->recordDefinitionId,
        ];

        if ($this->spanStart !== null) {
            $meta['span_start'] = $this->spanStart;
        }
        if ($this->spanEnd !== null) {
            $meta['span_end'] = $this->spanEnd;
        }

        return $meta;
    }
}
