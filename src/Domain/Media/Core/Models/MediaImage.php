<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent модель для метаданных изображений (MediaImage).
 *
 * Хранит специфичные метаданные для изображений:
 * размеры (width, height).
 * Связана с Media через отношение один-к-одному.
 *
 * @property string $id ULID идентификатор
 * @property string $media_id Идентификатор связанного медиа-файла (уникален)
 * @property int $width Ширина изображения в пикселях
 * @property int $height Высота изображения в пикселях
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Polymorph\Platform\Domain\Media\Core\Models\Media $media Связанный медиа-файл
 */
class MediaImage extends Model
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
     * Все поля устанавливаются только через Actions.
     * Нет прямого user input для этой модели.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'media_id',  // Foreign key (только через Actions)
        'width',     // Метаданные изображения (только через Actions)
        'height',    // Метаданные изображения (только через Actions)
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
    protected $table = 'media_images';

    /**
     * Преобразования типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
    ];

    /**
     * Связь с медиа-файлом (один-к-одному).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Polymorph\Platform\Domain\Media\Core\Models\Media, \Polymorph\Platform\Domain\Media\Core\Models\MediaImage>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
