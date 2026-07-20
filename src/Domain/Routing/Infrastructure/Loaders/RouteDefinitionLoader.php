<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Loaders;

use Illuminate\Support\Collection;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Polymorph\Platform\Domain\Routing\Infrastructure\Builders\RouteNodeBuilderFactory;
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
    /**
     * Фабрика для создания билдеров узлов.
     */
    private RouteNodeBuilderFactory $builderFactory;

    /**
     * Валидатор для декларативных маршрутов.
     */
    private RouteValidator $validator;

    /**
     * Конструктор.
     */
    public function __construct(
        RouteValidator $validator,
        RouteNodeBuilderFactory $builderFactory,
        private readonly AppLogger $logger,
    ) {
        $this->validator = $validator;
        $this->builderFactory = $builderFactory;
    }

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
     * Использует фабрику билдеров для создания узла соответствующего типа.
     * Делегирует всю логику создания билдерам, что упрощает код и улучшает расширяемость.
     *
     * @param  array<string, mixed>  $data  Данные конфигурации
     * @return RouteNodeDefinition|null Созданный RouteNodeDefinition или null при ошибке
     */
    public function createFromArray(array $data): ?RouteNodeDefinition
    {
        // Нормализация enum в строки для валидации
        $data = $this->normalizeEnumsToStrings($data);

        // Валидация данных
        $validated = $this->validator->validate($data, false);

        // Нормализация kind в enum
        $kind = RouteNodeKind::from($validated['kind']);

        // Сначала рекурсивно собираем детей (для GROUP узлов).
        $children = [];
        if (isset($validated['children']) && is_array($validated['children'])) {
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

        // Получаем билдер для типа узла.
        $builder = $this->builderFactory->create($kind);
        if ($builder === null) {
            $this->logger->error('routing.declarative.builder_not_found', [
                'kind' => $kind->value,
            ]);

            return null;
        }

        return $builder->build($validated, $children);
    }

    /**
     * Нормализовать enum объекты в строки для валидации.
     *
     * Рекурсивно проходит по массиву и преобразует объекты RouteNodeKind
     * и RouteNodeActionType в их строковые значения.
     *
     * @param  array<string, mixed>  $data  Данные для нормализации
     * @return array<string, mixed> Нормализованные данные
     */
    protected function normalizeEnumsToStrings(array $data): array
    {
        // Нормализация kind
        if (isset($data['kind']) && $data['kind'] instanceof \BackedEnum) {
            $data['kind'] = $data['kind']->value;
        }

        // Нормализация action_type
        if (isset($data['action_type']) && $data['action_type'] instanceof \BackedEnum) {
            $data['action_type'] = $data['action_type']->value;
        }

        // Рекурсивная обработка дочерних узлов
        if (isset($data['children']) && is_array($data['children'])) {
            $data['children'] = array_map(
                fn ($child) => is_array($child) ? $this->normalizeEnumsToStrings($child) : $child,
                $data['children']
            );
        }

        return $data;
    }
}
