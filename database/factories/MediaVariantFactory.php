<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Polymorph\Platform\Domain\Media\Core\Models\MediaVariant;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaVariantStatus;

/**
 * Фабрика для создания записей MediaVariant.
 *
 * Примеры использования:
 *
 * // Простой вариант
 * MediaVariant::factory()->create();
 *
 * // Конкретный тип варианта с правильными размерами
 * MediaVariant::factory()->thumbnail()->create(); // 150x150
 * MediaVariant::factory()->medium()->create();    // 800x600
 * MediaVariant::factory()->large()->create();     // 1920x1080
 *
 * // Для конкретного медиа
 * MediaVariant::factory()->for($media)->thumbnail()->create();
 *
 * // С конкретным статусом
 * MediaVariant::factory()->queued()->create();     // В очереди
 * MediaVariant::factory()->processing()->create(); // Обрабатывается
 * MediaVariant::factory()->failed('Error message')->create(); // С ошибкой
 *
 * // Произвольный тип варианта
 * MediaVariant::factory()->forVariantType('custom')->create();
 *
 * // Комбинация
 * MediaVariant::factory()
 *     ->for($media)
 *     ->thumbnail()
 *     ->queued()
 *     ->create();
 *
 * @extends Factory<MediaVariant>
 */
class MediaVariantFactory extends Factory
{
    protected $model = MediaVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $basename = strtolower(Str::ulid()->toBase32());
        $variant = $this->faker->randomElement(['thumbnail', 'medium', 'large']);
        $ext = 'jpg';
        $path = now('UTC')->format('Y/m/d')."/{$basename}-{$variant}.{$ext}";

        return [
            'media_id' => MediaFactory::new(),
            'variant' => $variant,
            'path' => $path,
            'width' => $this->faker->numberBetween(100, 1920),
            'height' => $this->faker->numberBetween(100, 1080),
            'size_bytes' => $this->faker->numberBetween(1_000, 1_000_000),
            'status' => MediaVariantStatus::Ready,
            'error_message' => null,
            'started_at' => now('UTC'),
            'finished_at' => now('UTC'),
        ];
    }

    /**
     * Указать статус варианта.
     *
     * @param  \Polymorph\Platform\Domain\Media\MediaVariantStatus  $status  Статус
     */
    public function withStatus(MediaVariantStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
        ]);
    }

    /**
     * Установить размеры в зависимости от типа варианта.
     *
     * @param  string  $name  Название варианта (thumbnail, medium, large, original)
     */
    public function forVariantType(string $name): static
    {
        $dimensions = match ($name) {
            'thumbnail' => ['width' => 150, 'height' => 150],
            'small' => ['width' => 320, 'height' => 240],
            'medium' => ['width' => 800, 'height' => 600],
            'large' => ['width' => 1920, 'height' => 1080],
            'original' => ['width' => 3840, 'height' => 2160],
            default => ['width' => $this->faker->numberBetween(100, 1920), 'height' => $this->faker->numberBetween(100, 1080)],
        };

        return $this->state(fn () => array_merge(
            ['variant' => $name],
            $dimensions
        ));
    }

    /**
     * Создать вариант типа thumbnail.
     */
    public function thumbnail(): static
    {
        return $this->forVariantType('thumbnail');
    }

    /**
     * Создать вариант типа medium.
     */
    public function medium(): static
    {
        return $this->forVariantType('medium');
    }

    /**
     * Создать вариант типа large.
     */
    public function large(): static
    {
        return $this->forVariantType('large');
    }

    /**
     * Создать вариант в статусе Queued (в очереди на обработку).
     */
    public function queued(): static
    {
        return $this->state(fn () => [
            'status' => MediaVariantStatus::Queued,
            'started_at' => null,
            'finished_at' => null,
        ]);
    }

    /**
     * Создать вариант в статусе Processing (обрабатывается).
     */
    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => MediaVariantStatus::Processing,
            'started_at' => now('UTC'),
            'finished_at' => null,
        ]);
    }

    /**
     * Создать вариант в статусе Failed (ошибка).
     *
     * @param  string|null  $errorMessage  Сообщение об ошибке
     */
    public function failed(?string $errorMessage = null): static
    {
        return $this->state(fn () => [
            'status' => MediaVariantStatus::Failed,
            'error_message' => $errorMessage ?? $this->faker->sentence(),
            'started_at' => now('UTC')->subMinutes(5),
            'finished_at' => now('UTC'),
        ]);
    }
}
