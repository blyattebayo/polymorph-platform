<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DisplayViews\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ValidateDisplayTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'display_template' => ['present', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function displayTemplate(): ?string
    {
        $value = $this->validated('display_template');

        return is_string($value) ? $value : null;
    }
}
