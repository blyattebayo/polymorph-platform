<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Core\Models;

use Database\Factories\EntryViewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent модель для JSON-конфига формы (EntryView).
 *
 * Хранит произвольный JSON-конфиг формы для конкретной пары RecordDefinition (id) + Schema.
 *
 * @property int $id
 * @property int $record_definition_id ID типа контента
 * @property int $schema_id ID schema
 * @property array<string, mixed> $config_json JSON-конфиг формы
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @package Polymorph\Platform\Domain\EntryView\Core\Models
 */
class EntryView extends Model
{
    use HasFactory;

    /**
     * Имя таблицы.
     *
     * @var string
     */
    protected $table = 'form_configs';

    /**
     * Mass-assignable fields.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'record_definition_id',
        'schema_id',
        'config_json',
    ];

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'config_json' => 'array',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Database\Factories\EntryViewFactory
     */
    protected static function newFactory(): EntryViewFactory
    {
        return EntryViewFactory::new();
    }
}
