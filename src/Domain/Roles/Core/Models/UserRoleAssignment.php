<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Core\Models;

use Illuminate\Database\Eloquent\Model;

final class UserRoleAssignment extends Model
{
    protected $table = 'user_role_assignments';

    protected $fillable = [
        'user_id',
        'role_id',
    ];

    protected $casts = [
        'user_id' => 'int',
        'role_id' => 'int',
    ];
}