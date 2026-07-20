<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Http\Controllers;

use Polymorph\Platform\Domain\EntryView\Http\Requests\StoreEntryViewRequest;
use Polymorph\Platform\Domain\EntryView\Http\Resources\EntryViewResource;
use Polymorph\Platform\Domain\EntryView\Queries\FindEntryViewQuery;
use Polymorph\Platform\Domain\EntryView\Services\EntryViewService;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ThrowsErrors;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;

/**
 * Контроллер для управления JSON-конфигом формы (EntryView).
 *
 * Тонкий HTTP-слой: request → service/query → response.
 * Write-операции выполняются через сервис, read-операции — через query-слой.
 *
 * Предоставляет операции управления конфигурацией:
 * - GET: получение конфигурации по record_definition_id + schema
 * - PUT: сохранение/обновление конфигурации
 *
 * @group Admin • Form configs
 * @package Polymorph\Platform\Domain\EntryView\Http\Controllers
 */
class EntryViewController extends Controller
{
    use ThrowsErrors;

    public function __construct(
        private readonly EntryViewService $entryViewService,
        private readonly FindEntryViewQuery $findQuery,
        private readonly RecordDefinitionRepository $recordDefinitions,
    ) {
    }

    /**
     * Получение конфигурации формы по типу контента и schema.
     *
     * @group Admin • Form configs
     * @name Get form config
     * @authenticated
     * @urlParam record_definition_id integer required ID типа контента. Example: 1
     * @urlParam schema integer required ID schema. Example: 1
    * @response status=200 {
    *   "data": {
    *     "layout": "tabs",
    *     "fields": ["title", "slug"]
    *   }
    * }
     * @response status=200 {
     *   "data": {}
     * }
     * @response status=404 {
     *   "type": "https://polymorph.dev/problems/not-found",
     *   "title": "RecordDefinition not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "RecordDefinition not found: 1"
     * }
     *
     * @param int $recordDefinitionId ID типа контента
     * @param SchemaModel $schema Schema (route model binding)
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $recordDefinitionId, SchemaModel $schema)
    {
        $this->ensureRecordDefinitionExists($recordDefinitionId);

        $config = $this->findQuery->execute($recordDefinitionId, $schema->id);

        // Если конфигурация не найдена, возвращаем пустой объект
        if (!$config) {
            return response()->json(new \stdClass());
        }

        return AdminResponse::json($config->config_json ?? []);
    }

    /**
     * Сохранение или обновление конфигурации формы.
     *
     * @group Admin • Form configs
     * @name Update form config
     * @authenticated
     * @urlParam record_definition_id integer required ID типа контента. Example: 1
     * @urlParam schema integer required ID schema. Example: 1
    * @bodyParam config_json object required JSON-конфиг формы (сохраняется без доменной валидации/нормализации). Example: {"layout":"tabs","fields":["title","slug"]}
     * @response status=200 {
     *   "data": {
     *     "record_definition_id": 1,
     *     "schema_id": 1,
     *     "config_json": {
     *       "author.contacts.phone": {
     *         "name": "inputText",
     *         "props": {
     *           "label": "Phone"
     *         }
     *       }
     *     },
     *     "created_at": "2026-02-06T12:00:00+00:00",
     *     "updated_at": "2026-02-06T12:00:00+00:00"
     *   }
     * }
     * @response status=404 {
     *   "type": "https://polymorph.dev/problems/not-found",
     *   "title": "RecordDefinition not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "RecordDefinition not found: 1"
     * }
     * @response status=422 {
     *   "type": "https://polymorph.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "The config_json field is required."
     * }
     *
     * @param StoreEntryViewRequest $request Валидированный запрос
     * @param int $recordDefinitionId ID типа контента
     * @param SchemaModel $schema Schema (route model binding)
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(StoreEntryViewRequest $request, int $recordDefinitionId, SchemaModel $schema): \Illuminate\Http\JsonResponse
    {
        $this->ensureRecordDefinitionExists($recordDefinitionId);

        $validated = $request->validated();
        $configJson = $validated['config_json'] ?? [];

        $config = $this->entryViewService->upsert($recordDefinitionId, $schema->id, $configJson);

        return (new EntryViewResource($config))->response()->setStatusCode(200);
    }

    /**
     * Проверить существование RecordDefinition по ID.
     *
     * @param int $recordDefinitionId ID типа контента
     * @throws \Polymorph\Platform\Support\Errors\HttpErrorException Если RecordDefinition не найден
     */
    private function ensureRecordDefinitionExists(int $recordDefinitionId): void
    {
        if ($this->recordDefinitions->find($recordDefinitionId) === null) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                "RecordDefinition not found: {$recordDefinitionId}",
                ['record_definition_id' => $recordDefinitionId]
            );
        }
    }
}
