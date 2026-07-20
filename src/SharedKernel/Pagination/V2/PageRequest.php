<?php

declare(strict_types=1);

namespace Polymorph\Platform\SharedKernel\Pagination\V2;

final readonly class PageRequest
{
    public function __construct(
        public int $page,
        public int $perPage,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated, ?PageLimits $limits = null): self
    {
        $limits ??= new PageLimits;

        $page = (int) ($validated['page'] ?? $limits->defaultPage);
        if ($page < $limits->defaultPage) {
            $page = $limits->defaultPage;
        }

        $perPage = (int) ($validated['per_page'] ?? $limits->defaultPerPage);
        if ($perPage < $limits->minPerPage) {
            $perPage = $limits->minPerPage;
        } elseif ($perPage > $limits->maxPerPage) {
            $perPage = $limits->maxPerPage;
        }

        return new self(page: $page, perPage: $perPage);
    }

    /**
     * @return array{page: int, per_page: int}
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }
}
