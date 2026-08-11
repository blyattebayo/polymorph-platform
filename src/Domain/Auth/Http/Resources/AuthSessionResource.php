<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Polymorph\Platform\Domain\Auth\Domain\Session;
use Polymorph\Platform\Support\DateTime\Iso8601Formatter;

final class AuthSessionResource extends JsonResource
{
    public function __construct(
        private readonly Session $session,
        private readonly bool $current,
    ) {
        parent::__construct($session);
    }

    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->session->id(),
            'created_at' => Iso8601Formatter::format($this->session->createdAt()),
            'expires_at' => Iso8601Formatter::format($this->session->expiresAt()),
            'ip' => $this->session->client()->ip,
            'user_agent' => $this->session->client()->userAgent,
            'current' => $this->current,
        ];
    }
}
