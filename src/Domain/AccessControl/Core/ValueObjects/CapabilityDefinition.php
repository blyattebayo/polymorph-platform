<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\ValueObjects;

use InvalidArgumentException;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final readonly class CapabilityDefinition
{
    public function __construct(
        public string $resource,
        public string $action,
        public string $label,
    ) {
        if (trim($this->resource) === '') {
            throw new InvalidArgumentException('Capability resource must not be empty.');
        }

        if (! in_array($this->action, CapabilityCatalog::policyActions(), true)) {
            throw new InvalidArgumentException('Capability action is not supported.');
        }

        if (trim($this->label) === '') {
            throw new InvalidArgumentException('Capability label must not be empty.');
        }
    }

    public function key(): string
    {
        return CapabilityCatalog::capabilityKey($this->resource, $this->action);
    }

    /**
     * @return array{resource:string,action:string,label:string}
     */
    public function toArray(): array
    {
        return [
            'resource' => $this->resource,
            'action' => $this->action,
            'label' => $this->label,
        ];
    }
}
