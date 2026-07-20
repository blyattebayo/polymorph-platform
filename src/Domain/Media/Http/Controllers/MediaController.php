<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Http\Controllers;

use Polymorph\Platform\Domain\Media\Commands\DeleteMediaCommand;
use Polymorph\Platform\Domain\Media\Commands\ForceDeleteMediaCommand;
use Polymorph\Platform\Domain\Media\Commands\RestoreMediaCommand;
use Polymorph\Platform\Domain\Media\Commands\UpdateMediaCommand;
use Polymorph\Platform\Domain\Media\Commands\UploadMediaCommand;
use Polymorph\Platform\Domain\Media\Core\Contracts\MediaRepository;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaDeletedFilter;
use Polymorph\Platform\Domain\Media\Core\ValueObjects\MediaQuery;
use Polymorph\Platform\Domain\Media\Http\Requests\BulkDeleteMediaRequest;
use Polymorph\Platform\Domain\Media\Http\Requests\BulkForceDeleteMediaRequest;
use Polymorph\Platform\Domain\Media\Http\Requests\BulkRestoreMediaRequest;
use Polymorph\Platform\Domain\Media\Http\Requests\BulkStoreMediaRequest;
use Polymorph\Platform\Domain\Media\Http\Requests\IndexMediaRequest;
use Polymorph\Platform\Domain\Media\Http\Requests\StoreMediaRequest;
use Polymorph\Platform\Domain\Media\Http\Requests\UpdateMediaRequest;
use Polymorph\Platform\Domain\Media\Http\Resources\MediaCollection;
use Polymorph\Platform\Domain\Media\Http\Resources\MediaConfigResource;
use Polymorph\Platform\Domain\Media\Http\Resources\Media\BaseMediaResource;
use Polymorph\Platform\Domain\Media\Http\Resources\MediaResourceFactory;
use Polymorph\Platform\Domain\Media\Queries\SearchMediaQuery;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Pagination\V2\PaginatedJsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\Infrastructure\Pagination\V2\LaravelPaginatorAdapter;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ThrowsErrors;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Контроллер для управления медиа-файлами в админ-панели.
 *
 * Предоставляет CRUD операции для медиа-файлов: загрузка, просмотр, обновление,
 * массовое мягкое удаление, массовое окончательное удаление, массовое восстановление,
 * управление вариантами и привязкой к записям.
 *
 * @package Polymorph\Platform\Domain\Media\Http\Controllers
 */
class MediaController extends Controller
{
    use ThrowsErrors;

    public function __construct(
        private readonly UploadMediaCommand $uploadMedia,
        private readonly UpdateMediaCommand $updateMedia,
        private readonly DeleteMediaCommand $deleteMedia,
        private readonly RestoreMediaCommand $restoreMedia,
        private readonly ForceDeleteMediaCommand $forceDeleteMedia,
        private readonly SearchMediaQuery $searchMedia,
        private readonly MediaRepository $mediaRepository,
        private readonly LaravelPaginatorAdapter $paginatorAdapter,
    ) {
    }


    /**
     * Получение конфигурации системы медиа-файлов.
     *
     * Возвращает информацию о разрешенных типах файлов (MIME-типы),
     * максимальном размере загрузки и доступных вариантах изображений.
     *
     * @return \Polymorph\Platform\Domain\Media\Http\Resources\MediaConfigResource Ресурс с конфигурацией медиа
     * @throws \Illuminate\Auth\Access\AuthorizationException Если нет прав на просмотр
     */
    public function config(): MediaConfigResource
    {
        return new MediaConfigResource(null);
    }


    public function index(IndexMediaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $page = $request->pageRequest();

        $mq = MediaQuery::fromValidated($validated, $page);

        $result = $this->paginatorAdapter
            ->toPageResult($this->searchMedia->execute($mq))
            ->mapItems(
                static fn (Media $media): array => MediaResourceFactory::make($media)->toArray($request)
            );

        return PaginatedJsonResponse::from(
            $result
        );
    }

