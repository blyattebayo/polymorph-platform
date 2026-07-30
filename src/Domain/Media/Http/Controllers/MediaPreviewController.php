<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Polymorph\Platform\Domain\Media\Access\MediaCapabilities;
use Polymorph\Platform\Domain\Media\Core\Contracts\MediaRepository;
use Polymorph\Platform\Domain\Media\Core\Exceptions\MediaNotFoundException;
use Polymorph\Platform\Domain\Media\Core\Exceptions\MediaStorageException;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Services\OnDemandVariantService;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Контроллер для предпросмотра медиа-файлов (публичный с поддержкой админских функций).
 *
 * Предоставляет доступ к медиа-файлам и их вариантам (thumbnails, resized) для публичных
 * и привилегированных запросов. Носителям `media/read` доступны удалённые файлы
 * (withTrashed) и админский TTL подписанных URL; публичным запросам — только активные.
 */
final class MediaPreviewController extends Controller
{
    /**
     * @param  OnDemandVariantService  $variantService  Сервис для генерации вариантов
     */
    public function __construct(
        private readonly OnDemandVariantService $variantService,
        private readonly MediaRepository $mediaRepository,
        private readonly AccessGate $gate,
    ) {}

    /**
     * Получить медиа-файл или его вариант.
     *
     * Поддерживает:
     * - Оригинальные файлы (без параметра variant)
     * - Варианты изображений (с параметром variant, например: thumbnail, medium, large)
     *
     * Для админов (аутентифицированных пользователей):
     * - Доступ к удаленным файлам (withTrashed)
     * - Проверка прав доступа через Policy
     * - Аутентификация выполняется через optional API guard
     *
     * Для публичных запросов:
     * - Доступ только к активным (не удаленным) файлам
     * - Без проверки прав доступа
     *
     * Поиск медиа-файла выполняется по ULID идентификатору через where('id', $id)->first()
     * для обеспечения корректной работы с ULID в Laravel.
     *
     * @group Media
     *
     * @name Get media or variant
     *
     * @unauthenticated
     *
     * @urlParam id string required ULID идентификатор медиа-файла. Example: 01HZYQNGQK74ZP6YVZ6E7SFJ2D
     *
     * @queryParam variant string Вариант изображения (thumbnail, medium, large). Если не указан, возвращается оригинал.
     *
     * @responseHeader X-URL-TTL "300"
     * @responseHeader X-URL-Expires-At "2025-01-10T12:05:00+00:00"
     * @responseHeader Location "https://cdn.polymorph.dev/...signed..."
     *
     * @response status=200 file
     * @response status=302 {}
     * @response status=404 {
     *   "type": "https://polymorph.dev/problems/not-found",
     *   "title": "Media not found",
     *   "status": 404,
     *   "code": "NOT_FOUND",
     *   "detail": "Media with ID 01HZYQNGQK74ZP6YVZ6E7SFJ2D does not exist.",
     *   "meta": {
     *     "request_id": "0c0c0c0c-0c0c-0c0c-0c0c-0c0c0c0c0c01",
     *     "media_id": "01HZYQNGQK74ZP6YVZ6E7SFJ2D"
     *   },
     *   "trace_id": "00-0c0c0c0c0c0c0c0c0c0c0c0c0c0c0c01-0c0c0c0c0c0c0c-01"
     * }
     * @response status=422 {
     *   "type": "https://polymorph.dev/problems/validation-error",
     *   "title": "Validation Error",
     *   "status": 422,
     *   "code": "VALIDATION_ERROR",
     *   "detail": "Variant foo is not configured.",
     *   "meta": {
     *     "request_id": "0b0b0b0b-b7cb-6c30-033f-3f5e0b0b0b03",
     *     "variant": "foo"
     *   },
     *   "trace_id": "00-0b0b0b0bb7cb6c30033f3f5e0b0b0b03-0b0b0b0bb7cb6c30-01"
     * }
     * @response status=500 {
     *   "type": "https://polymorph.dev/problems/media-variant-error",
     *   "title": "Failed to generate media variant",
     *   "status": 500,
     *   "code": "MEDIA_VARIANT_ERROR",
     *   "detail": "Failed to generate media variant.",
     *   "meta": {
     *     "request_id": "0b0b0b0b-b7cb-6c30-033f-3f5e0b0b0b04",
     *     "variant": "thumbnail"
     *   },
     *   "trace_id": "00-0b0b0b0bb7cb6c30033f3f5e0b0b0b04-0b0b0b0bb7cb6c30-01"
     * }
     *
     * @param  Request  $request  HTTP запрос
     * @param  string  $id  ULID идентификатор медиа-файла
     */
    public function show(Request $request, string $id): RedirectResponse|BinaryFileResponse
    {
        $variant = $request->query('variant');

        $privileged = $this->hasMediaReadCapability();

        // Носителям media/read используем withTrashed, для публичных — только активные
        $media = $privileged
            ? $this->mediaRepository->findWithTrashed($id)
            : $this->mediaRepository->find($id);

        if (! $media) {
            throw MediaNotFoundException::byId($id);
        }

        // Для публичных запросов проверяем, что файл не удален
        if (! $privileged && $media->trashed()) {
            throw MediaNotFoundException::byId($id);
        }

        if ($variant !== null) {
            return $this->serveVariant($media, $variant, $privileged);
        }

        // Иначе возвращаем оригинал
        return $this->serveFile($media->disk, $media->path, $media->mime, $privileged);
    }

