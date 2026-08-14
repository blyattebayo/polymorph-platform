<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

use Closure;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformBadRequest;
use Polymorph\Platform\Domain\DataPlatform\Errors\DataPlatformInvariantViolation;
use Polymorph\Platform\Domain\DataPlatform\Fields\Handlers\AbstractFieldTypeHandler;

final class FieldTypeRegistry
{
    /** @var array<string, FieldTypeHandler> */
    private array $handlers = [];

    /** @var array<int,true> */
    private array $loadedSdkSources = [];

    /**
     * @param  iterable<AbstractFieldTypeHandler>  $handlers
     * @param  Closure():iterable<SdkFieldTypeHandlerAdapter>|null  $sdkHandlers
     */
    public function __construct(iterable $handlers = [], private readonly ?Closure $sdkHandlers = null)
    {
        foreach ($handlers as $handler) {
            if (! $handler instanceof AbstractFieldTypeHandler) {
                throw DataPlatformInvariantViolation::because(
                    'untrusted_field_type_registration',
                    'Core field type registry accepts built-in handlers only.',
                );
            }
            $this->add($handler);
        }
    }

    private function add(FieldTypeHandler $handler): void
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

    public function get(FieldType|string $type): FieldTypeHandler
    {
        $this->synchronizeSdkHandlers();
        $type = $type instanceof FieldType ? $type->value : $type;

        return $this->handlers[$type]
            ?? throw DataPlatformBadRequest::because(
                'unsupported_field_type',
                "Unsupported field type '{$type}'.",
                ['field_type' => $type],
            );
    }

    /** @internal Contract-test introspection only. @return list<string> */
    public function types(): array
    {
        $this->synchronizeSdkHandlers();
        $types = array_keys($this->handlers);
        sort($types);

        return $types;
    }

    private function synchronizeSdkHandlers(): void
    {
        if ($this->sdkHandlers === null) {
            return;
        }
        foreach (($this->sdkHandlers)() as $handler) {
            if (! $handler instanceof SdkFieldTypeHandlerAdapter) {
                throw DataPlatformInvariantViolation::because(
                    'untrusted_field_type_registration',
                    'Plugin field types must pass through the SDK adapter.',
                );
            }
            $sourceId = $handler->sourceId();
            if (isset($this->loadedSdkSources[$sourceId])) {
                continue;
            }
            $this->add($handler);
            $this->loadedSdkSources[$sourceId] = true;
        }
    }
}
