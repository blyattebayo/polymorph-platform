<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Effect;

final class CompiledPolicy extends Model
{
    public $timestamps = false;

    protected $table = 'ac_compiled';

    protected $fillable = [
        'subject',
        'resource_pattern',
        'action',
        'effect',
        'priority',
        'policy_id',
        'compiled_at',
    ];

    protected $casts = [
        'effect' => Effect::class,
        'priority' => 'int',
        'policy_id' => 'int',
        'compiled_at' => 'datetime',
    ];
}
