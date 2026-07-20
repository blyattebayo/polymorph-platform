<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Polymorph\Platform\Support\DateTime\Iso8601Formatter;

final class AuthSessionResource extends JsonResource
{
    /**
     * @param  object  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'created_at' => Iso8601Formatter::format($this->resource->created_at ?? null),
            'last_used_at' => Iso8601Formatter::format($this->resource->last_used_at ?? null),
            'expires_at' => Iso8601Formatter::format($this->resource->expires_at ?? null),
            'ip' => $this->resource->ip,
            'user_agent' => $this->resource->user_agent,
            'current' => (bool) ($this->resource->current ?? false),
        ];
    }
}
