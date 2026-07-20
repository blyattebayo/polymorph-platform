<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\EntryView\Listeners;

use Polymorph\Platform\Domain\EntryView\Events\EntryViewCreated;
use Polymorph\Platform\Domain\EntryView\Events\EntryViewUpdated;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Слушатель для логирования событий конфигураций форм.
 *
 * Логирует все события жизненного цикла EntryView:
 * - создание (EntryViewCreated)
 * - обновление (EntryViewUpdated)
 */
final class LogEntryViewEvent
{
    public function __construct(
        private readonly AppLogger $logger,
    ) {}

    /**
     * Обработать событие создания конфигурации.
     *
     * @param  EntryViewCreated  $event  Событие создания
     */
    public function handleEntryViewCreated(EntryViewCreated $event): void
    {
        $config = $event->entryView;

        $this->logger->event('entry_view.created', [
            'config_id' => $config->id,
            'record_definition_id' => $config->record_definition_id,
            'schema_id' => $config->schema_id,
            'paths_count' => count($config->config_json ?? []),
        ]);
    }

    /**
     * Обработать событие обновления конфигурации.
     *
     * @param  EntryViewUpdated  $event  Событие обновления
     */
    public function handleEntryViewUpdated(EntryViewUpdated $event): void
    {
        $config = $event->entryView;

        $this->logger->event('entry_view.updated', [
            'config_id' => $config->id,
            'record_definition_id' => $config->record_definition_id,
            'schema_id' => $config->schema_id,
            'paths_count' => count($config->config_json ?? []),
        ]);
    }

    /**
     * Подписка на события.
     *
     * @return array<string, string>
     */
    public function subscribe(): array
    {
        return [
            EntryViewCreated::class => 'handleEntryViewCreated',
            EntryViewUpdated::class => 'handleEntryViewUpdated',
        ];
    }
}
