<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Http\Controllers;

use Polymorph\Platform\Domain\AccessControl\Services\CapabilityRegistry;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Illuminate\Http\JsonResponse;

final class CapabilityCatalogController extends Controller
{
    public function __construct(
        private readonly CapabilityRegistry $registry,
    ) {
    }

    public function index(): JsonResponse
    {
        return AdminResponse::json([
            'data' => $this->registry->capabilityDefinitionsAsArrays(),
        ]);
    }
}
