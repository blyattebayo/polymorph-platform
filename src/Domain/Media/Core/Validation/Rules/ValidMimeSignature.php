<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Core\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Laravel Validation Rule для проверки MIME-типа по сигнатурам файла (magic bytes).
 *
 * Определяет реальный MIME-тип файла по его сигнатурам и сравнивает
 * с заявленным типом. Защищает от подмены расширения файла.
 */
final class ValidMimeSignature implements ValidationRule
{
    /**
     * Маппинг сигнатур (hex) на MIME-типы.
     *
     * @var array<string, array<string>>
     */
    private const SIGNATURES = [
        // JPEG
        'ffd8ff' => ['image/jpeg'],
        // PNG
        '89504e47' => ['image/png'],
        // GIF
        '47494638' => ['image/gif'],
        // WebP
        '52494646' => ['image/webp'], // RIFF header, нужна дополнительная проверка
        // AVIF (ftyp box)
        '00000020avif' => ['image/avif'], // ftyp box может быть на разных позициях
        // HEIC/HEIF (ftyp box)
        '00000018' => ['image/heic', 'image/heif'],
        '0000001cheic' => ['image/heic', 'image/heif'],
        // MP4
        '00000020mp4' => ['video/mp4', 'audio/mp4'], // ftyp box
        '0000001cmp4' => ['video/mp4', 'audio/mp4'],
        // PDF
        '25504446' => ['application/pdf'], // %PDF
        // MP3 (ID3v2 или frame sync)
        '494433' => ['audio/mpeg'], // ID3
        'fff3' => ['audio/mpeg'], // MPEG-1 Layer 3
        'fffb' => ['audio/mpeg'], // MPEG-1 Layer 3
        'ffe3' => ['audio/mpeg'], // MPEG-2 Layer 3
        'fffa' => ['audio/mpeg'], // MPEG-2 Layer 3
        // AIFF
        '464f524d' => ['audio/aiff', 'audio/x-aiff'], // FORM
    ];

    /**
     * @param  array<string>  $allowedMimes  Опциональный список допустимых MIME-типов
     */
    public function __construct(
        private readonly array $allowedMimes = []
    ) {}

    /**
     * Выполнить валидацию файла.
     *
     * @param  string  $attribute  Имя атрибута (поля формы)
     * @param  mixed  $value  Значение для валидации
     * @param  Closure  $fail  Callback для регистрации ошибки валидации
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Проверка что значение - загруженный файл
        if (! $value instanceof UploadedFile) {
            return;
        }

        $mime = $value->getMimeType() ?? 'application/octet-stream';

        // Проверка в белом списке (если задан)
        if (! empty($this->allowedMimes) && ! in_array($mime, $this->allowedMimes, true)) {
            $fail("The {$attribute} must be a file of type: ".implode(', ', $this->allowedMimes).'.');

            return;
        }

        $path = $value->getRealPath() ?: $value->getPathname();

        if (! is_string($path) || ! is_file($path)) {
            $fail("The {$attribute} file cannot be read for validation.");

            return;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            $fail("The {$attribute} file cannot be opened for validation.");

            return;
        }

        try {
            $signature = $this->readSignature($handle, $mime);
            $detectedMimes = $this->detectMimeBySignature($signature, $path);

            if (empty($detectedMimes)) {
                // Если сигнатура не найдена, пропускаем валидацию (может быть неизвестный формат)
                return;
            }

            if (! in_array($mime, $detectedMimes, true)) {
                $fail(sprintf(
                    'The %s file format does not match its content (declared: %s, detected: %s).',
                    $attribute,
                    $mime,
                    implode(' or ', $detectedMimes)
                ));
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Прочитать сигнатуру файла.
     *
     * @param  resource  $handle  Файловый дескриптор
     * @param  string  $mime  MIME-тип для определения стратегии чтения
     * @return string Hex-строка сигнатуры
     */
    private function readSignature($handle, string $mime): string
    {
        rewind($handle);

        // Для MP4/HEIC/AVIF нужно искать ftyp box
        if (in_array($mime, ['video/mp4', 'audio/mp4', 'image/heic', 'image/heif', 'image/avif'], true)) {
            // Читаем первые 12 байт для поиска ftyp
            $bytes = fread($handle, 12);
            if ($bytes === false || strlen($bytes) < 12) {
                return '';
            }

            // ftyp box начинается с размера (4 байта) и 'ftyp' (4 байта)
            // Проверяем разные позиции
            for ($offset = 0; $offset <= 4; $offset += 4) {
                if (strlen($bytes) >= $offset + 8) {
                    $substr = substr($bytes, $offset, 8);
                    if (substr($substr, 4, 4) === 'ftyp') {
                        return bin2hex(substr($bytes, $offset, 8));
                    }
                }
            }

            // Fallback: первые 4 байта
            return bin2hex(substr($bytes, 0, 4));
        }

        // Для остальных форматов читаем первые 4-8 байт
        $bytes = fread($handle, 8);
        if ($bytes === false) {
            return '';
        }

        // Для WebP нужна проверка RIFF + WEBP
        if ($mime === 'image/webp') {
            rewind($handle);
            $fullBytes = fread($handle, 12);
            if ($fullBytes !== false && strlen($fullBytes) >= 12) {
                $riff = substr($fullBytes, 0, 4);
                $webp = substr($fullBytes, 8, 4);
                if ($riff === 'RIFF' && $webp === 'WEBP') {
                    return bin2hex($fullBytes);
                }
            }
        }

        return bin2hex(substr($bytes, 0, min(8, strlen($bytes))));
    }

