<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Fields;

final class DependencySet
{
    /** @var array<int, true> */
    private array $recordIds = [];

    /** @var array<string, true> */
    private array $mediaIds = [];

    public function addRecord(int $id): void
    {
        if ($id > 0) {
            $this->recordIds[$id] = true;
        }
    }

    public function addMedia(string $id): void
    {
        if ($id !== '') {
            $this->mediaIds[$id] = true;
        }
    }

    /** @return list<int> */
    public function recordIds(): array
    {
        return array_map('intval', array_keys($this->recordIds));
    }

    /** @return list<string> */
    public function mediaIds(): array
    {
        return array_keys($this->mediaIds);
    }
}
