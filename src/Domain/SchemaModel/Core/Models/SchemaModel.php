<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Models;

use Database\Factories\SchemaModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\SchemaModel\Core\Collections\FieldCollection;
use Polymorph\Platform\Domain\SchemaModel\Core\Collections\SchemaCollection;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnership;

/**
 * Схема структуры данных для Record.
 *
 * Схема определяет набор полей (Fields) и их организацию.
 * Используется RecordDefinition для валидации и структурирования Record.
 *
 * @property int $id
 * @property string $name Человеко-читаемое название
 * @property string $code Уникальный код (slug)
 * @property string|null $description Описание схемы
 * @property array|null $metadata Дополнительные метаданные (JSON)
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read FieldCollection|Field[] $fields
 */
class SchemaModel extends Model
{
    use HasFactory;

    protected $table = 'schemas';

    protected $fillable = [
        'name',
        'code',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Все поля схемы (корневые и вложенные).
     */
    public function fields(): HasMany
    {
        return $this->hasMany(Field::class, 'schema_id');
    }

    /**
     * Корневые поля (без родителя).
     */
    public function rootFields(): HasMany
    {
        return $this->fields()->whereNull('parent_id')->orderBy('sort_order');
    }

    /**
     * Типы записей, использующие эту схему.
     */
    public function recordDefinitions(): HasMany
    {
        return $this->hasMany(RecordDefinition::class, 'schema_id');
    }

    public function ownership(): HasOne
    {
        return $this->hasOne(ResourceOwnership::class, 'resource_id')
            ->where('resource_type', 'schema');
    }

    /**
     * Получить количество полей.
     */
    public function getFieldsCountAttribute(): int
    {
        return $this->fields()->count();
    }

    /**
     * Создать новую коллекцию схем.
     */
    public function newCollection(array $models = []): SchemaCollection
    {
        return new SchemaCollection($models);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): SchemaModelFactory
    {
        return SchemaModelFactory::new();
    }
}
