<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Extensions\Services;

use Polymorph\Platform\Domain\Extensions\Core\ValueObjects\DiscoveredExtension;

final class ExtensionCompatibilityService
{
    /**
     * Сообщение о несовместимости плагина с текущей версией ядра
     * (null — совместим).
     */
    public function coreVersionIssue(DiscoveredExtension $plugin): ?string
    {
        $coreVersion = (string) config('plugins.core_version', '0.0.0');
        $range = trim($plugin->coreVersionRange);

        if ($this->satisfies($coreVersion, $range)) {
            return null;
        }

        return "Plugin '{$plugin->id}' declares core range '{$range}', current core is '{$coreVersion}'.";
    }

    public function isStrict(): bool
    {
        return (bool) config('plugins.compatibility.strict', true);
    }

    /**
     * Проверка попадания версии в semver-диапазон.
     *
     * Поддерживается: '*', точная версия, '^x.y.z', '~x.y.z',
     * операторы сравнения (>=, >, <=, <, =), AND через пробел/запятую,
     * OR через '||'.
     */
    public function satisfies(string $version, string $range): bool
    {
        $range = trim($range);
        if ($range === '' || $range === '*') {
            return true;
        }

        $orParts = preg_split('/\s*\|\|\s*/', $range) ?: [];
        foreach ($orParts as $orPart) {
            if ($this->satisfiesAllConstraints($version, $orPart)) {
                return true;
            }
        }

        return false;
    }

    private function satisfiesAllConstraints(string $version, string $part): bool
    {
        $constraints = preg_split('/[\s,]+/', trim($part)) ?: [];
        $constraints = array_values(array_filter($constraints, static fn (string $c): bool => $c !== ''));

        if ($constraints === []) {
            return false;
        }

        foreach ($constraints as $constraint) {
            if (! $this->satisfiesConstraint($version, $constraint)) {
                return false;
            }
        }

        return true;
    }

    private function satisfiesConstraint(string $version, string $constraint): bool
    {
        $version = $this->normalize($version);

        if ($constraint === '*') {
            return true;
        }

        if (str_starts_with($constraint, '^')) {
            [$lower, $upper] = $this->caretBounds($this->normalize(substr($constraint, 1)));

            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }

        if (str_starts_with($constraint, '~')) {
            [$lower, $upper] = $this->tildeBounds($this->normalize(substr($constraint, 1)));

            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }

        if (preg_match('/^(>=|<=|>|<|==?)\s*(.+)$/', $constraint, $matches) === 1) {
            $operator = $matches[1] === '=' ? '==' : $matches[1];

            return version_compare($version, $this->normalize($matches[2]), $operator);
        }

        return version_compare($version, $this->normalize($constraint), '==');
    }

    /**
     * @return array{string, string} [нижняя граница включительно, верхняя исключительно]
     */
    private function caretBounds(string $version): array
    {
        [$major, $minor, $patch] = $this->parseParts($version);

        if ($major > 0) {
            return [$version, ($major + 1).'.0.0'];
        }

        if ($minor > 0) {
            return [$version, '0.'.($minor + 1).'.0'];
        }

        return [$version, '0.0.'.($patch + 1)];
    }

    /**
     * @return array{string, string}
     */
    private function tildeBounds(string $version): array
    {
        [$major, $minor] = $this->parseParts($version);

        return [$version, $major.'.'.($minor + 1).'.0'];
    }

    /**
     * @return array{int, int, int}
     */
    private function parseParts(string $version): array
    {
        $parts = explode('.', $version);

        return [
            (int) ($parts[0] ?? 0),
            (int) ($parts[1] ?? 0),
            (int) ($parts[2] ?? 0),
        ];
    }

    private function normalize(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }
}
