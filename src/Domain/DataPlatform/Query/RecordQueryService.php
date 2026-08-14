<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Query;

use Polymorph\Platform\Domain\DataPlatform\Read\RecordReadService;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

final class RecordQueryService
{
    public function __construct(
        private readonly QueryPlanner $planner,
        private readonly RecordReadService $reader,
        private readonly DatabaseJson $json,
    ) {}

    /** @return array{data:list<array<string,mixed>>,meta:array<string,mixed>,included?:array<string,mixed>} */
    public function execute(QuerySpec $spec, ?int $actorId): array
    {
        $plan = $this->planner->plan($spec, $actorId);
        $total = (clone $plan->builder)->count('r.id');
        $included = null;
        $rows = (clone $plan->builder)
            ->forPage($spec->page, $spec->perPage)
            ->get(['r.id', 'r.record_definition_id', 'r.schema_version_id', 'r.data', 'r.revision', 'r.author_id', 'r.created_at', 'r.updated_at'])
            ->map(function (object $row): array {
                $data = (array) $row;
                $data['data'] = $this->json->decodeMap($row->data, 'dp_records.data');

                return $data;
            })
            ->all();
        $rows = $this->reader->presentQueryRows($rows, $actorId);
        if ($spec->include !== []) {
            $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
            $hydrated = $this->reader->hydratePresentedRows(
                $rows,
                $actorId,
                $spec->include,
                1,
            );
            $rows = [];
            foreach ($ids as $id) {
                if (isset($hydrated['by_record_id'][(string) $id])) {
                    $rows[] = $hydrated['by_record_id'][(string) $id];
                }
            }
            $included = $hydrated['included'];
        }

        $result = [
            'data' => $rows,
            'meta' => [
                'page' => $spec->page,
                'per_page' => $spec->perPage,
                'total' => $total,
                'strategies' => $plan->strategies,
                'warnings' => $plan->warnings,
            ],
        ];
        if ($included !== null) {
            $result['included'] = $included;
        }
        if ($spec->aggregate !== null) {
            $result['meta']['aggregate'] = $this->planner->aggregate(
                $spec,
                $actorId,
                (string) ($spec->aggregate['op'] ?? ''),
                isset($spec->aggregate['field']) ? (string) $spec->aggregate['field'] : null,
                $plan,
            );
        }

        return $result;
    }
}
