<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Http\Resources;

use Polymorph\Platform\Domain\Media\Http\Resources\MediaResourceFactory;
use Polymorph\Platform\Http\Resources\Admin\AdminResourceCollection;

/**
 * API Resource Collection для списка Media в админ-панели.
 *
 * Форматирует коллекцию медиа-файлов с поддержкой пагинации.
 * Использует фабричный метод MediaResourceFactory::make() для каждого элемента.
 *
 * @package Polymorph\Platform\Http\Resources\Admin
 */
class MediaCollection extends AdminResourceCollection
{
    /**
     * Ресурс, в который нужно преобразовывать элементы коллекции.
     *
     * @var string
     */
    public $collects = MediaResourceFactory::class;

    /**
     * Получить класс ресурса для элементов коллекции.
     * 
     * Переопределяем чтобы разрешить коллекции содержать модели,
     * а не инстансы JsonResource (преобразование происходит в toArray).
     *
     * @return string|null
     */
    protected function collects(): ?string
    {
        return null;
    }

    /**
     * Преобразовать коллекцию ресурсов в массив.
     *
     * Использует фабричный метод MediaResourceFactory::make() для каждого элемента,
     * чтобы автоматически выбрать нужный специализированный ресурс.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с ключом 'data'
     */
    public function toArray($request): array
    {
        return [
            'data' => $this->collection->map(function ($media) {
                return MediaResourceFactory::make($media);
            }),
        ];
    }
}


