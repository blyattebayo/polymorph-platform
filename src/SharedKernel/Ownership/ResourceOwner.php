<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Ownership;

final readonly class ResourceOwner
{
    private function __construct(
        public OwnerType $type,
        public ?string $id = null,
    ) {
        if ($this->type === OwnerType::PLUGIN && ($this->id === null || trim($this->id) === '')) {
            throw new \InvalidArgumentException('Plugin owner requires owner id.');
        }

        if ($this->type !== OwnerType::PLUGIN && $this->id !== null) {
            throw new \InvalidArgumentException('Only plugin owners may have owner id.');
        }
    }

    public static function system(): self
    {
        return new self(OwnerType::SYSTEM);
    }

    public static function platform(): self
    {
        return new self(OwnerType::PLATFORM);
    }

    public static function plugin(string $pluginId): self
    {
        return new self(OwnerType::PLUGIN, trim($pluginId));
    }

    /**
     * @return array{owner_type: string, owner_id: string|null}
     */
    public function toArray(): array
    {
        return [
            'owner_type' => $this->type->value,
            'owner_id' => $this->id,
        ];
    }

    /**
     * @return array{type: string, id: string|null}
     */
    public function toApiArray(): array
    {
        return [
            'type' => $this->type->value,
            'id' => $this->id,
        ];
    }
}
