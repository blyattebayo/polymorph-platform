<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Write;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

/** Sole persistence contract for actor-scoped command idempotency. */
final class CommandIdempotencyStore
{
    public function __construct(private readonly DatabaseJson $json) {}

    /** @return array<string, mixed>|null */
    public function claim(
        ?int $actorId,
        string $command,
        ?string $idempotencyKey,
        string $requestHash,
    ): ?array {
        if ($idempotencyKey === null) {
            return null;
        }

        $actorScope = $this->actorScope($actorId);
        $keyHash = hash('sha256', $idempotencyKey);
        $existing = DB::table('dp_idempotency_keys')
            ->where('actor_scope', $actorScope)
            ->where('command', $command)
            ->where('key_hash', $keyHash)
            ->lockForUpdate()
            ->first();
        if ($existing !== null) {
            if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                throw new IdempotencyConflict('The idempotency key was already used with a different command payload.');
            }
            if ($existing->state === 'completed' && $existing->response !== null) {
                return $this->json->decodeMap($existing->response, 'dp_idempotency_keys.response');
            }

            throw new IdempotencyConflict('The idempotent command is already processing.');
        }

        try {
            DB::table('dp_idempotency_keys')->insert([
                'actor_id' => $actorId,
                'actor_scope' => $actorScope,
                'command' => $command,
                'key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'state' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new IdempotencyConflict('The idempotent command raced with another request.', previous: $exception);
        }

        return null;
    }

    /** @param array<string, mixed> $response */
    public function complete(
        ?int $actorId,
        string $command,
        ?string $idempotencyKey,
        string $requestHash,
        array $response,
    ): void {
        if ($idempotencyKey === null) {
            return;
        }

        DB::table('dp_idempotency_keys')
            ->where('actor_scope', $this->actorScope($actorId))
            ->where('command', $command)
            ->where('key_hash', hash('sha256', $idempotencyKey))
            ->where('request_hash', $requestHash)
            ->update([
                'state' => 'completed',
                'response' => $this->json->encode($response),
                'updated_at' => now(),
            ]);
    }

    private function actorScope(?int $actorId): string
    {
        return $actorId === null ? 'anonymous' : 'actor:'.$actorId;
    }
}
