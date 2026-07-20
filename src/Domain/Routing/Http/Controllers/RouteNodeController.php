<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Http\Controllers;

use Polymorph\Platform\Domain\Routing\Services\RouteNodeServiceInterface;
use Polymorph\Platform\Domain\Routing\Http\Requests\ReorderRouteNodesRequest;
use Polymorph\Platform\Domain\Routing\Http\Resources\RouteNodeResource;
use Polymorph\Platform\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Контроллер для управления узлами маршрутов (RouteNode) в админ-панели.
 *
 * Предоставляет CRUD операции для узлов маршрутов: создание, чтение, обновление, удаление.
 * Управляет иерархическим деревом маршрутов.
 *
 * ВАЖНО: Контроллер работает ТОЛЬКО с клиентскими роутами (owner type CLIENT).
 * Системные и плагинные роуты управляются через другие механизмы.
 *
 * @package Polymorph\Platform\Http\Controllers\Admin\V1
 */
class RouteNodeController extends Controller
{

    /**
     * Конструктор контроллера.
     */
    public function __construct(
        private RouteNodeServiceInterface $routeNodeService
    ) {}

    /**
     * Список всех клиентских маршрутов.
     *
     * Возвращает иерархическое дерево клиентских маршрутов из БД с сохранением вложенности.
     * Включает только роуты с owner type CLIENT.
     * Используется для отображения маршрутов в UI с поддержкой последовательной загрузки узлов.
     *
    * @group Admin • Routes
     * @name List client routes
     * @authenticated
     * @response status=200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "kind": "route",
     *       "uri": "/about",
     *       "methods": ["GET"],
     *       "name": "about",
     *       "owner": "client"
     *     },
     *     {
     *       "id": 2,
     *       "kind": "group",
     *       "prefix": "blog",
     *       "owner": "client",
     *       "children": [
     *         {
     *           "id": 3,
     *           "kind": "route",
     *           "uri": "/blog/post",
     *           "methods": ["GET"],
     *           "name": "blog.post",
     *           "owner": "client"
     *         }
     *       ]
     *     }
     *   ]
     * }
     * @response status=401 {
     *   "type": "https://polymorph.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED"
     * }
      */
     public function index(): JsonResponse
     {
         $allNodes = $this->routeNodeService->getTree(enabledOnly: true);

         return RouteNodeResource::collection($allNodes)->response();
     }

    /**
     * Создание узла маршрута.
     *
    * @group Admin • Routes
     * @name Create route node
     * @authenticated
     * @bodyParam kind string required Тип узла. Values: group,route. Example: route
     * @bodyParam parent_id int ID родителя. Example: 1
     * @bodyParam sort_order int Порядок сортировки. Default: 0.
     * @bodyParam enabled boolean Включён ли узел. Default: true.
     * @bodyParam name string Имя маршрута. Example: home
     * @bodyParam uri string URI паттерн (для kind=route). Example: /
     * @bodyParam methods array HTTP методы (для kind=route). Example: ["GET"]
     * @bodyParam action_type string Тип действия. Values: controller,view,redirect. Example: controller
        * @bodyParam action_meta object Метаданные действия. Example: {"action": "Polymorph\\Platform\\Domain\\Routing\\Http\\Controllers\\HomeController"}
        * @bodyParam action_meta.action string Действие для CONTROLLER. Example: Polymorph\\Platform\\Domain\\Routing\\Http\\Controllers\\HomeController
     * @bodyParam action_meta.view string Имя view для VIEW. Example: pages.about
     * @bodyParam action_meta.data object Опциональные данные для VIEW. Example: {"key": "value"}
     * @bodyParam action_meta.to string URL для REDIRECT. Example: /new-page
     * @bodyParam action_meta.status int Статус редиректа для REDIRECT. Values: 301,302,307,308. Example: 301
     * @response status=201 {
     *   "data": {
     *     "id": 1,
     *     "kind": "route",
     *     "uri": "/",
     *     "action_type": "controller",
        *     "action_meta": {"action": "Polymorph\\Platform\\Domain\\Routing\\Http\\Controllers\\HomeController"},
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://polymorph.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED"
     * }
     */
    public function store(Request $request): RouteNodeResource
    {
        $node = $this->routeNodeService->create($request->all());
        
        return new RouteNodeResource($node);
    }

