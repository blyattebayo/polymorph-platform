<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Resolvers;

use Polymorph\Platform\Domain\Routing\Core\Enums\OwnerType;
use Polymorph\Platform\Domain\Routing\Core\Enums\RouteNodeActionType;
use Polymorph\Platform\Domain\Routing\Core\ValueObjects\RouteNodeDefinition;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Преобразует описание узла в действие для Laravel Router.
 *
 * Набор типов действий закрыт схемой (enum-колонка action_type в route_nodes),
 * поэтому здесь match по enum, а не реестр стратегий: четвёртый тип невозможен
 * без миграции, и фабрика резолверов не была достижимой точкой расширения.
 *
 * Контракт отказа: null означает «узел не регистрировать». Вызывающий пропускает
 * такой узел, и URI достаётся fallback'у со структурированным 404. Раньше часть
 * веток вместо этого регистрировала маршрут-призрак с fn () => abort(404).
 */
final class RouteActionResolver
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    /**
     * @return callable|array{0: class-string, 1: string}|null
     */
    public function resolve(RouteNodeDefinition $node): callable|array|null
    {
        return match ($node->actionType) {
            RouteNodeActionType::CONTROLLER => $this->controller($node),
            RouteNodeActionType::VIEW => $this->view($node),
            RouteNodeActionType::REDIRECT => $this->redirect($node),
            null => $this->reject($node, 'routing.action.type_missing'),
        };
    }

    /**
     * @return array{0: class-string, 1: string}|null
     */
    private function controller(RouteNodeDefinition $node): ?array
    {
        $action = $node->actionMeta['action'] ?? null;

        if (! is_string($action) || trim($action) === '') {
            return $this->reject($node, 'routing.action.controller_missing');
        }

        [$class, $method] = str_contains($action, '@')
            ? explode('@', $action, 2)
            : [$action, null];

        // 'Controller@' — пустой метод после собаки. Проходил проверку резолвера
        // (пустая строка falsy) и доезжал до Route::match как ['Controller', ''],
        // что давало 500 BadMethodCallException на запросе.
        if ($method === '') {
            return $this->reject($node, 'routing.action.method_empty', ['controller' => $class]);
        }

        if (! class_exists($class)) {
            return $this->reject($node, 'routing.action.controller_not_found', ['controller' => $class]);
        }

        // Для invokable-контроллера Laravel требует __invoke и бросает
        // UnexpectedValueException прямо из boot() — то есть 500 на КАЖДЫЙ запрос,
        // а не только на этот маршрут. Проверяем до регистрации.
        $required = $method ?? '__invoke';
        if (! method_exists($class, $required)) {
            return $this->reject($node, 'routing.action.method_not_found', [
                'controller' => $class,
                'method' => $required,
            ]);
        }

        if (! $this->isAllowedController($node, $class)) {
            return $this->reject($node, 'routing.action.controller_not_allowed', ['controller' => $class]);
        }

        // Всегда пара [класс, метод], в том числе для invokable. Голая строка FQCN
        // считается Laravel'ом «Controller@method»-формой и получает префикс
        // namespace родительской группы (Router::prependGroupNamespace),
        // из-за чего под группой с namespace резолвился несуществующий класс.
        return [$class, $required];
    }

    private function view(RouteNodeDefinition $node): ?callable
    {
        $view = $node->actionMeta['view'] ?? null;

        if (! is_string($view) || $view === '') {
            return $this->reject($node, 'routing.action.view_missing');
        }

        $data = is_array($node->actionMeta['data'] ?? null) ? $node->actionMeta['data'] : [];

        return fn () => view($view, $data);
    }

    private function redirect(RouteNodeDefinition $node): ?callable
    {
        $to = $node->actionMeta['to'] ?? null;

        if (! is_string($to) || $to === '') {
            return $this->reject($node, 'routing.action.redirect_target_missing');
        }

        $status = (int) ($node->actionMeta['status'] ?? 302);

        return fn () => redirect($to, $status);
    }

    /**
     * Проверить контроллер против whitelist неймспейсов.
     *
     * Раньше whitelist проверялся ТОЛЬКО при записи через RouteNodeService, то есть
     * строка, попавшая в route_nodes мимо сервиса (сидер, миграция, прямой SQL,
     * запись до ужесточения конфига), становилась живым обработчиком без проверки.
     *
     * Доверяем только явным system/plugin: их конфиг приходит из файлов ядра и
     * манифестов плагинов. Client, отсутствующий и нераспознанный owner — по whitelist.
     */
    private function isAllowedController(RouteNodeDefinition $node, string $class): bool
    {
        $owner = OwnerType::typeFrom($node->owner);

        if ($owner === OwnerType::SYSTEM || $owner === OwnerType::PLUGIN) {
            return true;
        }

        $allowed = (array) config('dynamic-routes.validation.allowed_controller_namespaces', []);

        foreach ($allowed as $namespace) {
            if (is_string($namespace) && $namespace !== '' && str_starts_with($class, $namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function reject(RouteNodeDefinition $node, string $event, array $context = []): null
    {
        $this->logger->warning($event, $context + [
            'route_node_id' => $node->sourceId,
            'uri' => $node->uri,
        ]);

        return null;
    }
}
