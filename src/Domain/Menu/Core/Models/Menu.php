<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Menu\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Настраиваемое меню: дерево пунктов под строковым ключом.
 *
 * @property string $key
 * @property array<int, mixed> $tree
 */
final class Menu extends Model
{
    protected $table = 'menus';

    protected $fillable = ['key', 'tree'];

    protected $casts = ['tree' => 'array'];
}
