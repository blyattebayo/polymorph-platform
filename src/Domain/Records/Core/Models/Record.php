<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Core\Models;

use Database\Factories\RecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\Users\Core\Models\User;

/**
 * Eloquent-модель для записей контента (Record).
 *
 * Представляет единицу контента в CMS: статьи, страницы, посты и т.д.
 * Поддерживает мягкое удаление и публикацию по расписанию.
 *
 * @property int $id
 * @property int $record_definition_id ID типа записи
 * @property array<string, mixed>|null $data_json Произвольные структурированные данные контента
 * @property int $author_id ID автора записи
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at Дата мягкого удаления
 * @property-read RecordDefinition $recordDefinition Тип записи
 * @property-read Model $author Автор записи
 */
class Record extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'records';

    /**
     * Все поля доступны для массового присвоения.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data_json' => 'array',
    ];

    /**BelongsTo<\Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition, Record>
     */
    public function recordDefinition(): BelongsTo
    {
        return $this->belongsTo(RecordDefinition::class);
    }

    /**
     * Связь с автором записи.
     *
     * @return BelongsTo<Model, Record>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): RecordFactory
    {
        return RecordFactory::new();
    }
}
