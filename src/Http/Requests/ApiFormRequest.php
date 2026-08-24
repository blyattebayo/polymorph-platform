<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Polymorph\Platform\Support\Errors\ValidationFailedException;

abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationFailedException(
            $validator->errors()->messages(),
            $this->validationErrorDetail(),
        );
    }

    protected function validationErrorDetail(): string
    {
        return 'Validation failed.';
    }
}
