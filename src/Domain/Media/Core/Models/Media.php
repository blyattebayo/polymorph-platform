<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Polymorph\Platform\Domain\Media\Core\Collections\MediaCollection;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaKind;

/**
 * Eloquent модель для медиа-файлов (Media).
 *
 * Представляет загруженные файлы: изображения, видео, аудио, документы.
 * Использует ULID в качестве первичного ключа. Поддерживает мягкое удаление.
 * Уникальность обеспечивается по комбинации (disk, path).
 * Специфичные метаданные хранятся в связанных таблицах:
 * - MediaImage для изображений (width, height, exif_json)
 * - MediaAvMetadata для видео/аудио (duration_ms, bitrate, codecs и т.д.)
 *
 * @property string $id ULID идентификатор
 * @property string $disk Диск хранения
 * @property string $path Путь к файлу в хранилище (уникален в рамках disk)
 * @property string $original_name Оригинальное имя файла
 * @property string|null $ext Расширение файла
 * @property string $mime MIME-тип файла
 * @property int $size_bytes Размер файла в байтах
 * @property string|null $checksum_sha256 SHA256 checksum файла (индексирован)
 * @property string|null $title Заголовок медиа
 * @property string|null $alt Альтернативный текст
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at Дата мягкого удаления
 * @property-read Collection<int, MediaVariant> $variants Варианты файла (превью, миниатюры)
 * @property-read MediaImage|null $image Метаданные изображения (только для изображений)
 * @property-read MediaAvMetadata|null $avMetadata Нормализованные AV-метаданные (только для видео/аудио)
 */
class Media extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    /**
     * Имя таблицы.
     *
     * @var string
     */
    protected $table = 'media';

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
     * ТОЛЬКО title и alt могут быть установлены пользователем через fill().
     * Все технические поля (disk, path, checksum, etc.) устанавливаются
     * только через Actions с использованием forceFill() для безопасности.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',  // User input - может быть установлен через API
        'alt',    // User input - может быть установлен через API
    ];

    /**
     * Поля, защищенные от массового присвоения.
     *
     * Системные поля, которые никогда не должны быть установлены через fill().
     *
     * @var array<int, string>
     */
    protected $guarded = [
        'id',          // ULID генерируется автоматически
        'created_at',  // Timestamp управляется Eloquent
        'updated_at',  // Timestamp управляется Eloquent
        'deleted_at',  // Soft delete управляется Eloquent
    ];

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deleted_at' => 'datetime',
        'size_bytes' => 'integer',
    ];

    /**
     * Связь с вариантами медиа-файла (превью, миниатюры, ресайзы).
     *
     * @return HasMany<MediaVariant, Media>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    /**
     * Метаданные изображения (один-к-одному).
     *
     * Связь с таблицей media_images, содержащей специфичные метаданные для изображений:
     * width, height, exif_json.
     *
     * @return HasOne<MediaImage, Media>
     */
    public function image(): HasOne
    {
        return $this->hasOne(MediaImage::class);
    }

    /**
     * Нормализованные AV-метаданные (один-к-одному).
     *
     * Связь с таблицей media_av_metadata, содержащей технические характеристики
     * аудио/видео: длительность, битрейт, частоту кадров, кодеки.
     *
     * @return HasOne<MediaAvMetadata, Media>
     */
    public function avMetadata(): HasOne
    {
        return $this->hasOne(MediaAvMetadata::class);
    }

    /**
     * Определить тип медиа-файла по MIME-типу.
     *
     * Возвращает MediaKind enum в зависимости от MIME-типа.
     *
     * @return MediaKind Тип медиа-файла
     */
    public function kind(): MediaKind
    {
        return MediaKind::fromMime($this->mime);
    }

    /**
     * Создать новую Eloquent коллекцию
     *
     * @param  array<int, Model>  $models
     */
    public function newCollection(array $models = []): MediaCollection
    {
        return new MediaCollection($models);
    }
}
