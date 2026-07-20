<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Http\Resources;

use Polymorph\Platform\Http\Resources\Admin\AdminJsonResource;

/**
 * API Resource для EntryView в админ-панели.
 *
 * Форматирует конфигурацию формы для ответа API после upsert.
 *
 * @package Polymorph\Platform\Domain\EntryView\Http\Resources
 */
class EntryViewResource extends AdminJsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
    * Возвращает полную информацию о конфигурации с record_definition_id и schema_id.
     * Если config_json равен null, возвращается пустой объект {}.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями конфигурации
     */
    public function toArray($request): array
    {
        return [
            'record_definition_id' => $this->record_definition_id,
            'schema_id' => $this->schema_id,
            'config_json' => $this->config_json ?? new \stdClass(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
