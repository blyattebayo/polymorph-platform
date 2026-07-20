<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Runtime;

abstract class AbstractStep implements Step
{
    /**
     * @param class-string<PipelineContext> $contextClass
     */
    public function __construct(
        private readonly string $contextClass,
    ) {
    }

    public function contextClass(): string
    {
        return $this->contextClass;
    }

    public function shouldRun(PipelineContext $context): bool
    {
        return true;
    }

    public function requires(): array
    {
        return [];
    }

    public function produces(): array
    {
        return [];
    }
}
