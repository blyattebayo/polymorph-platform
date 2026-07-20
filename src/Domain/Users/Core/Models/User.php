<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Core\Models;

use Polymorph\Platform\SharedKernel\Identity\UserIdentity;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Eloquent модель для пользователей (User).
 *
 * Представляет пользователей системы с поддержкой аутентификации.
 *
 * @property int $id
 * @property string $name Имя пользователя
 * @property string $email Email пользователя (уникальный)
 * @property \Illuminate\Support\Carbon|null $email_verified_at Дата подтверждения email
 * @property string $password Хеш пароля
 * @property string|null $remember_token Токен для "запомнить меня"
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications Уведомления пользователя
 */
class User extends Authenticatable implements UserIdentity, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, MustVerifyEmailTrait;

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
        ];
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
