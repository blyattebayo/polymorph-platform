<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Models;

use Polymorph\Platform\Domain\Media\Core\Collections\MediaVariantCollection;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaVariantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent модель для вариантов медиа-файлов (MediaVariant).
 *
 * Представляет производные версии медиа-файла: превью, миниатюры, ресайзы изображений.
 * Использует ULID в качестве первичного ключа.
 *
 * @property string $id ULID идентификатор
 * @property string $media_id ID исходного медиа-файла
 * @property string $variant Название варианта (например, 'thumbnail', 'preview', 'large')
 * @property string $path Путь к файлу варианта в хранилище
 * @property int|null $width Ширина (для изображений)
 * @property int|null $height Высота (для изображений)
 * @property int $size_bytes Размер файла в байтах
 * @property MediaVariantStatus $status Статус генерации
 * @property string|null $error_message Сообщение об ошибке
 * @property int $attempts Количество попыток
 * @property \Illuminate\Support\Carbon|null $started_at Время начала обработки
 * @property \Illuminate\Support\Carbon|null $finished_at Время завершения обработки
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Polymorph\Platform\Domain\Media\Core\Models\Media $media Исходный медиа-файл
 */
class MediaVariant extends Model
{
    use HasFactory;
    use HasUlids;

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
     * Поля, доступные для массового присвоения.
     *
     * Все поля устанавливаются только через Actions/Services.
     * Нет прямого user input для этой модели.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'media_id',      // Foreign key (только через Actions)
        'variant',       // Название варианта (только через Actions)
        'path',          // Путь к файлу (только через Actions)
        'width',         // Размеры для изображений (только через Actions)
        'height',        // Размеры для изображений (только через Actions)
        'size_bytes',    // Размер файла (только через Actions)
        'status',        // Статус генерации (только через Actions)
        'error_message', // Ошибка генерации (только через Actions)
        'attempts',      // Количество попыток (только через Actions)
        'started_at',    // Время начала (только через Actions)
        'finished_at',   // Время завершения (только через Actions)
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
     * Имя таблицы.
     *
     * @var string
     */
    protected $table = 'media_variants';

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => MediaVariantStatus::class,
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
        'width' => 'integer',
        'height' => 'integer',
        'size_bytes' => 'integer',
        'attempts' => 'integer',
    ];

    /**
     * Связь с исходным медиа-файлом.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Polymorph\Platform\Domain\Media\Core\Models\Media, \Polymorph\Platform\Domain\Media\Core\Models\MediaVariant>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Создать новую Eloquent коллекцию
     *
     * @param  array<int, \Illuminate\Database\Eloquent\Model>  $models
     */
    public function newCollection(array $models = []): MediaVariantCollection
    {
        return new MediaVariantCollection($models);
    }
}
