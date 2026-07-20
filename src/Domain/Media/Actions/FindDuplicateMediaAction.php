<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Media\Actions;

use Polymorph\Platform\Domain\Media\Core\Models\Media;
use Illuminate\Support\Facades\DB;

/**
 * Action для поиска дубликата медиа по checksum с пессимистичным lock.
 *
 * Блокирует строку для UPDATE, предотвращая race condition.
 * Если найден soft-deleted медиа - восстанавливает его.
 * Если передан payload с title/alt - обновляет метаданные.
 *
 * @package Polymorph\Platform\Domain\Media\Actions
 */
final readonly class FindDuplicateMediaAction
{
    /**
     * Найти дубликат медиа по checksum.
     *
     * Использует SELECT ... FOR UPDATE для блокировки строки
     * и предотвращения одновременного создания дубликатов.
     *
     * @param string $checksum SHA256 checksum файла
     * @param array<string, mixed> $payload Данные для обновления (title, alt)
     * @return Media|null Найденный медиа или null если не найден
     */
    public function execute(string $checksum, array $payload = []): ?Media
    {
        return DB::transaction(function () use ($checksum, $payload) {
            // SELECT ... FOR UPDATE блокирует строку от других транзакций
            $existing = Media::withTrashed()
                ->where('checksum_sha256', $checksum)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                return null;
            }

            // Восстановить soft-deleted медиа
            if ($existing->trashed()) {
                $existing->restore();
            }

            // Обновить метаданные, если переданы в payload
            $updates = [];
            
            if (isset($payload['title']) && $existing->title !== $payload['title']) {
                $updates['title'] = $payload['title'];
            }
            
            if (isset($payload['alt']) && $existing->alt !== $payload['alt']) {
                $updates['alt'] = $payload['alt'];
            }

            if (!empty($updates)) {
                $existing->update($updates);
            }

            // Загружаем связи для полного объекта
            return $existing->fresh(['image', 'avMetadata', 'variants']);
        });
    }
}
