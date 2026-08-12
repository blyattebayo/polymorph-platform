<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Models;

use Database\Factories\FieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Polymorph\Platform\Domain\SchemaModel\Core\Casts\AsValidationRules;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\Cardinality;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldPath;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;

/** Persisted field node; mutation invariants belong to FieldMutationService. */
class Field extends Model
{
    use HasFactory;

    protected $table = 'fields';

    protected $fillable = [
        'schema_id', 'parent_id', 'name', 'full_path', 'type', 'cardinality',
        'is_indexed', 'is_system', 'validation_rules', 'sort_order', 'metadata',
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

    public function schema(): BelongsTo
    {
        return $this->belongsTo(SchemaModel::class, 'schema_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'parent_id');
    }

    public function refConstraint(): HasOne
    {
        return $this->hasOne(FieldRefConstraint::class, 'field_id');
    }

    public function mediaConstraints(): HasMany
    {
        return $this->hasMany(FieldMediaConstraint::class, 'field_id');
    }

    public function getPathObject(): FieldPath
    {
        return FieldPath::fromString((string) $this->full_path);
    }

    protected static function newFactory(): FieldFactory
    {
        return FieldFactory::new();
    }
}
