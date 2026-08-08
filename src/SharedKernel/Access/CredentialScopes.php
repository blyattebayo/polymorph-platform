<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Access;

/**
 * Чем ограничен credential сверх прав самого субъекта.
 *
 * ВНИМАНИЕ, слово «scope» в этом коде занято дважды. PolicyScopeAuthority
 * отвечает на вопрос «какими политиками админ вправе управлять». Здесь — другое:
 * какую часть собственных прав пользователя разрешено использовать конкретным
 * credential'ом. Поэтому тип называется CredentialScopes, и голое «scope» в
 * этом смысле в коде не употребляется.
 *
 * Живёт в SharedKernel\Access, а не рядом с credential в SharedKernel\Identity:
 * выражен он словарём доступа (ResourceRef + action), тем же, что ac_policies.
 *
 * Ключевой инвариант: credential может только СУЖАТЬ права субъекта, никогда не
 * расширять. Итоговое решение — пересечение «что можно субъекту» и «что
 * разрешено этому credential».
 */
final readonly class CredentialScopes
{
    /**
     * @param  list<AccessCheck>|null  $entries  null — ограничений нет
     */
    private function __construct(
        private ?array $entries,
    ) {}

    /**
     * Ограничений нет: credential может всё, что может субъект. Интерактивная
     * сессия — и любой токен, выпущенный до появления ограничений.
     */
    public static function unrestricted(): self
    {
        return new self(null);
    }

    /**
     * Явный список разрешённого. Пустой список — это НЕ «без ограничений»,
     * а «нельзя ничего»; различие намеренное и проверяется тестом.
     *
     * @param  list<AccessCheck>  $entries
     */
    public static function of(array $entries): self
    {
        return new self(array_values($entries));
    }

    public function isUnrestricted(): bool
    {
        return $this->entries === null;
    }

    /**
     * @return list<AccessCheck>
     */
    public function entries(): array
    {
        return $this->entries ?? [];
    }
}
