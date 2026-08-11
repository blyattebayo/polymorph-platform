<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

/**
 * Eloquent модель для пользователей (User).
 *
 * Представляет пользователей системы с поддержкой аутентификации.
 *
 * @property int $id
 * @property string $name Имя пользователя
 * @property string $email Email пользователя (уникальный)
 * @property string $password Хеш пароля
 * @property bool $is_platform_admin Встроенная учётка платформенного администратора
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications Уведомления пользователя
 */
class User extends Authenticatable implements UserIdentity
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * @return list<string>
     */
    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_BLOCKED,
            self::STATUS_INACTIVE,
        ];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * Флаг намеренно НЕ в $fillable: встроенность назначает только сидер,
     * никакой mass-assignment из HTTP-запроса не должен её включить.
     */
    public function isPlatformAdmin(): bool
    {
        return (bool) ($this->is_platform_admin ?? false);
    }

    public function statusValue(): string
    {
        return strtolower(trim((string) ($this->status ?? self::STATUS_ACTIVE)));
    }

    public function isActiveAccount(): bool
    {
        return $this->statusValue() === self::STATUS_ACTIVE;
    }

    public function userId(): int
    {
        return (int) $this->id;
    }
}
