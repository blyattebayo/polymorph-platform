<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Providers;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Polymorph\Platform\Domain\Media\Access\MediaCapabilityProvider;
use Polymorph\Platform\Domain\Media\Actions\CalculateChecksumAction;
use Polymorph\Platform\Domain\Media\Actions\CreateMediaRecordAction;
use Polymorph\Platform\Domain\Media\Actions\DeleteMediaFileAction;
use Polymorph\Platform\Domain\Media\Actions\ExtractMetadataAction;
use Polymorph\Platform\Domain\Media\Actions\FindDuplicateMediaAction;
use Polymorph\Platform\Domain\Media\Actions\StoreMediaFileAction;
use Polymorph\Platform\Domain\Media\Commands\DeleteMediaCommand;
use Polymorph\Platform\Domain\Media\Commands\ForceDeleteMediaCommand;
use Polymorph\Platform\Domain\Media\Commands\RestoreMediaCommand;
use Polymorph\Platform\Domain\Media\Commands\UpdateMediaCommand;
use Polymorph\Platform\Domain\Media\Commands\UploadMediaCommand;
use Polymorph\Platform\Domain\Media\Core\Contracts\ImageProcessor;
use Polymorph\Platform\Domain\Media\Core\Contracts\MediaIncludedProvider;
use Polymorph\Platform\Domain\Media\Core\Contracts\MediaRepository;
use Polymorph\Platform\Domain\Media\Core\Contracts\MediaVariantRepository;
use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Polymorph\Platform\Domain\Media\Core\Models\MediaVariant;
use Polymorph\Platform\Domain\Media\Core\Validation\MediaConfigValidator;
use Polymorph\Platform\Domain\Media\Events\MediaDeleted;
use Polymorph\Platform\Domain\Media\Events\MediaForceDeleted;
use Polymorph\Platform\Domain\Media\Events\MediaProcessed;
use Polymorph\Platform\Domain\Media\Events\MediaRestored;
use Polymorph\Platform\Domain\Media\Events\MediaUpdated;
use Polymorph\Platform\Domain\Media\Events\MediaUploaded;
use Polymorph\Platform\Domain\Media\Events\VariantGenerated;
use Polymorph\Platform\Domain\Media\Infrastructure\Images\GdImageProcessor;
use Polymorph\Platform\Domain\Media\Infrastructure\Images\GlideImageProcessor;
use Polymorph\Platform\Domain\Media\Infrastructure\Repositories\EloquentMediaRepository;
use Polymorph\Platform\Domain\Media\Infrastructure\Repositories\EloquentMediaVariantRepository;
use Polymorph\Platform\Domain\Media\Listeners\LogMediaEvent;
use Polymorph\Platform\Domain\Media\Observers\MediaObserver;
use Polymorph\Platform\Domain\Media\Observers\MediaVariantObserver;
use Polymorph\Platform\Domain\Media\Queries\FindManyMediaQuery;
use Polymorph\Platform\Domain\Media\Queries\FindMediaByChecksumQuery;
use Polymorph\Platform\Domain\Media\Queries\FindMediaByIdQuery;
use Polymorph\Platform\Domain\Media\Queries\SearchMediaQuery;
use Polymorph\Platform\Domain\Media\Services\DefaultMediaIncludedProvider;
use Polymorph\Platform\Domain\Media\Services\Metadata\MediaMetadataExtractor;
use Polymorph\Platform\Domain\Media\Services\Metadata\Plugins\ExiftoolMediaMetadataPlugin;
use Polymorph\Platform\Domain\Media\Services\Metadata\Plugins\FfprobeMediaMetadataPlugin;
use Polymorph\Platform\Domain\Media\Services\Metadata\Plugins\GetId3MediaMetadataPlugin;
use Polymorph\Platform\Domain\Media\Services\Metadata\Plugins\MediainfoMediaMetadataPlugin;
use Polymorph\Platform\Domain\Media\Services\Metadata\Plugins\MediaMetadataPlugin;
use Polymorph\Platform\Domain\Media\Services\OnDemandVariantService;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Service Provider для Media Domain
 *
 * Регистрирует все зависимости, биндинги и observers для Media системы.
 * Следует архитектуре Schema domain.
 */
