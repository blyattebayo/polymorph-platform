<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Polymorph\Platform\Domain\Routing\Core\Enums\OwnerType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\Routing\Core\Exceptions\ReadonlyRouteNodeException;
use Polymorph\Platform\Domain\Routing\Core\Exceptions\RouteNodeConflictException;
use Polymorph\Platform\Domain\Routing\Core\Exceptions\RouteNodeNotFoundException;
use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;
use Polymorph\Platform\Domain\Routing\Events\RouteNodeCreated;
use Polymorph\Platform\Domain\Routing\Events\RouteNodeDeleted;
use Polymorph\Platform\Domain\Routing\Events\RouteNodeUpdated;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\ClientRouteRepository;
use Polymorph\Platform\Domain\Routing\Infrastructure\Repositories\SystemRouteRepository;
use Polymorph\Platform\Domain\Routing\Infrastructure\Validators\RouteValidator;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

class RouteNodeService implements RouteNodeServiceInterface
{
    public function __construct(
        private RouteValidator $validator,
        private ClientRouteRepository $repository,
        private SystemRouteRepository $systemRepository,
        private RouteCycleDetector $cycleDetector,
        private readonly AppLogger $logger,
    ) {}

    public function create(array $data): RouteNode
    {
        // 1. Валидация через DynamicRouteValidator
        $validated = $this->validator->validate($data, false);

        // 2. Проверка конфликтов имени
        $this->validateNameUniqueness($validated['name'] ?? null);

        // 3. Проверка reserved prefixes
        $this->validateReservedPrefixes($validated);

        // 4. Проверка существования контроллера/view (опционально)
        $this->validateActionTarget($validated);

        // 4a. sort_order у клиентских узлов неотрицателен (колонка unsigned).
        $this->validateSortOrder($validated['sort_order'] ?? null);

        // 5. Владение не приходит из запроса: всё, что создано через админ-CRUD,
        //    по определению client. system/plugin живут только в памяти.
        $validated['owner'] = OwnerType::CLIENT->value;

        // 6. Автоматически устанавливаем created_by
        $validated['created_by_user_id'] = $this->currentActorId();

        // 7. Создание в транзакции
        return DB::transaction(function () use ($validated) {
            $node = RouteNode::create($validated);

            // Кэш инвалидируется через Observer; логирование — листенер на событии.
            event(new RouteNodeCreated($node));

            return $node->load(['parent', 'children']);
        });
    }

    public function getById(int $id): RouteNode
    {
        /** @var RouteNode|null $node */
        $node = RouteNode::find($id);

        if (! $node) {
            throw new RouteNodeNotFoundException("Node #{$id} not found");
        }

        return $node->load(['parent', 'children']);
    }

    public function update(int $id, array $data): RouteNode
    {
        /** @var RouteNode|null $node */
        $node = RouteNode::find($id);

        if (! $node) {
            throw new RouteNodeNotFoundException("Node #{$id} not found");
        }

        if ($node->readonly) {
            throw new ReadonlyRouteNodeException("Node #{$id} is readonly");
        }

        $validated = $this->validator->validate($data, true);

        // Проверка изменения parent_id на циклы
        if (isset($validated['parent_id']) && $validated['parent_id'] !== $node->parent_id) {
            $this->cycleDetector->assertNoCycle($id, $validated['parent_id']);
        }

        // Очистка полей при изменении kind
        if (isset($validated['kind']) && $validated['kind'] !== $node->kind->value) {
            $validated = $this->cleanFieldsOnKindChange($validated, $node);
        }

        // Проверка конфликтов имени (если изменяется)
        if (isset($validated['name']) && $validated['name'] !== $node->name) {
            $this->validateNameUniqueness($validated['name'], $id);
        }

        // Те же инварианты, что и при создании: без этого PUT был дырой, через
        // которую whitelist контроллеров и reserved prefixes обходились целиком.
        $this->validateReservedPrefixes($validated);
        $this->validateActionTarget($this->effectiveActionData($validated, $node));
        $this->validateSortOrder($validated['sort_order'] ?? null);

        // Автоматически устанавливаем updated_by
        $validated['updated_by_user_id'] = $this->currentActorId();

        return DB::transaction(function () use ($node, $validated) {
            $node->update($validated);

            event(new RouteNodeUpdated($node));

            return $node->fresh(['parent', 'children']);
        });
    }

