<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Core\Models;

use Database\Factories\RecordDefinitionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnership;

/**
 * Eloquent model for record definitions.
 *
 * @property int $id
 * @property string $name
 * @property int|null $schema_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Record> $records
 * @property-read SchemaModel|null $schema
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
     * @return HasMany<Record, RecordDefinition>
     */
    public function records()
    {
        return $this->hasMany(Record::class);
    }

    /**
     * Schema assigned to this record definition.
     *
     * @return BelongsTo<SchemaModel, RecordDefinition>
     */
    public function schema(): BelongsTo
    {
        return $this->belongsTo(SchemaModel::class);
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
     */
    protected static function newFactory(): RecordDefinitionFactory
    {
        return RecordDefinitionFactory::new();
    }
}
