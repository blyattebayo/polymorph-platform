<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;

final class FieldTypeRegistry
{
    /** @var array<string, FieldTypeHandler> */
    private array $handlers = [];

    /** @param iterable<FieldTypeHandler> $handlers */
    public function __construct(iterable $handlers = [])
    {
        foreach ($handlers as $handler) {
            $this->register($handler);
        }
    }

    public function register(FieldTypeHandler $handler): void
    {
        $type = trim($handler->type());
        if ($type === '') {
            throw DataPlatformBadRequest::because('empty_field_type', 'Field type name cannot be empty.');
        }
        if (isset($this->handlers[$type])) {
            throw DataPlatformInvariantViolation::because(
                'duplicate_field_type_registration',
                "Field type '{$type}' is already registered.",
                ['field_type' => $type],
            );
        }

        $this->handlers[$type] = $handler;
    }

    public function get(string $type): FieldTypeHandler
    {
        return $this->handlers[$type]
            ?? throw DataPlatformBadRequest::because(
                'unsupported_field_type',
                "Unsupported field type '{$type}'.",
                ['field_type' => $type],
            );
    }

    /** @return list<string> */
    public function types(): array
    {
        $types = array_keys($this->handlers);
        sort($types);

        return $types;
    }
}
