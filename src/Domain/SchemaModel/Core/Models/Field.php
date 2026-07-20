<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Models;

use Polymorph\Platform\Domain\SchemaModel\Core\Casts\AsValidationRules;
use Polymorph\Platform\Domain\SchemaModel\Core\Collections\FieldCollection;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\Cardinality;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldPath;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\ValidationRules;
use Database\Factories\FieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Поле внутри Schema с материализованным full_path.
 *
 * Поля образуют древовидную структуру через parent_id.
 * full_path вычисляется автоматически и хранится для быстрого доступа.
 *
 * @property int $id
 * @property int $schema_id
 * @property int|null $parent_id
 * @property string $name Локальное имя поля (snake_case)
 * @property string $full_path Материализованный путь (parent.child.grandchild)
 * @property FieldType $type Тип данных поля
 * @property Cardinality $cardinality Кардинальность (one/many)
 * @property bool $is_indexed Индексировать для поиска
 * @property bool $is_system Системное поле, управляется платформой
 * @property ValidationRules|null $validation_rules Правила валидации
 * @property int $sort_order Порядок сортировки
 * @property array|null $metadata Дополнительные метаданные
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read SchemaModel $schema
 * @property-read Field|null $parent
 * @property-read FieldCollection|Field[] $children
 * @property-read FieldRefConstraint|null $refConstraint
 * @property-read \Illuminate\Database\Eloquent\Collection|FieldMediaConstraint[] $mediaConstraints
 */
class  Field extends Model
{
    use HasFactory;

    protected $table = 'fields';

    protected $fillable = [
        'schema_id',
        'parent_id',
        'name',
        'full_path',
        'type',
        'cardinality',
        'is_indexed',
        'is_system',
        'validation_rules',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'type' => FieldType::class,
        'cardinality' => Cardinality::class,
        'validation_rules' => AsValidationRules::class,
        'is_indexed' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'cardinality' => 'one',
        'is_indexed' => false,
        'is_system' => false,
        'sort_order' => 0,
    ];

    /**
     * Схема-владелец поля.
     */
    public function schema(): BelongsTo
    {
        return $this->belongsTo(SchemaModel::class, 'schema_id');
    }

    /**
     * Родительское поле (для вложенных).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'parent_id');
    }

    /**
     * Дочерние поля.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Field::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Constraint для ref-поля (один на поле).
     */
    public function refConstraint(): HasOne
    {
        return $this->hasOne(FieldRefConstraint::class, 'field_id');
    }

    /**
     * Constraints для media-полей.
     */
    public function mediaConstraints(): HasMany
    {
        return $this->hasMany(FieldMediaConstraint::class, 'field_id');
    }

    /**
     * Получить FieldPath как Value Object.
     */
    public function getPathObject(): FieldPath
    {
        return FieldPath::fromString($this->full_path);
    }

    /**
     * Получить ValidationRules как Value Object.
     */
    public function getValidationRulesObject(): ?ValidationRules
    {
        return $this->validation_rules;
    }

    /**
     * Проверить, является ли поле контейнером (JSON).
     */
    public function isContainer(): bool
    {
        // Делегируем VO — единый источник правды о «контейнерных» типах.
        return $this->type->isContainer();
    }

    /**
     * Проверить, требует ли поле constraints.
     */
    public function requiresConstraints(): bool
    {
        return $this->type->requiresConstraints();
    }

    /**
     * Проверить, является ли корневым (без родителя).
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Получить уровень вложенности (0 = корень).
     */
    public function getDepth(): int
    {
        return $this->getPathObject()->depth() - 1;
    }

    /**
     * Получить ID допустимого RecordDefinition для ref-поля.
     *
     * @return int|null
     */
    public function getAllowedRecordDefinitionId(): ?int
    {
        if ($this->type !== FieldType::REF) {
            return null;
        }

        return $this->refConstraint?->allowed_record_definition_id;
    }

    /**
     * Получить все допустимые MIME-типы для media-поля.
     *
     * @return array<string>
     */
    public function getAllowedMimeTypes(): array
    {
        if ($this->type !== FieldType::MEDIA) {
            return [];
        }

        return $this->mediaConstraints()->pluck('allowed_mime')->toArray();
    }

    /**
     * Создать типизированную коллекцию.
     */
    public function newCollection(array $models = []): FieldCollection
    {
        return new FieldCollection($models);
    }

    /**
     * Получить все ограничения поля в виде массива.
     * 
     * @return array{constraint_id?: int, allowed_record_definition_id?: int, allowed_mimes?: string[]}|null
     */
    public function getAllConstraints(): ?array
    {
        if ($this->type === FieldType::REF) {
            if (!$this->refConstraint) {
                return null;
            }
            
            return [
                'constraint_id' => $this->refConstraint->id,
                'allowed_record_definition_id' => $this->refConstraint->allowed_record_definition_id,
            ];
        }

        if ($this->type === FieldType::MEDIA) {
            return [
                'allowed_mimes' => $this->mediaConstraints
                    ->pluck('allowed_mime')
                    ->toArray()
            ];
        }

        return null;
    }

    /**
     * Является ли поле REF-типом (требует graph lookup).
     */
    public function isRefField(): bool
    {
        return $this->type === FieldType::REF;
    }

    /**
     * Должно ли поле индексироваться в поисковом слое.
     */
    public function shouldBeIndexed(): bool
    {
        return $this->is_indexed;
    }

    /**
     * Получить target RecordDefinition ID для REF поля.
     */
    public function getTargetRecordDefinitionId(): ?int
    {
        return $this->refConstraint?->allowed_record_definition_id;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): FieldFactory
    {
        return FieldFactory::new();
    }
}