    /**
     * Получить вариант изображения.
     *
     * Генерирует или возвращает существующий вариант изображения (thumbnail, medium, large).
     *
     * @param  Media  $media  Медиа-файл
     * @param  string  $variant  Имя варианта
     * @param  bool  $privileged  Есть ли у актора media/read
     */
    private function serveVariant(Media $media, string $variant, bool $privileged): RedirectResponse|BinaryFileResponse
    {
        $variantModel = $this->variantService->ensureVariant($media, $variant);

        // Определяем MIME-тип варианта на основе расширения
        $variantMime = $this->getMimeFromPath($variantModel->path, $media->mime);

        return $this->serveFile($media->disk, $variantModel->path, $variantMime, $privileged);
    }

    /**
     * Отдать файл через контроллер или редирект на подписанный URL.
     *
     * Для локального диска возвращает файл напрямую через response()->file().
     * Для облачных дисков (S3) возвращает редирект на подписанный URL.
     * Использует разные TTL для публичных и админских запросов.
     *
     * @param  string  $diskName  Имя диска
     * @param  string  $path  Путь к файлу
     * @param  string  $mimeType  MIME-тип файла для установки Content-Type
     * @param  bool  $isAdmin  Является ли запрос админским
     *
     * @throws MediaStorageException Если не удалось создать URL или файл не найден
     */
    private function serveFile(string $diskName, string $path, string $mimeType, bool $isAdmin): RedirectResponse|BinaryFileResponse
    {
        $disk = Storage::disk($diskName);

        // Для админов используем signed_ttl, для публичных - public_signed_ttl
        $ttl = $isAdmin
            ? (int) config('media.signed_ttl', 300)
            : (int) (config('media.public_signed_ttl') ?? config('media.signed_ttl', 300));

        $expiry = now('UTC')->addSeconds($ttl);

        // Тип диска определяем по драйверу из конфига, а не через исключения:
        // path() у S3 не бросает (возвращает строку), поэтому catch-all раньше
        // путал «отсутствующий локальный файл» с «облачный диск» и молча уводил
        // реально пропавший файл в URL-ветку.
        $driver = (string) config("filesystems.disks.{$diskName}.driver", '');

        if ($driver === 'local') {
            // Локальный диск: файл обязан существовать — иначе это настоящая ошибка.
            $filePath = $disk->path($path);

            if (! file_exists($filePath)) {
                throw MediaStorageException::readFailed($diskName, $path);
            }

            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'X-URL-TTL' => (string) $ttl,
                'X-URL-Expires-At' => $expiry->toIso8601String(),
            ]);
        }

        // Для облачных дисков используем подписанный URL
        try {
            /** @var FilesystemAdapter $disk */
            $url = $disk->temporaryUrl($path, $expiry);

            return redirect()->away($url)->withHeaders([
                'X-URL-TTL' => (string) $ttl,
                'X-URL-Expires-At' => $expiry->toIso8601String(),
            ]);
        } catch (\Throwable) {
            // Fallback на обычный URL для облачных дисков
            /** @var FilesystemAdapter $disk */
            $url = $disk->url($path);

            if (! $url) {
                throw MediaStorageException::urlGenerationFailed($diskName, $path);
            }

            return redirect()->away($url)->withHeaders([
                'X-URL-TTL' => (string) $ttl,
                'X-URL-Expires-At' => $expiry->toIso8601String(),
            ]);
        }
    }

    /**
     * Есть ли у текущего актора капабилити media/read.
     *
     * Раньше здесь было `Auth::check()` — «админом» считался ЛЮБОЙ аутентифицированный
     * пользователь, включая PAT с минимальными правами, и получал trashed-файлы и
     * админский TTL на публичном эндпоинте. Теперь решение принимает та же ACL-цепочка,
     * что и middleware RequireCapability на админских маршрутах медиа: доступ к
     * удалённым файлам симметричен праву видеть их в медиатеке. Актора нет — публичный
     * режим (fail-closed).
     */
    private function hasMediaReadCapability(): bool
    {
        return $this->gate->currentActorAllows(
            ResourceRef::fromString(MediaCapabilities::RESOURCE),
            CapabilityCatalog::ACTION_READ,
        );
    }

    /**
     * Получить MIME-тип из пути файла.
     *
     * Определяет MIME-тип на основе расширения файла.
     * Если не удалось определить, возвращает оригинальный MIME-тип.
     *
     * @param  string  $path  Путь к файлу
     * @param  string  $fallbackMime  MIME-тип по умолчанию
     * @return string MIME-тип файла
     */
    private function getMimeFromPath(string $path, string $fallbackMime): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
        ];

        return $mimeMap[$extension] ?? $fallbackMime;
    }
}
