<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

/**
 * Исключение: определение записи не найдено.
 *
 * Заменило RecordDefinitionNotFoundErrorFactory — класс, который собирал
 * HttpErrorException вручную и требовал инъекции ErrorFactory в контроллер.
 * Пять других доменов (Users, Roles, SchemaModel, Media, Auth) на тот же случай
 * бросают ErrorConvertible-исключение, и один домен из шести не должен быть особым.
 */
final class RecordDefinitionNotFoundException extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    public function __construct(
        private readonly int $recordDefinitionId,
    ) {
        parent::__construct("RecordDefinition not found: {$recordDefinitionId}");
    }

    public static function byId(int $recordDefinitionId): self
    {
        return new self($recordDefinitionId);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::NOT_FOUND;
    }

    /**
     * Тот же заголовок, что отдавала удалённая RecordDefinitionNotFoundErrorFactory:
     * каталожный «Not Found» здесь менее информативен, а поле клиент-видимое.
     */
    protected function errorTitle(): ?string
    {
        return 'RecordDefinition not found';
    }

    /**
     * @return array<string, mixed>
     */
    public function errorMeta(): array
    {
        return [
            'resource' => 'record_definition',
            'identifier' => $this->recordDefinitionId,
            'identifier_type' => 'id',
        ];
    }
}