class MediaServiceProvider extends ServiceProvider
{
    /**
     * Регистрация биндингов в контейнере
     */
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerActions();
        $this->registerCommands();
        $this->registerQueries();
        $this->registerCoreServices();
        $this->registerImageProcessing();
        $this->registerMetadataExtraction();
    }

    /**
     * Bootstrap сервисов после регистрации всех providers
     */
    public function boot(): void
    {
        $this->registerObservers();
        $this->registerEventListeners();
        $this->validateConfiguration();

        $this->app->tag([MediaCapabilityProvider::class], 'access.capability_providers');
    }

    /**
     * Регистрация репозиториев
     */
    protected function registerRepositories(): void
    {
        // Основной репозиторий Media
        $this->app->singleton(MediaRepository::class, EloquentMediaRepository::class);

        // Репозиторий вариантов
        $this->app->singleton(MediaVariantRepository::class, EloquentMediaVariantRepository::class);
    }

    /**
     * Регистрация Actions (атомарные операции).
     *
     * Actions - это building blocks для Commands.
     * Каждый Action выполняет одну конкретную задачу.
     */
    protected function registerActions(): void
    {
        // Action для вычисления checksum (всегда NOT NULL)
        $this->app->singleton(CalculateChecksumAction::class);

        // Action для поиска дубликатов с pessimistic lock
        $this->app->singleton(FindDuplicateMediaAction::class);

        // Action для сохранения файла на диск (с rollback)
        $this->app->singleton(StoreMediaFileAction::class);

        // Action для удаления файла с диска
        $this->app->singleton(DeleteMediaFileAction::class);

        // Action для извлечения метаданных
        $this->app->singleton(ExtractMetadataAction::class);

        // Action для атомарного создания записей в БД
        $this->app->singleton(CreateMediaRecordAction::class);
    }

    /**
     * Регистрация Commands (оркестраторы).
     *
     * Commands координируют несколько Actions для выполнения
     * сложных бизнес-операций с гарантией атомарности.
     *
     * Часть CQRS паттерна - операции изменения состояния (Write).
     */
    protected function registerCommands(): void
    {
        // Command для загрузки медиа (решает проблему 1.2.1)
        $this->app->singleton(UploadMediaCommand::class);

        // Command для soft delete медиа
        $this->app->singleton(DeleteMediaCommand::class);

        // Command для восстановления soft-deleted медиа
        $this->app->singleton(RestoreMediaCommand::class);

        // Command для полного удаления медиа (force delete)
        $this->app->singleton(ForceDeleteMediaCommand::class);

        // Command для обновления метаданных медиа
        $this->app->singleton(UpdateMediaCommand::class);
    }

    /**
     * Регистрация Queries (операции чтения).
     *
     * Queries выполняют только чтение данных без изменения состояния.
     *
     * Часть CQRS паттерна - операции чтения (Read).
     */
    protected function registerQueries(): void
    {
        // Query для поиска медиа по ID
        $this->app->singleton(FindMediaByIdQuery::class);

        // Query для поиска медиа по checksum
        $this->app->singleton(FindMediaByChecksumQuery::class);

        // Query для поиска с фильтрацией и пагинацией
        $this->app->singleton(SearchMediaQuery::class);

        // Query для поиска нескольких медиа по ID
        $this->app->singleton(FindManyMediaQuery::class);
    }

    /**
     * Регистрация основных сервисов
     */
    protected function registerCoreServices(): void
    {
        // Сервис on-demand вариантов
        $this->app->singleton(OnDemandVariantService::class);
        $this->app->singleton(MediaIncludedProvider::class, DefaultMediaIncludedProvider::class);
    }

    /**
     * Регистрация Image Processing
     */
    protected function registerImageProcessing(): void
    {
        // Выбор драйвера обработки изображений из конфига
        $this->app->singleton(ImageProcessor::class, function () {
            $driver = (string) config('media.image.driver', 'gd');

            switch ($driver) {
                case 'glide':
                    $glideDriver = (string) config('media.image.glide_driver', 'gd');
                    $driverInstance = $glideDriver === 'imagick' ? new ImagickDriver : new GdDriver;

                    return new GlideImageProcessor(new ImageManager(driver: $driverInstance));

                case 'gd':
                default:
                    return new GdImageProcessor;
            }
        });
    }

    /**
     * Регистрация Metadata Extraction
     */
    protected function registerMetadataExtraction(): void
    {
        // MediaMetadataExtractor с плагинами и кэшированием
        $this->app->singleton(MediaMetadataExtractor::class, function ($app): MediaMetadataExtractor {
            $images = $app->make(ImageProcessor::class);
            $plugins = [];

            // Порядок важен: пробуем плагины по порядку с graceful fallback
            if (config('media.metadata.getid3.enabled', true)) {
                $plugins[] = new GetId3MediaMetadataPlugin;
            }

            if (config('media.metadata.ffprobe.enabled', true)) {
                $binary = config('media.metadata.ffprobe.binary', null);
                $plugins[] = new FfprobeMediaMetadataPlugin($binary);
            }

            if (config('media.metadata.mediainfo.enabled', false)) {
                $binary = config('media.metadata.mediainfo.binary', null);
                $plugins[] = new MediainfoMediaMetadataPlugin($binary);
            }

            if (config('media.metadata.exiftool.enabled', false)) {
                $binary = config('media.metadata.exiftool.binary', null);
                $plugins[] = new ExiftoolMediaMetadataPlugin($binary);
            }

            /** @var iterable<MediaMetadataPlugin> $pluginsIterable */
            $pluginsIterable = $plugins;
            $cache = $app->make(CacheRepository::class);
            $cacheTtl = (int) config('media.metadata.cache_ttl', 3600);

            return new MediaMetadataExtractor($images, $app->make(AppLogger::class), $pluginsIterable, $cache, $cacheTtl);
        });
    }

    /**
     * Регистрация Eloquent Observers
     */
    protected function registerObservers(): void
    {
        // Observer для Media модели
        Media::observe(MediaObserver::class);

        // Observer для MediaVariant модели
        MediaVariant::observe(MediaVariantObserver::class);
    }

    /**
     * Регистрация слушателей событий Media
     */
    protected function registerEventListeners(): void
    {
        Event::listen(MediaUploaded::class, [LogMediaEvent::class, 'handleMediaUploaded']);

        Event::listen(MediaUpdated::class, [LogMediaEvent::class, 'handleMediaUpdated']);

        Event::listen(MediaRestored::class, [LogMediaEvent::class, 'handleMediaRestored']);

        Event::listen(MediaProcessed::class, [LogMediaEvent::class, 'handleMediaProcessed']);

        Event::listen(VariantGenerated::class, [LogMediaEvent::class, 'handleVariantGenerated']);

        Event::listen(MediaDeleted::class, [LogMediaEvent::class, 'handleMediaDeleted']);

        Event::listen(MediaForceDeleted::class, [LogMediaEvent::class, 'handleMediaForceDeleted']);
    }

    /**
     * Валидация конфигурации Media
     */
    protected function validateConfiguration(): void
    {
        (new MediaConfigValidator)->validate();
    }
}
