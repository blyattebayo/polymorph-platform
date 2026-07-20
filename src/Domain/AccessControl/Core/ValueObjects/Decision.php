<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\ValueObjects;

final readonly class Decision
{
    /**
     * @param  list<int>  $matchedPolicyIds
     */
    public function __construct(
        private bool $allowed,
        private string $reason,
        private array $matchedPolicyIds = [],
    ) {}

    /**
     * @param  list<int>  $matchedPolicyIds
     */
    public static function allow(
        string $reason = 'explicit_allow',
        array $matchedPolicyIds = [],
    ): self {
        return new self(true, $reason, $matchedPolicyIds);
    }

    /**
     * @param  list<int>  $matchedPolicyIds
     */
    public static function deny(
        string $reason = 'explicit_deny',
        array $matchedPolicyIds = [],
    ): self {
        return new self(false, $reason, $matchedPolicyIds);
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * @return list<int>
     */
    public function matchedPolicyIds(): array
    {
        return $this->matchedPolicyIds;
    }

    /**
     * @return array{allowed:bool,reason:string,matched_policy_ids:list<int>}
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'matched_policy_ids' => $this->matchedPolicyIds,
        ];
    }
}
