<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Observers;

use Polymorph\Platform\Domain\Media\Core\Models\MediaVariant;
use Polymorph\Platform\Domain\Media\Events\VariantGenerated;
use Polymorph\Platform\Domain\Media\Events\VariantGenerationFailed;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Illuminate\Support\Facades\Event;

/**
 * Observer для модели MediaVariant
 *
 * Обрабатывает события жизненного цикла MediaVariant и отправляет domain события.
 */
class MediaVariantObserver
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    /**
     * Обработка события после создания MediaVariant
     */
    public function created(MediaVariant $variant): void
    {
        $this->logger->event('media.variant.created', [
            'variant_id' => $variant->id,
            'media_id' => $variant->media_id,
            'variant_name' => $variant->variant,
            'status' => $variant->status->value ?? null,
        ]);
    }

    /**
     * Обработка события после обновления MediaVariant
     */
    public function updated(MediaVariant $variant): void
    {
        // Проверяем изменение статуса для отправки событий
        if ($variant->wasChanged('status')) {
            $this->handleStatusChange($variant);
        }

        $this->logger->debug('media.variant.updated', [
            'variant_id' => $variant->id,
            'changes' => $variant->getChanges(),
        ]);
    }

    /**
     * Обработка события после удаления MediaVariant
     */
    public function deleted(MediaVariant $variant): void
    {
        $this->logger->event('media.variant.deleted', [
            'variant_id' => $variant->id,
            'media_id' => $variant->media_id,
        ]);
    }

    /**
     * Обработка изменения статуса варианта
     */
    protected function handleStatusChange(MediaVariant $variant): void
    {
        $newStatus = $variant->status;

        // Если статус изменился на "ready" - вариант успешно сгенерирован
        if ($newStatus && $newStatus->value === 'ready') {
            Event::dispatch(new VariantGenerated($variant));

            $this->logger->event('media.variant.generation_completed', [
                'variant_id' => $variant->id,
                'media_id' => $variant->media_id,
                'variant_name' => $variant->variant,
            ]);
        }

        // Если статус изменился на "failed" - ошибка генерации
        if ($newStatus && $newStatus->value === 'failed') {
            Event::dispatch(new VariantGenerationFailed($variant));

            $this->logger->error('media.variant.generation_failed', [
                'variant_id' => $variant->id,
                'media_id' => $variant->media_id,
                'variant_name' => $variant->variant,
                'error' => $variant->error_message ?? 'Unknown error',
            ]);
        }
    }
}