    /**
     * Определить MIME-типы по сигнатуре.
     *
     * @param  string  $signature  Hex-строка сигнатуры
     * @param  string  $path  Путь к файлу (для дополнительных проверок)
     * @return array<string> Массив возможных MIME-типов
     */
    private function detectMimeBySignature(string $signature, string $path): array
    {
        $signature = strtolower($signature);
        $mimes = [];

        // Проверяем точные совпадения
        foreach (self::SIGNATURES as $sig => $types) {
            $sigLower = is_string($sig) ? strtolower($sig) : (string) $sig;
            if (str_starts_with($signature, $sigLower)) {
                $mimes = array_merge($mimes, $types);
            }
        }

        // Специальная обработка для WebP
        if (str_starts_with($signature, '52494646')) {
            // RIFF header, проверяем наличие WEBP
            $handle = @fopen($path, 'rb');
            if ($handle !== false) {
                fseek($handle, 8);
                $webp = fread($handle, 4);
                fclose($handle);
                if ($webp === 'WEBP') {
                    $mimes[] = 'image/webp';
                }
            }
        }

        // Специальная обработка для AIFF
        if (str_starts_with($signature, '464f524d')) {
            // FORM header, проверяем наличие AIFF
            $handle = @fopen($path, 'rb');
            if ($handle !== false) {
                fseek($handle, 8);
                $aiff = fread($handle, 4);
                fclose($handle);
                if ($aiff === 'AIFF' || $aiff === 'AIFC') {
                    $mimes[] = 'audio/aiff';
                    $mimes[] = 'audio/x-aiff';
                }
            }
        }

        // Специальная обработка для MP4/HEIC/AVIF (ftyp box)
        if (strlen($signature) >= 8) {
            $handle = @fopen($path, 'rb');
            if ($handle !== false) {
                // Ищем ftyp box
                for ($offset = 0; $offset <= 4; $offset += 4) {
                    fseek($handle, $offset);
                    $box = fread($handle, 8);
                    if ($box !== false && strlen($box) >= 8 && substr($box, 4, 4) === 'ftyp') {
                        $brand = fread($handle, 4);
                        if ($brand !== false) {
                            $brand = strtolower($brand);
                            if (in_array($brand, ['isom', 'mp41', 'mp42', 'avc1', 'iso2'], true)) {
                                $mimes[] = 'video/mp4';
                                $mimes[] = 'audio/mp4';
                            } elseif (in_array($brand, ['heic', 'mif1'], true)) {
                                $mimes[] = 'image/heic';
                                $mimes[] = 'image/heif';
                            } elseif ($brand === 'avif') {
                                $mimes[] = 'image/avif';
                            }
                        }
                    }
                }
                fclose($handle);
            }
        }

        return array_unique($mimes);
    }
}
