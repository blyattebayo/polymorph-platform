<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Http\Requests;

use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;
use Polymorph\Platform\Http\Pagination\V2\Concerns\HasPageRules;
use Polymorph\Platform\Http\Pagination\V2\Concerns\ResolvesPageRequest;
use Polymorph\Platform\Http\Requests\ApiFormRequest;

/**
 * Request для получения списка медиа-файлов в админ-панели.
 *
 * Валидирует параметры фильтрации, поиска, сортировки и пагинации
 * для списка медиа-файлов.
 */
class IndexMediaRequest extends ApiFormRequest
{
    use HasPageRules;
    use ResolvesPageRequest;

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - q: опциональный поисковый запрос (максимум 255 символов)
     * - kind: опциональный тип медиа (image, video, audio, document)
     * - mime: опциональный MIME тип (максимум 120 символов)
     * - deleted: опциональный фильтр удалённых (with, only)
     * - sort: опциональная сортировка (created_at, size_bytes, mime)
     * - order: опциональный порядок (asc, desc)
     * - page: опциональный номер страницы
     * - per_page: опциональное количество на странице (1-100)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->pageRules(), [
            'q' => 'nullable|string|max:255',
            'kind' => 'nullable|string|in:'.implode(',', array_map(static fn (MediaKind $kind): string => $kind->value, MediaKind::cases())),
            'mime' => 'nullable|string|max:120',
            'deleted' => 'nullable|string|in:with,only',
            'sort' => 'nullable|string|in:created_at,size_bytes,mime',
            'order' => 'nullable|string|in:asc,desc',
        ]);
    }

    protected function validationErrorDetail(): string
    {
        return 'Invalid media filter parameters.';
    }
}
