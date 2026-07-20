<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Core\Enums;

/**
 * Тип владельца маршрута.
 *
 * Определяет источник создания маршрута для приоритизации загрузки
 * и контроля доступа к операциям.
 *
 * @package Polymorph\Platform\Domain\Routing\Enums
 */
enum OwnerType: string
{
    /**
     * Системные маршруты (декларативные из файлов).
     *
     * - Загружаются первыми (sort_order: -10000 до -1)
     * - Всегда readonly
     * - Не могут быть изменены или переупорядочены
     * - Формат: 'system'
     */
    case SYSTEM = 'system';

    /**
     * Маршруты плагинов.
     *
     * - Загружаются вторыми (sort_order: 0 до 9999)
     * - Обычно readonly
     * - Привязаны к конкретному плагину
     * - Формат: 'plugin:plugin-name'
     */
    case PLUGIN = 'plugin';

    /**
     * Пользовательские маршруты (созданные через API).
     *
     * - Загружаются последними (sort_order: 10000+)
     * - Могут быть изменены и переупорядочены
     * - Управляются через админ-панель
     * - Формат: 'client'
     */
    case CLIENT = 'client';

    /**
     * Создать композитное значение owner.
     *
     * @param self $type Тип владельца
     * @param string|null $id ID владельца (для PLUGIN - обязателен)
     * @return string Композитное значение для поля owner
     *
     * @example OwnerType::make(OwnerType::SYSTEM) в†’ 'system'
     * @example OwnerType::make(OwnerType::PLUGIN, 'blog') в†’ 'plugin:blog'
     * @example OwnerType::make(OwnerType::CLIENT) в†’ 'client'
     */
    public static function make(self $type, ?string $id = null): string
    {
        if ($type === self::PLUGIN && $id === null) {
            throw new \InvalidArgumentException('Plugin owner type requires an ID');
        }

        return $id ? "{$type->value}:{$id}" : $type->value;
    }

    /**
     * Разобрать композитное значение owner.
     *
     * @param string $owner Значение поля owner
     * @return array{type: self, id: string|null} Тип и ID владельца
     *
     * @example OwnerType::parse('system') в†’ ['type' => SYSTEM, 'id' => null]
     * @example OwnerType::parse('plugin:blog') в†’ ['type' => PLUGIN, 'id' => 'blog']
     */
    public static function parse(string $owner): array
    {
        $parts = explode(':', $owner, 2);

        return [
            'type' => self::from($parts[0]),
            'id' => $parts[1] ?? null,
        ];
    }
}
