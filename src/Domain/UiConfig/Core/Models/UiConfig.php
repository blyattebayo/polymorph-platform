<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\UiConfig\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Polymorph\Platform\Domain\UiConfig\Core\Contracts\UiConfigDocument;

final class UiConfig extends Model implements UiConfigDocument
{
    protected $table = 'ui_configs';

    /** @var list<string> */
    protected $fillable = [
        'namespace',
        'key',
        'user_id',
        'version',
        'document',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'user_id' => 'integer',
        'version' => 'integer',
    ];

    public function rawDocument(): string
    {
        return (string) $this->getRawOriginal('document');
    }
}
