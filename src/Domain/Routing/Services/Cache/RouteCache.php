<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Services\Cache;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Сервис для кэширования дерева динамических маршрутов.
 *
 * Предоставляет методы для кэширования и инвалидации дерева маршрутов.
 * Использует версионирование ключей для возможности инвалидации при изменении схемы.
 */
class RouteCache
{
    private const CACHE_VERSION = 'v2';

    /**
     * Получить ключ кэша для декларативных маршрутов.
     *
     * Формат: {prefix}:declarative_tree:v{version}
     *
     * @return string Ключ кэша
     */
    private function getSystemCacheKey(): string
    {
        $prefix = config('dynamic-routes.cache_key_prefix', 'dynamic_routes');
        $version = self::CACHE_VERSION;
        $fingerprint = $this->systemFilesFingerprint();

        return "{$prefix}:system_tree:{$version}:{$fingerprint}";
    }

    /**
     * Построить fingerprint декларативных route-файлов.
     *
     * Позволяет автоматически инвалидировать кэш при изменении routes/*.php,
     * даже без ручного optimize:clear.
     */
    private function systemFilesFingerprint(): string
    {
        $routeFiles = (array) config('routing.declarative_files', [
            'web_core.php',
            'api.php',
            'api_plugins.php',
            'api_admin.php',
        ]);
        $base = rtrim((string) config('routing.base_path', base_path('routes')), '/');
        $files = array_map(
            static fn (string $file): string => $base.'/'.ltrim($file, '/'),
            $routeFiles,
        );

        $parts = [];

        foreach ($files as $file) {
            $mtime = is_file($file) ? (string) (filemtime($file) ?: 0) : '0';
            $parts[] = $file.':'.$mtime;
        }

        return substr(sha1(implode('|', $parts)), 0, 12);
    }

    /**
     * Получить ключ кэша для динамических маршрутов из БД.
     *
     * Формат: {prefix}:dynamic_tree:v{version}
     *
     * @return string Ключ кэша
     */
    private function getClientCacheKey(bool $enabledOnly): string
    {
        $prefix = config('dynamic-routes.cache_key_prefix', 'dynamic_routes');
        $version = self::CACHE_VERSION;
        $scope = $enabledOnly ? 'enabled' : 'all';

        return "{$prefix}:client_tree:{$version}:{$scope}";
    }

    /**
     * Получить ключ кэша для declarative-маршрутов плагинов.
     *
     * Формат: {prefix}:plugin_tree:v{version}:{fingerprint}
     *
     * Fingerprint включённого набора плагинов входит в ключ — enable/disable
     * или правка routes.php ротируют ключ и автоматически инвалидируют кэш
     * на всех воркерах без явного forget.
     *
     * @return string Ключ кэша
     */
    private function getPluginCacheKey(string $fingerprint): string
    {
        $prefix = config('dynamic-routes.cache_key_prefix', 'dynamic_routes');
        $version = self::CACHE_VERSION;

        return "{$prefix}:plugin_tree:{$version}:{$fingerprint}";
    }

    /**
     * Получить TTL кэша в секундах.
     *
     * @return int TTL в секундах
     */
    private function getCacheTtl(): int
    {
        return (int) config('dynamic-routes.cache_ttl', 3600);
    }

    /**
     * Прочитать дерево из кэша, деградируя до незакэшированного построения, если
     * кэш-бэкенд недоступен.
     *
     * Регистрация маршрутов идёт в RoutingServiceProvider::boot() на КАЖДОМ бутстрапе —
     * включая `artisan migrate` и `package:discover` на чистом хосте. При CACHE_STORE=database
     * таблицы `cache` там ещё нет, а redis может лежать; без этого guard'а Cache::get бросил бы
     * из boot() и уронил бы весь app (в т.ч. саму миграцию, которая создаёт таблицу — курица/яйцо).
     * Загрузчики дерева — чистые чтения (идемпотентны), поэтому повторный вызов callback безопасен.
     *
     * @param  callable(): Collection<int, mixed>  $callback
     * @return Collection<int, mixed>
     */
    private function rememberGuarded(string $key, callable $callback): Collection
    {
        try {
            return Cache::remember($key, $this->getCacheTtl(), static fn () => $callback());
        } catch (\Throwable) {
            return $callback();
        }
    }

    /**
     * Получить дерево декларативных маршрутов из кэша или выполнить callback.
     *
     * @param  callable(): Collection<int, mixed>  $callback  Callback для получения дерева
     * @return Collection<int, mixed> Дерево декларативных маршрутов
     */
    public function rememberSystemTree(callable $callback): Collection
    {
        $key = $this->getSystemCacheKey();

        return $this->rememberGuarded($key, $callback);
    }

    /**
     * Получить дерево динамических маршрутов из кэша или выполнить callback.
     *
     * @param  callable(): Collection<int, mixed>  $callback  Callback для получения дерева
     * @return Collection<int, mixed> Дерево динамических маршрутов
     */
    public function rememberClientTree(bool $enabledOnly, callable $callback): Collection
    {
        $key = $this->getClientCacheKey($enabledOnly);

        return $this->rememberGuarded($key, $callback);
    }

    /**
     * Получить дерево declarative-маршрутов плагинов из кэша или callback.
     *
     * @param  callable(): Collection<int, mixed>  $callback
     * @return Collection<int, mixed>
     */
    public function rememberPluginTree(string $fingerprint, callable $callback): Collection
    {
        $key = $this->getPluginCacheKey($fingerprint);

        return $this->rememberGuarded($key, $callback);
    }

    /**
     * Очистить кэш дерева маршрутов.
     *
     * Удаляет закэшированное дерево маршрутов для принудительного обновления.
     * Снимает кэш системного (declarative) и динамического (client) деревьев.
     * Записи plugin_tree и system_tree версионируются по fingerprint
     * (mtime файлов), поэтому ротируются сами; здесь снимаем текущие ключи
     * на случай явного `route:clear`.
     */
    public function forgetTree(): void
    {
        Cache::forget($this->getSystemCacheKey());
        $this->forgetClientTree();
    }

    /**
     * Очистить только кэш динамических (клиентских) маршрутов из БД.
     *
     * Динамический кэш — единственный без fingerprint, поэтому требует явной
     * инвалидации (вызывается из RouteNodeObserver при изменениях route_nodes).
     */
    public function forgetClientTree(): void
    {
        Cache::forget($this->getClientCacheKey(true));
        Cache::forget($this->getClientCacheKey(false));
    }
}
