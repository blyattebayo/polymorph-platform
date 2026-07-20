<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Policy extends Model
{
    protected $table = 'ac_policies';

    protected $fillable = [
        'resource_pattern',
        'action',
        'effect',
        'priority',
        'is_active',
        'metadata',
        'matcher_hash',
    ];

    protected $casts = [
        'priority' => 'int',
        'is_active' => 'bool',
        'metadata' => 'array',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'policy_id');
    }

    public function compiled(): HasMany
    {
        return $this->hasMany(CompiledPolicy::class, 'policy_id');
    }
}
