<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Access;

use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CapabilityDefinition;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/**
 * Компактное объявление capability одного ресурса для Access-манифестов:
 *
 *     CapabilitySet::for(self::RESOURCE)
 *         ->read('Read media')->write('Write media')->delete('Delete media')
 *         ->all()
 *
 * вместо трёх `new CapabilityDefinition(...)` с повторением ресурса и констант.
 */
final class CapabilitySet
{
    /** @var list<CapabilityDefinition> */
    private array $definitions = [];

    private function __construct(private readonly string $resource) {}

    public static function for(string $resource): self
    {
        return new self($resource);
    }

    public function read(string $label): self
    {
        return $this->add(CapabilityCatalog::ACTION_READ, $label);
    }

    public function write(string $label): self
    {
        return $this->add(CapabilityCatalog::ACTION_WRITE, $label);
    }

    public function delete(string $label): self
    {
        return $this->add(CapabilityCatalog::ACTION_DELETE, $label);
    }

    public function manage(string $label): self
    {
        return $this->add(CapabilityCatalog::ACTION_MANAGE, $label);
    }

    public function access(string $label): self
    {
        return $this->add(CapabilityCatalog::ACTION_ACCESS, $label);
    }

    /** @return list<CapabilityDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    private function add(string $action, string $label): self
    {
        $this->definitions[] = new CapabilityDefinition($this->resource, $action, $label);

        return $this;
    }
}
