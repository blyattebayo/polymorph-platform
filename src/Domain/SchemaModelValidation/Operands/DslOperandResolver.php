<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Operands;

use Illuminate\Support\Str;
use Polymorph\Platform\Domain\Records\Support\RecordPayloadPathRegistry;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptor;

final class DslOperandResolver
{
    public function resolve(string $operand, string $currentFieldFullPath, SchemaDescriptor $schema): ResolvedOperand
    {
        $normalized = trim($operand);

        if ($normalized === '') {
            return new ResolvedOperand($operand, '', false);
        }

        if (RecordPayloadPathRegistry::hasDottedPrefix($normalized)) {
            return new ResolvedOperand($operand, $normalized, true);
        }

        $fullPath = $this->toFullPath($normalized, $currentFieldFullPath);

        if ($fullPath === null || $fullPath === '') {
            return new ResolvedOperand($operand, '', false);
        }

        $dataPath = $schema->dataPathByFullPath($fullPath);
        if ($dataPath === null) {
            return new ResolvedOperand($operand, RecordPayloadPathRegistry::rootPath(ltrim($fullPath, '.')), false);
        }

        return new ResolvedOperand($operand, RecordPayloadPathRegistry::rootPath(ltrim($dataPath, '.')), true);
    }

    public function resolveWithinCollection(string $inFieldOperand, string $foreignCollectionFullPath, SchemaDescriptor $schema): ResolvedOperand
    {
        $normalized = trim($inFieldOperand);
        if ($normalized === '') {
            return new ResolvedOperand($inFieldOperand, '', false);
        }

        if (str_starts_with($normalized, '$self.')) {
            $normalized = substr($normalized, strlen('$self.'));
        }

        if (str_starts_with($normalized, '$root.')) {
            $fullPath = ltrim(substr($normalized, strlen('$root.')), '.');
        } else {
            $fullPath = trim($foreignCollectionFullPath.'.'.ltrim($normalized, '.'), '.');
        }

        $dataPath = $schema->dataPathByFullPath($fullPath);

        if ($dataPath === null) {
            return new ResolvedOperand($inFieldOperand, RecordPayloadPathRegistry::rootPath(ltrim($fullPath, '.')), false);
        }

        return new ResolvedOperand($inFieldOperand, RecordPayloadPathRegistry::rootPath(ltrim($dataPath, '.')), true);
    }

    private function toFullPath(string $operand, string $currentFieldFullPath): ?string
    {
        if (str_starts_with($operand, '$self.')) {
            $parentPath = Str::beforeLast($currentFieldFullPath, '.');
            $local = ltrim(substr($operand, strlen('$self.')), '.');

            if ($local === '') {
                return null;
            }

            return $parentPath !== $currentFieldFullPath
                ? trim($parentPath.'.'.$local, '.')
                : $local;
        }

        if (str_starts_with($operand, '$root.')) {
            return ltrim(substr($operand, strlen('$root.')), '.');
        }

        return ltrim($operand, '.');
    }
}
