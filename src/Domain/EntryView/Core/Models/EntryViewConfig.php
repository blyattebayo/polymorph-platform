<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Polymorph\Platform\Domain\UiConfig\Core\Contracts\UiConfigDocument;

final class EntryViewConfig extends Model implements UiConfigDocument
{
    protected $table = 'entry_view_configs';

    /** @var list<string> */
    protected $fillable = [
        'record_definition_id',
        'schema_id',
        'version',
        'document',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'record_definition_id' => 'integer',
        'schema_id' => 'integer',
        'version' => 'integer',
    ];

    public function rawDocument(): string
    {
        return (string) $this->getRawOriginal('document');
    }
}
