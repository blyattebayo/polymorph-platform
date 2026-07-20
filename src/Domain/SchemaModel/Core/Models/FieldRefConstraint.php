<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Models;

use Database\Factories\FieldRefConstraintFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ограничение на допустимый RecordDefinition для ref-поля.
 *
 * Каждое поле типа REF может ссылаться только на один тип поста.
 *
 * @property int $id
 * @property int $field_id
 * @property int $allowed_record_definition_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Field $field
 */
class FieldRefConstraint extends Model
{
    use HasFactory;

    protected $table = 'field_ref_constraints';

    protected $fillable = [
        'field_id',
        'allowed_record_definition_id',
    ];

    protected $casts = [
        'field_id' => 'integer',
        'allowed_record_definition_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'field_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): FieldRefConstraintFactory
    {
        return FieldRefConstraintFactory::new();
    }
}
