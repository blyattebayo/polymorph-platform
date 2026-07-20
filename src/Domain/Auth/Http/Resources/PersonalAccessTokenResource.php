<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Http\Resources;

use Polymorph\Platform\Domain\Auth\Application\DTO\PersonalAccessTokenView;

final class PersonalAccessTokenResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromView(PersonalAccessTokenView $view): array
    {
        return $view->toArray();
    }

    /**
     * @param  list<PersonalAccessTokenView>  $views
     * @return list<array<string, mixed>>
     */
    public static function collection(array $views): array
    {
        return array_map(static fn (PersonalAccessTokenView $view): array => self::fromView($view), $views);
    }
}
