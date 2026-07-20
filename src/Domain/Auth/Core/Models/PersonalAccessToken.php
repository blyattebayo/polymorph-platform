<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token_hash
 * @property string $token_prefix
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_used_at
 * @property int|null $created_by_user_id
 */
final class PersonalAccessToken extends Model
{
    protected $table = 'auth_personal_access_tokens';

    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'token_prefix',
        'expires_at',
        'revoked_at',
        'last_used_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
