<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\TableConfig\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Polymorph\Platform\Domain\TableConfig\Core\Contracts\TableConfigRepository;
use Polymorph\Platform\Domain\TableConfig\Core\Models\TableConfig;
use Polymorph\Platform\Domain\TableConfig\Http\Resources\TableConfigResource;
use Polymorph\Platform\Domain\TableConfig\Services\TableConfigMergeService;
use Polymorph\Platform\Domain\TableConfig\Services\TableConfigSchemaValidator;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ThrowsErrors;
use Symfony\Component\HttpFoundation\Response;

final class TableConfigController extends Controller
{
    use ThrowsErrors;

    public function __construct(
        private readonly TableConfigSchemaValidator $schemaValidator,
        private readonly TableConfigMergeService $mergeService,
        private readonly TableConfigRepository $repository,
    ) {}

    public function showBase(string $tableKey): JsonResponse
    {
        $config = $this->findBaseOrFail($tableKey);

        return (new TableConfigResource($config))->response()->setStatusCode(200);
    }

    public function updateBase(Request $request, string $tableKey, UserIdentity $actor): JsonResponse
    {
        [$schemaVersion, $configJson] = $this->validatePayload($request, true);

        $config = $this->repository->upsertBase($tableKey, $schemaVersion, $configJson);

        return (new TableConfigResource($config))->response()->setStatusCode(200);
    }

    public function showMyOverride(string $tableKey, UserIdentity $actor): JsonResponse
    {
        $config = $this->repository->findUserOverride($tableKey, $actor->userId());

        if (! $config) {
            return AdminResponse::json([
                'data' => [
                    'id' => null,
                    'table_key' => $tableKey,
                    'scope' => 'user',
                    'user_id' => $actor->userId(),
                    'schema_version' => 1,
                    'config_json' => [],
                    'created_at' => null,
                    'updated_at' => null,
                ],
            ]);
        }

        return (new TableConfigResource($config))->response()->setStatusCode(200);
    }

    public function updateMyOverride(Request $request, string $tableKey, UserIdentity $actor): JsonResponse
    {
        [$schemaVersion, $configJson] = $this->validatePayload($request, false);

        $config = $this->repository->upsertUserOverride($tableKey, $actor->userId(), $schemaVersion, $configJson);

        return (new TableConfigResource($config))->response()->setStatusCode(200);
    }

    public function destroyMyOverride(string $tableKey, UserIdentity $actor): Response
    {
        $config = $this->findUserOverrideOrFail($tableKey, $actor->userId());
        $this->repository->delete($config);

        return AdminResponse::noContent();
    }

    public function resolved(string $tableKey, UserIdentity $actor): JsonResponse
    {
        $base = $this->repository->findBase($tableKey);
        $override = $this->repository->findUserOverride($tableKey, $actor->userId());

        if (! $base && ! $override) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                sprintf('Table config not found for table_key=%s', $tableKey),
                ['table_key' => $tableKey]
            );
        }

        $baseJson = is_array($base?->config_json) ? $base->config_json : [];
        $overrideJson = is_array($override?->config_json) ? $override->config_json : [];
        $resolvedConfig = $this->mergeService->merge($baseJson, $overrideJson);

        return AdminResponse::json([
            'data' => [
                'id' => $override?->id ?? $base?->id,
                'table_key' => $tableKey,
                'scope' => $override ? 'user' : 'base',
                'user_id' => $override ? $actor->userId() : null,
                'schema_version' => $override?->schema_version ?? $base?->schema_version,
                'config_json' => $resolvedConfig,
                'created_at' => optional($override?->created_at ?? $base?->created_at)->toIso8601String(),
                'updated_at' => optional($override?->updated_at ?? $base?->updated_at)->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array{0:int, 1:array<string, mixed>}
     */
    private function validatePayload(Request $request, bool $isBase): array
    {
        $schemaVersion = $request->input('schema_version');
        $configJson = $request->input('config_json');

        if ($schemaVersion !== 1) {
            $this->throwError(
                ErrorCode::VALIDATION_ERROR,
                'schema_version must be 1.',
                ['schema_version' => $schemaVersion]
            );
        }

        if (! is_array($configJson)) {
            $this->throwError(
                ErrorCode::VALIDATION_ERROR,
                'config_json must be an object.',
                ['config_json' => $configJson]
            );
        }

        $validationError = $isBase
            ? $this->schemaValidator->validateDefinitionV1($configJson)
            : $this->schemaValidator->validateOverrideV1($configJson);

        if ($validationError !== null) {
            $this->throwError(
                ErrorCode::VALIDATION_ERROR,
                $validationError,
                ['schema_version' => 1]
            );
        }

        return [1, $configJson];
    }

    private function findBaseOrFail(string $tableKey): TableConfig
    {
        $config = $this->repository->findBase($tableKey);

        if (! $config) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                sprintf('Base table config not found for table_key=%s', $tableKey),
                ['table_key' => $tableKey, 'scope' => 'base']
            );
        }

        return $config;
    }

    private function findUserOverrideOrFail(string $tableKey, int $userId): TableConfig
    {
        $config = $this->repository->findUserOverride($tableKey, $userId);

        if (! $config) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                sprintf('User table config not found for table_key=%s', $tableKey),
                ['table_key' => $tableKey, 'scope' => 'user', 'user_id' => $userId]
            );
        }

        return $config;
    }
}
