<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\ValueObjects;

use InvalidArgumentException;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final readonly class PolicyData
{
    public function __construct(
        public string $resourcePattern,
        public string $action,
        public Effect $effect,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromInput(array $payload): self
    {
        $resourcePattern = trim((string) ($payload['resource_pattern'] ?? ''));
        $action = strtolower(trim((string) ($payload['action'] ?? '')));
        $effectValue = strtolower(trim((string) ($payload['effect'] ?? '')));

        if ($resourcePattern === '') {
            throw new InvalidArgumentException('resource_pattern is required.');
        }

        if ($action === '') {
            throw new InvalidArgumentException('action is required.');
        }

        if (! in_array($action, CapabilityCatalog::policyActions(), true)) {
            throw new InvalidArgumentException('action is not registered.');
        }

        $effect = Effect::tryFrom($effectValue);
        if (! $effect instanceof Effect) {
            throw new InvalidArgumentException('effect must be allow or deny.');
        }

        return new self(
            resourcePattern: $resourcePattern,
            action: $action,
            effect: $effect,
        );
    }

    /**
     * @return array{resource_pattern:string,action:string,effect:string}
     */
    public function toPersistence(): array
    {
        return [
            'resource_pattern' => $this->resourcePattern,
            'action' => $this->action,
            'effect' => $this->effect->value,
        ];
    }
}
