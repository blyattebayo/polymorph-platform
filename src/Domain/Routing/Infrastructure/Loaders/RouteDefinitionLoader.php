<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Loaders;

use Illuminate\Support\Collection;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Polymorph\Platform\Domain\Routing\Infrastructure\Validators\RouteValidator;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Базовый сервис для загрузки маршрутов из конфигурационных файлов.
 *
 * Загружает массивы конфигурации RouteNode из файлов
 * и преобразует их в коллекцию RouteNodeDefinition (в памяти, без сохранения в БД).
 * Использует билдеры для создания узлов разных типов.
 *
 * Может быть расширен для добавления специфичной логики (например, для системных роутов).
 */
class RouteDefinitionLoader
{
    public function __construct(
        private readonly RouteValidator $validator,
        private readonly AppLogger $logger,
    ) {}

    /**
     * Загрузить декларативные маршруты из файла.
     *
     * @param  string  $file  Путь к файлу относительно routes/
     * @return array<int, array<string, mixed>> Массив конфигурации маршрутов
     */
    public function loadFromFile(string $file): array
    {
        $base = (string) config('routing.base_path', base_path('routes'));
        $path = rtrim($base, '/').'/'.ltrim($file, '/');

        if (! file_exists($path)) {
            $this->logger->warning('routing.declarative.file_not_found', ['file' => $file]);

            return [];
        }

        $config = require $path;

        if (! is_array($config)) {
            $this->logger->warning('routing.declarative.invalid_file_return', ['file' => $file]);

            return [];
        }

        return $config;
    }

    /**
     * Преобразовать массив конфигурации в коллекцию RouteNodeDefinition.
     *
     * Создаёт RouteNodeDefinition объекты в памяти (без сохранения в БД).
     * Обрабатывает иерархию (группы и вложенные маршруты).
     * sort_order должен быть указан в файлах routes/*.php.
     *
     * @param  array<int, array<string, mixed>>  $config  Массив конфигурации
     * @return Collection<int, RouteNodeDefinition> Коллекция RouteNodeDefinition
     */
    public function convertToRouteNodes(array $config): Collection
    {
        $nodes = new Collection;

        foreach ($config as $item) {
            $node = $this->createFromArray($item);
            if ($node) {
                $nodes->push($node);
            }
        }

        return $nodes;
    }

    /**
     * Создать RouteNodeDefinition из массива конфигурации.
     *
     * Валидация неотделима от сборки: сюда приходит сырой конфиг из файла ядра
     * или из routes.php плагина, и единственный путь «массив → VO» проходит
     * через RouteValidator.
     *
     * @param  array<string, mixed>  $data  Данные конфигурации
     * @return RouteNodeDefinition|null Созданный RouteNodeDefinition или null при ошибке
     */
    public function createFromArray(array $data): ?RouteNodeDefinition
    {
        $validated = $this->validator->validate($this->normalizeEnumsToStrings($data), false);
        $kind = RouteNodeKind::from($validated['kind']);

        // Дети валидируются как непрозрачный массив (у правила children нет
        // вложенных правил), поэтому каждый ребёнок проходит этот же метод.
        $children = [];
        if ($kind === RouteNodeKind::GROUP && is_array($validated['children'] ?? null)) {
            foreach ($validated['children'] as $childData) {
                if (! is_array($childData)) {
                    continue;
                }

                $child = $this->createFromArray($childData);
                if ($child !== null) {
                    $children[] = $child;
                }
            }
        }

        return $this->buildDefinition($validated, $kind, $children);
    }

    /**
     * Собрать VO, обнулив поля, несовместимые с видом узла.
     *
     * @param  array<string, mixed>  $data  Валидированные данные
     * @param  list<RouteNodeDefinition>  $children
     */
    private function buildDefinition(array $data, RouteNodeKind $kind, array $children): RouteNodeDefinition
    {
        $isGroup = $kind === RouteNodeKind::GROUP;

        return new RouteNodeDefinition(
            kind: $kind,
            owner: is_string($data['owner'] ?? null) ? $data['owner'] : null,
            sortOrder: (int) ($data['sort_order'] ?? 0),
            enabled: (bool) ($data['enabled'] ?? true),
            name: is_string($data['name'] ?? null) ? $data['name'] : null,
            domain: is_string($data['domain'] ?? null) ? $data['domain'] : null,
            prefix: $isGroup && is_string($data['prefix'] ?? null) ? $data['prefix'] : null,
            namespace: $isGroup && is_string($data['namespace'] ?? null) ? $data['namespace'] : null,
            uri: ! $isGroup && is_string($data['uri'] ?? null) ? $data['uri'] : null,
            methods: ! $isGroup && is_array($data['methods'] ?? null) ? array_values($data['methods']) : [],
            actionType: ! $isGroup && isset($data['action_type'])
                ? RouteNodeActionType::from((string) $data['action_type'])
                : null,
            actionMeta: ! $isGroup && is_array($data['action_meta'] ?? null) ? $data['action_meta'] : [],
            middleware: is_array($data['middleware'] ?? null) ? array_values($data['middleware']) : [],
            where: is_array($data['where'] ?? null) ? $data['where'] : [],
            defaults: ! $isGroup && is_array($data['defaults'] ?? null) ? $data['defaults'] : [],
            children: $children,
        );
    }

    /**
     * Нормализовать enum объекты в строки для валидации.
     *
     * Конфиг плагина может нести kind/action_type как enum (см. ExtensionRouteService),
     * а правила валидации ожидают строки. Дети не обходятся: каждый ребёнок
     * попадает сюда самостоятельно через createFromArray().
     *
     * @param  array<string, mixed>  $data  Данные для нормализации
     * @return array<string, mixed> Нормализованные данные
     */
    protected function normalizeEnumsToStrings(array $data): array
    {
        foreach (['kind', 'action_type'] as $key) {
            if (($data[$key] ?? null) instanceof \BackedEnum) {
                $data[$key] = $data[$key]->value;
            }
        }

        return $data;
    }
}
