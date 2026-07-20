<?php

declare(strict_types=1);

namespace Polymorph\Platform\Http\Requests;

use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\HttpErrorException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        if (! isset($this->container)) {
            throw new \RuntimeException('Form request container is not available.');
        }

        /** @var ErrorFactory $factory */
        $factory = $this->container->make(ErrorFactory::class);

        $payload = $factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail($this->validationErrorDetail())
            ->meta(['errors' => $validator->errors()->messages()])
            ->build();

        throw new HttpErrorException($payload);
    }

    protected function validationErrorDetail(): string
    {
        return 'Validation failed.';
    }
}