    /**
     * Получение узла маршрута.
     *
    * @group Admin • Routes
     * @name Show route node
     * @authenticated
     * @urlParam id int required ID узла. Example: 1
     * @response status=200 {
     *   "data": {
     *     "id": 1,
     *     "kind": "route",
     *     "uri": "/",
     *     "action_type": "controller",
        *     "action_meta": {"action": "Polymorph\\Platform\\Domain\\Routing\\Http\\Controllers\\HomeController"},
     *     "created_at": "2025-01-10T12:00:00+00:00",
     *     "updated_at": "2025-01-10T12:00:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://polymorph.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED"
     * }
     * @response status=404 {
     *   "type": "https://polymorph.dev/problems/not-found",
     *   "title": "Route node not found",
     *   "status": 404,
     *   "code": "NOT_FOUND"
     * }
     */
    public function show(int $id): RouteNodeResource
    {
        return new RouteNodeResource($this->routeNodeService->getById($id));
    }

    /**
     * Обновление узла маршрута.
     *
    * @group Admin • Routes
     * @name Update route node
     * @authenticated
     * @urlParam id int required ID узла. Example: 1
     * @bodyParam kind string Тип узла. Values: group,route.
     * @bodyParam parent_id int ID родителя.
     * @bodyParam sort_order int Порядок сортировки.
     * @bodyParam enabled boolean Включён ли узел.
     * @bodyParam name string Имя маршрута.
     * @bodyParam uri string URI паттерн.
     * @bodyParam methods array HTTP методы.
     * @bodyParam action_type string Тип действия. Values: controller,view,redirect.
     * @bodyParam action_meta object Метаданные действия.
     * @bodyParam action_meta.action string Действие для CONTROLLER.
     * @bodyParam action_meta.view string Имя view для VIEW.
     * @bodyParam action_meta.data object Опциональные данные для VIEW.
     * @bodyParam action_meta.to string URL для REDIRECT.
     * @bodyParam action_meta.status int Статус редиректа для REDIRECT. Values: 301,302,307,308.
     * @response status=200 {
     *   "data": {
     *     "id": 1,
     *     "kind": "route",
     *     "uri": "/updated",
     *     "updated_at": "2025-01-10T12:05:00+00:00"
     *   }
     * }
     * @response status=401 {
     *   "type": "https://polymorph.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED"
     * }
     * @response status=404 {
     *   "type": "https://polymorph.dev/problems/not-found",
     *   "title": "Route node not found",
     *   "status": 404,
     *   "code": "NOT_FOUND"
     * }
     */
    public function update(Request $request, int $id): RouteNodeResource
    {
        $node = $this->routeNodeService->update($id, $request->all());

        return new RouteNodeResource($node);
    }

    /**
     * Удаление узла маршрута.
     *
     * Выполняет мягкое удаление (soft delete) узла и всех его дочерних узлов
     * (каскадное удаление). Все операции выполняются в транзакции.
     *
    * @group Admin • Routes
     * @name Delete route node
     * @authenticated
     * @urlParam id int required ID узла. Example: 1
     * @response status=204
     * @response status=401 {
     *   "type": "https://polymorph.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED"
     * }
     * @response status=404 {
     *   "type": "https://polymorph.dev/problems/not-found",
     *   "title": "Route node not found",
     *   "status": 404,
     *   "code": "NOT_FOUND"
     * }
     */
    public function destroy(int $id): Response
    {
        $this->routeNodeService->delete($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }

    /**
     * Переупорядочивание узлов маршрутов.
     *
     * Массовое изменение parent_id и sort_order для множества узлов.
     * Выполняется в транзакции для атомарности.
     *
    * @group Admin • Routes
     * @name Reorder route nodes
     * @authenticated
     * @bodyParam nodes array required Массив узлов для переупорядочивания. Example: [{"id":1,"parent_id":null,"sort_order":0},{"id":2,"parent_id":1,"sort_order":0}]
     * @bodyParam nodes.*.id int required ID узла. Example: 1
     * @bodyParam nodes.*.parent_id int ID родителя (null для корневых). Example: null
     * @bodyParam nodes.*.sort_order int Порядок сортировки. Example: 0
     * @response status=200 {
     *   "data": {
     *     "updated": 2
     *   }
     * }
     * @response status=401 {
     *   "type": "https://polymorph.dev/problems/unauthorized",
     *   "title": "Unauthorized",
     *   "status": 401,
     *   "code": "UNAUTHORIZED"
     * }
     * @response status=422 {
     *   "type": "https://polymorph.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The given data was invalid."
     * }
     */
    public function reorder(ReorderRouteNodesRequest $request): JsonResponse
    {
        $updated = $this->routeNodeService->reorder($request->validated()['nodes']);

        return response()->json([
            'data' => ['updated' => $updated],
        ], Response::HTTP_OK);
    }
}

