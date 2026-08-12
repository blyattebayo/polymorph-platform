<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Models;

use Database\Factories\SchemaModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnership;

/** Persisted schema aggregate; SchemaMutationService owns its lifecycle. */
class SchemaModel extends Model
{
    use HasFactory;

    protected $table = 'schemas';

    protected $fillable = ['name', 'code', 'description', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class, 'schema_id');
    }

    public function recordDefinitions(): HasMany
    {
        return $this->hasMany(RecordDefinition::class, 'schema_id');
    }

    public function ownership(): HasOne
    {
        return $this->hasOne(ResourceOwnership::class, 'resource_id')
            ->where('resource_type', 'schema');
    }

    protected static function newFactory(): SchemaModelFactory
    {
        return SchemaModelFactory::new();
    }
}
