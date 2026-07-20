<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Core\ValueObjects;

use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeKind;
use Polymorph\Platform\Domain\Routing\Core\Models\RouteNode;

/**
 * Неизменяемое описание узла маршрута для конвейера регистрации.
 *
 * Это «чистая» единица дерева роутов, не связанная с персистентностью: на ней
 * работают registrar'ы/резолверы независимо от того, откуда узел пришёл —
 * из файлов (system), из be/routes.php (plugin) или из таблицы route_nodes
 * (client). Eloquent-модель {@see RouteNode} остаётся только хранилищем
 * клиентских роутов; маппинг в это VO — через fromModel().
 */
final readonly class RouteNodeDefinition
{
    /**
     * @param  list<string>  $methods
     * @param  array<string, mixed>  $actionMeta
     * @param  list<string>  $middleware
     * @param  array<string, string>  $where
     * @param  array<string, mixed>  $defaults
     * @param  list<RouteNodeDefinition>  $children
     * @param  int|null  $sourceId  id исходной записи (для логов), null для in-memory
     */
    public function __construct(
        public RouteNodeKind $kind,
        public ?string $owner,
        public int $sortOrder,
        public bool $enabled,
        public ?string $name,
        public ?string $domain,
        public ?string $prefix,
        public ?string $namespace,
        public ?string $uri,
        public array $methods,
        public ?RouteNodeActionType $actionType,
        public array $actionMeta,
        public array $middleware,
        public array $where,
        public array $defaults,
        public array $children,
        public ?int $sourceId = null,
    ) {}

    /**
     * Построить VO из Eloquent-модели (рекурсивно по загруженным детям).
     */
    public static function fromModel(RouteNode $node): self
    {
        $children = [];
        $loaded = $node->relationLoaded('children') ? $node->getRelation('children') : [];
        foreach ($loaded as $child) {
            if ($child instanceof RouteNode) {
                $children[] = self::fromModel($child);
            }
        }

        return new self(
            kind: $node->kind,
            owner: $node->owner !== null ? (string) $node->owner : null,
            sortOrder: (int) ($node->sort_order ?? 0),
            enabled: (bool) ($node->enabled ?? true),
            name: $node->name,
            domain: $node->domain,
            prefix: $node->prefix,
            namespace: $node->namespace,
            uri: $node->uri,
            methods: is_array($node->methods) ? array_values($node->methods) : [],
            actionType: $node->action_type,
            actionMeta: is_array($node->action_meta) ? $node->action_meta : [],
            middleware: is_array($node->middleware) ? array_values($node->middleware) : [],
            where: is_array($node->where) ? $node->where : [],
            defaults: is_array($node->defaults) ? $node->defaults : [],
            children: $children,
            sourceId: $node->id !== null ? (int) $node->id : null,
        );
    }

    /**
     * Вернуть копию узла с новым owner/sortOrder.
     */
    public function withOwnership(?string $owner, int $sortOrder): self
    {
        return new self(
            kind: $this->kind,
            owner: $owner,
            sortOrder: $sortOrder,
            enabled: $this->enabled,
            name: $this->name,
            domain: $this->domain,
            prefix: $this->prefix,
            namespace: $this->namespace,
            uri: $this->uri,
            methods: $this->methods,
            actionType: $this->actionType,
            actionMeta: $this->actionMeta,
            middleware: $this->middleware,
            where: $this->where,
            defaults: $this->defaults,
            children: $this->children,
            sourceId: $this->sourceId,
        );
    }
}
