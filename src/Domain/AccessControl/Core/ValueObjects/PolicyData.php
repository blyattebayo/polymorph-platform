<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\ValueObjects;

use InvalidArgumentException;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\ActionRegistry;

final readonly class PolicyData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $resourcePattern,
        public string $action,
        public string $effect,
        public int $priority,
        public bool $isActive,
        public ?array $metadata,
        public string $matcherHash,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromInput(array $payload, ?ActionRegistry $actionRegistry = null): self
    {
        $resourcePattern = trim((string) ($payload['resource_pattern'] ?? ''));
        $action = strtolower(trim((string) ($payload['action'] ?? '')));
        $effect = strtolower(trim((string) ($payload['effect'] ?? '')));
        $priority = (int) ($payload['priority'] ?? 100);
        $isActive = (bool) ($payload['is_active'] ?? true);
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : null;

        if ($resourcePattern === '') {
            throw new InvalidArgumentException('resource_pattern is required.');
        }

        if ($action === '') {
            throw new InvalidArgumentException('action is required.');
        }

        if ($actionRegistry !== null && ! $actionRegistry->allows($action)) {
            throw new InvalidArgumentException('action is not registered.');
        }

        if (! in_array($effect, [Effect::ALLOW->value, Effect::DENY->value], true)) {
            throw new InvalidArgumentException('effect must be allow or deny.');
        }

        if ($priority <= 0) {
            throw new InvalidArgumentException('priority must be positive integer.');
        }

        return new self(
            resourcePattern: $resourcePattern,
            action: $action,
            effect: $effect,
            priority: $priority,
            isActive: $isActive,
            metadata: $metadata,
            matcherHash: hash('sha256', json_encode([
                'resource_pattern' => $resourcePattern,
                'action' => $action,
                'effect' => $effect,
                'priority' => $priority,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        );
    }

    /**
     * @return array{resource_pattern:string,action:string,effect:string,priority:int,is_active:bool,metadata:?array<string,mixed>,matcher_hash:string}
     */
    public function toPersistence(): array
    {
        return [
            'resource_pattern' => $this->resourcePattern,
            'action' => $this->action,
            'effect' => $this->effect,
            'priority' => $this->priority,
            'is_active' => $this->isActive,
            'metadata' => $this->metadata,
            'matcher_hash' => $this->matcherHash,
        ];
    }

    /**
     * @return array{resource_pattern:string,action:string,effect:string,priority:int}
     */
    public function toAuditPayload(): array
    {
        return [
            'resource_pattern' => $this->resourcePattern,
            'action' => $this->action,
            'effect' => $this->effect,
            'priority' => $this->priority,
        ];
    }
}
