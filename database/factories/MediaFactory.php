<?php

declare(strict_types=1);

namespace Database\Factories;

use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Database\Factories\MediaImageFactory;
use Database\Factories\MediaAvMetadataFactory;
use Database\Factories\MediaVariantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Фабрика для создания записей Media.
 *
 * Примеры использования:
 *
 * // Простое изображение
 * Media::factory()->image()->create();
 *
 * // Изображение с метаданными
 * Media::factory()->image()->withImage(['width' => 1920, 'height' => 1080])->create();
 *
 * // Полный медиа-объект (изображение с метаданными и вариантами)
 * Media::factory()->image()->complete()->create();
 *
 * // Видео с AV метаданными
 * Media::factory()->video()->withAvMetadata(['duration_ms' => 60000])->create();
 *
 * // Изображение с конкретными вариантами
 * Media::factory()->image()->withVariants(['thumbnail', 'medium'])->create();
 *
 * // Аудио файл
 * Media::factory()->audio()->withAvMetadata(['duration_ms' => 180000])->create();
 *
 * // Документ
 * Media::factory()->document()->create();
 *
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * Имя модели, связанной с фабрикой.
     *
     * @var string
     */
    protected $model = Media::class;

    /**
     * Определить значения атрибутов по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ext = 'jpg';
        $basename = strtolower(Str::ulid()->toBase32());
        $path = now('UTC')->format('Y/m/d')."/{$basename}.{$ext}";

        return [
            'disk' => 'media',
            'path' => $path,
            'original_name' => "{$basename}.{$ext}",
            'ext' => $ext,
            'mime' => 'image/jpeg',
            'size_bytes' => $this->faker->numberBetween(10_000, 5_000_000),
            'checksum_sha256' => hash('sha256', $basename),
            'title' => null,  // nullable - для тестов достаточно
            'alt' => null,    // nullable - для тестов достаточно
        ];
    }

    /**
     * Указать, что медиа является изображением.
     *
     * @return static
     */
    public function image(): static
    {
        return $this->state(fn () => [
            'mime' => 'image/jpeg',
            'ext' => 'jpg',
        ]);
    }

    /**
     * Указать, что медиа является видео.
     *
     * @return static
     */
    public function video(): static
    {
        return $this->state(fn () => [
            'mime' => 'video/mp4',
            'ext' => 'mp4',
        ]);
    }

    /**
     * Указать, что медиа является аудио.
     *
     * @return static
     */
    public function audio(): static
    {
        return $this->state(fn () => [
            'mime' => 'audio/mpeg',
            'ext' => 'mp3',
        ]);
    }

    /**
     * Указать, что медиа является документом.
     *
     * @return static
     */
    public function document(): static
    {
        return $this->state(fn () => [
            'mime' => 'application/pdf',
            'ext' => 'pdf',
        ]);
    }

    /**
     * Создать медиа с связанной записью MediaImage.
     *
     * @param array<string, mixed> $imageAttributes Атрибуты для MediaImage
     * @return static
     */
    public function withImage(array $imageAttributes = []): static
    {
        return $this->afterCreating(function (Media $media) use ($imageAttributes) {
            MediaImageFactory::new()->for($media)->create($imageAttributes);
        });
    }

    /**
     * Создать медиа с связанной записью MediaAvMetadata.
     *
     * @param array<string, mixed> $avAttributes Атрибуты для MediaAvMetadata
     * @return static
     */
    public function withAvMetadata(array $avAttributes = []): static
    {
        return $this->afterCreating(function (Media $media) use ($avAttributes) {
            MediaAvMetadataFactory::new()->for($media)->create($avAttributes);
        });
    }

    /**
     * Создать медиа с несколькими вариантами.
     *
     * @param array<string> $variants Названия вариантов (например, ['thumbnail', 'medium', 'large'])
     * @return static
     */
    public function withVariants(array $variants = ['thumbnail', 'medium', 'large']): static
    {
        return $this->afterCreating(function (Media $media) use ($variants) {
            foreach ($variants as $variantName) {
                MediaVariantFactory::new()
                    ->for($media)
                    ->forVariantType($variantName)
                    ->create();
            }
        });
    }

    /**
     * Создать медиа с одним вариантом.
     *
     * @param string $name Название варианта
     * @param array<string, mixed> $attributes Дополнительные атрибуты
     * @return static
     */
    public function withVariant(string $name, array $attributes = []): static
    {
        return $this->afterCreating(function (Media $media) use ($name, $attributes) {
            MediaVariantFactory::new()
                ->for($media)
                ->forVariantType($name)
                ->create($attributes);
        });
    }

    /**
     * Создать полный медиа-объект со всеми связанными данными.
     *
     * Автоматически создает:
     * - MediaImage для изображений (с размерами)
     * - MediaAvMetadata для видео/аудио (с метаданными)
     * - MediaVariant для всех типов (thumbnail, medium, large)
     *
     * @return static
     */
    public function complete(): static
    {
        return $this->afterCreating(function (Media $media) {
            $kind = $media->kind();

            // Для изображений: создаем MediaImage и варианты
            if ($kind === \Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind::Image) {
                MediaImageFactory::new()
                    ->for($media)
                    ->create([
                        'width' => 1920,
                        'height' => 1080,
                    ]);

                // Создаем варианты для изображений
                foreach (['thumbnail', 'medium', 'large'] as $variantName) {
                    MediaVariantFactory::new()
                        ->for($media)
                        ->forVariantType($variantName)
                        ->create();
                }
            }

            // Для видео: создаем MediaAvMetadata
            if ($kind === \Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind::Video) {
                MediaAvMetadataFactory::new()
                    ->for($media)
                    ->forVideo()
                    ->create();
            }

            // Для аудио: создаем MediaAvMetadata
            if ($kind === \Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind::Audio) {
                MediaAvMetadataFactory::new()
                    ->for($media)
                    ->forAudio()
                    ->create();
            }
        });
    }
}

