<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\PersonalAccessToken\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $secret_digest
 * @property string $display_hint
 * @property array<array-key, mixed> $scopes
 * @property Carbon $issued_at
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by_user_id
 * @property string|null $revocation_reason
 * @property Carbon|null $last_used_at
 */
final class PersonalAccessTokenRecord extends Model
{
    public const TABLE = 'auth_personal_access_tokens';

    protected $table = self::TABLE;

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'name',
        'secret_digest',
        'display_hint',
        'scopes',
        'issued_at',
        'expires_at',
        'revoked_at',
        'revoked_by_user_id',
        'revocation_reason',
        'last_used_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'issued_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'last_used_at' => 'immutable_datetime',
    ];
}
