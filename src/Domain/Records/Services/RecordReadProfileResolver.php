<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Services;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\Records\Core\Models\Record;
use Polymorph\Platform\Domain\Records\Support\RecordSchemaResolver;
use Polymorph\Platform\Domain\SchemaModel\Services\FieldAccessService;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

final class RecordReadProfileResolver
{
    public function __construct(
        private readonly RecordDefinitionRepository $recordDefinitionRepository,
        private readonly FieldAccessService $fieldAccessService,
    ) {}

    public function forDefinition(?UserIdentity $actor, int $recordDefinitionId, string $action = CapabilityCatalog::ACTION_READ): RecordReadProfile
    {
        $definition = $this->recordDefinitionRepository->find($recordDefinitionId);
        $schemaId = $definition?->schema_id;

        return $this->forSchema($actor, is_int($schemaId) && $schemaId > 0 ? $schemaId : null, $action);
    }

    public function forRecord(?UserIdentity $actor, Record $record, string $action = CapabilityCatalog::ACTION_READ): RecordReadProfile
    {
        return $this->forSchema($actor, RecordSchemaResolver::fromRecord($record), $action);
    }

    public function forSchema(?UserIdentity $actor, ?int $schemaId, string $action = CapabilityCatalog::ACTION_READ): RecordReadProfile
    {
        if ($schemaId === null || $schemaId <= 0) {
            return new RecordReadProfile(null, [], true);
        }

        // Fail-closed: нет актора — не видно НИ ОДНОГО поля. Раньше здесь было
        // «видно всё», и любой будущий вызов без аутентификации (все нынешние
        // HTTP-пути передают актора из DI) молча раскрывал бы поля, которые
        // политики прячут от каждого реального пользователя.
        if (! $actor instanceof UserIdentity) {
            return new RecordReadProfile($schemaId, [], false);
        }

        // Схема без описанных полей: полевых политик к ней не существует,
        // фильтровать не по чему. Пустой список видимых путей означал бы
        // «срезать все данные», а не «ничего не скрыто».
        if ($this->fieldAccessService->schemaFieldPaths($schemaId) === []) {
            return new RecordReadProfile($schemaId, [], true);
        }

        // Дальше фильтруем ВСЕГДА, поштучным перебором полей. Раньше здесь
        // спрашивался корень schema.{id}.fields, и при allow профиль объявлял
        // «видно всё». Матчер dot-prefix считает совпадением только сам паттерн
        // и его поддерево, поэтому deny на schema.{id}.fields.{path} вопросом о
        // корне не матчился вовсе — любой грант schema/read (а его несут базовые
        // роли каждого пользователя) отменял все поштучные запреты, и HTTP-путь
        // ядра расходился с SDK-путём, который всегда перебирает поля.

        return new RecordReadProfile($schemaId, $this->fieldAccessService->visibleFieldPaths($actor, $schemaId, $action), false);
    }
}
