<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Menu\Services;

use Polymorph\Platform\Domain\Menu\Core\Models\Menu;

/**
 * Хранилище настраиваемых меню по ключу. Бэкенд не интерпретирует содержимое
 * узлов — дефолты и фильтрация по правам на стороне фронта.
 */
final class MenuService
{
    /**
     * Сохранённое дерево меню или null, если меню не настроено.
     *
     * @return list<array<string, mixed>>|null
     */
    public function get(string $key): ?array
    {
        $menu = Menu::query()->where('key', $key)->first();

        return $menu instanceof Menu ? (array) $menu->tree : null;
    }

    /**
     * Полностью заменить дерево меню.
     *
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    public function save(string $key, array $tree): array
    {
        Menu::query()->updateOrCreate(['key' => $key], ['tree' => $tree]);

        return $tree;
    }

    /**
     * Сбросить меню к дефолту (удалить конфигурацию — фронт покажет дефолт).
     */
    public function reset(string $key): void
    {
        Menu::query()->where('key', $key)->delete();
    }
}