    /**
     * Загрузка нового медиа-файла.
     *
     * Сохраняет файл на диск, извлекает метаданные и создает запись Media в БД.
     * Возвращает специализированный ресурс в зависимости от типа медиа:
     * - MediaImageResource для изображений (с width, height, preview_urls)
     * - MediaVideoResource для видео (с duration_ms, bitrate_kbps, frame_rate и т.д.)
     * - MediaAudioResource для аудио (с duration_ms, bitrate_kbps, audio_codec)
     * - MediaDocumentResource для документов (только базовые поля)
     *
     * При дедупликации (файл с таким же checksum уже существует) возвращает 200,
     * при создании новой записи - 201.
     *
     * @param \Polymorph\Platform\Domain\Media\Http\Requests\StoreMediaRequest $request HTTP запрос с файлом и метаданными
     * @return \Illuminate\Http\JsonResponse JSON ответ со специализированным ресурсом медиа-файла
     * @throws \Illuminate\Auth\Access\AuthorizationException Если нет прав на создание
     * @throws \Polymorph\Platform\Support\Errors\HttpErrorException Если файл не прошел валидацию или не удалось сохранить
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $file = $request->file('file');

        $media = $this->uploadMedia->execute($file, $validated);
        
        // Загружаем связи для MediaResource (width, height из image, duration_ms из avMetadata)
        $media->load(['image', 'avMetadata']);

        // При дедупликации возвращаем 200, при создании - 201
        $statusCode = $media->wasRecentlyCreated ? HttpResponse::HTTP_CREATED : HttpResponse::HTTP_OK;

        return MediaResourceFactory::make($media)->response()->setStatusCode($statusCode);
    }

    /**
     * Массовая загрузка медиа-файлов.
     *
     * Обрабатывает массив файлов и создаёт записи Media для каждого файла.
     * Возвращает коллекцию с специализированными ресурсами для каждого типа медиа.
     * Опциональные метаданные (title, alt) применяются ко всем файлам.
     *
     * @param \Polymorph\Platform\Domain\Media\Http\Requests\BulkStoreMediaRequest $request HTTP запрос с массивом файлов и метаданными
     * @return \Illuminate\Http\JsonResponse JSON ответ с коллекцией загруженных медиа-файлов (статус 201)
     * @throws \Illuminate\Auth\Access\AuthorizationException Если нет прав на создание
     */
    public function bulkStore(BulkStoreMediaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $files = $request->file('files', []);

        $uploadedMedia = [];

        foreach ($files as $file) {
            $payload = [];
            if (isset($validated['title'])) {
                $payload['title'] = $validated['title'];
            }
            if (isset($validated['alt'])) {
                $payload['alt'] = $validated['alt'];
            }

            $media = $this->uploadMedia->execute($file, $payload);
            
            // Загружаем связи для MediaResource (width, height из image, duration_ms из avMetadata)
            $media->load(['image', 'avMetadata']);
            
            $uploadedMedia[] = $media;
        }

        // Преобразуем каждый Media в специализированный ресурс
        $resources = collect($uploadedMedia)->map(fn($media) => MediaResourceFactory::make($media));
        
        return response()->json(['data' => $resources], HttpResponse::HTTP_CREATED);
    }

    /**
     * Просмотр информации о медиа-файле.
     *
     * Возвращает специализированный ресурс в зависимости от типа медиа:
     * - MediaImageResource для изображений (с width, height, preview_urls)
     * - MediaVideoResource для видео (с duration_ms, bitrate_kbps, frame_rate и т.д.)
     * - MediaAudioResource для аудио (с duration_ms, bitrate_kbps, audio_codec)
     * - MediaDocumentResource для документов (только базовые поля)
     *
     * @param string $mediaId ULID идентификатор медиа-файла
     * @return \Polymorph\Platform\Domain\Media\Http\Resources\Media\BaseMediaResource Специализированный ресурс медиа-файла
     * @throws \Illuminate\Auth\Access\AuthorizationException Если нет прав на просмотр
     */
    public function show(string $mediaId): BaseMediaResource
    {
        $media = $this->mediaRepository->findForDisplay($mediaId);

        if (! $media) {
            $this->throwMediaNotFound($mediaId);
        }

        return MediaResourceFactory::make($media);
    }