    public function delete(int $id): int
    {
        /** @var RouteNode|null $node */
        $node = RouteNode::find($id);

        if (! $node) {
            throw new RouteNodeNotFoundException("Node #{$id} not found");
        }

        if ($node->readonly) {
            throw new ReadonlyRouteNodeException("Node #{$id} is readonly");
        }

        $deletedCount = 0;

        DB::transaction(function () use ($node, &$deletedCount) {
            $deletedCount = $this->repository->deleteSubtree($node);
        });

        event(new RouteNodeDeleted((int) $node->id, $deletedCount));

        return $deletedCount;
    }

    public function reorder(array $nodes): int
    {
        // Валидация всех узлов
        $nodeIds = array_column($nodes, 'id');
        $routeNodes = RouteNode::whereIn('id', $nodeIds)->get()->keyBy('id');

        foreach ($nodes as $nodeData) {
            $node = $routeNodes->get($nodeData['id']);

            if (! $node) {
                throw new RouteNodeNotFoundException("Node #{$nodeData['id']} not found");
            }

            // Только CLIENT узлы можно переупорядочивать
            $ownerType = OwnerType::typeFrom($node->owner);
            if ($ownerType !== OwnerType::CLIENT) {
                $label = $ownerType?->value ?? 'unknown';
                throw new \InvalidArgumentException(
                    "Cannot reorder {$label} nodes. Only CLIENT nodes can be reordered."
                );
            }

            $this->validateSortOrder($nodeData['sort_order'] ?? null);

            // Readonly нельзя переупорядочивать
            if ($node->readonly) {
                throw new ReadonlyRouteNodeException("Cannot reorder readonly node #{$node->id}");
            }
        }

        // Проверка циклов после изменения
        $this->cycleDetector->assertReorderHasNoCycles($nodes, $routeNodes);

        $updated = 0;

        DB::transaction(function () use ($nodes, $routeNodes, &$updated) {
            foreach ($nodes as $nodeData) {
                $node = $routeNodes->get($nodeData['id']);

                // Используем Eloquent (не Query Builder) для вызова Observer
                $node->update([
                    'parent_id' => $nodeData['parent_id'] ?? null,
                    'sort_order' => $nodeData['sort_order'] ?? 0,
                ]);

                $updated++;
            }
        });

        $this->logger->event('routing.nodes.reordered', ['updated' => $updated]);

        return $updated;
    }

    public function getTree(bool $enabledOnly = true): Collection
    {
        return $this->repository->eloquentTree($enabledOnly);
    }

    public function restoreWithChildren(int $id): void
    {
        /** @var RouteNode|null $node */
        $node = RouteNode::onlyTrashed()->find($id);

        if (! $node) {
            throw new RouteNodeNotFoundException("Deleted node #{$id} not found");
        }

        DB::transaction(function () use ($node) {
            $this->repository->restoreSubtree($node);
        });

        $this->logger->event('routing.node.restored_with_children', ['id' => $id]);
    }

    // ========== Private Helper Methods ==========

    /**
     * Проверить уникальность имени маршрута.
     *
     * @throws RouteNodeConflictException
     */
    private function validateNameUniqueness(?string $name, ?int $excludeId = null): void
    {
        if ($name === null) {
            return;
        }

        // Проверка в БД
        $existsInDb = RouteNode::where('name', $name)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if ($existsInDb) {
            throw new RouteNodeConflictException("Route name '{$name}' already exists in database");
        }

        // Проверка в системных (декларативных) маршрутах.
        // Имена живут на листьях, а корни дерева — группы без имени, поэтому
        // pluck по корням не находил НИЧЕГО и проверка была тихим no-op.
        if (in_array($name, $this->declarativeRouteNames(), true)) {
            throw new RouteNodeConflictException("Route name '{$name}' already exists in declarative routes");
        }
    }

    /**
     * Собрать имена всех декларативных маршрутов, включая вложенные в группы.
     *
     * @return list<string>
     */
    private function declarativeRouteNames(): array
    {
        $names = [];

        $walk = static function (iterable $nodes) use (&$walk, &$names): void {
            foreach ($nodes as $node) {
                if ($node->name !== null) {
                    $names[] = $node->name;
                }

                $walk($node->children);
            }
        };

        $walk($this->systemRepository->getTree());

        return $names;
    }

