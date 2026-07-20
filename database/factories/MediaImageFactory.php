<?php

declare(strict_types=1);

namespace Database\Factories;

use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\Models\MediaImage;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Фабрика для создания записей MediaImage.
 *
 * Примеры использования:
 *
 * // Простая запись с размерами
 * MediaImage::factory()->create();
 *
 * // Для конкретного медиа-файла
 * MediaImage::factory()->for($media)->create();
 *
 * // С конкретными размерами
 * MediaImage::factory()->withDimensions(1920, 1080)->create();
 *
 * // Комбинация
 * MediaImage::factory()
 *     ->for($media)
 *     ->withDimensions(1920, 1080)
 *     ->create();
 *
 * @extends Factory<MediaImage>
 */
class MediaImageFactory extends Factory
{
    /**
     * Имя модели, связанной с фабрикой.
     *
     * @var string
     */
    protected $model = MediaImage::class;

    /**
     * Определить значения атрибутов по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_id' => MediaFactory::new(),
            'width' => $this->faker->numberBetween(100, 4000),
            'height' => $this->faker->numberBetween(100, 4000),
        ];
    }

    /**
     * Указать медиа-файл для изображения.
     *
     * @param \Polymorph\Platform\Domain\Media\Core\Models\Media $media Медиа-файл
     * @return static
     */
    public function forMedia(Media $media): static
    {
        return $this->state(fn () => [
            'media_id' => $media->id,
        ]);
    }

    /**
     * Указать размеры изображения.
     *
     * @param int $width Ширина
     * @param int $height Высота
     * @return static
     */
    public function withDimensions(int $width, int $height): static
    {
        return $this->state(fn () => [
            'width' => $width,
            'height' => $height,
        ]);
    }
}

