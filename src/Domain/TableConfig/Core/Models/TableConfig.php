<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Core\Models;

use Illuminate\Database\Eloquent\Model;

class TableConfig extends Model
{
    protected $table = 'table_configs';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'table_key',
        'scope',
        'user_id',
        'schema_version',
        'config_json',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'schema_version' => 'integer',
        'config_json' => 'array',
    ];

}