    /**
     * Проверить reserved prefixes.
     *
     * @throws ValidationException
     */
    private function validateReservedPrefixes(array $validated): void
    {
        $prefix = $validated['prefix'] ?? null;

        if ($prefix === null) {
            return;
        }

        $reserved = config('dynamic-routes.reserved_prefixes', []);

        if (in_array($prefix, $reserved)) {
            throw ValidationException::withMessages([
                'prefix' => ["The prefix '{$prefix}' is reserved and cannot be used."],
            ]);
        }
    }

    /**
     * Собрать действие узла с учётом того, что PUT может менять action_meta,
     * не трогая action_type (и наоборот) — проверять надо итоговую пару.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function effectiveActionData(array $validated, RouteNode $node): array
    {
        return [
            'action_type' => array_key_exists('action_type', $validated)
                ? $validated['action_type']
                : $node->action_type?->value,
            'action_meta' => array_key_exists('action_meta', $validated)
                ? $validated['action_meta']
                : ($node->action_meta ?? []),
        ];
    }

    /**
     * Клиентские узлы не могут иметь отрицательный sort_order: колонка unsigned,
     * а отрицательный диапазон зарезервирован за SYSTEM/PLUGIN.
     *
     * @throws ValidationException
     */
    private function validateSortOrder(mixed $sortOrder): void
    {
        if ($sortOrder !== null && (int) $sortOrder < 0) {
            throw ValidationException::withMessages([
                'sort_order' => ['sort_order must be >= 0 for CLIENT nodes. Negative values are reserved for SYSTEM/PLUGIN.'],
            ]);
        }
    }

    /**
     * Очистить несовместимые поля при изменении kind.
     */
    private function cleanFieldsOnKindChange(array $validated, RouteNode $node): array
    {
        $newKind = RouteNodeKind::from($validated['kind']);
        $oldKind = $node->kind;

        if ($newKind === $oldKind) {
            return $validated;
        }

        if ($newKind === RouteNodeKind::GROUP) {
            // Меняем на GROUP → очищаем поля ROUTE
            $validated['uri'] = null;
            $validated['methods'] = null;
            $validated['action_type'] = null;
            $validated['action_meta'] = null;
            $validated['name'] = null; // GROUP обычно не имеет name
        } else {
            // Меняем на ROUTE → очищаем поля GROUP
            $validated['prefix'] = null;
            $validated['namespace'] = null;
        }

        $this->logger->event('routing.node.kind_changed', [
            'node_id' => $node->id,
            'old_kind' => $oldKind->value,
            'new_kind' => $newKind->value,
        ]);

        return $validated;
    }

    /**
     * Проверить валидность контроллера/view.
     *
     * @throws ValidationException
     */
    private function validateActionTarget(array $validated): void
    {
        if (! config('dynamic-routes.validation.check_controller_exists', true)) {
            return;
        }

        $actionType = $validated['action_type'] ?? null;
        $actionMeta = $validated['action_meta'] ?? [];

        if ($actionType === 'controller' && isset($actionMeta['action'])) {
            $controllerClass = explode('@', $actionMeta['action'])[0];

            if (! class_exists($controllerClass)) {
                throw ValidationException::withMessages([
                    'action_meta.action' => ["Controller class '{$controllerClass}' does not exist."],
                ]);
            }

            // Опционально: проверка namespace whitelist
            $allowedNamespaces = config('dynamic-routes.validation.allowed_controller_namespaces', [
                'Polymorph\\Platform\\Http\\Controllers\\',
            ]);

            $allowed = false;
            foreach ($allowedNamespaces as $ns) {
                if (str_starts_with($controllerClass, $ns)) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                throw ValidationException::withMessages([
                    'action_meta.action' => ["Controller '{$controllerClass}' is not in allowed namespace."],
                ]);
            }
        }

        if (config('dynamic-routes.validation.check_view_exists', true) && $actionType === 'view' && isset($actionMeta['view'])) {
            if (! view()->exists($actionMeta['view'])) {
                throw ValidationException::withMessages([
                    'action_meta.view' => ["View '{$actionMeta['view']}' does not exist."],
                ]);
            }
        }
    }

    private function currentActorId(): ?int
    {
        $identifier = Auth::id();
        if (is_int($identifier)) {
            return $identifier;
        }
        if (is_string($identifier) && ctype_digit($identifier)) {
            return (int) $identifier;
        }

        return null;
    }
}
