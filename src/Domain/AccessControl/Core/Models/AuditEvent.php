<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Models;

use Illuminate\Database\Eloquent\Model;

final class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ac_audit';

    protected $fillable = [
        'event_type',
        'actor',
        'target_subject',
        'policy_id',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'policy_id' => 'int',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
