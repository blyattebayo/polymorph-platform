<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors\Resolvers;

use Illuminate\Http\Request;
use Polymorph\Platform\PipelineCore\Runtime\PipelineDomainFailureException;
use Polymorph\Platform\Support\Errors\ErrorResolution;
use Polymorph\Platform\Support\Errors\PipelineDomainFailureHttpMapper;
use Polymorph\Platform\Support\Errors\ResolvesError;
use Throwable;

/**
 * Доменный сбой пайплайна: разворачиваем в payload через штатный маппер стадий.
 *
 * Раньше это разворачивание жило отдельной ветвью HTTP bootstrap и подменяло
 * там `$e`, из-за чего дальше по обработчику ехало уже другое исключение, а в лог
 * попадал не тот тип, который на самом деле бросили.
 */
final class PipelineFailureResolver implements ResolvesError
{
    public function __construct(
        private readonly PipelineDomainFailureHttpMapper $mapper,
    ) {}

    public function resolve(Throwable $exception, ?Request $request = null): ?ErrorResolution
    {
        if (! $exception instanceof PipelineDomainFailureException) {
            return null;
        }

        return new ErrorResolution($this->mapper->map($exception)->payload());
    }
}
