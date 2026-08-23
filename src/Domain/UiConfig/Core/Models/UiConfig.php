<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Polymorph\Platform\Domain\UiConfig\Core\Contracts\UiConfigDocument;
use Polymorph\Platform\Domain\UiConfig\Core\Models\Concerns\StoresRawDocument;

final class UiConfig extends Model implements UiConfigDocument
{
    use StoresRawDocument;

    protected $table = 'ui_configs';

    /** @var list<string> */
    protected $fillable = [
        'namespace',
        'key',
        'version',
        'revision',
        'document',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'version' => 'integer',
        'revision' => 'integer',
    ];
}
