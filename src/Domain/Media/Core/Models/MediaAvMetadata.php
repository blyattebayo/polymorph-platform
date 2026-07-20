<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eloquent модель для нормализованных AV-метаданных медиа (MediaAvMetadata).
 *
 * Хранит технические характеристики аудио/видео:
 * длительность, битрейт, частоту кадров, количество кадров и кодеки.
 *
 * @property string $id ULID идентификатор
 * @property string $media_id Идентификатор связанного медиа-файла
 * @property int|null $duration_ms Длительность медиа в миллисекундах
 * @property int|null $bitrate_kbps Битрейт в килобитах в секунду
 * @property float|null $frame_rate Частота кадров
 * @property int|null $frame_count Количество кадров
 * @property string|null $video_codec Видео кодек
 * @property string|null $audio_codec Аудио кодек
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Media $media Связанный медиа-файл
 */
class MediaAvMetadata extends Model
{
    use HasFactory;
    use HasUlids;

    /**
     * Имя таблицы.
     *
     * @var string
     */
    protected $table = 'media_av_metadata';

    /**
     * Поля, доступные для массового присвоения.
     *
     * Все поля устанавливаются только через Actions.
     * Нет прямого user input для этой модели.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'media_id',      // Foreign key (только через Actions)
        'duration_ms',   // AV метаданные (только через Actions)
        'bitrate_kbps',  // AV метаданные (только через Actions)
        'frame_rate',    // AV метаданные (только через Actions)
        'frame_count',   // AV метаданные (только через Actions)
        'video_codec',   // AV метаданные (только через Actions)
        'audio_codec',   // AV метаданные (только через Actions)
    ];

    /**
     * Поля, защищенные от массового присвоения.
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'id',         // ULID генерируется автоматически
        'created_at', // Timestamp управляется Eloquent
        'updated_at', // Timestamp управляется Eloquent
    ];

    /**
     * Тип первичного ключа (ULID строка).
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Отключить автоинкремент (используется ULID).
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'duration_ms' => 'integer',
        'bitrate_kbps' => 'integer',
        'frame_rate' => 'float',
        'frame_count' => 'integer',
    ];

    /**
     * Связанный медиа-файл.
     *
     * @return BelongsTo<Media, MediaAvMetadata>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
