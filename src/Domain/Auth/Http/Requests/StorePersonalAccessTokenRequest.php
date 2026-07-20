<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Requests;

use Polymorph\Platform\Domain\Auth\Infrastructure\Services\PersonalAccessTokenService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePersonalAccessTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PersonalAccessTokenService $service */
        $service = app(PersonalAccessTokenService::class);

        return [
            'name' => ['required', 'string', 'max:255'],
            'ttl' => ['sometimes', 'nullable', 'string', Rule::in($service->ttlOptions())],
        ];
    }
}
