<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Core\Models;

use Database\Factories\RecordDefinitionFactory;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnership;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Eloquent model for record definitions.
 *
 * @property int $id
 * @property string $name
 * @property int|null $schema_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Polymorph\Platform\Domain\Records\Core\Models\Record> $records
 * @property-read \Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel|null $schema
 */
class RecordDefinition extends Model
{
    use HasFactory;

    /**
     * Mass-assignable fields.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'schema_id',
        'display_template',
    ];

    /**
     * Records that belong to this definition.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Polymorph\Platform\Domain\Records\Core\Models\Record, \Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition>
     */
    public function records()
    {
        return $this->hasMany(\Polymorph\Platform\Domain\Records\Core\Models\Record::class);
    }

    /**
     * Schema assigned to this record definition.
     *
     * @return BelongsTo<\Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel, RecordDefinition>
     */
    public function schema(): BelongsTo
    {
        return $this->belongsTo(\Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel::class);
    }

    public function ownership(): HasOne
    {
        return $this->hasOne(ResourceOwnership::class, 'resource_id')
            ->where('resource_type', 'record_definition');
    }

    /**
     * Returns the configured display template, if any.
     */
    public function getDisplayTemplate(): ?string
    {
        return $this->display_template;
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Database\Factories\RecordDefinitionFactory
     */
    protected static function newFactory(): RecordDefinitionFactory
    {
        return RecordDefinitionFactory::new();
    }
}
