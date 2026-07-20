<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Core\Models;

use Database\Factories\RouteNodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;

class RouteNode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'sort_order',
        'enabled',
        'readonly',
        'owner',
        'created_by_user_id',
        'updated_by_user_id',
        'kind',
        'name',
        'domain',
        'prefix',
        'namespace',
        'methods',
        'uri',
        'action_type',
        'action_meta',
        'middleware',
        'where',
        'defaults',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'readonly' => 'boolean',
        'kind' => RouteNodeKind::class,
        'action_type' => RouteNodeActionType::class,
        'action_meta' => 'array',
        'methods' => 'array',
        'middleware' => 'array',
        'where' => 'array',
        'defaults' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RouteNode::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(RouteNode::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    protected static function newFactory(): RouteNodeFactory
    {
        return RouteNodeFactory::new();
    }
}
