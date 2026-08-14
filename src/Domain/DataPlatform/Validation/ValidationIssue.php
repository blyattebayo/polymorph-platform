<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Validation;

final readonly class ValidationIssue
{
    public function __construct(
        public string $code,
        public string $message,
        public string $fieldPath,
        public ?string $occurrence = null,
        public array $meta = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'field_path' => $this->fieldPath,
            'occurrence' => $this->occurrence,
            'meta' => $this->meta,
        ];
    }
}
