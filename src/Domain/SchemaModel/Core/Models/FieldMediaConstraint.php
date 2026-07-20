<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ограничение на допустимые MIME-типы для media-полей.
 *
 * @property int $id
 * @property int $field_id
 * @property string $allowed_mime MIME-тип (image/jpeg, video/mp4, etc.)
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Field $field
 */
class FieldMediaConstraint extends Model
{
    protected $table = 'field_media_constraints';

    protected $fillable = [
        'field_id',
        'allowed_mime',
    ];

    protected $casts = [
        'field_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'field_id');
    }
}
