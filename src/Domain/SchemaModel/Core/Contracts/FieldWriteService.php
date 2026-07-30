<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\Contracts;

use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\CircularDependencyException;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\DuplicateFieldPathException;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\InvalidParentFieldException;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\Field;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Data\CreateFieldData;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Data\UpdateFieldData;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;

/**
 * ВНИМАНИЕ для вызывающих: доменные исключения ниже НЕ наследуются от
 * \DomainException — DuplicateFieldPathException расширяет RuntimeException, а
 * CircularDependencyException и InvalidParentFieldException — LogicException.
 * `catch (\DomainException)` их не поймает. Ловить следует
 * {@see DomainErrorDescriptor}: его реализуют все
 * они, и он же несёт errorCode/meta для StepResult::failure().
 */
interface FieldWriteService
{
    /**
     * @throws DuplicateFieldPathException путь уже занят в схеме (409)
     * @throws InvalidParentFieldException родитель не найден/чужой/не контейнер
     * @throws \DomainException DSL валидации полей не прошёл
     */
    public function create(SchemaModel $schema, CreateFieldData $data): Field;

    /**
     * @throws DuplicateFieldPathException новый путь уже занят в схеме (409)
     * @throws CircularDependencyException смена родителя создала бы цикл
     * @throws InvalidParentFieldException родитель не найден/чужой/не контейнер
     * @throws \DomainException DSL валидации полей не прошёл
     */
    public function update(SchemaModel $schema, Field $field, UpdateFieldData $data): Field;
}
