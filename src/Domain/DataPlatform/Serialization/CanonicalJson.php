<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Serialization;

/** Produces deterministic JSON for equality, hashes, and projection identities. */
final class CanonicalJson
{
    public function __construct(private readonly DatabaseJson $json) {}

    public function encode(mixed $value): string
    {
        return $this->json->encode($this->normalize($value));
    }

    public function hash(mixed $value): string
    {
        return hash('sha256', $this->encode($value));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $child) {
            $value[$key] = $this->normalize($child);
        }

        return $value;
    }
}
