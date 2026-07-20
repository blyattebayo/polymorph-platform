<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Http\Resources;

use Polymorph\Platform\Http\Resources\Admin\AdminJsonResource;

class TableConfigResource extends AdminJsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'table_key' => $this->table_key,
            'scope' => $this->scope,
            'user_id' => $this->user_id,
            'schema_version' => $this->schema_version,
            'config_json' => $this->config_json ?? new \stdClass(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
