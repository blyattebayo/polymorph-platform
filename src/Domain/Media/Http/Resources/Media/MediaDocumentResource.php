<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Http\Resources\Media;

use Illuminate\Http\Request;

/**
 * API Resource для документов (Media).
 *
 * Возвращает только базовые поля медиа-файла.
 * Документы не имеют специфичных метаданных.
 */
class MediaDocumentResource extends BaseMediaResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * Возвращает только базовые поля медиа-файла.
     *
     * @param  Request  $request  HTTP запрос
     * @return array<string, mixed> Массив с базовыми полями документа
     */
    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}
