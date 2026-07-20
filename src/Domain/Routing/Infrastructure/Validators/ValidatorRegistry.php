<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Routing\Infrastructure\Validators;

/**
 * Регистр валидаторов правил RouteNode по kind.
 *
 * Хранит и управляет валидаторами для различных типов узлов маршрутов.
 * Каждый валидатор связан с конкретным kind (например, 'group', 'route').
 */
final class ValidatorRegistry
{
    /**
     * Регистр валидаторов: kind => ValidatorInterface.
     *
     * @var array<string, ValidatorInterface>
     */
    private array $validators = [];

    /**
     * Зарегистрировать валидатор для определённого kind.
     *
     * Если валидатор для данного kind уже зарегистрирован,
     * он будет перезаписан новым валидатором.
     *
     * @param  string  $kind  Тип узла (например, 'group', 'route')
     * @param  \Polymorph\Platform\Domain\Routing\Validators\ValidatorInterface  $validator  Валидатор правил
     */
    public function register(string $kind, ValidatorInterface $validator): void
    {
        $this->validators[$kind] = $validator;
    }

    /**
     * Получить валидатор для определённого kind.
     *
     * @param  string  $kind  Тип узла (например, 'group', 'route')
     * @return \Polymorph\Platform\Domain\Routing\Validators\ValidatorInterface|null Валидатор или null, если не найден
     */
    public function getValidator(string $kind): ?ValidatorInterface
    {
        return $this->validators[$kind] ?? null;
    }

    /**
     * Проверить, зарегистрирован ли валидатор для kind.
     *
     * @param  string  $kind  Тип узла (например, 'group', 'route')
     * @return bool true, если валидатор зарегистрирован
     */
    public function hasValidator(string $kind): bool
    {
        return isset($this->validators[$kind]);
    }

    /**
     * Получить список всех поддерживаемых kind.
     *
     * @return array<string> Массив kind, для которых зарегистрированы валидаторы
     */
    public function getSupportedKinds(): array
    {
        return array_keys($this->validators);
    }

    /**
     * Получить все зарегистрированные валидаторы.
     *
     * @return array<string, ValidatorInterface> Массив валидаторов
     */
    public function getAllValidators(): array
    {
        return $this->validators;
    }
}