    /**
     * Обновление метаданных медиа-файла.
     *
     * Обновляет title, alt и возвращает специализированный ресурс
     * в зависимости от типа медиа (изображение, видео, аудио, документ).
     *
     * @param \Polymorph\Platform\Domain\Media\Http\Requests\UpdateMediaRequest $request HTTP запрос с валидированными данными
     * @param string $mediaId ULID идентификатор медиа-файла
     * @return \Polymorph\Platform\Domain\Media\Http\Resources\Media\BaseMediaResource Обновленный специализированный ресурс медиа-файла
     * @throws \Illuminate\Auth\Access\AuthorizationException Если нет прав на обновление
     */
    public function update(UpdateMediaRequest $request, string $mediaId): BaseMediaResource
    {
        $media = $this->mediaRepository->findWithTrashed($mediaId);

        if (! $media) {
            $this->throwMediaNotFound($mediaId);
        }

        $updated = $this->updateMedia->execute($mediaId, $request->validated());
        
        // Загружаем связи для MediaResource (width, height из image, duration_ms из avMetadata)
        $updated->load(['image', 'avMetadata']);
        
        return MediaResourceFactory::make($updated);
    }

    public function bulkDestroy(BulkDeleteMediaRequest $request): HttpResponse
    {
        $ids = $request->validated()['ids'];
        $mediaItems = $this->mediaRepository->findMany($ids);

        /** @var Media $media */
        foreach ($mediaItems as $media) {
            $this->deleteMedia->execute($media); // Observer автоматически отправит MediaDeleted
        }

        return AdminResponse::noContent();
    }

    /**
     * Массовое восстановление удаленных медиа-файлов.
     *
     * Восстанавливает мягко удаленные медиа-файлы по массиву идентификаторов.
     * Возвращает коллекцию с специализированными ресурсами для каждого типа медиа.
     *
     * @param \Polymorph\Platform\Domain\Media\Http\Requests\BulkRestoreMediaRequest $request HTTP запрос с массивом ids
     * @return \Polymorph\Platform\Domain\Media\Http\Resources\MediaCollection Коллекция восстановленных медиа-файлов
     * @throws \Illuminate\Auth\Access\AuthorizationException Если нет прав на восстановление
     */
    public function bulkRestore(BulkRestoreMediaRequest $request): MediaCollection
    {
        $ids = $request->validated()['ids'];
        $mediaItems = $this->mediaRepository->findManyTrashed($ids);

        $restoredMedia = [];

        /** @var Media $media */
        foreach ($mediaItems as $media) {
            $this->restoreMedia->execute($media);

            // Перечитываем восстановленную запись со связями для ресурса
            // (width/height из image, duration_ms из avMetadata).
            $restored = $this->mediaRepository->findForDisplay((string) $media->id);
            if ($restored !== null) {
                $restoredMedia[] = $restored;
            }
        }

        return new MediaCollection(collect($restoredMedia));
    }

    public function bulkForceDestroy(BulkForceDeleteMediaRequest $request): HttpResponse
    {
        $ids = $request->validated()['ids'];
        $mediaItems = $this->mediaRepository->findManyWithTrashed($ids);

        /** @var Media $media */
        foreach ($mediaItems as $media) {
            $this->forceDeleteMedia->execute($media);
        }

        return AdminResponse::noContent();
    }

    /**
     * Очистка корзины медиа-файлов.
     *
     * Выполняет окончательное удаление всех мягко удаленных медиа-файлов.
     * Для каждого файла проверяются права доступа через политику forceDelete.
     * Физические файлы и варианты удаляются с диска, затем записи удаляются из БД.
     *
     * @return \Symfony\Component\HttpFoundation\Response HTTP ответ 204 No Content
     * @throws \Illuminate\Auth\Access\AuthorizationException Если нет прав на forceDelete
     */
    public function emptyTrash(): HttpResponse
    {
        $mediaItems = $this->mediaRepository->allTrashed();

        /** @var Media $media */
        foreach ($mediaItems as $media) {
            $this->forceDeleteMedia->execute($media);
        }

        return AdminResponse::noContent();
    }

    private function throwMediaNotFound(string $mediaId, ?string $prefix = null): never
    {
        $detail = $prefix
            ? sprintf('%s media with ID %s does not exist.', ucfirst($prefix), $mediaId)
            : sprintf('Media with ID %s does not exist.', $mediaId);

        $this->throwError(
            ErrorCode::NOT_FOUND,
            $detail,
            [
                'media_id' => $mediaId,
                'prefix' => $prefix,
            ],
        );
    }
}


