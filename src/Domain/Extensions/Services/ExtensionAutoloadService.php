<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Composer\Autoload\ClassLoader;

final class ExtensionAutoloadService
{
    private bool $registered = false;

    public function registerAutoload(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registerPluginComposerAutoloads();

        spl_autoload_register(function (string $class): void {
            if (! str_starts_with($class, 'Plugins\\')) {
                return;
            }

            $path = $this->resolvePath($class);
            if ($path !== null && is_file($path)) {
                require_once $path;
            }
        });

        $this->registered = true;
    }

    /**
     * Подключить composer-автолоадеры расширений — ПОСЛЕ хостового.
     *
     * Composer генерирует `$loader->register(true)`, то есть встраивает себя
     * в НАЧАЛО стека spl_autoload. Для расширения это означает право перекрыть
     * любой класс хоста, который оказался у него в вендоре.
     *
     * Так и произошло: артефакт везёт с собой Polymorph\Sdk (php-scoper его
     * не переименовывает — SDK обязан быть общим), автолоадер расширения
     * вставал первым, и хост начинал сообщать ЧУЖУЮ версию контракта.
     * Дальше — тупик: установка новой версии расширения отвергалась по
     * несовместимости с версией SDK, которую притащила старая.
     *
     * Поэтому перерегистрируем каждый загрузчик в конец очереди: классы
     * расширения находятся по-прежнему (хост о них не знает), а всё, что
     * есть у хоста, резолвится хостом.
     *
     * Каталоги с ведущим подчёркиванием пропускаются — так же, как их
     * пропускает обход в {@see ExtensionDiscoveryService::discoverAll()}.
     * Иначе «отключить расширение переименованием» работало наполовину:
     * из каталога оно исчезало, но его автолоадер продолжал подключаться,
     * и битый classmap отключённого расширения ронял artisan-команды.
     */
    private function registerPluginComposerAutoloads(): void
    {
        $root = rtrim((string) config('plugins.root_path'), '/\\');
        if ($root === '' || ! is_dir($root)) {
            return;
        }

        foreach (glob($root.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $pluginBase) {
            if (str_starts_with(basename($pluginBase), '_')) {
                continue;
            }

            $this->registerVendorAutoload($pluginBase);
        }
    }

    /**
     * Подключить автолоадер расширения, появившегося на диске уже ПОСЛЕ
     * бутстрапа.
     *
     * `plugins:install` распаковывает артефакт и тут же включает расширение —
     * в одном процессе. Автолоадеры регистрируются на бутстрапе, то есть до
     * распаковки, поэтому классов только что установленного расширения в этом
     * процессе не существовало: файл его маршрутов падал с «Class not found».
     *
     * На апгрейде дефект не проявлялся — каталог расширения уже лежал на диске
     * и его автолоадер подхватывался при старте. Видно его только на ПЕРВОЙ
     * установке.
     */
    public function registerExtension(string $pluginId): void
    {
        $root = rtrim((string) config('plugins.root_path'), '/\\');

        if ($root === '' || trim($pluginId) === '') {
            return;
        }

        $this->registerVendorAutoload($root.DIRECTORY_SEPARATOR.trim($pluginId));
    }

    private function registerVendorAutoload(string $pluginBase): void
    {
        $autoloadPath = $pluginBase.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

        if (! is_file($autoloadPath)) {
            return;
        }

        /** @var mixed $loader */
        $loader = require_once $autoloadPath;

        // require_once уже подключённого файла возвращает true, а не загрузчик.
        if ($loader instanceof ClassLoader) {
            $loader->unregister();
            $loader->register(false);
        }
    }

    private function resolvePath(string $class): ?string
    {
        $parts = explode('\\', $class);
        if (count($parts) < 3 || $parts[0] !== 'Plugins') {
            return null;
        }

        $pluginNamespace = $parts[1];
        $relativeClass = implode(DIRECTORY_SEPARATOR, array_slice($parts, 2)).'.php';
        $pluginId = $this->toSnakeCase($pluginNamespace);
        $root = rtrim((string) config('plugins.root_path'), '/\\');

        if ($root === '') {
            return null;
        }

        $pluginBase = $root.DIRECTORY_SEPARATOR.$pluginId;

        return $pluginBase.DIRECTORY_SEPARATOR.'be'.DIRECTORY_SEPARATOR.$relativeClass;
    }

    private function toSnakeCase(string $value): string
    {
        $normalized = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);
        if (! is_string($normalized)) {
            return strtolower($value);
        }

        return strtolower($normalized);
    }
}
