<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Handlers;

use Polymorph\Platform\Domain\SchemaModel\Application\DTO\BulkDeleteSchemasResult;
use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\SchemaRepository;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Commands\DeleteSchemaCommand;
use Throwable;

final class BulkDeleteSchemasHandler
{
    public function __construct(
        private readonly SchemaRepository $schemas,
        private readonly DeleteSchemaHandler $deleteSchemaHandler,
    ) {
    }

    /**
     * Удаляет каждую схему в собственной транзакции (см. DeleteSchemaHandler),
     * поэтому операция частично-успешна: результат разбит на deleted / blocked /
     * not_found / failed. Сбой одного элемента не прерывает батч.
     *
     * @param list<int> $ids
     */
    public function handle(array $ids): BulkDeleteSchemasResult
    {
        $deleted = [];
        $blocked = [];
        $notFound = [];
        $failed = [];

        foreach ($ids as $id) {
            $schema = $this->schemas->find($id);
            if ($schema === null) {
                $notFound[] = $id;
                continue;
            }

            // Тот же источник правды, что и в ValidateDeleteSchemaStep.
            $usage = $this->schemas->getUsageInfo($schema);
            if ($usage->isInUse()) {
                $blocked[] = $usage->toBlockedEntry();
                continue;
            }

            try {
                $this->deleteSchemaHandler->handle(new DeleteSchemaCommand(
                    schema: $schema,
                ));
                $deleted[] = (int) $schema->id;
            } catch (Throwable) {
                // Непредвиденный сбой (гонка, блокировка, ошибка БД) не должен
                // ронять весь батч — фиксируем id и продолжаем.
                $failed[] = (int) $schema->id;
            }
        }

        return new BulkDeleteSchemasResult(
            deleted: $deleted,
            blocked: $blocked,
            notFound: $notFound,
            failed: $failed,
        );
    }
}
