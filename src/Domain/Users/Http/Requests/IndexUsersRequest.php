<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Http\Requests;

use Polymorph\Platform\Http\Pagination\V2\Concerns\HasPageRules;
use Polymorph\Platform\Http\Pagination\V2\Concerns\ResolvesPageRequest;
use Polymorph\Platform\Http\Requests\ApiFormRequest;

final class IndexUsersRequest extends ApiFormRequest
{
    use HasPageRules;
    use ResolvesPageRequest;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge($this->pageRules(), [
            'q' => 'nullable|string|max:255',
            'exclude_self' => 'nullable|boolean',
        ]);
    }

    /**
     * Убрать вызывающего из выдачи. Флаг запрашивает клиент (админка так
     * прячет вас из списка пользователей), а не эндпоинт целиком: подборщики
     * субъектов — назначение политики себе, выбор себя в плагине — должны
     * по-прежнему вас находить.
     */
    public function excludesSelf(): bool
    {
        return $this->boolean('exclude_self');
    }

    public function searchQuery(): ?string
    {
        $query = $this->validated('q');

        if ($query === null || $query === '') {
            return null;
        }

        return (string) $query;
    }
}
