<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Http\Resources\Media;

use Illuminate\Http\Request;

/**
 * API Resource для документов (Media).
 *
 * Возвращает только базовые поля медиа-файла.
 * Документы не имеют специфичных метаданных.
 *
 * @package Polymorph\Platform\Http\Resources\Media
 */
class MediaDocumentResource extends BaseMediaResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * Возвращает только базовые поля медиа-файла.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с базовыми полями документа
     */
    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}

