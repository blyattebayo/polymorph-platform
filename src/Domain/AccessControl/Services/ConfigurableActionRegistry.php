<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use InvalidArgumentException;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\ActionDefinitionProvider;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\ActionRegistry;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class ConfigurableActionRegistry implements ActionRegistry
{
    /**
     * @var list<string>
     */
    private readonly array $actions;

    /**
     * @param  iterable<ActionDefinitionProvider>  $providers
     */
    public function __construct(iterable $providers = [])
    {
        $actions = CapabilityCatalog::policyActions();

        foreach ($providers as $provider) {
            foreach ($provider->actions() as $action) {
                $normalized = strtolower(trim($action));
                if ($normalized === '') {
                    continue;
                }

                $actions[] = $normalized;
            }
        }

        $this->actions = array_values(array_unique($actions));

        if ($this->actions === []) {
            throw new InvalidArgumentException('Action registry must contain at least one action.');
        }
    }

    public function allows(string $action): bool
    {
        return in_array(strtolower(trim($action)), $this->actions, true);
    }

    public function actions(): array
    {
        return $this->actions;
    }
}
