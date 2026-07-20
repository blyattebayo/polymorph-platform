<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Core\Models;

use Illuminate\Database\Eloquent\Model;

final class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = ['code', 'name', 'description'];
}
