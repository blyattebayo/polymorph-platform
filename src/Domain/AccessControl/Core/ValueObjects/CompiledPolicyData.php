<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\ValueObjects;

use DateTimeInterface;

final readonly class CompiledPolicyData
{
    public function __construct(
        public string $resourcePattern,
        public string $action,
        public string $effect,
        public int $priority,
        public ?int $policyId,
        public DateTimeInterface $compiledAt,
    ) {
    }

    /**
     * @return array{subject:string,resource_pattern:string,action:string,effect:string,priority:int,policy_id:?int,compiled_at:DateTimeInterface}
     */
    public function toPersistence(string $subject): array
    {
        return [
            'subject' => $subject,
            'resource_pattern' => $this->resourcePattern,
            'action' => $this->action,
            'effect' => $this->effect,
            'priority' => $this->priority,
            'policy_id' => $this->policyId,
            'compiled_at' => $this->compiledAt,
        ];
    }
}