<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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
 * @property Carbon|null $email_verified_at Дата подтверждения email
 * @property string $password Хеш пароля
 * @property bool $is_platform_admin Встроенная учётка платформенного администратора
 * @property string|null $remember_token Токен для "запомнить меня"
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications Уведомления пользователя
 */
class User extends Authenticatable implements MustVerifyEmail, UserIdentity
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable;

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
        'email_verified_at',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
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
