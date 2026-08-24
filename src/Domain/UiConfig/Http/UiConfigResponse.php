<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Http;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\UiConfig\Core\Models\UiConfig;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;

final class UiConfigResponse
{
    /**
     * Домен в ответе обязателен: запрос личной конфигурации возвращает общую,
     * если личной ещё нет, и от того, какая приехала, зависит ревизия следующей
     * записи — у ещё не созданной личной строки она нулевая.
     */
    public static function make(?UiConfig $config): JsonResponse
    {
        if ($config === null) {
            return AdminResponse::json(['data' => null, 'version' => null, 'revision' => 0, 'domain' => null]);
        }

        return AdminResponse::json([
            'data' => $config->value(),
            'version' => $config->version,
            'revision' => $config->revision,
            'domain' => $config->domain->value,
        ]);
    }
}
