<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class AuthenticatedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
