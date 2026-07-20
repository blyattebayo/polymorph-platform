<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Http\Resources;

use Polymorph\Platform\Http\Resources\Admin\AdminJsonResource;

/**
 * API Resource для RecordDefinition в админ-панели.
 *
 * Форматирует тип записи для ответа API.
 *
 * @package Polymorph\Platform\Domain\RecordDefinitions\Http\Resources
 */
class RecordDefinitionResource extends AdminJsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями типа записи
     */
    public function toArray($request): array
    {
        $schema = $this->schema;
        $schemaCode = is_string($schema?->code) && $schema->code !== '' ? $schema->code : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_template' => $this->display_template,
            'schema_id' => $this->schema_id,
            'schema_code' => $schemaCode,
            'owner' => $this->ownership->toOwner()->toApiArray(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}

