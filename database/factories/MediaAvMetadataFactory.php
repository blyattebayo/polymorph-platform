<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\Models\MediaAvMetadata;

/**
 * Фабрика для создания записей MediaAvMetadata.
 *
 * Примеры использования:
 *
 * // Простая запись с метаданными
 * MediaAvMetadata::factory()->create();
 *
 * // Для конкретного медиа-файла
 * MediaAvMetadata::factory()->for($media)->create();
 *
 * // С конкретной длительностью
 * MediaAvMetadata::factory()->withDuration(120000)->create(); // 2 минуты
 *
 * // С конкретными кодеками
 * MediaAvMetadata::factory()->withCodecs('h264', 'aac')->create();
 *
 * // С битрейтом
 * MediaAvMetadata::factory()->withBitrate(2500)->create();
 *
 * // Предустановки для видео
 * MediaAvMetadata::factory()->forVideo()->create();
 *
 * // Предустановки для аудио
 * MediaAvMetadata::factory()->forAudio()->create();
 *
 * // Комбинация
 * MediaAvMetadata::factory()
 *     ->for($media)
 *     ->withDuration(180000)
 *     ->withCodecs('h265', 'opus')
 *     ->create();
 *
 * @extends Factory<MediaAvMetadata>
 */
class MediaAvMetadataFactory extends Factory
{
    /**
     * Имя модели, связанной с фабрикой.
     *
     * @var string
     */
    protected $model = MediaAvMetadata::class;

    /**
     * Определить значения атрибутов по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_id' => MediaFactory::new(),
            'duration_ms' => $this->faker->numberBetween(1000, 3600000),
            'bitrate_kbps' => $this->faker->numberBetween(128, 5000),
            'frame_rate' => $this->faker->randomFloat(2, 24.0, 60.0),
            'frame_count' => $this->faker->numberBetween(100, 100000),
            'video_codec' => $this->faker->randomElement(['h264', 'h265', 'vp9', 'av1']),
            'audio_codec' => $this->faker->randomElement(['aac', 'mp3', 'opus', 'vorbis']),
        ];
    }

    /**
     * Указать медиа-файл для метаданных.
     *
     * @param  Media  $media  Медиа-файл
     */
    public function forMedia(Media $media): static
    {
        return $this->state(fn () => [
            'media_id' => $media->id,
        ]);
    }

    /**
     * Установить длительность медиа.
     *
     * @param  int  $ms  Длительность в миллисекундах
     */
    public function withDuration(int $ms): static
    {
        return $this->state(fn () => [
            'duration_ms' => $ms,
        ]);
    }

    /**
     * Установить кодеки.
     *
     * @param  string  $video  Видео кодек
     * @param  string  $audio  Аудио кодек
     */
    public function withCodecs(string $video, string $audio): static
    {
        return $this->state(fn () => [
            'video_codec' => $video,
            'audio_codec' => $audio,
        ]);
    }

    /**
     * Установить битрейт.
     *
     * @param  int  $kbps  Битрейт в килобитах в секунду
     */
    public function withBitrate(int $kbps): static
    {
        return $this->state(fn () => [
            'bitrate_kbps' => $kbps,
        ]);
    }

    /**
     * Предустановленные значения для видео.
     */
    public function forVideo(): static
    {
        return $this->state(fn () => [
            'duration_ms' => $this->faker->numberBetween(10000, 600000), // 10s - 10min
            'bitrate_kbps' => $this->faker->numberBetween(1000, 5000),
            'frame_rate' => $this->faker->randomElement([24.0, 25.0, 30.0, 60.0]),
            'frame_count' => $this->faker->numberBetween(240, 36000),
            'video_codec' => $this->faker->randomElement(['h264', 'h265', 'vp9']),
            'audio_codec' => $this->faker->randomElement(['aac', 'opus']),
        ]);
    }

    /**
     * Предустановленные значения для аудио.
     */
    public function forAudio(): static
    {
        return $this->state(fn () => [
            'duration_ms' => $this->faker->numberBetween(30000, 300000), // 30s - 5min
            'bitrate_kbps' => $this->faker->numberBetween(128, 320),
            'frame_rate' => null,
            'frame_count' => null,
            'video_codec' => null,
            'audio_codec' => $this->faker->randomElement(['aac', 'mp3', 'opus', 'vorbis']),
        ]);
    }
}
