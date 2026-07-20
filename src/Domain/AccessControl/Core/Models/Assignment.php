<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Assignment extends Model
{
    protected $table = 'ac_assignments';

    protected $fillable = [
        'policy_id',
        'subject',
    ];

    protected $casts = [
        'policy_id' => 'int',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }
}