<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RoutingV2\Plugin;

use Polymorph\Sdk\Routing\Routes;
use RuntimeException;

/**
 * Единственное место, где исполняется файл маршрутов расширения.
 *
 * Файл приезжает в zip и исполняется через require, поэтому здесь стоит
 * ровно одна проверка: вернулся ли объект нашего же билдера. Разбирать
 * в нём нечего — SDK резолвится из вендора ХОСТА (php-scoper исключает
 * Polymorph\Sdk из скоупинга), значит приехавший объект собран нашим
 * собственным классом и уже типизирован.
 *
 * Проверка НЕ является границей безопасности: require исполняет что угодно
 * до return. Она нужна для диагностируемой ошибки вместо падения в глубине
 * монтирования.
 */
final class PluginRoutes
{
    /**
     * @throws RuntimeException если файла нет или он вернул не Routes
     */
    public static function fromFile(string $path): Routes
    {
        if (! is_file($path)) {
            throw new RuntimeException("Plugin route file not found: {$path}");
        }

        $value = require $path;

        if (! $value instanceof Routes) {
            throw new RuntimeException(
                'Plugin route file must return '.Routes::class.', got '.get_debug_type($value).": {$path}",
            );
        }

        return $value;
    }
}
