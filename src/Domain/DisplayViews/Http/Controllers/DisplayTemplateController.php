<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DisplayViews\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Domain\DisplayViews\Http\Requests\ValidateDisplayTemplateRequest;
use Polymorph\Platform\Domain\DisplayViews\Services\DisplayTemplateCompiler;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Exceptions\RecordDefinitionNotFoundException;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaSnapshotService;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnershipService;
use Polymorph\Platform\SharedKernel\Ownership\ResourceType;

final class DisplayTemplateController extends Controller
{
    public function __construct(
        private readonly RecordDefinitionRepository $recordDefinitions,
        private readonly ResourceOwnershipService $ownership,
        private readonly SchemaSnapshotService $schemaSnapshots,
        private readonly DisplayTemplateCompiler $compiler,
    ) {}

    public function validateTemplate(ValidateDisplayTemplateRequest $request, int $id): JsonResponse
    {
        $recordDefinition = $this->recordDefinitions->find($id)
            ?? throw RecordDefinitionNotFoundException::byId($id);
        $this->ownership->require(ResourceType::RECORD_DEFINITION, $id);

        $compiled = $this->compiler->compile(
            $id,
            $request->displayTemplate(),
            $this->schemaSnapshots->snapshotForRootRecordDefinition($id),
        );

        return AdminResponse::json([
            'data' => [
                'valid' => true,
                'template_hash' => $compiled['template_hash'],
            ],
        ]);
    }
}
