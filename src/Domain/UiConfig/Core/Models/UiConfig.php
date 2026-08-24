<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Polymorph\Platform\Domain\UiConfig\Core\UiConfigDomain;

/**
 * Конфигурация лежит в колонке как есть, без Eloquent json cast: её смысл
 * принадлежит клиенту, а не PHP-типам. Отсюда два метода вместо каста — исходные
 * байты и значение из конверта.
 */
final class UiConfig extends Model
{
    protected $table = 'ui_configs';

    /** @var list<string> */
    protected $fillable = [
        'key',
        'domain',
        'author_id',
        'version',
        'revision',
        'config',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'domain' => UiConfigDomain::class,
        'author_id' => 'integer',
        'version' => 'integer',
        'revision' => 'integer',
    ];

    public function rawConfig(): string
    {
        return (string) $this->getRawOriginal('config');
    }

    public function value(): mixed
    {
        /** @var array{value: mixed} $envelope */
        $envelope = json_decode($this->rawConfig(), true, flags: JSON_THROW_ON_ERROR);

        return $envelope['value'];
    }
}
