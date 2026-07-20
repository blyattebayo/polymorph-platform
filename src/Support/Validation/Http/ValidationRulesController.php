<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Validation\Http;

use Illuminate\Http\JsonResponse;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\Support\Validation\ValidationConstraints;

final class ValidationRulesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return AdminResponse::json([
            'data' => ValidationConstraints::clientManifest(),
        ]);
    }
}
